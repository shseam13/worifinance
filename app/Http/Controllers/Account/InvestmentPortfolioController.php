<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InvestmentPortfolioController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['all', 'pending', 'active', 'completed', 'rejected'])],
        ]);
        $status = $filters['status'] ?? 'all';
        $query = $this->investmentQuery((int) $request->user()->id);

        if ($status === 'pending' || $status === 'rejected') {
            $query->where('investments.status', $status);
        } elseif ($status === 'active') {
            $query->where('investments.status', 'confirmed')
                ->whereNull('investments.principal_returned_at');
        } elseif ($status === 'completed') {
            $query->where('investments.status', 'confirmed')
                ->whereNotNull('investments.principal_returned_at');
        }

        $investments = $query->latest('investments.created_at')
            ->latest('investments.id')
            ->paginate(20)
            ->withQueryString();
        $investments->getCollection()->transform(function (object $investment): object {
            $investment->progress_percent = $this->progressPercent($investment);
            $investment->display_status = $this->displayStatus($investment);

            return $investment;
        });

        return view('account.investments.index', [
            'investments' => $investments,
            'status' => $status,
        ]);
    }

    public function show(Request $request, int $investment): View
    {
        $record = $this->findInvestment((int) $request->user()->id, $investment);
        $record->progress_percent = $this->progressPercent($record);
        $record->display_status = $this->displayStatus($record);

        return view('account.investments.show', [
            'investment' => $record,
            'profitHistory' => $this->profitQuery($record->id)
                ->latest('profit_distributions.period_end')
                ->limit(12)
                ->get(),
            'profitTotals' => $this->profitTotals($record->id),
        ]);
    }

    public function statement(Request $request, int $investment): View
    {
        $record = $this->findInvestment((int) $request->user()->id, $investment);
        $record->progress_percent = $this->progressPercent($record);
        $record->display_status = $this->displayStatus($record);

        return view('account.investments.statement', [
            'investment' => $record,
            'profitHistory' => $this->profitQuery($record->id)
                ->orderBy('profit_distributions.period_start')
                ->get(),
            'profitTotals' => $this->profitTotals($record->id),
            'generatedAt' => now(),
        ]);
    }

    private function investmentQuery(int $userId): Builder
    {
        return DB::table('investments')
            ->join('projects', 'projects.id', '=', 'investments.project_id')
            ->join('project_terms', 'project_terms.id', '=', 'investments.project_term_id')
            ->where('investments.user_id', $userId)
            ->select([
                'investments.id',
                'investments.project_id',
                'investments.project_term_id',
                'investments.unit_count',
                'investments.unit_price_bdt',
                'investments.amount_bdt',
                'investments.payment_currency',
                'investments.paid_amount',
                'investments.duration_months',
                'investments.status',
                'investments.confirmed_at',
                'investments.matures_at',
                'investments.principal_returned_at',
                'investments.created_at',
                'projects.name as project_name',
                'projects.name_bn as project_name_bn',
                'projects.slug as project_slug',
                'project_terms.monthly_rate_percent',
                'project_terms.annual_rate_percent',
            ]);
    }

    private function findInvestment(int $userId, int $investmentId): object
    {
        $investment = $this->investmentQuery($userId)
            ->where('investments.id', $investmentId)
            ->first();
        abort_if($investment === null, 404);

        return $investment;
    }

    private function profitQuery(int $investmentId): Builder
    {
        return DB::table('distribution_items')
            ->join('profit_distributions', 'profit_distributions.id', '=', 'distribution_items.profit_distribution_id')
            ->where('distribution_items.investment_id', $investmentId)
            ->where('profit_distributions.status', 'posted')
            ->select([
                'distribution_items.id',
                'distribution_items.amount',
                'distribution_items.payout_currency',
                'distribution_items.eligible_principal_bdt',
                'distribution_items.monthly_rate_snapshot',
                'distribution_items.annual_rate_snapshot',
                'profit_distributions.period_start',
                'profit_distributions.period_end',
            ]);
    }

    private function profitTotals(int $investmentId): Collection
    {
        return DB::table('distribution_items')
            ->join('profit_distributions', 'profit_distributions.id', '=', 'distribution_items.profit_distribution_id')
            ->where('distribution_items.investment_id', $investmentId)
            ->where('profit_distributions.status', 'posted')
            ->selectRaw('distribution_items.payout_currency as currency, SUM(distribution_items.amount) as total, COUNT(*) as distribution_count')
            ->groupBy('distribution_items.payout_currency')
            ->get()
            ->keyBy('currency');
    }

    private function progressPercent(object $investment): int
    {
        if ($investment->principal_returned_at !== null) {
            return 100;
        }
        if (
            $investment->status !== 'confirmed'
            || $investment->confirmed_at === null
            || $investment->matures_at === null
        ) {
            return 0;
        }

        $confirmedAt = Carbon::parse($investment->confirmed_at);
        $maturesAt = Carbon::parse($investment->matures_at);
        $duration = $maturesAt->timestamp - $confirmedAt->timestamp;
        if ($duration <= 0) {
            return 100;
        }

        $elapsed = now()->timestamp - $confirmedAt->timestamp;

        return min(100, max(0, (int) floor($elapsed * 100 / $duration)));
    }

    private function displayStatus(object $investment): string
    {
        if ($investment->principal_returned_at !== null) {
            return 'completed';
        }

        return $investment->status === 'confirmed' ? 'active' : $investment->status;
    }
}
