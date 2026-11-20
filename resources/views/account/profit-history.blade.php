@extends('layouts.app')

@section('title', __('site.investor_activity.profit_history_title'))

@section('content')
    <section class="portal-section">
        <div class="portal-heading">
            <span class="section-eyebrow">{{ __('site.investor_activity.profit_wallet_title') }}</span>
            <h1>{{ __('site.investor_activity.profit_history_title') }}</h1>
            <p>{{ __('site.investor_activity.profit_history_description') }}</p>
        </div>

        <div class="portal-stat-grid">
            @foreach (['BDT', 'USDT'] as $currencyCode)
                <article class="portal-stat-card">
                    <span>{{ __('site.investor_activity.profit_total') }} · {{ $currencyCode }}</span>
                    <strong>@money($profitTotals->get($currencyCode)->total ?? 0, $currencyCode)</strong>
                </article>
            @endforeach
        </div>

        <section class="portal-panel">
            <form class="admin-filter-form" method="get" action="{{ route('account.profit-history') }}">
                <label class="admin-form-field">
                    <span>{{ __('site.investor_activity.currency') }}</span>
                    <select name="currency">
                        <option value="">{{ __('site.investor_activity.all_currencies') }}</option>
                        @foreach (['BDT', 'USDT'] as $currencyCode)
                            <option value="{{ $currencyCode }}" @selected($currency === $currencyCode)>{{ $currencyCode }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="button button-dark" type="submit">{{ __('site.investor_activity.apply_filter') }}</button>
            </form>

            @if ($profitHistory->isEmpty())
                <p class="portal-empty">{{ __('site.investor_activity.no_profit_history') }}</p>
            @else
                <div class="portal-table-wrap">
                    <table class="portal-table">
                        <thead><tr><th>{{ __('site.investor_activity.date') }}</th><th>{{ __('site.investor_activity.project') }}</th><th>{{ __('site.investor_activity.investment') }}</th><th>{{ __('site.investor_activity.period') }}</th><th>{{ __('site.investor_activity.rate') }}</th><th>{{ __('site.investor_activity.amount') }}</th></tr></thead>
                        <tbody>
                            @foreach ($profitHistory as $profit)
                                <tr>
                                    <td>{{ \Illuminate\Support\Carbon::parse($profit->period_end)->format('M j, Y') }}</td>
                                    <td>{{ app()->getLocale() === 'bn' && $profit->project_name_bn ? $profit->project_name_bn : $profit->project_name }}</td>
                                    <td><a class="table-link" href="{{ route('account.investments.show', $profit->investment_id) }}">#{{ $profit->investment_id }}</a></td>
                                    <td>{{ \Illuminate\Support\Carbon::parse($profit->period_start)->format('M j, Y') }} — {{ \Illuminate\Support\Carbon::parse($profit->period_end)->format('M j, Y') }}</td>
                                    <td>{{ $profit->monthly_rate_snapshot !== null ? number_format((float) $profit->monthly_rate_snapshot, 2).'%' : number_format((float) $profit->annual_rate_snapshot, 2).'%' }}</td>
                                    <td>@money($profit->amount, $profit->payout_currency)</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $profitHistory->links() }}
            @endif
        </section>
    </section>
@endsection
