<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfitDistributionManagementController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'all');
        abort_unless(in_array($status, ['draft', 'approved', 'posted', 'all'], true), 404);

        $totals = DB::table('distribution_items')
            ->select(
                'profit_distribution_id',
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('COUNT(*) as item_count'),
            )
            ->groupBy('profit_distribution_id');

        $query = DB::table('profit_distributions')
            ->join('projects', 'projects.id', '=', 'profit_distributions.project_id')
            ->leftJoinSub($totals, 'allocation_totals', 'allocation_totals.profit_distribution_id', '=', 'profit_distributions.id')
            ->select([
                'profit_distributions.*',
                'projects.name as project_name',
                'projects.name_bn as project_name_bn',
                'allocation_totals.total_amount',
                'allocation_totals.item_count',
            ]);

        if ($status !== 'all') {
            $query->where('profit_distributions.status', $status);
        }

        $distributions = $query->latest('profit_distributions.created_at')->paginate(20)->withQueryString();
        $allocationItems = DB::table('distribution_items')
            ->join('investments', 'investments.id', '=', 'distribution_items.investment_id')
            ->join('users', 'users.id', '=', 'investments.user_id')
            ->whereIn('distribution_items.profit_distribution_id', $distributions->pluck('id'))
            ->select([
                'distribution_items.profit_distribution_id',
                'distribution_items.eligible_principal_bdt',
                'distribution_items.annual_rate_snapshot',
                'distribution_items.eligible_days',
                'distribution_items.payout_currency',
                'distribution_items.amount',
                'users.name as investor_name',
                'users.email as investor_email',
            ])
            ->orderBy('users.name')
            ->get()
            ->groupBy('profit_distribution_id');

        return view('admin.profit-distributions.index', [
            'pendingDeposits' => DB::table('deposit_requests')->where('status', 'pending')->count(),
            'pendingWithdrawals' => DB::table('withdrawal_requests')->where('status', 'pending')->count(),
            'pendingInvestments' => DB::table('investments')->where('status', 'pending')->count(),
            'draftProfitDistributions' => DB::table('profit_distributions')->where('status', 'draft')->count(),
            'distributions' => $distributions,
            'allocationItems' => $allocationItems,
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        $projectIds = DB::table('investments')
            ->where('status', 'confirmed')
            ->distinct()
            ->pluck('project_id');
        $projects = Project::query()
            ->whereIn('id', $projectIds)
            ->where('is_sample', false)
            ->whereHas('terms', fn ($query) => $query->whereNotNull('annual_rate_percent'))
            ->orderBy('name')
            ->get()
            ->values();

        return view('admin.profit-distributions.create', [
            'pendingDeposits' => DB::table('deposit_requests')->where('status', 'pending')->count(),
            'pendingWithdrawals' => DB::table('withdrawal_requests')->where('status', 'pending')->count(),
            'pendingInvestments' => DB::table('investments')->where('status', 'pending')->count(),
            'pendingProfitDistributions' => DB::table('profit_distributions')->whereIn('status', ['draft', 'approved'])->count(),
            'projects' => $projects,
            'exchangeRate' => DB::table('exchange_rates')
                ->where('base_currency', 'USDT')
                ->where('quote_currency', 'BDT')
                ->orderByDesc('version')
                ->value('quote_per_base'),
            'defaultEnd' => today()->subDay()->toDateString(),
            'defaultStart' => today()->subMonth()->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start', 'before_or_equal:today'],
            'payout_currency' => ['required', Rule::in(['BDT', 'USDT'])],
        ]);

        $distributionId = DB::transaction(function () use ($request, $validated): int {
            $project = Project::query()->lockForUpdate()->findOrFail($validated['project_id']);
            abort_unless(! $project->is_sample, 404);

            $term = DB::table('project_terms')
                ->where('project_id', $project->id)
                ->whereNotNull('annual_rate_percent')
                ->orderByDesc('version')
                ->lockForUpdate()
                ->first();
            if ($term === null) {
                throw ValidationException::withMessages([
                    'project_id' => __('site.profit_distributions.project_terms_unavailable'),
                ]);
            }

            $periodStart = Carbon::parse($validated['period_start'])->startOfDay();
            $periodEnd = Carbon::parse($validated['period_end'])->endOfDay();
            $overlapExists = DB::table('profit_distributions')
                ->where('project_id', $project->id)
                ->whereDate('period_start', '<=', $periodEnd->toDateString())
                ->whereDate('period_end', '>=', $periodStart->toDateString())
                ->exists();
            if ($overlapExists) {
                throw ValidationException::withMessages([
                    'period_start' => __('site.profit_distributions.overlapping_period'),
                ]);
            }

            $exchangeRate = null;
            if ($validated['payout_currency'] === 'USDT') {
                $exchangeRate = DB::table('exchange_rates')
                    ->where('base_currency', 'USDT')
                    ->where('quote_currency', 'BDT')
                    ->orderByDesc('version')
                    ->lockForUpdate()
                    ->first();
                if ($exchangeRate === null || (float) $exchangeRate->quote_per_base <= 0) {
                    throw ValidationException::withMessages([
                        'payout_currency' => __('site.profit_distributions.exchange_rate_unavailable'),
                    ]);
                }
            }

            $eligibleInvestments = DB::table('investments')
                ->join('project_terms', 'project_terms.id', '=', 'investments.project_term_id')
                ->where('investments.project_id', $project->id)
                ->where('investments.status', 'confirmed')
                ->whereNotNull('investments.confirmed_at')
                ->whereDate('investments.confirmed_at', '<=', $periodEnd->toDateString())
                ->select([
                    'investments.id',
                    'investments.amount_bdt',
                    'investments.confirmed_at',
                    'project_terms.annual_rate_percent',
                ])
                ->orderBy('investments.id')
                ->lockForUpdate()
                ->get();

            $allocations = [];
            foreach ($eligibleInvestments as $investment) {
                if ($investment->annual_rate_percent === null) {
                    throw ValidationException::withMessages([
                        'project_id' => __('site.profit_distributions.investment_rate_unavailable'),
                    ]);
                }

                $eligibleStart = Carbon::parse($investment->confirmed_at)->startOfDay()->max($periodStart);
                if ($eligibleStart->greaterThan($periodEnd)) {
                    continue;
                }

                $eligibleDays = (int) $eligibleStart->diffInDays($periodEnd->copy()->startOfDay()) + 1;
                $sql = $validated['payout_currency'] === 'BDT'
                    ? 'SELECT CAST(ROUND(CAST(? AS DECIMAL(20,2)) * CAST(? AS DECIMAL(8,4)) * ? / 36500, 2) AS CHAR) AS amount'
                    : 'SELECT CAST(ROUND(CAST(? AS DECIMAL(20,2)) * CAST(? AS DECIMAL(8,4)) * ? / (36500 * CAST(? AS DECIMAL(20,8))), 8) AS CHAR) AS amount';
                $bindings = [
                    $investment->amount_bdt,
                    $investment->annual_rate_percent,
                    $eligibleDays,
                ];
                if ($validated['payout_currency'] === 'USDT') {
                    $bindings[] = $exchangeRate->quote_per_base;
                }
                $amount = DB::selectOne($sql, $bindings)?->amount;
                if ($amount === null || ! is_numeric($amount)) {
                    throw new \RuntimeException('Unable to calculate a profit distribution allocation.');
                }
                if (preg_match('/^0(?:\.0+)?$/', (string) $amount) === 1) {
                    continue;
                }

                $allocations[] = [
                    'investment_id' => $investment->id,
                    'eligible_principal_bdt' => $investment->amount_bdt,
                    'annual_rate_snapshot' => $investment->annual_rate_percent,
                    'eligible_days' => $eligibleDays,
                    'payout_currency' => $validated['payout_currency'],
                    'amount' => (string) $amount,
                    'bdt_per_usdt_snapshot' => $exchangeRate?->quote_per_base,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($allocations === []) {
                throw ValidationException::withMessages([
                    'period_end' => __('site.profit_distributions.no_eligible_investments'),
                ]);
            }

            $distributionId = DB::table('profit_distributions')->insertGetId([
                'project_id' => $project->id,
                'project_term_id' => $term->id,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'annual_rate_snapshot' => $term->annual_rate_percent,
                'payout_currency' => $validated['payout_currency'],
                'bdt_per_usdt_snapshot' => $exchangeRate?->quote_per_base,
                'exchange_rate_id' => $exchangeRate?->id,
                'status' => 'draft',
                'created_by_user_id' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($allocations as $allocation) {
                $allocation['profit_distribution_id'] = $distributionId;
                DB::table('distribution_items')->insert($allocation);
            }

            DB::table('admin_audit_logs')->insert([
                'admin_user_id' => $request->user()->id,
                'action' => 'profit_distribution.created',
                'subject_type' => 'profit_distribution',
                'subject_id' => $distributionId,
                'before_values' => null,
                'after_values' => json_encode([
                    'project_id' => $project->id,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'payout_currency' => $validated['payout_currency'],
                    'allocation_count' => count($allocations),
                ], JSON_THROW_ON_ERROR),
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $distributionId;
        });

        return redirect()->route('admin.profit-distributions.index')
            ->with('status', __('site.profit_distributions.created'));
    }

    public function transition(Request $request, int $distribution): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'post'])],
        ]);

        DB::transaction(function () use ($request, $distribution, $validated): void {
            $record = DB::table('profit_distributions')
                ->where('id', $distribution)
                ->lockForUpdate()
                ->first();
            abort_if($record === null, 404);

            if ($validated['decision'] === 'approve') {
                abort_unless($record->status === 'draft', 409, __('site.profit_distributions.invalid_transition'));
                DB::table('profit_distributions')->where('id', $record->id)->update([
                    'status' => 'approved',
                    'approved_by_user_id' => $request->user()->id,
                    'approved_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                abort_unless($record->status === 'approved', 409, __('site.profit_distributions.invalid_transition'));
                $items = DB::table('distribution_items')
                    ->join('investments', 'investments.id', '=', 'distribution_items.investment_id')
                    ->where('profit_distribution_id', $record->id)
                    ->select('distribution_items.*', 'investments.user_id')
                    ->orderBy('distribution_items.id')
                    ->lockForUpdate()
                    ->get();
                abort_if($items->isEmpty(), 409, __('site.profit_distributions.no_allocations'));

                foreach ($items as $item) {
                    DB::table('wallet_accounts')->insertOrIgnore([
                        'user_id' => $item->user_id,
                        'currency' => $item->payout_currency,
                        'balance' => '0.00000000',
                        'reserved_balance' => '0.00000000',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $wallet = DB::table('wallet_accounts')
                        ->where('user_id', $item->user_id)
                        ->where('currency', $item->payout_currency)
                        ->lockForUpdate()
                        ->first();
                    abort_if($wallet === null, 500, __('site.profit_distributions.wallet_unavailable'));

                    DB::table('wallet_accounts')->where('id', $wallet->id)
                        ->increment('balance', $item->amount, ['updated_at' => now()]);
                    $updatedWallet = DB::table('wallet_accounts')->where('id', $wallet->id)->first();
                    abort_if($updatedWallet === null, 500, __('site.profit_distributions.wallet_unavailable'));

                    $transactionId = DB::table('ledger_transactions')->insertGetId([
                        'idempotency_key' => 'profit-distribution-item:'.$item->id,
                        'type' => 'profit_distribution',
                        'status' => 'posted',
                        'description' => 'Post approved project profit distribution',
                        'exchange_rate_id' => $record->exchange_rate_id,
                        'metadata' => json_encode([
                            'profit_distribution_id' => $record->id,
                            'distribution_item_id' => $item->id,
                            'investment_id' => $item->investment_id,
                            'period_start' => $record->period_start,
                            'period_end' => $record->period_end,
                            'bdt_per_usdt_snapshot' => $record->bdt_per_usdt_snapshot,
                        ], JSON_THROW_ON_ERROR),
                        'created_by_user_id' => $request->user()->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    DB::table('ledger_entries')->insert([
                        'ledger_transaction_id' => $transactionId,
                        'wallet_account_id' => $wallet->id,
                        'entry_type' => 'profit_credit',
                        'amount' => $item->amount,
                        'balance_after' => $updatedWallet->balance,
                        'reserved_after' => $updatedWallet->reserved_balance,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('profit_distributions')->where('id', $record->id)->update([
                    'status' => 'posted',
                    'posted_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('admin_audit_logs')->insert([
                'admin_user_id' => $request->user()->id,
                'action' => 'profit_distribution.'.($validated['decision'] === 'approve' ? 'approved' : 'posted'),
                'subject_type' => 'profit_distribution',
                'subject_id' => $record->id,
                'before_values' => json_encode(['status' => $record->status], JSON_THROW_ON_ERROR),
                'after_values' => json_encode([
                    'status' => $validated['decision'] === 'approve' ? 'approved' : 'posted',
                ], JSON_THROW_ON_ERROR),
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $message = $validated['decision'] === 'approve'
            ? __('site.profit_distributions.approved')
            : __('site.profit_distributions.posted');

        return redirect()->route('admin.profit-distributions.index')->with('status', $message);
    }
}
