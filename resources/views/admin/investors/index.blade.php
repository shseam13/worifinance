@extends('layouts.admin')

@section('title', __('site.investor_accounts.title'))
@section('admin_breadcrumb', __('site.investor_accounts.title'))

@section('content')
    <section class="admin-dashboard">
        <div class="portal-heading">
            <span class="section-eyebrow">{{ __('site.investor_accounts.eyebrow') }}</span>
            <h1>{{ __('site.investor_accounts.title') }}</h1>
            <p>{{ __('site.investor_accounts.description') }}</p>
        </div>
        <div class="portal-stat-grid admin-stat-grid">
            <article class="portal-stat-card"><span>{{ __('site.investor_accounts.total_accounts') }}</span><strong>{{ number_format($totalCount) }}</strong></article>
            <article class="portal-stat-card"><span>{{ __('site.investor_accounts.active_accounts') }}</span><strong>{{ number_format($activeCount) }}</strong></article>
            <article class="portal-stat-card"><span>{{ __('site.investor_accounts.suspended_accounts') }}</span><strong>{{ number_format($suspendedCount) }}</strong></article>
        </div>
        <section class="portal-panel">
            <form class="admin-filter-form" method="get" action="{{ route('admin.investors.index') }}">
                <label class="admin-form-field">
                    <span>{{ __('site.investor_accounts.search') }}</span>
                    <input type="search" name="search" value="{{ $search }}" placeholder="{{ __('site.investor_accounts.search_placeholder') }}">
                </label>
                <label class="admin-form-field">
                    <span>{{ __('site.investor_accounts.account_status') }}</span>
                    <select name="status">
                        @foreach (['all', 'active', 'suspended'] as $filter)
                            <option value="{{ $filter }}" @selected($status === $filter)>{{ __('site.investor_accounts.filters.'.$filter) }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="admin-filter-actions">
                    <button class="button button-dark button-small" type="submit">{{ __('site.investor_accounts.apply_filters') }}</button>
                    <a class="text-link" href="{{ route('admin.investors.index') }}">{{ __('site.investor_accounts.clear_filters') }}</a>
                </div>
            </form>
        </section>
        <section class="portal-panel">
            <div class="portal-panel-heading">
                <div><span class="section-eyebrow">{{ __('site.investor_accounts.accounts') }}</span><h2>{{ __('site.investor_accounts.account_results', ['count' => number_format($accounts->total())]) }}</h2></div>
            </div>
            @if ($accounts->isEmpty())
                <p class="portal-empty">{{ __('site.investor_accounts.empty') }}</p>
            @else
                <div class="portal-table-wrap">
                    <table class="portal-table">
                        <thead><tr>
                            <th>{{ __('site.investor_accounts.account') }}</th>
                            <th>{{ __('site.investor_accounts.joined') }}</th>
                            <th>{{ __('site.investor_accounts.status') }}</th>
                            <th>{{ __('site.investor_accounts.wallets') }}</th>
                            <th>{{ __('site.investor_accounts.confirmed_investments') }}</th>
                            <th>{{ __('site.investor_accounts.action') }}</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($accounts as $account)
                                <tr>
                                    <td>
                                        <span class="table-primary">{{ $account->name }}</span>
                                        <span class="table-secondary">{{ $account->email }}</span>
                                        <span class="table-secondary">{{ $account->phone ?: __('site.investor_accounts.no_phone') }}</span>
                                        @if ($account->referral_code)<span class="table-secondary">{{ __('site.investor_accounts.referral_code') }}: {{ $account->referral_code }}</span>@endif
                                    </td>
                                    <td>{{ \Illuminate\Support\Carbon::parse($account->created_at)->format('M j, Y') }}</td>
                                    <td><span class="status-pill status-{{ \Illuminate\Support\Str::slug($account->status) }}">{{ __('site.auth.'.$account->status) }}</span></td>
                                    <td>
                                        <span class="table-primary">@money($account->bdt_balance ?? 0, 'BDT')</span>
                                        <span class="table-secondary">@money($account->usdt_balance ?? 0, 'USDT')</span>
                                    </td>
                                    <td>
                                        <span class="table-primary">@money($account->confirmed_bdt ?? 0, 'BDT')</span>
                                        <span class="table-secondary">{{ number_format((int) ($account->confirmed_count ?? 0)) }} {{ __('site.investor_accounts.investments') }}</span>
                                    </td>
                                    <td><a class="button button-dark button-small" href="{{ route('admin.investors.show', $account->id) }}">{{ __('site.investor_accounts.manage_account') }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="admin-pagination">{{ $accounts->links() }}</div>
            @endif
        </section>
    </section>
@endsection
