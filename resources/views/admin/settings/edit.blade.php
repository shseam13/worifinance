@extends('layouts.admin')

@section('title', __('site.admin_settings.title'))
@section('admin_breadcrumb', __('site.admin_settings.title'))

@section('content')
    <section class="admin-dashboard">
        <div class="portal-heading">
            <span class="section-eyebrow">{{ __('site.admin_settings.eyebrow') }}</span>
            <h1>{{ __('site.admin_settings.title') }}</h1>
            <p>{{ __('site.admin_settings.description') }}</p>
        </div>
        @if (session('status'))
            <div class="admin-flash" role="status">{{ session('status') }}</div>
        @endif
        <div class="portal-notice portal-notice-warning">
            <div><h2>{{ __('site.admin_settings.automation_title') }}</h2><p>{{ __('site.admin_settings.automation_notice') }}</p></div>
        </div>
        <form class="admin-project-form" action="{{ route('admin.settings.update') }}" method="post" enctype="multipart/form-data">
            @csrf
            @method('put')
            @if ($errors->any())
                <div class="portal-notice portal-notice-warning" role="alert"><div><p>{{ $errors->first() }}</p></div></div>
            @endif
            <section class="admin-form-section">
                <div class="admin-form-heading"><span class="section-eyebrow">01</span><div><h2>{{ __('site.admin_settings.financial_policies') }}</h2><p>{{ __('site.admin_settings.financial_policies_help') }}</p></div></div>
                <div class="admin-form-grid">
                    <label class="admin-form-field"><span>{{ __('site.admin_settings.annual_fee') }} (BDT) <b>*</b></span><input name="annual_fee" type="number" min="0" max="9999999999999999.99" step="0.01" value="{{ old('annual_fee', $annualFee) }}" required><small>{{ __('site.admin_settings.annual_fee_help') }}</small>@error('annual_fee')<small class="auth-error">{{ $message }}</small>@enderror</label>
                    <label class="admin-form-field"><span>{{ __('site.admin_settings.minimum_withdrawal') }} (BDT) <b>*</b></span><input name="minimum_withdrawal" type="number" min="0" max="999999999999999999" step="0.01" value="{{ old('minimum_withdrawal', $minimumWithdrawal) }}" required>@error('minimum_withdrawal')<small class="auth-error">{{ $message }}</small>@enderror</label>
                    <label class="admin-form-field"><span>{{ __('site.admin_settings.referral_rate') }} (%) <b>*</b></span><input name="referral_commission_percent" type="number" min="0" max="100" step="0.0001" value="{{ old('referral_commission_percent', $referralRate) }}" required>@error('referral_commission_percent')<small class="auth-error">{{ $message }}</small>@enderror</label>
                </div>
                <p class="admin-form-hint">{{ __('site.admin_settings.version_notice') }}</p>
            </section>
            <section class="admin-form-section">
                <div class="admin-form-heading"><span class="section-eyebrow">02</span><div><h2>{{ __('site.admin_settings.site_currency') }}</h2><p>{{ __('site.admin_settings.site_currency_help') }}</p></div></div>
                <div class="admin-form-grid">
                    <label class="admin-form-field">
                        <span>{{ __('site.admin_settings.display_currency') }} <b>*</b></span>
                        <select name="display_currency" required>
                            <option value="BDT" @selected(old('display_currency', $displayCurrency) === 'BDT')>BDT</option>
                            <option value="USDT" @selected(old('display_currency', $displayCurrency) === 'USDT')>USDT</option>
                        </select>
                        @error('display_currency')<small class="auth-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="admin-form-field">
                        <span>{{ __('site.admin_settings.usdt_to_bdt_rate') }} <b>*</b></span>
                        <input name="usdt_to_bdt_rate" type="number" min="0.00000001" max="999999999999.99999999" step="0.00000001" value="{{ old('usdt_to_bdt_rate', $usdtToBdtRate) }}" required>
                        <small>{{ __('site.admin_settings.usdt_to_bdt_rate_help') }}</small>
                        @error('usdt_to_bdt_rate')<small class="auth-error">{{ $message }}</small>@enderror
                    </label>
                </div>
                <p class="admin-form-hint">{{ __('site.admin_settings.currency_conversion_notice') }}</p>
            </section>
            <section class="admin-form-section" id="payment-instructions">
                <div class="admin-form-heading"><span class="section-eyebrow">03</span><div><h2>{{ __('site.admin_settings.payment_instructions') }}</h2><p>{{ __('site.admin_settings.payment_instructions_help') }}</p></div></div>
                <div class="admin-form-grid">
                    <label class="admin-form-field admin-form-field-wide">
                        <span>{{ __('site.admin_settings.bdt_payment_instructions') }}</span>
                        <textarea name="bdt_payment_instructions" rows="5" maxlength="5000">{{ old('bdt_payment_instructions', $bdtPaymentInstructions) }}</textarea>
                        <small>{{ __('site.admin_settings.payment_instructions_public_notice') }}</small>
                        @error('bdt_payment_instructions')<small class="auth-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="admin-form-field admin-form-field-wide">
                        <span>{{ __('site.admin_settings.usdt_payment_instructions') }}</span>
                        <textarea name="usdt_payment_instructions" rows="5" maxlength="5000">{{ old('usdt_payment_instructions', $usdtPaymentInstructions) }}</textarea>
                        <small>{{ __('site.admin_settings.usdt_instructions_help') }}</small>
                        @error('usdt_payment_instructions')<small class="auth-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="admin-form-field admin-form-field-wide">
                        <span>{{ __('site.admin_settings.usdt_qr_code') }}</span>
                        <input type="file" name="usdt_qr_code" accept="image/png,image/jpeg,image/webp">
                        <small>{{ __('site.admin_settings.usdt_qr_code_help') }}</small>
                        @error('usdt_qr_code')<small class="auth-error">{{ $message }}</small>@enderror
                    </label>
                    @if ($usdtQrUrl)
                        <figure class="payment-qr payment-qr-admin admin-form-field-wide">
                            <img src="{{ $usdtQrUrl }}" alt="{{ __('site.deposits.usdt_qr_alt') }}">
                            <figcaption>{{ __('site.admin_settings.current_usdt_qr') }}</figcaption>
                        </figure>
                    @endif
                </div>
            </section>
            <div class="admin-form-actions"><button class="button button-dark" type="submit">{{ __('site.admin_settings.save') }} <span aria-hidden="true">→</span></button></div>
        </form>
    </section>
@endsection
