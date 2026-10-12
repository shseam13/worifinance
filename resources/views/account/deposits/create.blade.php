@extends('layouts.app')

@section('title', __('site.deposits.title'))

@section('content')
    <section class="portal-section">
        <div class="portal-heading">
            <a class="admin-back-link" href="{{ route('account.dashboard') }}">← {{ __('site.deposits.back_to_dashboard') }}</a>
            <span class="section-eyebrow">{{ __('site.deposits.eyebrow') }}</span>
            <h1>{{ __('site.deposits.title') }}</h1>
            <p>{{ __('site.deposits.description') }}</p>
        </div>

        @if (session('status'))
            <div class="admin-flash" role="status">{{ session('status') }}</div>
        @endif

        @if ($enabledCurrencies === [])
            <div class="portal-notice portal-notice-warning">
                <div><h2>{{ __('site.deposits.not_configured_title') }}</h2><p>{{ __('site.deposits.not_configured_notice') }}</p></div>
            </div>
        @else
            <div class="portal-notice portal-notice-warning">
                <div><h2>{{ __('site.deposits.payment_warning_title') }}</h2><p>{{ __('site.deposits.payment_warning') }}</p></div>
            </div>

            @foreach ($enabledCurrencies as $currency)
                <section class="portal-panel">
                    <div class="portal-panel-heading">
                        <div><span class="section-eyebrow">{{ $currency }}</span><h2>{{ __('site.deposits.instructions_title') }}</h2></div>
                    </div>
                    <p class="deposit-instructions">{!! nl2br(e($instructions[$currency]['text'])) !!}</p>
                    @if ($currency === 'USDT' && $usdtQrUrl)
                        <figure class="payment-qr">
                            <img src="{{ $usdtQrUrl }}" alt="{{ __('site.deposits.usdt_qr_alt') }}">
                            <figcaption>{{ __('site.deposits.usdt_qr_caption') }}</figcaption>
                        </figure>
                    @endif
                </section>
            @endforeach

            <form class="admin-project-form" action="{{ route('account.deposits.store') }}" method="post">
                @csrf
                <input type="hidden" name="bdt_payment_instructions_version" value="{{ $instructions['BDT']['version'] }}">
                <input type="hidden" name="usdt_payment_instructions_version" value="{{ $instructions['USDT']['version'] }}">
                <input type="hidden" name="usdt_payment_qr_version" value="{{ $usdtQrVersion }}">
                <section class="admin-form-section">
                    <div class="admin-form-heading"><span class="section-eyebrow">01</span><div><h2>{{ __('site.deposits.request_title') }}</h2><p>{{ __('site.deposits.request_help') }}</p></div></div>
                    <div class="admin-form-grid">
                        <label class="admin-form-field">
                            <span>{{ __('site.deposits.currency') }} <b>*</b></span>
                            <select name="currency" required>
                                @foreach ($enabledCurrencies as $currency)
                                    <option value="{{ $currency }}" @selected(old('currency', $enabledCurrencies[0]) === $currency)>{{ $currency }}</option>
                                @endforeach
                            </select>
                            @error('currency')<small class="auth-error">{{ $message }}</small>@enderror
                        </label>
                        <label class="admin-form-field">
                            <span>{{ __('site.deposits.amount') }} <b>*</b></span>
                            <input type="number" name="amount" min="0.00000001" step="any" value="{{ old('amount') }}" required>
                            @error('amount')<small class="auth-error">{{ $message }}</small>@enderror
                        </label>
                        <label class="admin-form-field admin-form-field-wide">
                            <span>{{ __('site.deposits.payment_reference') }} <b>*</b></span>
                            <input name="payment_reference" maxlength="150" value="{{ old('payment_reference') }}" required>
                            <small>{{ __('site.deposits.payment_reference_help') }}</small>
                            @error('payment_reference')<small class="auth-error">{{ $message }}</small>@enderror
                        </label>
                    </div>
                </section>
                <div class="admin-form-actions"><button class="button button-dark" type="submit">{{ __('site.deposits.submit') }} <span aria-hidden="true">→</span></button></div>
            </form>
        @endif
    </section>
@endsection
