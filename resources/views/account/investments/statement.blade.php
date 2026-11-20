@extends('layouts.app')

@section('title', __('site.investment_statement.title'))

@section('content')
    <style>
        @media print {
            .site-header,
            .site-footer,
            .mobile-bottom-nav,
            .investment-statement-actions {
                display: none !important;
            }

            .portal-section {
                width: 100%;
                max-width: none;
                padding: 0;
            }

            .portal-panel {
                box-shadow: none;
                break-inside: avoid;
            }
        }
    </style>

    <section class="portal-section">
        <div class="portal-heading portal-heading-row investment-statement-actions">
            <div>
                <a class="admin-back-link" href="{{ route('account.investments.show', $investment->id) }}">← {{ __('site.investor_portfolio.back') }}</a>
                <span class="section-eyebrow">{{ __('site.investor_portfolio.eyebrow') }}</span>
                <h1>{{ __('site.investment_statement.title') }}</h1>
                <p>{{ __('site.investment_statement.description') }}</p>
            </div>
            <button class="button button-dark" type="button" onclick="window.print()">{{ __('site.investment_statement.print') }}</button>
        </div>

        <section class="portal-panel">
            <div class="portal-panel-heading">
                <div><span class="section-eyebrow">{{ __('site.investment_statement.investor') }}</span><h2>{{ auth()->user()->name }}</h2></div>
                <small>{{ __('site.investment_statement.generated_at') }}: {{ $generatedAt->format('M j, Y H:i') }}</small>
            </div>
            <p>{{ __('site.investment_statement.email') }}: {{ auth()->user()->email }}</p>
        </section>

        <section class="portal-panel">
            <div class="portal-panel-heading">
                <div><span class="section-eyebrow">{{ __('site.investment_statement.reference') }} #{{ $investment->id }}</span><h2>{{ app()->getLocale() === 'bn' && $investment->project_name_bn ? $investment->project_name_bn : $investment->project_name }}</h2></div>
                <span class="status-pill status-{{ \Illuminate\Support\Str::slug($investment->status) }}">{{ in_array($investment->display_status, ['active', 'completed'], true) ? __('site.investor_portfolio.filters.'.$investment->display_status) : __('site.auth.statuses.'.$investment->display_status) }}</span>
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
                <div class="project-summary-row"><span>{{ __('site.investor_portfolio.progress') }}</span><strong>{{ $investment->progress_percent }}%</strong></div>
                <div class="project-summary-row"><span>{{ __('site.investor_portfolio.profits_earned') }} · BDT</span><strong>@money($profitTotals->get('BDT')->total ?? 0, 'BDT')</strong></div>
                <div class="project-summary-row"><span>{{ __('site.investor_portfolio.profits_earned') }} · USDT</span><strong>@money($profitTotals->get('USDT')->total ?? 0, 'USDT')</strong></div>
            </div>
        </section>

        <section class="portal-panel">
            <div class="portal-panel-heading"><div><span class="section-eyebrow">{{ __('site.investor_activity.profit_history_title') }}</span><h2>{{ __('site.investor_portfolio.profit_history') }}</h2></div></div>
            @if ($profitHistory->isEmpty())
                <p class="portal-empty">{{ __('site.investment_statement.no_profit_history') }}</p>
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
            <p class="admin-form-hint">{{ __('site.investment_statement.record_notice') }}</p>
        </section>
    </section>
@endsection
