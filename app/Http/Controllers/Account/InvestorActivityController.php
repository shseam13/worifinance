<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InvestorActivityController extends Controller
{
    public function profitWallet(Request $request): View
    {
        $userId = (int) $request->user()->id;

        return view('account.profit-wallet', [
            'wallets' => DB::table('wallet_accounts')
                ->where('user_id', $userId)
                ->get()
                ->keyBy('currency'),
            'profitTotals' => $this->profitTotals($userId),
            'recentProfits' => $this->profitQuery($userId)
                ->latest('profit_distributions.period_end')
                ->limit(10)
                ->get(),
        ]);
    }

    public function profitHistory(Request $request): View
    {
        $filters = $request->validate([
            'currency' => ['nullable', Rule::in(['BDT', 'USDT'])],
        ]);
        $userId = (int) $request->user()->id;
        $query = $this->profitQuery($userId);

        if (! empty($filters['currency'])) {
            $query->where('distribution_items.payout_currency', $filters['currency']);
        }

        return view('account.profit-history', [
            'profitHistory' => $query
                ->orderByDesc('profit_distributions.period_end')
                ->orderByDesc('distribution_items.id')
                ->paginate(20)
                ->withQueryString(),
            'profitTotals' => $this->profitTotals($userId),
            'currency' => $filters['currency'] ?? '',
        ]);
    }

    public function transactions(Request $request): View
    {
        $filters = $request->validate([
            'currency' => ['nullable', Rule::in(['BDT', 'USDT'])],
        ]);
        $query = DB::table('ledger_entries')
            ->join('wallet_accounts', 'wallet_accounts.id', '=', 'ledger_entries.wallet_account_id')
            ->join('ledger_transactions', 'ledger_transactions.id', '=', 'ledger_entries.ledger_transaction_id')
            ->where('wallet_accounts.user_id', $request->user()->id)
            ->select([
                'ledger_transactions.id as transaction_id',
                'ledger_transactions.type',
                'ledger_transactions.status',
                'ledger_transactions.description',
                'ledger_transactions.created_at',
                'ledger_entries.entry_type',
                'ledger_entries.amount',
                'ledger_entries.balance_after',
                'ledger_entries.reserved_after',
                'wallet_accounts.currency',
            ]);

        if (! empty($filters['currency'])) {
            $query->where('wallet_accounts.currency', $filters['currency']);
        }

        return view('account.transactions.index', [
            'transactions' => $query
                ->latest('ledger_transactions.created_at')
                ->latest('ledger_entries.id')
                ->paginate(30)
                ->withQueryString(),
            'currency' => $filters['currency'] ?? '',
        ]);
    }

    private function profitQuery(int $userId): Builder
    {
        return DB::table('distribution_items')
            ->join('profit_distributions', 'profit_distributions.id', '=', 'distribution_items.profit_distribution_id')
            ->join('investments', 'investments.id', '=', 'distribution_items.investment_id')
            ->join('projects', 'projects.id', '=', 'investments.project_id')
            ->where('investments.user_id', $userId)
            ->where('profit_distributions.status', 'posted')
            ->select([
                'distribution_items.id',
                'distribution_items.investment_id',
                'distribution_items.amount',
                'distribution_items.payout_currency',
                'distribution_items.eligible_principal_bdt',
                'distribution_items.monthly_rate_snapshot',
                'distribution_items.annual_rate_snapshot',
                'profit_distributions.period_start',
                'profit_distributions.period_end',
                'projects.name as project_name',
                'projects.name_bn as project_name_bn',
            ]);
    }

    private function profitTotals(int $userId): Collection
    {
        return DB::table('distribution_items')
            ->join('profit_distributions', 'profit_distributions.id', '=', 'distribution_items.profit_distribution_id')
            ->join('investments', 'investments.id', '=', 'distribution_items.investment_id')
            ->where('investments.user_id', $userId)
            ->where('profit_distributions.status', 'posted')
            ->selectRaw('distribution_items.payout_currency as currency, SUM(distribution_items.amount) as total, COUNT(*) as distribution_count')
            ->groupBy('distribution_items.payout_currency')
            ->get()
            ->keyBy('currency');
    }
}
