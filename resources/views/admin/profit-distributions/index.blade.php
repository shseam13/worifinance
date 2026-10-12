@extends('layouts.admin')

@section('title', __('site.profit_distributions.admin_title'))
@section('admin_breadcrumb', __('site.profit_distributions.admin_title'))

@section('content')
    <section class="admin-dashboard">
        <div class="portal-heading portal-heading-row">
            <div>
                <span class="section-eyebrow">{{ __('site.profit_distributions.eyebrow') }}</span>
                <h1>{{ __('site.profit_distributions.admin_title') }}</h1>
                <p>{{ __('site.profit_distributions.admin_description') }}</p>
            </div>
            <a class="button button-dark" href="{{ route('admin.profit-distributions.create') }}">{{ __('site.profit_distributions.create') }} <span aria-hidden="true">+</span></a>
        </div>

        @if (session('status'))
            <div class="admin-flash" role="status">{{ session('status') }}</div>
        @endif

        <div class="portal-notice portal-notice-warning">
            <div><h2>{{ __('site.profit_distributions.posting_notice_title') }}</h2><p>{{ __('site.profit_distributions.posting_notice') }}</p></div>
        </div>

        <nav class="deposit-status-tabs" aria-label="{{ __('site.profit_distributions.filter') }}">
            @foreach (['all', 'draft', 'approved', 'posted'] as $filter)
                <a class="@if ($status === $filter) is-active @endif" href="{{ route('admin.profit-distributions.index', ['status' => $filter]) }}">{{ __('site.profit_distributions.filters.'.$filter) }}@if ($filter === 'draft') <span>{{ $draftProfitDistributions }}</span>@endif</a>
            @endforeach
        </nav>

        <section class="portal-panel">
            @if ($distributions->isEmpty())
                <p class="portal-empty">{{ __('site.profit_distributions.empty') }}</p>
            @else
                <div class="portal-table-wrap">
                    <table class="portal-table">
                        <thead><tr><th>{{ __('site.auth.project') }}</th><th>{{ __('site.auth.period') }}</th><th>{{ __('site.profit_distributions.allocations') }}</th><th>{{ __('site.profit_distributions.total') }}</th><th>{{ __('site.profit_distributions.currency') }}</th><th>{{ __('site.auth.status') }}</th><th>{{ __('site.profit_distributions.actions') }}</th></tr></thead>
                        <tbody>
                            @foreach ($distributions as $distribution)
                                <tr>
                                    <td>{{ app()->getLocale() === 'bn' && $distribution->project_name_bn ? $distribution->project_name_bn : $distribution->project_name }}</td>
                                    <td>{{ \Illuminate\Support\Carbon::parse($distribution->period_start)->format('M j, Y') }} – {{ \Illuminate\Support\Carbon::parse($distribution->period_end)->format('M j, Y') }}</td>
                                    <td>
                                        {{ number_format((int) $distribution->item_count) }}
                                        <details class="profit-allocation-details">
                                            <summary>{{ __('site.profit_distributions.view_allocations') }}</summary>
                                            <div class="portal-table-wrap">
                                                <table class="portal-table">
                                                    <thead><tr><th>{{ __('site.auth.investor') }}</th><th>{{ __('site.profit_distributions.principal') }}</th><th>{{ __('site.profit_distributions.annual_rate') }}</th><th>{{ __('site.profit_distributions.eligible_days') }}</th><th>{{ __('site.profit_distributions.total') }}</th></tr></thead>
                                                    <tbody>
                                                        @foreach ($allocationItems->get($distribution->id, collect()) as $allocation)
                                                            <tr><td><span class="table-primary">{{ $allocation->investor_name }}</span><span class="table-secondary">{{ $allocation->investor_email }}</span></td><td>@money($allocation->eligible_principal_bdt, 'BDT')</td><td>{{ number_format((float) $allocation->annual_rate_snapshot, 4) }}%</td><td>{{ number_format((int) $allocation->eligible_days) }}</td><td>@money($allocation->amount, $allocation->payout_currency)</td></tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </details>
                                    </td>
                                    <td>@money($distribution->total_amount ?? 0, $distribution->payout_currency)</td>
                                    <td>{{ $distribution->payout_currency }}@if ($distribution->bdt_per_usdt_snapshot)<small class="table-secondary">{{ __('site.profit_distributions.rate_snapshot', ['rate' => $distribution->bdt_per_usdt_snapshot]) }}</small>@endif</td>
                                    <td><span class="status-pill status-{{ \Illuminate\Support\Str::slug($distribution->status) }}">{{ __('site.auth.statuses.'.$distribution->status) }}</span></td>
                                    <td>
                                        @if ($distribution->status === 'draft')
                                            <form action="{{ route('admin.profit-distributions.transition', $distribution->id) }}" method="post">@csrf<input type="hidden" name="decision" value="approve"><button class="button button-dark button-small" type="submit">{{ __('site.profit_distributions.approve') }}</button></form>
                                        @elseif ($distribution->status === 'approved')
                                            <form action="{{ route('admin.profit-distributions.transition', $distribution->id) }}" method="post">@csrf<input type="hidden" name="decision" value="post"><button class="button button-dark button-small" type="submit">{{ __('site.profit_distributions.post') }}</button></form>
                                        @else
                                            <span>{{ __('site.profit_distributions.completed') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="admin-pagination">{{ $distributions->links() }}</div>
            @endif
        </section>
    </section>
@endsection
