<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InvestmentController extends Controller
{
    public function create(Project $project): View
    {
        abort_unless(
            $project->status === 'published'
                && $project->published_at !== null
                && $project->published_at->isPast()
                && ! $project->is_sample,
            404,
        );
        $project->load('currentTerms');
        abort_unless($project->currentTerms?->annual_rate_percent !== null, 404);

        return view('account.investments.create', [
            'pendingDeposits' => DB::table('deposit_requests')->where('status', 'pending')->count(),
            'pendingWithdrawals' => DB::table('withdrawal_requests')->where('status', 'pending')->count(),
            'project' => $project,
            'wallets' => DB::table('wallet_accounts')
                ->selectRaw('wallet_accounts.*, CAST(balance - reserved_balance AS CHAR) as available_balance')
                ->where('user_id', auth()->id())
                ->get()
                ->keyBy('currency'),
            'exchangeRate' => DB::table('exchange_rates')
                ->where('base_currency', 'USDT')
                ->where('quote_currency', 'BDT')
                ->orderByDesc('version')
                ->value('quote_per_base'),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'units' => ['required', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
            'currency' => ['required', Rule::in(['BDT', 'USDT'])],
        ]);

        DB::transaction(function () use ($request, $project, $validated): void {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            $project->load('currentTerms');

            if (
                $project->status !== 'published'
                || $project->published_at === null
                || ! $project->published_at->isPast()
                || $project->is_sample
            ) {
                throw ValidationException::withMessages([
                    'project' => __('site.investments.project_unavailable'),
                ]);
            }

            $term = $project->currentTerms;

            if ($term === null || $term->annual_rate_percent === null) {
                throw ValidationException::withMessages([
                    'project' => __('site.investments.terms_unavailable'),
                ]);
            }

            $units = (int) $validated['units'];

            if ($units > $project->available_units) {
                throw ValidationException::withMessages([
                    'units' => __('site.investments.units_unavailable'),
                ]);
            }

            $amountBdt = $this->decimalResult(
                'SELECT CAST(ROUND(CAST(? AS DECIMAL(20,2)) * ?, 2) AS CHAR) AS amount',
                [$term->unit_price_bdt, $units],
            );

            if ($this->integerDigits($amountBdt) > 18) {
                throw ValidationException::withMessages([
                    'units' => __('site.investments.amount_too_large'),
                ]);
            }

            $exchangeRate = null;
            $paidAmount = $amountBdt;

            if ($validated['currency'] === 'USDT') {
                $exchangeRate = DB::table('exchange_rates')
                    ->where('base_currency', 'USDT')
                    ->where('quote_currency', 'BDT')
                    ->orderByDesc('version')
                    ->lockForUpdate()
                    ->first();

                if ($exchangeRate === null || (float) $exchangeRate->quote_per_base <= 0) {
                    throw ValidationException::withMessages([
                        'currency' => __('site.investments.exchange_rate_unavailable'),
                    ]);
                }

                $paidAmount = $this->decimalResult(
                    'SELECT CAST(ROUND(CAST(? AS DECIMAL(20,2)) / CAST(? AS DECIMAL(20,8)), 8) AS CHAR) AS amount',
                    [$amountBdt, $exchangeRate->quote_per_base],
                );
            }

            if ($this->integerDigits($paidAmount) > 16) {
                throw ValidationException::withMessages([
                    'currency' => __('site.investments.amount_too_large'),
                ]);
            }

            $wallet = DB::table('wallet_accounts')
                ->where('user_id', $request->user()->id)
                ->where('currency', $validated['currency'])
                ->lockForUpdate()
                ->first();

            if (
                $wallet === null
                || DB::table('wallet_accounts')
                    ->where('id', $wallet->id)
                    ->whereRaw('balance - reserved_balance >= CAST(? AS DECIMAL(24,8))', [$paidAmount])
                    ->doesntExist()
            ) {
                throw ValidationException::withMessages([
                    'currency' => __('site.investments.insufficient_balance'),
                ]);
            }

            $updatedUnits = DB::table('projects')
                ->where('id', $project->id)
                ->where('available_units', '>=', $units)
                ->decrement('available_units', $units, ['updated_at' => now()]);

            if ($updatedUnits !== 1) {
                throw ValidationException::withMessages([
                    'units' => __('site.investments.units_unavailable'),
                ]);
            }

            DB::table('wallet_accounts')->where('id', $wallet->id)->increment(
                'reserved_balance',
                $paidAmount,
                ['updated_at' => now()],
            );
            $updatedWallet = DB::table('wallet_accounts')->where('id', $wallet->id)->first();
            abort_if($updatedWallet === null, 500, __('site.investments.wallet_unavailable'));

            $investmentId = DB::table('investments')->insertGetId([
                'user_id' => $request->user()->id,
                'project_id' => $project->id,
                'project_term_id' => $term->id,
                'unit_count' => $units,
                'unit_price_bdt' => $term->unit_price_bdt,
                'amount_bdt' => $amountBdt,
                'payment_currency' => $validated['currency'],
                'paid_amount' => $paidAmount,
                'exchange_rate_id' => $exchangeRate?->id,
                'bdt_per_usdt_snapshot' => $exchangeRate?->quote_per_base,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $transactionId = DB::table('ledger_transactions')->insertGetId([
                'idempotency_key' => 'investment-reserve:'.$investmentId,
                'type' => 'investment_reserve',
                'status' => 'posted',
                'description' => 'Reserve wallet funds for pending project investment',
                'exchange_rate_id' => $exchangeRate?->id,
                'metadata' => json_encode([
                    'investment_id' => $investmentId,
                    'unit_count' => $units,
                    'amount_bdt' => $amountBdt,
                    'paid_amount' => $paidAmount,
                    'currency' => $validated['currency'],
                    'bdt_per_usdt_snapshot' => $exchangeRate?->quote_per_base,
                ], JSON_THROW_ON_ERROR),
                'created_by_user_id' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('ledger_entries')->insert([
                'ledger_transaction_id' => $transactionId,
                'wallet_account_id' => $wallet->id,
                'entry_type' => 'investment_reserve',
                'amount' => '0.00000000',
                'balance_after' => $updatedWallet->balance,
                'reserved_after' => $updatedWallet->reserved_balance,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('admin_audit_logs')->insert([
                'admin_user_id' => null,
                'action' => 'investment.submitted',
                'subject_type' => 'investment',
                'subject_id' => $investmentId,
                'before_values' => null,
                'after_values' => json_encode([
                    'user_id' => $request->user()->id,
                    'project_id' => $project->id,
                    'unit_count' => $units,
                    'amount_bdt' => $amountBdt,
                    'paid_amount' => $paidAmount,
                    'currency' => $validated['currency'],
                    'status' => 'pending',
                ], JSON_THROW_ON_ERROR),
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('account.dashboard')->with('status', __('site.investments.submitted'));
    }

    /**
     * @param  array<int, int|string>  $bindings
     */
    private function decimalResult(string $query, array $bindings): string
    {
        $result = DB::selectOne($query, $bindings);

        if ($result === null || ! is_numeric($result->amount)) {
            throw new \RuntimeException('Unable to calculate the project investment amount.');
        }

        return (string) $result->amount;
    }

    private function integerDigits(string $amount): int
    {
        $integer = explode('.', $amount, 2)[0];

        return strlen(ltrim($integer, '0'));
    }
}
