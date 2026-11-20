<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvestmentReportController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->validatedFilters($request);
        $summary = $this->applyFilters(DB::table('investments'), $filters)
            ->selectRaw('COUNT(*) as investment_count, COALESCE(SUM(amount_bdt), 0) as principal_total')
            ->first();

        $investments = $this->investmentQuery($filters)
            ->orderByDesc('investments.created_at')
            ->orderByDesc('investments.id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.reports.investments', [
            'pendingDeposits' => DB::table('deposit_requests')->where('status', 'pending')->count(),
            'pendingWithdrawals' => DB::table('withdrawal_requests')->where('status', 'pending')->count(),
            'filters' => $filters,
            'summary' => $summary,
            'investments' => $investments,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);

        DB::table('admin_audit_logs')->insert([
            'admin_user_id' => $request->user()->id,
            'action' => 'investment_report.export_requested',
            'subject_type' => 'investment_report',
            'before_values' => null,
            'after_values' => json_encode($filters, JSON_THROW_ON_ERROR),
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->streamDownload(function () use ($filters): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                throw new RuntimeException('Unable to open the investment report output stream.');
            }

            try {
                if (fwrite($output, "\xEF\xBB\xBF") !== 3) {
                    throw new RuntimeException('Unable to write the investment report encoding marker.');
                }
                $this->writeCsvRow($output, [
                    'Investment ID',
                    'Investor',
                    'Email',
                    'Project',
                    'Investment status',
                    'Completion status',
                    'Investment date',
                    'Confirmed date',
                    'Maturity date',
                    'Principal returned date',
                    'Units',
                    'Principal (BDT)',
                    'Paid amount',
                    'Payment currency',
                    'Monthly return rate (%)',
                    'Duration (months)',
                ]);

                $this->investmentQuery($filters)
                    ->orderBy('investments.id')
                    ->chunk(500, function ($investments) use ($output): void {
                        foreach ($investments as $investment) {
                            $this->writeCsvRow($output, [
                                $investment->id,
                                $this->safeCsvText($investment->investor_name),
                                $this->safeCsvText($investment->investor_email),
                                $this->safeCsvText($investment->project_name),
                                $investment->status,
                                $investment->principal_returned_at !== null ? 'completed' : $investment->status,
                                $investment->created_at,
                                $investment->confirmed_at,
                                $investment->matures_at,
                                $investment->principal_returned_at,
                                $investment->unit_count,
                                $investment->amount_bdt,
                                $investment->paid_amount,
                                $investment->payment_currency,
                                $investment->monthly_rate_percent,
                                $investment->duration_months,
                            ]);
                        }
                    });
            } finally {
                fclose($output);
            }
        }, 'investment-report-'.today()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{status: string, from: string|null, to: string|null}
     */
    private function validatedFilters(Request $request): array
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['all', 'pending', 'active', 'completed', 'rejected'])],
            'from' => ['nullable', 'date_format:Y-m-d', 'required_with:to'],
            'to' => ['nullable', 'date_format:Y-m-d', 'required_with:from'],
        ]);
        if (
            isset($filters['from'], $filters['to'])
            && $filters['from'] > $filters['to']
        ) {
            throw ValidationException::withMessages([
                'to' => __('validation.before_or_equal', ['date' => $filters['from']]),
            ]);
        }

        return [
            'status' => $filters['status'] ?? 'all',
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
        ];
    }

    /**
     * @param  array{status: string, from: string|null, to: string|null}  $filters
     */
    private function investmentQuery(array $filters): Builder
    {
        return $this->applyFilters(DB::table('investments'), $filters)
            ->join('users', 'users.id', '=', 'investments.user_id')
            ->join('projects', 'projects.id', '=', 'investments.project_id')
            ->join('project_terms', 'project_terms.id', '=', 'investments.project_term_id')
            ->select([
                'investments.id',
                'investments.status',
                'investments.created_at',
                'investments.confirmed_at',
                'investments.matures_at',
                'investments.principal_returned_at',
                'investments.unit_count',
                'investments.amount_bdt',
                'investments.paid_amount',
                'investments.payment_currency',
                'investments.duration_months',
                'users.name as investor_name',
                'users.email as investor_email',
                'projects.name as project_name',
                'project_terms.monthly_rate_percent',
            ]);
    }

    /**
     * @param  array{status: string, from: string|null, to: string|null}  $filters
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if ($filters['status'] === 'pending' || $filters['status'] === 'rejected') {
            $query->where('investments.status', $filters['status']);
        } elseif ($filters['status'] === 'active') {
            $query->where('investments.status', 'confirmed')
                ->whereNull('investments.principal_returned_at');
        } elseif ($filters['status'] === 'completed') {
            $query->where('investments.status', 'confirmed')
                ->whereNotNull('investments.principal_returned_at');
        }
        if ($filters['from'] !== null) {
            $query->whereDate('investments.created_at', '>=', $filters['from']);
        }
        if ($filters['to'] !== null) {
            $query->whereDate('investments.created_at', '<=', $filters['to']);
        }

        return $query;
    }

    /**
     * @param  resource  $output
     * @param  array<int, string|int|null>  $fields
     */
    private function writeCsvRow($output, array $fields): void
    {
        if (fputcsv($output, $fields, ',', '"', '') === false) {
            throw new RuntimeException('Unable to write an investment report row.');
        }
    }

    private function safeCsvText(?string $value): ?string
    {
        return $value !== null && preg_match('/^[=+\-@\t\r]/', $value) === 1
            ? "'".$value
            : $value;
    }
}
