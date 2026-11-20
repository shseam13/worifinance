@extends('layouts.app')

@section('title', __('site.investor_activity.transactions_title'))

@section('content')
    <section class="portal-section">
        <div class="portal-heading">
            <span class="section-eyebrow">{{ __('site.auth.account_activity') }}</span>
            <h1>{{ __('site.investor_activity.transactions_title') }}</h1>
            <p>{{ __('site.investor_activity.transactions_description') }}</p>
        </div>

        <section class="portal-panel">
            <form class="admin-filter-form" method="get" action="{{ route('account.transactions.index') }}">
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

            @if ($transactions->isEmpty())
                <p class="portal-empty">{{ __('site.investor_activity.no_transactions') }}</p>
            @else
                <div class="portal-table-wrap">
                    <table class="portal-table">
                        <thead><tr><th>{{ __('site.investor_activity.date') }}</th><th>{{ __('site.investor_activity.transaction') }}</th><th>{{ __('site.investor_activity.type') }}</th><th>{{ __('site.investor_activity.status') }}</th><th>{{ __('site.investor_activity.description') }}</th><th>{{ __('site.investor_activity.amount') }}</th><th>{{ __('site.investor_activity.balance_after') }}</th><th>{{ __('site.investor_activity.reserved_after') }}</th></tr></thead>
                        <tbody>
                            @foreach ($transactions as $transaction)
                                <tr>
                                    <td>{{ \Illuminate\Support\Carbon::parse($transaction->created_at)->format('M j, Y H:i') }}</td>
                                    <td>#{{ $transaction->transaction_id }}</td>
                                    <td>{{ $transaction->entry_type }} · {{ $transaction->type }}</td>
                                    <td>{{ $transaction->status }}</td>
                                    <td>{{ $transaction->description ?: '—' }}</td>
                                    <td>@money($transaction->amount, $transaction->currency)</td>
                                    <td>@money($transaction->balance_after, $transaction->currency)</td>
                                    <td>@money($transaction->reserved_after, $transaction->currency)</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $transactions->links() }}
            @endif
        </section>
    </section>
@endsection
