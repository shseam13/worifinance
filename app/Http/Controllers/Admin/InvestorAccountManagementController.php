<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InvestorAccountManagementController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'suspended', 'all'])],
        ]);
        $status = $filters['status'] ?? 'all';
        $search = trim($filters['search'] ?? '');
        $walletTotals = DB::table('wallet_accounts')
            ->select('user_id')
            ->selectRaw("SUM(CASE WHEN currency = 'BDT' THEN balance ELSE 0 END) as bdt_balance")
            ->selectRaw("SUM(CASE WHEN currency = 'USDT' THEN balance ELSE 0 END) as usdt_balance")
            ->groupBy('user_id');
        $investmentTotals = DB::table('investments')
            ->select('user_id')
            ->selectRaw("SUM(CASE WHEN status = 'confirmed' THEN amount_bdt ELSE 0 END) as confirmed_bdt")
            ->selectRaw("SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_count")
            ->groupBy('user_id');
        $query = DB::table('users')
            ->where('users.role', 'investor')
            ->leftJoinSub($walletTotals, 'wallet_totals', 'wallet_totals.user_id', '=', 'users.id')
            ->leftJoinSub($investmentTotals, 'investment_totals', 'investment_totals.user_id', '=', 'users.id')
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.phone',
                'users.referral_code',
                'users.status',
                'users.created_at',
                'wallet_totals.bdt_balance',
                'wallet_totals.usdt_balance',
                'investment_totals.confirmed_bdt',
                'investment_totals.confirmed_count',
            ]);

        if ($status !== 'all') {
            $query->where('users.status', $status);
        }
        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('users.phone', 'like', "%{$search}%")
                    ->orWhere('users.referral_code', 'like', "%{$search}%");
            });
        }

        return view('admin.investors.index', [
            ...$this->navigationCounts(),
            'accounts' => $query->orderByDesc('users.created_at')->paginate(20)->withQueryString(),
            'activeCount' => DB::table('users')->where('role', 'investor')->where('status', 'active')->count(),
            'suspendedCount' => DB::table('users')->where('role', 'investor')->where('status', 'suspended')->count(),
            'totalCount' => DB::table('users')->where('role', 'investor')->count(),
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function show(int $investor): View
    {
        $account = $this->investor($investor);

        return view('admin.investors.show', [
            ...$this->navigationCounts(),
            'account' => $account,
            'wallets' => DB::table('wallet_accounts')
                ->where('user_id', $account->id)
                ->select('*')
                ->selectRaw('balance - reserved_balance as available_balance')
                ->orderBy('currency')
                ->get(),
            'confirmedInvestmentCount' => DB::table('investments')->where('user_id', $account->id)->where('status', 'confirmed')->count(),
            'confirmedInvestmentTotal' => DB::table('investments')->where('user_id', $account->id)->where('status', 'confirmed')->sum('amount_bdt'),
            'pendingInvestmentCount' => DB::table('investments')->where('user_id', $account->id)->where('status', 'pending')->count(),
            'referrals' => DB::table('users')
                ->where('referred_by_user_id', $account->id)
                ->select(['id', 'name', 'email', 'status', 'created_at'])
                ->latest()
                ->limit(10)
                ->get(),
            'referralCount' => DB::table('users')->where('referred_by_user_id', $account->id)->count(),
            'referrer' => $account->referred_by_user_id
                ? DB::table('users')->where('id', $account->referred_by_user_id)->where('role', 'investor')->select(['id', 'name'])->first()
                : null,
            'recentInvestments' => DB::table('investments')
                ->join('projects', 'projects.id', '=', 'investments.project_id')
                ->where('investments.user_id', $account->id)
                ->select(['investments.id', 'investments.amount_bdt', 'investments.payment_currency', 'investments.paid_amount', 'investments.status', 'investments.created_at', 'projects.name as project_name'])
                ->latest('investments.created_at')
                ->limit(10)
                ->get(),
            'recentDeposits' => DB::table('deposit_requests')
                ->where('user_id', $account->id)
                ->select(['id', 'amount', 'currency', 'status', 'payment_reference', 'created_at'])
                ->latest()
                ->limit(10)
                ->get(),
            'recentWithdrawals' => DB::table('withdrawal_requests')
                ->where('user_id', $account->id)
                ->select(['id', 'amount', 'currency', 'status', 'destination', 'created_at'])
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    public function update(Request $request, int $investor): RedirectResponse
    {
        $account = $this->investor($investor);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($account->id)],
            'phone' => ['nullable', 'string', 'max:32', Rule::unique('users', 'phone')->ignore($account->id)],
        ]);

        DB::transaction(function () use ($request, $account, $validated): void {
            $lockedAccount = DB::table('users')->where('id', $account->id)->lockForUpdate()->first();
            abort_if($lockedAccount === null || $lockedAccount->role !== 'investor', 404);
            $before = ['name' => $lockedAccount->name, 'email' => $lockedAccount->email, 'phone' => $lockedAccount->phone];
            DB::table('users')->where('id', $lockedAccount->id)->update([
                ...$validated,
                'updated_at' => now(),
            ]);
            $this->audit($request, $lockedAccount->id, 'investor_account.updated', $before, $validated);
        });

        return redirect()->route('admin.investors.show', $account->id)->with('status', __('site.investor_accounts.profile_saved'));
    }

    public function updateStatus(Request $request, int $investor): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended'])],
        ]);
        $account = $this->investor($investor);

        DB::transaction(function () use ($request, $account, $validated): void {
            $lockedAccount = DB::table('users')->where('id', $account->id)->lockForUpdate()->first();
            abort_if($lockedAccount === null || $lockedAccount->role !== 'investor', 404);
            if ($lockedAccount->status === $validated['status']) {
                return;
            }

            DB::table('users')->where('id', $lockedAccount->id)->update([
                'status' => $validated['status'],
                'updated_at' => now(),
            ]);
            $this->audit(
                $request,
                $lockedAccount->id,
                'investor_account.status_changed',
                ['status' => $lockedAccount->status],
                ['status' => $validated['status']],
            );
        });

        $message = $validated['status'] === 'suspended'
            ? __('site.investor_accounts.account_suspended')
            : __('site.investor_accounts.account_activated');

        return redirect()->route('admin.investors.show', $account->id)->with('status', $message);
    }

    private function investor(int $id): object
    {
        $account = DB::table('users')->where('id', $id)->where('role', 'investor')->first();
        abort_if($account === null, 404);

        return $account;
    }

    private function navigationCounts(): array
    {
        return [
            'pendingDeposits' => DB::table('deposit_requests')->where('status', 'pending')->count(),
            'pendingWithdrawals' => DB::table('withdrawal_requests')->where('status', 'pending')->count(),
        ];
    }

    private function audit(Request $request, int $investorId, string $action, array $before, array $after): void
    {
        DB::table('admin_audit_logs')->insert([
            'admin_user_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => 'investor_account',
            'subject_id' => $investorId,
            'before_values' => json_encode($before, JSON_THROW_ON_ERROR),
            'after_values' => json_encode($after, JSON_THROW_ON_ERROR),
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
