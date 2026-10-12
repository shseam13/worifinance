@extends('layouts.app')

@section('title', __('site.investments.title'))

@section('content')
    <section class="portal-section">
        <div class="portal-heading">
            <a class="admin-back-link" href="{{ route('projects.show', $project) }}">← {{ __('site.investments.back_to_project') }}</a>
            <span class="section-eyebrow">{{ __('site.investments.eyebrow') }}</span>
            <h1>{{ __('site.investments.title') }} · {{ $project->displayName() }}</h1>
            <p>{{ __('site.investments.description') }}</p>
        </div>

        @if ($errors->any())
            <div class="portal-notice portal-notice-warning" role="alert">
                <div><h2>{{ __('site.investments.check_request') }}</h2><p>{{ $errors->first() }}</p></div>
            </div>
        @endif

        <div class="portal-notice portal-notice-warning">
            <div><h2>{{ __('site.investments.review_notice_title') }}</h2><p>{{ __('site.investments.review_notice') }}</p></div>
        </div>

        <section class="portal-panel">
            <div class="portal-panel-heading">
                <div><span class="section-eyebrow">{{ __('site.investments.terms') }}</span><h2>{{ __('site.projects.investment_terms') }}</h2></div>
            </div>
            <div class="project-summary-list">
                <div class="project-summary-row"><span>{{ __('site.projects.unit_price') }}</span><strong>@money($project->currentTerms->unit_price_bdt, 'BDT')</strong></div>
                <div class="project-summary-row"><span>{{ __('site.investments.annual_rate') }}</span><strong>{{ number_format((float) $project->currentTerms->annual_rate_percent, 4) }}%</strong></div>
                <div class="project-summary-row"><span>{{ __('site.projects.units') }}</span><strong>{{ number_format($project->available_units) }}</strong></div>
            </div>
        </section>

        <form class="admin-project-form" action="{{ route('account.projects.invest.store', $project) }}" method="post">
            @csrf
            <section class="admin-form-section">
                <div class="admin-form-heading"><span class="section-eyebrow">01</span><div><h2>{{ __('site.investments.request_title') }}</h2><p>{{ __('site.investments.request_help') }}</p></div></div>
                <div class="admin-form-grid">
                    <label class="admin-form-field">
                        <span>{{ __('site.investments.units') }} <b>*</b></span>
                        <input type="number" name="units" min="1" max="{{ $project->available_units }}" step="1" value="{{ old('units', 1) }}" required>
                        @error('units')<small class="auth-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="admin-form-field">
                        <span>{{ __('site.investments.payment_currency') }} <b>*</b></span>
                        <select name="currency" required>
                            @php
                                $availableBdt = (float) ($wallets->get('BDT')->available_balance ?? 0);
                                $availableUsdt = (float) ($wallets->get('USDT')->available_balance ?? 0);
                                $usdtEnabled = $exchangeRate !== null && (float) $exchangeRate > 0;
                                $firstCurrency = $availableBdt > 0 ? 'BDT' : 'USDT';
                            @endphp
                            @if ($availableBdt > 0)
                                <option value="BDT" @selected(old('currency', $firstCurrency) === 'BDT')>BDT · @money($wallets->get('BDT')->available_balance, 'BDT') {{ __('site.investments.available') }}</option>
                            @endif
                            @if ($availableUsdt > 0 && $usdtEnabled)
                                <option value="USDT" @selected(old('currency', $firstCurrency) === 'USDT')>USDT · @money($wallets->get('USDT')->available_balance, 'USDT') {{ __('site.investments.available') }}</option>
                            @endif
                        </select>
                        @error('currency')<small class="auth-error">{{ $message }}</small>@enderror
                    </label>
                </div>
                @if ($availableBdt <= 0 && ($availableUsdt <= 0 || ! $usdtEnabled))
                    <p class="portal-empty">{{ __('site.investments.no_usable_balance') }} <a class="text-link" href="{{ route('account.deposits.create') }}">{{ __('site.deposits.recharge') }}</a></p>
                @endif
                @if ($exchangeRate !== null && $usdtEnabled)
                    <p class="admin-form-hint">{{ __('site.investments.current_rate', ['rate' => $exchangeRate]) }}</p>
                @endif
            </section>
            <div class="admin-form-actions">
                <button class="button button-dark" type="submit" @disabled($availableBdt <= 0 && ($availableUsdt <= 0 || ! $usdtEnabled))>{{ __('site.investments.submit') }} <span aria-hidden="true">→</span></button>
            </div>
        </form>
    </section>
@endsection
