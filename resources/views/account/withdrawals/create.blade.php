@extends('layouts.app')

@section('title', __('site.withdrawals.title'))

@section('content')
    <section class="portal-section">
        <div class="portal-heading">
            <a class="admin-back-link" href="{{ route('account.dashboard') }}">← {{ __('site.withdrawals.back_to_dashboard') }}</a>
            <span class="section-eyebrow">{{ __('site.withdrawals.eyebrow') }}</span>
            <h1>{{ __('site.withdrawals.title') }}</h1>
            <p>{{ __('site.withdrawals.description') }}</p>
        </div>

        @if ($errors->any())
            <div class="portal-notice portal-notice-warning" role="alert">
                <div><h2>{{ __('site.withdrawals.check_request') }}</h2><p>{{ $errors->first() }}</p></div>
            </div>
        @endif

        <div class="portal-notice portal-notice-warning">
            <div><h2>{{ __('site.withdrawals.manual_review_title') }}</h2><p>{{ __('site.withdrawals.manual_review_notice') }}</p></div>
        </div>

        <section class="portal-panel">
            <div class="portal-panel-heading"><div><span class="section-eyebrow">{{ __('site.withdrawals.minimum_title') }}</span><h2>{{ __('site.withdrawals.minimum_help') }}</h2></div></div>
            <div class="project-summary-list">
                <div class="project-summary-row"><span>{{ __('site.withdrawals.minimum_bdt') }}</span><strong>{{ number_format((float) $minimumBdt, 2) }} BDT</strong></div>
                @if ($minimumUsdt !== null)
                    <div class="project-summary-row"><span>{{ __('site.withdrawals.minimum_usdt') }}</span><strong>{{ number_format((float) $minimumUsdt, 8) }} USDT</strong></div>
                    <p class="admin-form-hint">{{ __('site.withdrawals.current_rate', ['rate' => $exchangeRate]) }}</p>
                @endif
            </div>
        </section>

        <form class="admin-project-form" action="{{ route('account.withdrawals.store') }}" method="post">
            @csrf
            @php
                $availableBdt = (float) ($wallets->get('BDT')->available_balance ?? 0);
                $availableUsdt = (float) ($wallets->get('USDT')->available_balance ?? 0);
                $usdtEnabled = $exchangeRate !== null && (float) $exchangeRate > 0;
                $firstCurrency = $availableBdt > 0 ? 'BDT' : 'USDT';
            @endphp
            <section class="admin-form-section">
                <div class="admin-form-heading"><span class="section-eyebrow">01</span><div><h2>{{ __('site.withdrawals.request_title') }}</h2><p>{{ __('site.withdrawals.request_help') }}</p></div></div>
                <div class="admin-form-grid">
                    <label class="admin-form-field">
                        <span>{{ __('site.withdrawals.currency') }} <b>*</b></span>
                        <select name="currency" required>
                            @if ($availableBdt > 0)
                                <option value="BDT" @selected(old('currency', $firstCurrency) === 'BDT')>BDT · {{ number_format($availableBdt, 2) }} {{ __('site.withdrawals.available') }}</option>
                            @endif
                            @if ($availableUsdt > 0 && $usdtEnabled)
                                <option value="USDT" @selected(old('currency', $firstCurrency) === 'USDT')>USDT · {{ number_format($availableUsdt, 8) }} {{ __('site.withdrawals.available') }}</option>
                            @endif
                        </select>
                        @error('currency')<small class="auth-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="admin-form-field">
                        <span>{{ __('site.withdrawals.amount') }} <b>*</b></span>
                        <input type="number" name="amount" min="0.00000001" step="any" value="{{ old('amount') }}" required>
                        <small>{{ __('site.withdrawals.amount_precision') }}</small>
                        @error('amount')<small class="auth-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="admin-form-field admin-form-field-wide">
                        <span>{{ __('site.withdrawals.destination') }} <b>*</b></span>
                        <input name="destination" maxlength="255" value="{{ old('destination') }}" required>
                        <small>{{ __('site.withdrawals.destination_help') }}</small>
                        @error('destination')<small class="auth-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="admin-form-field">
                        <span>{{ __('site.withdrawals.network') }}</span>
                        <input name="network" maxlength="40" value="{{ old('network') }}">
                        <small>{{ __('site.withdrawals.network_help') }}</small>
                        @error('network')<small class="auth-error">{{ $message }}</small>@enderror
                    </label>
                </div>
                @if ($availableBdt <= 0 && ($availableUsdt <= 0 || ! $usdtEnabled))
                    <p class="portal-empty">{{ __('site.withdrawals.no_usable_balance') }} <a class="text-link" href="{{ route('account.deposits.create') }}">{{ __('site.deposits.recharge') }}</a></p>
                @endif
            </section>
            <div class="admin-form-actions">
                <button class="button button-dark" type="submit" @disabled($availableBdt <= 0 && ($availableUsdt <= 0 || ! $usdtEnabled))>{{ __('site.withdrawals.submit') }} <span aria-hidden="true">→</span></button>
            </div>
        </form>
    </section>
@endsection
