<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function investor(): View
    {
        $user = auth()->user();
        $userId = $user->id;

        if ($user->referral_code === null) {
            do {
                $code = Str::upper(Str::random(10));
            } while (DB::table('users')->where('referral_code', $code)->exists());

            $user->forceFill(['referral_code' => $code])->save();
        }

        $confirmedReferralInvestments = DB::table('investments')
            ->select('user_id', DB::raw('SUM(amount_bdt) as confirmed_bdt'))
            ->where('status', 'confirmed')
            ->groupBy('user_id');
        $referredUsers = DB::table('users')
            ->leftJoinSub($confirmedReferralInvestments, 'investment_totals', 'investment_totals.user_id', '=', 'users.id')
            ->where('users.referred_by_user_id', $userId)
            ->select('users.id', 'users.name', 'users.created_at', DB::raw('COALESCE(investment_totals.confirmed_bdt, 0) as confirmed_bdt'))
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();
        $referredCount = DB::table('users')
            ->where('referred_by_user_id', $userId)
            ->count();
        $referralInvestmentTotals = DB::table('investments')
            ->join('users', 'users.id', '=', 'investments.user_id')
            ->where('users.referred_by_user_id', $userId)
            ->where('investments.status', 'confirmed')
            ->sum('investments.amount_bdt');
        $referralCommissionTotals = DB::table('referral_commissions')
            ->where('referrer_user_id', $userId)
            ->select('status', DB::raw('SUM(commission_amount_bdt) as total'), DB::raw('COUNT(*) as commission_count'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $wallets = DB::table('wallet_accounts')
            ->where('user_id', $userId)
            ->get()
            ->keyBy('currency');

        $investmentSummary = DB::table('investments')
            ->where('user_id', $userId)
            ->select('status', DB::raw('COUNT(*) as investment_count'), DB::raw('SUM(amount_bdt) as amount_bdt'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $paidProfits = DB::table('distribution_items')
            ->join('profit_distributions', 'profit_distributions.id', '=', 'distribution_items.profit_distribution_id')
            ->join('investments', 'investments.id', '=', 'distribution_items.investment_id')
            ->where('investments.user_id', $userId)
            ->where('profit_distributions.status', 'posted')
            ->select('distribution_items.payout_currency as currency', DB::raw('SUM(distribution_items.amount) as total'))
            ->groupBy('distribution_items.payout_currency')
            ->get()
            ->keyBy('currency');

        $creditedCommissionBdt = DB::table('referral_commissions')
            ->where('referrer_user_id', $userId)
            ->where('status', 'credited')
            ->sum('commission_amount_bdt');

        $investments = DB::table('investments')
            ->join('projects', 'projects.id', '=', 'investments.project_id')
            ->where('investments.user_id', $userId)
            ->select([
                'investments.id',
                'investments.unit_count',
                'investments.amount_bdt',
                'investments.paid_amount',
                'investments.payment_currency',
                'investments.status',
                'investments.created_at',
                'projects.name as project_name',
                'projects.name_bn as project_name_bn',
                'projects.slug as project_slug',
            ])
            ->latest('investments.created_at')
            ->limit(8)
            ->get();

        $depositRequests = DB::table('deposit_requests')
            ->where('user_id', $userId)
            ->latest()
            ->limit(5)
            ->get();

        $withdrawalRequests = DB::table('withdrawal_requests')
            ->where('user_id', $userId)
            ->latest()
            ->limit(5)
            ->get();
        $membershipCharges = DB::table('membership_charges')
            ->where('user_id', $userId)
            ->latest('period_start')
            ->limit(5)
            ->get();

        $profitHistory = DB::table('distribution_items')
            ->join('profit_distributions', 'profit_distributions.id', '=', 'distribution_items.profit_distribution_id')
            ->join('investments', 'investments.id', '=', 'distribution_items.investment_id')
            ->join('projects', 'projects.id', '=', 'investments.project_id')
            ->where('investments.user_id', $userId)
            ->where('profit_distributions.status', 'posted')
            ->select([
                'distribution_items.amount',
                'distribution_items.payout_currency',
                'profit_distributions.period_start',
                'profit_distributions.period_end',
                'profit_distributions.status',
                'projects.name as project_name',
                'projects.name_bn as project_name_bn',
            ])
            ->latest('profit_distributions.period_end')
            ->limit(5)
            ->get();

        return view('account.dashboard', [
            'wallets' => $wallets,
            'confirmedInvestment' => $investmentSummary->get('confirmed'),
            'pendingInvestment' => $investmentSummary->get('pending'),
            'paidProfits' => $paidProfits,
            'creditedCommissionBdt' => $creditedCommissionBdt,
            'investments' => $investments,
            'depositRequests' => $depositRequests,
            'withdrawalRequests' => $withdrawalRequests,
            'membershipCharges' => $membershipCharges,
            'profitHistory' => $profitHistory,
            'referralUrl' => route('register', ['ref' => $user->referral_code]),
            'referredUsers' => $referredUsers,
            'referredCount' => $referredCount,
            'referralInvestmentTotal' => $referralInvestmentTotals,
            'referralCommissionTotals' => $referralCommissionTotals,
        ]);
    }

    public function admin(): View
    {
        $walletTotals = DB::table('wallet_accounts')
            ->select('currency', DB::raw('SUM(balance) as balance'), DB::raw('SUM(reserved_balance) as reserved_balance'))
            ->groupBy('currency')
            ->get()
            ->keyBy('currency');

        $accounts = DB::table('users')
            ->where('role', 'investor')
            ->select('status', DB::raw('COUNT(*) as account_count'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $profitTotals = DB::table('distribution_items')
            ->join('profit_distributions', 'profit_distributions.id', '=', 'distribution_items.profit_distribution_id')
            ->whereIn('profit_distributions.status', ['approved', 'posted'])
            ->select(
                'profit_distributions.status',
                'distribution_items.payout_currency as currency',
                DB::raw('SUM(distribution_items.amount) as total'),
            )
            ->groupBy('profit_distributions.status', 'distribution_items.payout_currency')
            ->get();

        $commissionTotals = DB::table('referral_commissions')
            ->whereIn('status', ['pending', 'credited'])
            ->select('status', DB::raw('SUM(commission_amount_bdt) as total'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $payableProfits = $profitTotals->where('status', 'approved')->keyBy('currency');
        $paidProfits = $profitTotals->where('status', 'posted')->keyBy('currency');

        return view('admin.dashboard', [
            'totalInvestors' => (int) $accounts->sum('account_count'),
            'activeInvestors' => (int) ($accounts->get('active')?->account_count ?? 0),
            'suspendedInvestors' => (int) ($accounts->get('suspended')?->account_count ?? 0),
            'publishedProjects' => DB::table('projects')->where('status', 'published')->count(),
            'pendingDeposits' => DB::table('deposit_requests')->where('status', 'pending')->count(),
            'pendingWithdrawals' => DB::table('withdrawal_requests')->where('status', 'pending')->count(),
            'dueMembershipCharges' => DB::table('membership_charges')->where('status', 'due')->count(),
            'pendingInvestments' => DB::table('investments')->where('status', 'pending')->count(),
            'pendingProfitDistributions' => DB::table('profit_distributions')->whereIn('status', ['draft', 'approved'])->count(),
            'pendingReferralCommissions' => DB::table('referral_commissions')->where('status', 'pending')->count(),
            'confirmedInvestmentTotal' => DB::table('investments')
                ->where('status', 'confirmed')
                ->sum('amount_bdt'),
            'confirmedInvestmentCount' => DB::table('investments')->where('status', 'confirmed')->count(),
            'walletTotals' => $walletTotals,
            'payableProfits' => $payableProfits,
            'paidProfits' => $paidProfits,
            'commissionTotals' => $commissionTotals,
            'recentDeposits' => DB::table('deposit_requests')
                ->join('users', 'users.id', '=', 'deposit_requests.user_id')
                ->select('deposit_requests.*', 'users.name as investor_name', 'users.email as investor_email')
                ->latest('deposit_requests.created_at')
                ->limit(6)
                ->get(),
            'recentWithdrawals' => DB::table('withdrawal_requests')
                ->join('users', 'users.id', '=', 'withdrawal_requests.user_id')
                ->select('withdrawal_requests.*', 'users.name as investor_name', 'users.email as investor_email')
                ->latest('withdrawal_requests.created_at')
                ->limit(6)
                ->get(),
            'recentInvestments' => DB::table('investments')
                ->join('users', 'users.id', '=', 'investments.user_id')
                ->join('projects', 'projects.id', '=', 'investments.project_id')
                ->select(
                    'investments.*',
                    'users.name as investor_name',
                    'projects.name as project_name',
                    'projects.name_bn as project_name_bn',
                )
                ->latest('investments.created_at')
                ->limit(8)
                ->get(),
            'topProjects' => DB::table('investments')
                ->join('projects', 'projects.id', '=', 'investments.project_id')
                ->where('investments.status', 'confirmed')
                ->select(
                    'projects.name as project_name',
                    'projects.name_bn as project_name_bn',
                    DB::raw('COUNT(investments.id) as investment_count'),
                    DB::raw('SUM(investments.amount_bdt) as invested_bdt'),
                )
                ->groupBy('projects.id', 'projects.name', 'projects.name_bn')
                ->orderByDesc('invested_bdt')
                ->limit(5)
                ->get(),
        ]);
    }
}
