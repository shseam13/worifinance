@extends('layouts.app')

@section('title', __('site.investor_portfolio.details'))

@section('content')
    <section class="portal-section">
        <div class="portal-heading portal-heading-row">
            <div>
                <a class="admin-back-link" href="{{ route('account.investments.index') }}">← {{ __('site.investor_portfolio.back') }}</a>
                <span class="section-eyebrow">{{ __('site.investor_portfolio.eyebrow') }}</span>
                <h1>{{ app()->getLocale() === 'bn' && $investment->project_name_bn ? $investment->project_name_bn : $investment->project_name }}</h1>
                <p>{{ __('site.investor_portfolio.reference') }} #{{ $investment->id }}</p>
            </div>
            <a class="button button-outline" href="{{ route('account.investments.statement', $investment->id) }}">{{ __('site.investor_portfolio.statement') }}</a>
        </div>

        @if ($investment->status === 'pending')
            <div class="portal-notice portal-notice-warning">
                <div><p>{{ __('site.investor_portfolio.pending_notice') }}</p></div>
            </div>
        @endif

        <section class="portal-panel">
            <div class="portal-panel-heading">
                <div><span class="section-eyebrow">{{ __('site.investments.terms') }}</span><h2>{{ __('site.investor_portfolio.details') }}</h2></div>
            </div>
            <div class="project-summary-list">
                <div class="project-summary-row"><span>{{ __('site.investor_portfolio.principal') }}</span><strong>@money($investment->amount_bdt, 'BDT')</strong></div>
                <div class="project-summary-row"><span>{{ __('site.investor_portfolio.paid_amount') }}</span><strong>@money($investment->paid_amount, $investment->payment_currency)</strong></div>
                <div class="project-summary-row"><span>{{ __('site.investor_portfolio.units') }}</span><strong>{{ number_format((int) $investment->unit_count) }}</strong></div>
                <div class="project-summary-row"><span>{{ __('site.investor_portfolio.duration') }}</span><strong>{{ $investment->duration_months ? $investment->duration_months.' '.__('site.projects.months') : '—' }}</strong></div>
                <div class="project-summary-row"><span>{{ $investment->monthly_rate_percent !== null ? __('site.investor_portfolio.monthly_rate') : __('site.investor_portfolio.annual_rate') }}</span><strong>{{ number_format((float) ($investment->monthly_rate_percent ?? $investment->annual_rate_percent), 2) }}%</strong></div>
                <div class="project-summary-row"><span>{{ __('site.investor_portfolio.invested_on') }}</span><strong>{{ \Illuminate\Support\Carbon::parse($investment->created_at)->format('M j, Y') }}</strong></div>
                <div class="project-summary-row"><span>{{ __('site.investor_portfolio.confirmed_on') }}</span><strong>{{ $investment->confirmed_at ? \Illuminate\Support\Carbon::parse($investment->confirmed_at)->format('M j, Y') : '—' }}</strong></div>
                <div class="project-summary-row"><span>{{ __('site.investor_portfolio.maturity') }}</span><strong>{{ $investment->matures_at ? \Illuminate\Support\Carbon::parse($investment->matures_at)->format('M j, Y') : '—' }}</strong></div>
                @if ($investment->principal_returned_at)
                    <div class="project-summary-row"><span>{{ __('site.investor_portfolio.principal_returned') }}</span><strong>{{ \Illuminate\Support\Carbon::parse($investment->principal_returned_at)->format('M j, Y') }}</strong></div>
                @endif
                <div class="project-summary-row"><span>{{ __('site.auth.status') }}</span><strong>{{ in_array($investment->display_status, ['active', 'completed'], true) ? __('site.investor_portfolio.filters.'.$investment->display_status) : __('site.auth.statuses.'.$investment->display_status) }}</strong></div>
            </div>
            <div class="investment-progress">
                <span>{{ __('site.investor_portfolio.progress') }}</span>
                <progress value="{{ $investment->progress_percent }}" max="100" aria-label="{{ __('site.investor_portfolio.progress') }}">{{ $investment->progress_percent }}%</progress>
                <strong>{{ $investment->progress_percent }}%</strong>
            </div>
        </section>

        <div class="portal-stat-grid">
            <article class="portal-stat-card"><span>{{ __('site.investor_portfolio.profits_earned') }} · BDT</span><strong>@money($profitTotals->get('BDT')->total ?? 0, 'BDT')</strong></article>
            <article class="portal-stat-card"><span>{{ __('site.investor_portfolio.profits_earned') }} · USDT</span><strong>@money($profitTotals->get('USDT')->total ?? 0, 'USDT')</strong></article>
        </div>

        <section class="portal-panel">
            <div class="portal-panel-heading">
                <div><span class="section-eyebrow">{{ __('site.investor_activity.profit_history_title') }}</span><h2>{{ __('site.investor_portfolio.profit_history') }}</h2></div>
                <a class="text-link" href="{{ route('account.profit-history') }}">{{ __('site.investor_activity.view_all_profit_history') }} →</a>
            </div>
            @if ($profitHistory->isEmpty())
                <p class="portal-empty">{{ __('site.investor_portfolio.no_profit_history') }}</p>
            @else
                <div class="portal-table-wrap">
                    <table class="portal-table">
                        <thead><tr><th>{{ __('site.investor_activity.period') }}</th><th>{{ __('site.investor_activity.amount') }}</th><th>{{ __('site.investor_activity.rate') }}</th></tr></thead>
                        <tbody>
                            @foreach ($profitHistory as $profit)
                                <tr>
                                    <td>{{ \Illuminate\Support\Carbon::parse($profit->period_start)->format('M j, Y') }} — {{ \Illuminate\Support\Carbon::parse($profit->period_end)->format('M j, Y') }}</td>
                                    <td>@money($profit->amount, $profit->payout_currency)</td>
                                    <td>{{ $profit->monthly_rate_snapshot !== null ? number_format((float) $profit->monthly_rate_snapshot, 2).'%' : number_format((float) $profit->annual_rate_snapshot, 2).'%' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </section>
@endsection
