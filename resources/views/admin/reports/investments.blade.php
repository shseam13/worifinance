@extends('layouts.admin')

@section('title', __('site.investment_reports.admin_title'))
@section('admin_breadcrumb', __('site.investment_reports.admin_title'))

@section('content')
    <section class="admin-dashboard">
        <div class="portal-heading portal-heading-row">
            <div>
                <span class="section-eyebrow">{{ __('site.investment_reports.eyebrow') }}</span>
                <h1>{{ __('site.investment_reports.admin_title') }}</h1>
                <p>{{ __('site.investment_reports.description') }}</p>
            </div>
            <a class="button button-dark" href="{{ route('admin.reports.investments.export', request()->query()) }}">{{ __('site.investment_reports.export_csv') }}</a>
        </div>

        @if ($errors->any())
            <div class="portal-notice portal-notice-warning" role="alert"><div><p>{{ $errors->first() }}</p></div></div>
        @endif

        <section class="portal-panel">
            <form class="admin-filter-form" method="get" action="{{ route('admin.reports.investments.index') }}">
                <label class="admin-form-field">
                    <span>{{ __('site.investment_reports.status') }}</span>
                    <select name="status">
                        @foreach (['all', 'pending', 'active', 'completed', 'rejected'] as $filter)
                            <option value="{{ $filter }}" @selected($filters['status'] === $filter)>{{ __('site.investment_reports.statuses.'.$filter) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="admin-form-field">
                    <span>{{ __('site.investment_reports.from') }}</span>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}">
                </label>
                <label class="admin-form-field">
                    <span>{{ __('site.investment_reports.to') }}</span>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}">
                </label>
                <div class="admin-form-actions">
                    <button class="button button-dark button-small" type="submit">{{ __('site.investment_reports.apply') }}</button>
                    <a class="button button-outline button-small" href="{{ route('admin.reports.investments.index') }}">{{ __('site.investment_reports.clear') }}</a>
                </div>
            </form>
        </section>

        <div class="portal-stat-grid">
            <article class="portal-stat-card">
                <span>{{ __('site.investment_reports.investment_count') }}</span>
                <strong>{{ number_format((int) $summary->investment_count) }}</strong>
            </article>
            <article class="portal-stat-card">
                <span>{{ __('site.investment_reports.principal_total') }}</span>
                <strong>@money($summary->principal_total, 'BDT')</strong>
            </article>
        </div>

        <section class="portal-panel">
            @if ($investments->isEmpty())
                <p class="portal-empty">{{ __('site.investment_reports.empty') }}</p>
            @else
                <div class="portal-table-wrap">
                    <table class="portal-table">
                        <thead>
                            <tr>
                                <th>{{ __('site.auth.investor') }}</th>
                                <th>{{ __('site.auth.project') }}</th>
                                <th>{{ __('site.investment_reports.invested_at') }}</th>
                                <th>{{ __('site.investment_reports.principal') }}</th>
                                <th>{{ __('site.investment_reports.paid_amount') }}</th>
                                <th>{{ __('site.investment_reports.rate') }}</th>
                                <th>{{ __('site.investment_reports.duration') }}</th>
                                <th>{{ __('site.investment_reports.completion') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($investments as $investment)
                                <tr>
                                    <td><span class="table-primary">{{ $investment->investor_name }}</span><span class="table-secondary">{{ $investment->investor_email }}</span></td>
                                    <td>{{ $investment->project_name }}</td>
                                    <td>{{ \Illuminate\Support\Carbon::parse($investment->created_at)->format('M j, Y') }}</td>
                                    <td>@money($investment->amount_bdt, 'BDT')</td>
                                    <td>@money($investment->paid_amount, $investment->payment_currency)</td>
                                    <td>{{ $investment->monthly_rate_percent !== null ? number_format((float) $investment->monthly_rate_percent, 2).'%' : '—' }}</td>
                                    <td>{{ $investment->duration_months ? number_format((int) $investment->duration_months).' '.__('site.projects.months') : '—' }}</td>
                                    <td>
                                        @if ($investment->principal_returned_at !== null)
                                            <span class="status-pill status-confirmed">{{ __('site.investment_reports.statuses.completed') }}</span>
                                        @else
                                            <span class="status-pill status-{{ \Illuminate\Support\Str::slug($investment->status) }}">{{ __('site.auth.statuses.'.$investment->status) }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="admin-pagination">{{ $investments->links() }}</div>
            @endif
        </section>
    </section>
@endsection
