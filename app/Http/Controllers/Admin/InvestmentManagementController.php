<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InvestmentManagementController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');
        abort_unless(in_array($status, ['pending', 'confirmed', 'rejected', 'all'], true), 404);

        $query = DB::table('investments')
            ->join('users', 'users.id', '=', 'investments.user_id')
            ->join('projects', 'projects.id', '=', 'investments.project_id')
            ->select([
                'investments.*',
                'users.name as investor_name',
                'users.email as investor_email',
                'projects.name as project_name',
                'projects.name_bn as project_name_bn',
            ]);

        if ($status !== 'all') {
            $query->where('investments.status', $status);
        }

        return view('admin.investments.index', [
            'pendingDeposits' => DB::table('deposit_requests')->where('status', 'pending')->count(),
            'pendingWithdrawals' => DB::table('withdrawal_requests')->where('status', 'pending')->count(),
            'pendingInvestments' => DB::table('investments')->where('status', 'pending')->count(),
            'investments' => $query->latest('investments.created_at')->paginate(20)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function review(Request $request, int $investment): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['confirmed', 'rejected'])],
            'review_note' => ['nullable', 'string', 'max:2000', Rule::requiredIf($request->input('decision') === 'rejected')],
        ]);

        DB::transaction(function () use ($request, $investment, $validated): void {
            $initialRecord = DB::table('investments')->where('id', $investment)->first();
            abort_if($initialRecord === null, 404);
            $project = DB::table('projects')
                ->where('id', $initialRecord->project_id)
                ->lockForUpdate()
                ->first();
            abort_if($project === null, 404);
            $record = DB::table('investments')->where('id', $investment)->lockForUpdate()->first();
            abort_if($record === null, 404);
            abort_unless($record->status === 'pending', 409, __('site.investments.already_reviewed'));

            $wallet = DB::table('wallet_accounts')
                ->where('user_id', $record->user_id)
                ->where('currency', $record->payment_currency)
                ->lockForUpdate()
                ->first();
            abort_if($wallet === null, 500, __('site.investments.wallet_unavailable'));

            $hasReservedFunds = DB::table('wallet_accounts')
                ->where('id', $wallet->id)
                ->whereRaw('reserved_balance >= CAST(? AS DECIMAL(24,8))', [$record->paid_amount])
                ->exists();
            abort_unless($hasReservedFunds, 409, __('site.investments.reservation_unavailable'));

            if ($validated['decision'] === 'confirmed') {
                $hasBalance = DB::table('wallet_accounts')
                    ->where('id', $wallet->id)
                    ->whereRaw('balance >= CAST(? AS DECIMAL(24,8))', [$record->paid_amount])
                    ->exists();
                abort_unless($hasBalance, 409, __('site.investments.reservation_unavailable'));

                DB::table('wallet_accounts')->where('id', $wallet->id)
                    ->decrement('balance', $record->paid_amount, ['updated_at' => now()]);
                DB::table('wallet_accounts')->where('id', $wallet->id)
                    ->decrement('reserved_balance', $record->paid_amount, ['updated_at' => now()]);
                DB::table('investments')->where('id', $record->id)->update([
                    'status' => 'confirmed',
                    'confirmed_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->createReferralCommission($record, $request);
            } else {
                DB::table('wallet_accounts')->where('id', $wallet->id)
                    ->decrement('reserved_balance', $record->paid_amount, ['updated_at' => now()]);
                DB::table('projects')->where('id', $record->project_id)
                    ->increment('available_units', $record->unit_count, ['updated_at' => now()]);
                DB::table('investments')->where('id', $record->id)->update([
                    'status' => 'rejected',
                    'updated_at' => now(),
                ]);
            }

            $updatedWallet = DB::table('wallet_accounts')->where('id', $wallet->id)->first();
            abort_if($updatedWallet === null, 500, __('site.investments.wallet_unavailable'));

            $transactionId = DB::table('ledger_transactions')->insertGetId([
                'idempotency_key' => 'investment-review:'.$record->id,
                'type' => $validated['decision'] === 'confirmed' ? 'investment_confirmation' : 'investment_release',
                'status' => 'posted',
                'description' => $validated['decision'] === 'confirmed'
                    ? 'Confirm project investment and debit reserved funds'
                    : 'Release reserved funds for rejected project investment',
                'exchange_rate_id' => $record->exchange_rate_id,
                'metadata' => json_encode([
                    'investment_id' => $record->id,
                    'decision' => $validated['decision'],
                    'review_note' => $validated['review_note'] ?? null,
                ], JSON_THROW_ON_ERROR),
                'created_by_user_id' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('ledger_entries')->insert([
                'ledger_transaction_id' => $transactionId,
                'wallet_account_id' => $wallet->id,
                'entry_type' => $validated['decision'] === 'confirmed' ? 'investment_debit' : 'investment_release',
                'amount' => $validated['decision'] === 'confirmed'
                    ? '-'.$record->paid_amount
                    : '0.00000000',
                'balance_after' => $updatedWallet->balance,
                'reserved_after' => $updatedWallet->reserved_balance,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('admin_audit_logs')->insert([
                'admin_user_id' => $request->user()->id,
                'action' => 'investment.'.$validated['decision'],
                'subject_type' => 'investment',
                'subject_id' => $record->id,
                'before_values' => json_encode(['status' => 'pending'], JSON_THROW_ON_ERROR),
                'after_values' => json_encode([
                    'status' => $validated['decision'],
                    'review_note' => $validated['review_note'] ?? null,
                ], JSON_THROW_ON_ERROR),
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $message = $validated['decision'] === 'confirmed'
            ? __('site.investments.confirmed')
            : __('site.investments.rejected');

        return redirect()->route('admin.investments.index')->with('status', $message);
    }

    private function createReferralCommission(object $investment, Request $request): void
    {
        $referrerId = DB::table('users')
            ->where('id', $investment->user_id)
            ->value('referred_by_user_id');
        if ($referrerId === null || (int) $referrerId === (int) $investment->user_id) {
            return;
        }

        $setting = DB::table('platform_settings')
            ->where('key', 'platform.referral_commission_percent')
            ->orderByDesc('version')
            ->lockForUpdate()
            ->first();
        if ($setting === null || (float) $setting->value <= 0) {
            return;
        }

        $amount = DB::selectOne(
            'SELECT CAST(ROUND(CAST(? AS DECIMAL(20,2)) * CAST(? AS DECIMAL(8,4)) / 100, 2) AS CHAR) AS amount',
            [$investment->amount_bdt, $setting->value],
        )?->amount;
        if ($amount === null || ! is_numeric($amount)) {
            throw new \RuntimeException('Unable to calculate the referral commission.');
        }
        if (preg_match('/^0(?:\.0+)?$/', (string) $amount) === 1) {
            return;
        }

        $commissionId = DB::table('referral_commissions')->insertGetId([
            'referrer_user_id' => $referrerId,
            'referred_user_id' => $investment->user_id,
            'investment_id' => $investment->id,
            'commission_rate_snapshot' => $setting->value,
            'basis_amount_bdt' => $investment->amount_bdt,
            'commission_amount_bdt' => $amount,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_audit_logs')->insert([
            'admin_user_id' => $request->user()->id,
            'action' => 'referral_commission.calculated',
            'subject_type' => 'referral_commission',
            'subject_id' => $commissionId,
            'before_values' => null,
            'after_values' => json_encode([
                'investment_id' => $investment->id,
                'referrer_user_id' => $referrerId,
                'referred_user_id' => $investment->user_id,
                'basis_amount_bdt' => $investment->amount_bdt,
                'commission_rate_snapshot' => $setting->value,
                'commission_amount_bdt' => $amount,
                'status' => 'pending',
            ], JSON_THROW_ON_ERROR),
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
