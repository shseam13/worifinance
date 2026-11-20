@extends('layouts.app')

@section('title', __('site.investor_activity.profit_wallet_title'))

@section('content')
    <section class="portal-section">
        <div class="portal-heading">
            <span class="section-eyebrow">{{ __('site.auth.account_activity') }}</span>
            <h1>{{ __('site.investor_activity.profit_wallet_title') }}</h1>
            <p>{{ __('site.investor_activity.profit_wallet_description') }}</p>
        </div>

        <div class="portal-stat-grid">
            @foreach (['BDT', 'USDT'] as $currency)
                <article class="portal-stat-card">
                    <span>{{ __('site.investor_activity.profit_total') }} · {{ $currency }}</span>
                    <strong>@money($profitTotals->get($currency)->total ?? 0, $currency)</strong>
                    <small>{{ number_format((int) ($profitTotals->get($currency)->distribution_count ?? 0)) }} {{ __('site.investor_activity.profit_history_title') }}</small>
                </article>
                <article class="portal-stat-card">
                    <span>{{ __('site.investor_activity.available_balance') }} · {{ $currency }}</span>
                    <strong>@money(($wallets->get($currency)->balance ?? 0) - ($wallets->get($currency)->reserved_balance ?? 0), $currency)</strong>
                    <small>{{ __('site.auth.reserved') }}: @money($wallets->get($currency)->reserved_balance ?? 0, $currency)</small>
                </article>
            @endforeach
        </div>

        <div class="portal-notice portal-notice-warning">
            <div><p>{{ __('site.investor_activity.wallet_notice') }}</p></div>
        </div>

        <section class="portal-panel">
            <div class="portal-panel-heading">
                <div><span class="section-eyebrow">{{ __('site.investor_activity.profit_history_title') }}</span><h2>{{ __('site.investor_portfolio.profit_history') }}</h2></div>
                <a class="text-link" href="{{ route('account.profit-history') }}">{{ __('site.investor_activity.view_all_profit_history') }} →</a>
            </div>
            @if ($recentProfits->isEmpty())
                <p class="portal-empty">{{ __('site.investor_activity.no_profit_history') }}</p>
            @else
                <div class="portal-table-wrap">
                    <table class="portal-table">
                        <thead><tr><th>{{ __('site.investor_activity.date') }}</th><th>{{ __('site.investor_activity.project') }}</th><th>{{ __('site.investor_activity.period') }}</th><th>{{ __('site.investor_activity.amount') }}</th></tr></thead>
                        <tbody>
                            @foreach ($recentProfits as $profit)
                                <tr>
                                    <td>{{ \Illuminate\Support\Carbon::parse($profit->period_end)->format('M j, Y') }}</td>
                                    <td>{{ app()->getLocale() === 'bn' && $profit->project_name_bn ? $profit->project_name_bn : $profit->project_name }}</td>
                                    <td>{{ \Illuminate\Support\Carbon::parse($profit->period_start)->format('M j, Y') }} — {{ \Illuminate\Support\Carbon::parse($profit->period_end)->format('M j, Y') }}</td>
                                    <td>@money($profit->amount, $profit->payout_currency)</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <div class="portal-heading-actions">
                <a class="button button-outline" href="{{ route('account.profit-history') }}">{{ __('site.investor_activity.view_all_profit_history') }}</a>
                <a class="button button-dark" href="{{ route('account.transactions.index') }}">{{ __('site.investor_activity.view_transactions') }}</a>
            </div>
        </section>
    </section>
@endsection
