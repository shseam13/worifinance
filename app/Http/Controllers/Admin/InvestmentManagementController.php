<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InvestmentManagementController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');
        abort_unless(in_array($status, ['pending', 'active', 'confirmed', 'matured', 'completed', 'rejected', 'all'], true), 404);

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

        if ($status === 'active') {
            $query->where('investments.status', 'confirmed')
                ->whereNull('investments.principal_returned_at');
        } elseif ($status === 'matured') {
            $query->where('investments.status', 'confirmed')
                ->whereNotNull('investments.matures_at')
                ->where('investments.matures_at', '<=', now())
                ->whereNull('investments.principal_returned_at');
        } elseif ($status === 'completed') {
            $query->where('investments.status', 'confirmed')
                ->whereNotNull('investments.principal_returned_at');
        } elseif ($status !== 'all') {
            $query->where('investments.status', $status);
        }

        return view('admin.investments.index', [
            'pendingDeposits' => DB::table('deposit_requests')->where('status', 'pending')->count(),
            'pendingWithdrawals' => DB::table('withdrawal_requests')->where('status', 'pending')->count(),
            'pendingInvestments' => DB::table('investments')->where('status', 'pending')->count(),
            'pendingMaturityReturns' => DB::table('investments')
                ->where('status', 'confirmed')
                ->whereNotNull('matures_at')
                ->where('matures_at', '<=', now())
                ->whereNull('principal_returned_at')
                ->count(),
            'completedInvestments' => DB::table('investments')
                ->where('status', 'confirmed')
                ->whereNotNull('principal_returned_at')
                ->count(),
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

            $maturesAt = null;
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
                $confirmedAt = now();
                if ($record->duration_months !== null) {
                    $term = DB::table('project_terms')->where('id', $record->project_term_id)->first();
                    abort_if($term === null, 409, __('site.investments.terms_unavailable'));
                    $minimumDuration = (int) ($term->minimum_duration_months ?? 3);
                    $maximumDuration = (int) ($term->maximum_duration_months ?? 24);
                    abort_unless(
                        (int) $record->duration_months >= $minimumDuration
                            && (int) $record->duration_months <= $maximumDuration,
                        409,
                        __('site.investments.duration_unavailable'),
                    );
                    $maturesAt = $confirmedAt->copy()->addMonthsNoOverflow((int) $record->duration_months);
                }

                DB::table('investments')->where('id', $record->id)->update([
                    'status' => 'confirmed',
                    'confirmed_at' => $confirmedAt,
                    'matures_at' => $maturesAt,
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
                    'duration_months' => $record->duration_months,
                    'matures_at' => $validated['decision'] === 'confirmed' ? $maturesAt : null,
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

    public function returnPrincipal(Request $request, int $investment): RedirectResponse
    {
        DB::transaction(function () use ($request, $investment): void {
            $record = DB::table('investments')
                ->where('id', $investment)
                ->lockForUpdate()
                ->first();
            abort_if($record === null, 404);
            abort_unless($record->status === 'confirmed' && $record->matures_at !== null, 409, __('site.investments.principal_return_not_ready'));
            abort_if($record->principal_returned_at !== null, 409, __('site.investments.principal_return_already'));
            abort_unless(
                Carbon::parse($record->matures_at)->lessThanOrEqualTo(now()),
                409,
                __('site.investments.principal_return_not_ready'),
            );

            $wallet = DB::table('wallet_accounts')
                ->where('user_id', $record->user_id)
                ->where('currency', $record->payment_currency)
                ->lockForUpdate()
                ->first();
            abort_if($wallet === null, 500, __('site.investments.wallet_unavailable'));

            DB::table('wallet_accounts')->where('id', $wallet->id)
                ->increment('balance', $record->paid_amount, ['updated_at' => now()]);
            $updatedWallet = DB::table('wallet_accounts')->where('id', $wallet->id)->first();
            abort_if($updatedWallet === null, 500, __('site.investments.wallet_unavailable'));

            $transactionId = DB::table('ledger_transactions')->insertGetId([
                'idempotency_key' => 'investment-principal-return:'.$record->id,
                'type' => 'investment_principal_return',
                'status' => 'posted',
                'description' => 'Return matured project investment principal',
                'exchange_rate_id' => $record->exchange_rate_id,
                'metadata' => json_encode([
                    'investment_id' => $record->id,
                    'maturity_at' => $record->matures_at,
                    'amount' => $record->paid_amount,
                    'currency' => $record->payment_currency,
                    'usd_bdt_rate_snapshot' => $record->usd_bdt_rate_snapshot,
                ], JSON_THROW_ON_ERROR),
                'created_by_user_id' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('ledger_entries')->insert([
                'ledger_transaction_id' => $transactionId,
                'wallet_account_id' => $wallet->id,
                'entry_type' => 'investment_principal_return',
                'amount' => $record->paid_amount,
                'balance_after' => $updatedWallet->balance,
                'reserved_after' => $updatedWallet->reserved_balance,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('investments')->where('id', $record->id)->update([
                'principal_returned_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('admin_audit_logs')->insert([
                'admin_user_id' => $request->user()->id,
                'action' => 'investment.principal_returned',
                'subject_type' => 'investment',
                'subject_id' => $record->id,
                'before_values' => json_encode([
                    'principal_returned_at' => null,
                    'wallet_balance' => $wallet->balance,
                ], JSON_THROW_ON_ERROR),
                'after_values' => json_encode([
                    'principal_returned_at' => now(),
                    'amount' => $record->paid_amount,
                    'currency' => $record->payment_currency,
                    'wallet_balance' => $updatedWallet->balance,
                ], JSON_THROW_ON_ERROR),
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('admin.investments.index', ['status' => 'matured'])
            ->with('status', __('site.investments.principal_return_confirmed'));
    }

    private function createReferralCommission(object $investment, Request $request): void
    {
        $referrerId = DB::table('users')
            ->where('id', $investment->user_id)
            ->value('referred_by_user_id');
        if ($referrerId === null || (int) $referrerId === (int) $investment->user_id) {
            return;
        }

        $commissionType = DB::table('platform_settings')
            ->where('key', 'platform.referral_commission_type')
            ->orderByDesc('version')
            ->lockForUpdate()
            ->value('value') ?? 'percentage';
        $setting = DB::table('platform_settings')
            ->where('key', 'platform.referral_commission_percent')
            ->orderByDesc('version')
            ->lockForUpdate()
            ->first();
        if ($setting === null || (float) $setting->value <= 0) {
            return;
        }

        $amount = $commissionType === 'fixed'
            ? $setting->value
            : DB::selectOne(
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
            'commission_rate_snapshot' => $commissionType === 'fixed' ? '0.0000' : $setting->value,
            'commission_type_snapshot' => $commissionType,
            'commission_value_snapshot' => $setting->value,
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
                'commission_rate_snapshot' => $commissionType === 'fixed' ? '0.0000' : $setting->value,
                'commission_type_snapshot' => $commissionType,
                'commission_value_snapshot' => $setting->value,
                'commission_amount_bdt' => $amount,
                'status' => 'pending',
            ], JSON_THROW_ON_ERROR),
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
