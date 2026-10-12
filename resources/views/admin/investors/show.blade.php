@extends('layouts.admin')

@section('title', __('site.investor_accounts.account_details'))
@section('admin_breadcrumb', __('site.investor_accounts.account_details'))

@section('content')
    <section class="admin-dashboard">
        <div class="portal-heading portal-heading-row">
            <div>
                <span class="section-eyebrow">{{ __('site.investor_accounts.eyebrow') }}</span>
                <h1>{{ $account->name }}</h1>
                <p>{{ $account->email }} · {{ __('site.investor_accounts.member_since') }} {{ \Illuminate\Support\Carbon::parse($account->created_at)->format('M j, Y') }}</p>
            </div>
            <a class="button button-light button-small" href="{{ route('admin.investors.index') }}">{{ __('site.investor_accounts.back_to_accounts') }}</a>
        </div>
        @if (session('status'))
            <div class="admin-flash" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="portal-notice portal-notice-warning" role="alert"><div><p>{{ $errors->first() }}</p></div></div>
        @endif
        <div class="portal-stat-grid admin-stat-grid">
            <article class="portal-stat-card"><span>{{ __('site.investor_accounts.account_status') }}</span><strong><span class="status-pill status-{{ \Illuminate\Support\Str::slug($account->status) }}">{{ __('site.auth.'.$account->status) }}</span></strong></article>
            <article class="portal-stat-card"><span>{{ __('site.investor_accounts.confirmed_investments') }}</span><strong>@money($confirmedInvestmentTotal, 'BDT')</strong><small>{{ number_format($confirmedInvestmentCount) }} {{ __('site.investor_accounts.investments') }}</small></article>
            <article class="portal-stat-card"><span>{{ __('site.investor_accounts.pending_investments') }}</span><strong>{{ number_format($pendingInvestmentCount) }}</strong></article>
            <article class="portal-stat-card"><span>{{ __('site.investor_accounts.referrals') }}</span><strong>{{ number_format($referralCount) }}</strong></article>
        </div>
        <div class="portal-two-column">
            <section class="portal-panel">
                <div class="portal-panel-heading"><div><span class="section-eyebrow">{{ __('site.investor_accounts.profile') }}</span><h2>{{ __('site.investor_accounts.edit_profile') }}</h2></div></div>
                <form class="admin-form-grid" action="{{ route('admin.investors.update', $account->id) }}" method="post">
                    @csrf
                    @method('put')
                    <label class="admin-form-field admin-form-field-wide">
                        <span>{{ __('site.investor_accounts.name') }}</span>
                        <input name="name" type="text" value="{{ old('name', $account->name) }}" required maxlength="255">
                    </label>
                    <label class="admin-form-field admin-form-field-wide">
                        <span>{{ __('site.investor_accounts.email') }}</span>
                        <input name="email" type="email" value="{{ old('email', $account->email) }}" required maxlength="255">
                    </label>
                    <label class="admin-form-field admin-form-field-wide">
                        <span>{{ __('site.investor_accounts.phone') }}</span>
                        <input name="phone" type="tel" value="{{ old('phone', $account->phone) }}" maxlength="32">
                    </label>
                    <div class="admin-form-actions admin-form-field-wide"><button class="button button-dark" type="submit">{{ __('site.investor_accounts.save_profile') }}</button></div>
                </form>
            </section>
            <section class="portal-panel">
                <div class="portal-panel-heading"><div><span class="section-eyebrow">{{ __('site.investor_accounts.account_controls') }}</span><h2>{{ __('site.investor_accounts.access_status') }}</h2></div></div>
                <p>{{ $account->status === 'active' ? __('site.investor_accounts.active_help') : __('site.investor_accounts.suspended_help') }}</p>
                <form action="{{ route('admin.investors.status', $account->id) }}" method="post">
                    @csrf
                    @method('patch')
                    @if ($account->status === 'active')
                        <input type="hidden" name="status" value="suspended">
                        <button class="button button-dark" type="submit">{{ __('site.investor_accounts.suspend_account') }}</button>
                    @else
                        <input type="hidden" name="status" value="active">
                        <button class="button button-dark" type="submit">{{ __('site.investor_accounts.activate_account') }}</button>
                    @endif
                </form>
                @if ($account->referral_code || $account->referred_by_user_id)
                    <dl class="account-facts">
                        <div><dt>{{ __('site.investor_accounts.referral_code') }}</dt><dd>{{ $account->referral_code ?: '—' }}</dd></div>
                        @if ($referrer)
                            <div><dt>{{ __('site.investor_accounts.referred_by') }}</dt><dd><a href="{{ route('admin.investors.show', $referrer->id) }}">{{ $referrer->name }}</a></dd></div>
                        @endif
                    </dl>
                @endif
            </section>
        </div>
        <section class="portal-panel">
            <div class="portal-panel-heading"><div><span class="section-eyebrow">{{ __('site.investor_accounts.wallets') }}</span><h2>{{ __('site.investor_accounts.wallet_balances') }}</h2></div></div>
            @if ($wallets->isEmpty())
                <p class="portal-empty">{{ __('site.investor_accounts.no_wallets') }}</p>
            @else
                <div class="portal-table-wrap"><table class="portal-table">
                    <thead><tr><th>{{ __('site.investor_accounts.currency') }}</th><th>{{ __('site.investor_accounts.balance') }}</th><th>{{ __('site.investor_accounts.reserved') }}</th><th>{{ __('site.investor_accounts.available') }}</th></tr></thead>
                    <tbody>@foreach ($wallets as $wallet)
                        <tr><td>{{ $wallet->currency }}</td><td>@money($wallet->balance, $wallet->currency)</td><td>@money($wallet->reserved_balance, $wallet->currency)</td><td>@money($wallet->available_balance, $wallet->currency)</td></tr>
                    @endforeach</tbody>
                </table></div>
            @endif
        </section>
        <section class="portal-panel">
            <div class="portal-panel-heading"><div><span class="section-eyebrow">{{ __('site.investor_accounts.investments') }}</span><h2>{{ __('site.investor_accounts.recent_investments') }}</h2></div></div>
            @if ($recentInvestments->isEmpty())
                <p class="portal-empty">{{ __('site.investor_accounts.no_investments') }}</p>
            @else
                <div class="portal-table-wrap"><table class="portal-table">
                    <thead><tr><th>{{ __('site.investor_accounts.project') }}</th><th>{{ __('site.investor_accounts.amount') }}</th><th>{{ __('site.investor_accounts.payment') }}</th><th>{{ __('site.investor_accounts.status') }}</th><th>{{ __('site.investor_accounts.date') }}</th></tr></thead>
                    <tbody>@foreach ($recentInvestments as $investment)
                        <tr><td>{{ $investment->project_name }}</td><td>@money($investment->amount_bdt, 'BDT')</td><td>@money($investment->paid_amount, $investment->payment_currency)</td><td><span class="status-pill status-{{ \Illuminate\Support\Str::slug($investment->status) }}">{{ __('site.auth.statuses.'.$investment->status) }}</span></td><td>{{ \Illuminate\Support\Carbon::parse($investment->created_at)->format('M j, Y') }}</td></tr>
                    @endforeach</tbody>
                </table></div>
            @endif
        </section>
        <div class="portal-two-column">
            <section class="portal-panel">
                <div class="portal-panel-heading"><div><span class="section-eyebrow">{{ __('site.investor_accounts.finance') }}</span><h2>{{ __('site.investor_accounts.recent_deposits') }}</h2></div></div>
                @if ($recentDeposits->isEmpty())
                    <p class="portal-empty">{{ __('site.investor_accounts.no_deposits') }}</p>
                @else
                    <div class="portal-table-wrap"><table class="portal-table">
                        <thead><tr><th>{{ __('site.investor_accounts.amount') }}</th><th>{{ __('site.investor_accounts.reference') }}</th><th>{{ __('site.investor_accounts.status') }}</th></tr></thead>
                        <tbody>@foreach ($recentDeposits as $deposit)
                            <tr><td>@money($deposit->amount, $deposit->currency)</td><td>{{ $deposit->payment_reference }}</td><td><span class="status-pill status-{{ \Illuminate\Support\Str::slug($deposit->status) }}">{{ __('site.auth.statuses.'.$deposit->status) }}</span></td></tr>
                        @endforeach</tbody>
                    </table></div>
                @endif
            </section>
            <section class="portal-panel">
                <div class="portal-panel-heading"><div><span class="section-eyebrow">{{ __('site.investor_accounts.finance') }}</span><h2>{{ __('site.investor_accounts.recent_withdrawals') }}</h2></div></div>
                @if ($recentWithdrawals->isEmpty())
                    <p class="portal-empty">{{ __('site.investor_accounts.no_withdrawals') }}</p>
                @else
                    <div class="portal-table-wrap"><table class="portal-table">
                        <thead><tr><th>{{ __('site.investor_accounts.amount') }}</th><th>{{ __('site.investor_accounts.destination') }}</th><th>{{ __('site.investor_accounts.status') }}</th></tr></thead>
                        <tbody>@foreach ($recentWithdrawals as $withdrawal)
                            <tr><td>@money($withdrawal->amount, $withdrawal->currency)</td><td>{{ $withdrawal->destination }}</td><td><span class="status-pill status-{{ \Illuminate\Support\Str::slug($withdrawal->status) }}">{{ __('site.auth.statuses.'.$withdrawal->status) }}</span></td></tr>
                        @endforeach</tbody>
                    </table></div>
                @endif
            </section>
        </div>
        <section class="portal-panel">
            <div class="portal-panel-heading"><div><span class="section-eyebrow">{{ __('site.investor_accounts.referrals') }}</span><h2>{{ __('site.investor_accounts.referred_accounts') }}</h2></div></div>
            @if ($referrals->isEmpty())
                <p class="portal-empty">{{ __('site.investor_accounts.no_referrals') }}</p>
            @else
                <div class="portal-table-wrap"><table class="portal-table">
                    <thead><tr><th>{{ __('site.investor_accounts.account') }}</th><th>{{ __('site.investor_accounts.status') }}</th><th>{{ __('site.investor_accounts.joined') }}</th></tr></thead>
                    <tbody>@foreach ($referrals as $referral)
                        <tr><td><a class="table-primary" href="{{ route('admin.investors.show', $referral->id) }}">{{ $referral->name }}</a><span class="table-secondary">{{ $referral->email }}</span></td><td><span class="status-pill status-{{ \Illuminate\Support\Str::slug($referral->status) }}">{{ __('site.auth.'.$referral->status) }}</span></td><td>{{ \Illuminate\Support\Carbon::parse($referral->created_at)->format('M j, Y') }}</td></tr>
                    @endforeach</tbody>
                </table></div>
            @endif
        </section>
    </section>
@endsection
