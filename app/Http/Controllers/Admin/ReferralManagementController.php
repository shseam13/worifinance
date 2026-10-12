<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReferralManagementController extends Controller
{
    public function index(): View
    {
        $currentRate = DB::table('platform_settings')
            ->where('key', 'platform.referral_commission_percent')
            ->orderByDesc('version')
            ->value('value');

        $referredInvestmentTotals = DB::table('investments')
            ->select('user_id', DB::raw('SUM(amount_bdt) as confirmed_bdt'), DB::raw('COUNT(*) as confirmed_count'))
            ->where('status', 'confirmed')
            ->groupBy('user_id');

        $referralTotals = DB::table('referral_commissions')
            ->select(
                'referrer_user_id',
                DB::raw("SUM(CASE WHEN status = 'pending' THEN commission_amount_bdt ELSE 0 END) as pending_bdt"),
                DB::raw("SUM(CASE WHEN status = 'credited' THEN commission_amount_bdt ELSE 0 END) as credited_bdt"),
            )
            ->groupBy('referrer_user_id');

        $referrers = DB::table('users')
            ->where('users.role', 'investor')
            ->leftJoin('users as referred_users', 'referred_users.referred_by_user_id', '=', 'users.id')
            ->leftJoinSub($referredInvestmentTotals, 'investment_totals', 'investment_totals.user_id', '=', 'referred_users.id')
            ->leftJoinSub($referralTotals, 'referral_totals', 'referral_totals.referrer_user_id', '=', 'users.id')
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'users.referral_code',
                DB::raw('COUNT(DISTINCT referred_users.id) as referred_count'),
                DB::raw('COALESCE(SUM(investment_totals.confirmed_bdt), 0) as confirmed_bdt'),
                DB::raw('COALESCE(SUM(investment_totals.confirmed_count), 0) as confirmed_count'),
                DB::raw('COALESCE(MAX(referral_totals.pending_bdt), 0) as pending_bdt'),
                DB::raw('COALESCE(MAX(referral_totals.credited_bdt), 0) as credited_bdt'),
            )
            ->groupBy('users.id', 'users.name', 'users.email', 'users.referral_code')
            ->havingRaw('COUNT(DISTINCT referred_users.id) > 0')
            ->orderByDesc('referred_count')
            ->paginate(20);

        return view('admin.referrals.index', [
            'pendingDeposits' => DB::table('deposit_requests')->where('status', 'pending')->count(),
            'pendingWithdrawals' => DB::table('withdrawal_requests')->where('status', 'pending')->count(),
            'referrers' => $referrers,
            'currentRate' => $currentRate ?? '0',
            'pendingCommissionItems' => DB::table('referral_commissions')
                ->join('users as referrers', 'referrers.id', '=', 'referral_commissions.referrer_user_id')
                ->join('users as referred_users', 'referred_users.id', '=', 'referral_commissions.referred_user_id')
                ->join('investments', 'investments.id', '=', 'referral_commissions.investment_id')
                ->join('projects', 'projects.id', '=', 'investments.project_id')
                ->where('referral_commissions.status', 'pending')
                ->select([
                    'referral_commissions.*',
                    'referrers.name as referrer_name',
                    'referrers.email as referrer_email',
                    'referred_users.name as referred_name',
                    'referred_users.email as referred_email',
                    'projects.name as project_name',
                    'projects.name_bn as project_name_bn',
                ])
                ->latest('referral_commissions.created_at')
                ->paginate(20, ['*'], 'commission_page'),
            'totalReferrers' => (int) DB::table('users')->where('role', 'investor')->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('users as referrals')
                    ->whereColumn('referrals.referred_by_user_id', 'users.id');
            })->count(),
            'totalReferred' => (int) DB::table('users')->whereNotNull('referred_by_user_id')->count(),
            'pendingCommissions' => DB::table('referral_commissions')->where('status', 'pending')->sum('commission_amount_bdt'),
            'creditedCommissions' => DB::table('referral_commissions')->where('status', 'credited')->sum('commission_amount_bdt'),
        ]);
    }

    public function credit(Request $request, int $commission): RedirectResponse
    {
        $request->validate([
            'decision' => ['required', Rule::in(['credit'])],
        ]);

        DB::transaction(function () use ($request, $commission): void {
            $record = DB::table('referral_commissions')
                ->where('id', $commission)
                ->lockForUpdate()
                ->first();
            abort_if($record === null, 404);
            abort_unless($record->status === 'pending', 409, __('site.admin_referrals.already_reviewed'));

            $investment = DB::table('investments')
                ->where('id', $record->investment_id)
                ->lockForUpdate()
                ->first();
            abort_if($investment === null, 404);
            abort_unless($investment->status === 'confirmed', 409, __('site.admin_referrals.investment_not_confirmed'));

            DB::table('wallet_accounts')->insertOrIgnore([
                'user_id' => $record->referrer_user_id,
                'currency' => 'BDT',
                'balance' => '0.00000000',
                'reserved_balance' => '0.00000000',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $wallet = DB::table('wallet_accounts')
                ->where('user_id', $record->referrer_user_id)
                ->where('currency', 'BDT')
                ->lockForUpdate()
                ->first();
            abort_if($wallet === null, 500, __('site.admin_referrals.wallet_unavailable'));

            DB::table('wallet_accounts')->where('id', $wallet->id)
                ->increment('balance', $record->commission_amount_bdt, ['updated_at' => now()]);
            $updatedWallet = DB::table('wallet_accounts')->where('id', $wallet->id)->first();
            abort_if($updatedWallet === null, 500, __('site.admin_referrals.wallet_unavailable'));

            $ledgerTransactionId = DB::table('ledger_transactions')->insertGetId([
                'idempotency_key' => 'referral-commission:'.$record->id,
                'type' => 'referral_commission',
                'status' => 'posted',
                'description' => 'Credit approved referral commission',
                'metadata' => json_encode([
                    'referral_commission_id' => $record->id,
                    'investment_id' => $record->investment_id,
                    'referred_user_id' => $record->referred_user_id,
                    'commission_rate_snapshot' => $record->commission_rate_snapshot,
                    'basis_amount_bdt' => $record->basis_amount_bdt,
                ], JSON_THROW_ON_ERROR),
                'created_by_user_id' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('ledger_entries')->insert([
                'ledger_transaction_id' => $ledgerTransactionId,
                'wallet_account_id' => $wallet->id,
                'entry_type' => 'referral_commission_credit',
                'amount' => $record->commission_amount_bdt,
                'balance_after' => $updatedWallet->balance,
                'reserved_after' => $updatedWallet->reserved_balance,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('referral_commissions')->where('id', $record->id)->update([
                'status' => 'credited',
                'credited_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('admin_audit_logs')->insert([
                'admin_user_id' => $request->user()->id,
                'action' => 'referral_commission.credited',
                'subject_type' => 'referral_commission',
                'subject_id' => $record->id,
                'before_values' => json_encode(['status' => 'pending'], JSON_THROW_ON_ERROR),
                'after_values' => json_encode([
                    'status' => 'credited',
                    'referrer_user_id' => $record->referrer_user_id,
                    'commission_amount_bdt' => $record->commission_amount_bdt,
                ], JSON_THROW_ON_ERROR),
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('admin.referrals.index')->with('status', __('site.admin_referrals.credited'));
    }
}
