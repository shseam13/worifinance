<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WithdrawalRequestController extends Controller
{
    public function create(): View
    {
        $rate = DB::table('exchange_rates')
            ->where('base_currency', 'USDT')
            ->where('quote_currency', 'BDT')
            ->orderByDesc('version')
            ->first();
        $minimumBdt = (string) (DB::table('platform_settings')
            ->where('key', 'platform.minimum_withdrawal')
            ->orderByDesc('version')
            ->value('value') ?? '0');
        $minimumUsdt = $rate !== null && (float) $rate->quote_per_base > 0
            ? $this->usdtMinimumSnapshot($minimumBdt, (string) $rate->quote_per_base)
            : null;

        return view('account.withdrawals.create', [
            'wallets' => DB::table('wallet_accounts')
                ->selectRaw('wallet_accounts.*, CAST(balance - reserved_balance AS CHAR) as available_balance')
                ->where('user_id', auth()->id())
                ->get()
                ->keyBy('currency'),
            'minimumBdt' => $minimumBdt,
            'minimumUsdt' => $minimumUsdt,
            'exchangeRate' => $rate?->quote_per_base,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'currency' => ['required', Rule::in(['BDT', 'USDT'])],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999.99'],
            'destination' => ['required', 'string', 'max:255'],
            'network' => ['nullable', 'string', 'max:40', Rule::requiredIf($request->input('currency') === 'USDT')],
        ]);

        $amountPattern = $validated['currency'] === 'BDT'
            ? '/^\d{1,16}(?:\.\d{1,2})?$/'
            : '/^\d{1,16}(?:\.\d{1,8})?$/';
        if (preg_match($amountPattern, (string) $validated['amount']) !== 1) {
            throw ValidationException::withMessages([
                'amount' => __('site.withdrawals.amount_precision'),
            ]);
        }

        DB::transaction(function () use ($request, $validated): void {
            $minimumSetting = DB::table('platform_settings')
                ->where('key', 'platform.minimum_withdrawal')
                ->orderByDesc('version')
                ->lockForUpdate()
                ->first();
            $minimumBdt = (string) ($minimumSetting->value ?? '0');
            $exchangeRate = null;

            if ($validated['currency'] === 'USDT') {
                $exchangeRate = DB::table('exchange_rates')
                    ->where('base_currency', 'USDT')
                    ->where('quote_currency', 'BDT')
                    ->orderByDesc('version')
                    ->lockForUpdate()
                    ->first();
                if ($exchangeRate === null || (float) $exchangeRate->quote_per_base <= 0) {
                    throw ValidationException::withMessages([
                        'currency' => __('site.withdrawals.exchange_rate_unavailable'),
                    ]);
                }
            }

            $minimumAmount = $validated['currency'] === 'BDT'
                ? $minimumBdt
                : $this->usdtMinimumSnapshot($minimumBdt, (string) $exchangeRate->quote_per_base);
            if ($this->integerDigits($minimumAmount) > 16) {
                throw ValidationException::withMessages([
                    'amount' => __('site.withdrawals.minimum_too_large'),
                ]);
            }

            $minimumSatisfied = $validated['currency'] === 'BDT'
                ? DB::selectOne(
                    'SELECT CASE WHEN CAST(? AS DECIMAL(24,8)) >= CAST(? AS DECIMAL(20,2)) THEN 1 ELSE 0 END AS allowed',
                    [$validated['amount'], $minimumBdt],
                )?->allowed
                : DB::selectOne(
                    'SELECT CASE WHEN CAST(? AS DECIMAL(24,8)) * CAST(? AS DECIMAL(20,8)) >= CAST(? AS DECIMAL(20,2)) THEN 1 ELSE 0 END AS allowed',
                    [$validated['amount'], $exchangeRate->quote_per_base, $minimumBdt],
                )?->allowed;
            if ((int) $minimumSatisfied !== 1) {
                throw ValidationException::withMessages([
                    'amount' => __('site.withdrawals.below_minimum', ['minimum' => $minimumAmount, 'currency' => $validated['currency']]),
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
                    ->whereRaw('balance - reserved_balance >= CAST(? AS DECIMAL(24,8))', [$validated['amount']])
                    ->doesntExist()
            ) {
                throw ValidationException::withMessages([
                    'amount' => __('site.withdrawals.insufficient_balance'),
                ]);
            }

            DB::table('wallet_accounts')->where('id', $wallet->id)
                ->increment('reserved_balance', $validated['amount'], ['updated_at' => now()]);
            $updatedWallet = DB::table('wallet_accounts')->where('id', $wallet->id)->first();
            abort_if($updatedWallet === null, 500, __('site.withdrawals.wallet_unavailable'));

            $withdrawalId = DB::table('withdrawal_requests')->insertGetId([
                'user_id' => $request->user()->id,
                'currency' => $validated['currency'],
                'amount' => $validated['amount'],
                'fee_amount' => '0.00000000',
                'minimum_amount_snapshot' => $minimumAmount,
                'exchange_rate_id' => $exchangeRate?->id,
                'bdt_per_usdt_snapshot' => $exchangeRate?->quote_per_base,
                'destination' => trim($validated['destination']),
                'network' => isset($validated['network']) ? trim($validated['network']) : null,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $ledgerTransactionId = DB::table('ledger_transactions')->insertGetId([
                'idempotency_key' => 'withdrawal-reserve:'.$withdrawalId,
                'type' => 'withdrawal_reserve',
                'status' => 'posted',
                'description' => 'Reserve wallet funds for pending withdrawal request',
                'exchange_rate_id' => $exchangeRate?->id,
                'metadata' => json_encode([
                    'withdrawal_request_id' => $withdrawalId,
                    'currency' => $validated['currency'],
                    'amount' => $validated['amount'],
                    'minimum_amount_snapshot' => $minimumAmount,
                    'bdt_per_usdt_snapshot' => $exchangeRate?->quote_per_base,
                ], JSON_THROW_ON_ERROR),
                'created_by_user_id' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('ledger_entries')->insert([
                'ledger_transaction_id' => $ledgerTransactionId,
                'wallet_account_id' => $wallet->id,
                'entry_type' => 'withdrawal_reserve',
                'amount' => '0.00000000',
                'balance_after' => $updatedWallet->balance,
                'reserved_after' => $updatedWallet->reserved_balance,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('admin_audit_logs')->insert([
                'admin_user_id' => null,
                'action' => 'withdrawal_request.submitted',
                'subject_type' => 'withdrawal_request',
                'subject_id' => $withdrawalId,
                'before_values' => null,
                'after_values' => json_encode([
                    'user_id' => $request->user()->id,
                    'currency' => $validated['currency'],
                    'amount' => $validated['amount'],
                    'minimum_amount_snapshot' => $minimumAmount,
                    'status' => 'pending',
                ], JSON_THROW_ON_ERROR),
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('account.dashboard')->with('status', __('site.withdrawals.submitted'));
    }

    /**
     * @param  array<int, int|string>  $bindings
     */
    private function decimalResult(string $query, array $bindings): string
    {
        $result = DB::selectOne($query, $bindings);
        if ($result === null || ! is_numeric($result->amount)) {
            throw new \RuntimeException('Unable to calculate the withdrawal minimum.');
        }

        return (string) $result->amount;
    }

    private function integerDigits(string $amount): int
    {
        $integer = explode('.', $amount, 2)[0];

        return strlen(ltrim($integer, '0'));
    }

    private function usdtMinimumSnapshot(string $minimumBdt, string $exchangeRate): string
    {
        $division = DB::connection()->getDriverName() === 'sqlite'
            ? 'CAST(? AS NUMERIC) * 1.0 / CAST(? AS NUMERIC)'
            : 'CAST(? AS DECIMAL(20,2)) / CAST(? AS DECIMAL(20,8))';
        $candidate = $this->decimalResult(
            'SELECT CAST(ROUND('.$division.', 8) AS CHAR) AS amount',
            [$minimumBdt, $exchangeRate],
        );
        $meetsMinimum = DB::selectOne(
            'SELECT CASE WHEN CAST(? AS DECIMAL(24,8)) * CAST(? AS DECIMAL(20,8)) >= CAST(? AS DECIMAL(20,2)) THEN 1 ELSE 0 END AS allowed',
            [$candidate, $exchangeRate, $minimumBdt],
        )?->allowed;
        if ((int) $meetsMinimum === 1) {
            return $candidate;
        }

        return $this->decimalResult(
            'SELECT CAST(ROUND(CAST(? AS DECIMAL(24,8)) + 0.00000001, 8) AS CHAR) AS amount',
            [$candidate],
        );
    }
}
