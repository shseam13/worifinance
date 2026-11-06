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
        <form class="admin-project-form admin-settings-form" action="{{ route('admin.settings.update') }}" method="post" enctype="multipart/form-data">
            @csrf
            @method('put')
            @if ($errors->any())
                <div class="portal-notice portal-notice-warning" role="alert"><div><p>{{ $errors->first() }}</p></div></div>
            @endif
            <details class="admin-form-section admin-settings-section" id="financial-policies" open>
                <summary class="admin-form-heading"><span class="section-eyebrow">01</span><span class="admin-settings-heading-copy"><span class="admin-form-title" role="heading" aria-level="2">{{ __('site.admin_settings.financial_policies') }}</span><span class="admin-form-description">{{ __('site.admin_settings.financial_policies_help') }}</span></span></summary>
                <div class="admin-form-grid">
                    <label class="admin-form-field"><span>{{ __('site.admin_settings.annual_fee') }} (BDT) <b>*</b></span><input name="annual_fee" type="number" min="0" max="9999999999999999.99" step="0.01" value="{{ old('annual_fee', $annualFee) }}" required><small>{{ __('site.admin_settings.annual_fee_help') }}</small>@error('annual_fee')<small class="auth-error">{{ $message }}</small>@enderror</label>
                    <label class="admin-form-field"><span>{{ __('site.admin_settings.minimum_withdrawal') }} (BDT) <b>*</b></span><input name="minimum_withdrawal" type="number" min="0" max="999999999999999999" step="0.01" value="{{ old('minimum_withdrawal', $minimumWithdrawal) }}" required>@error('minimum_withdrawal')<small class="auth-error">{{ $message }}</small>@enderror</label>
                    <label class="admin-form-field">
                        <span>{{ __('site.admin_settings.referral_commission_type') }} <b>*</b></span>
                        <select name="referral_commission_type" required>
                            <option value="percentage" @selected(old('referral_commission_type', $referralCommissionType) === 'percentage')>{{ __('site.admin_settings.referral_percentage') }}</option>
                            <option value="fixed" @selected(old('referral_commission_type', $referralCommissionType) === 'fixed')>{{ __('site.admin_settings.referral_fixed') }}</option>
                        </select>
                        <small>{{ __('site.admin_settings.referral_commission_help') }}</small>
                        @error('referral_commission_type')<small class="auth-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="admin-form-field"><span>{{ __('site.admin_settings.referral_rate') }} (%)</span><input name="referral_commission_percent" type="number" min="0" max="100" step="0.0001" value="{{ old('referral_commission_percent', $referralCommissionType === 'percentage' ? $referralRate : '0') }}">@error('referral_commission_percent')<small class="auth-error">{{ $message }}</small>@enderror</label>
                    <label class="admin-form-field"><span>{{ __('site.admin_settings.referral_fixed_amount') }} (BDT)</span><input name="referral_commission_fixed_bdt" type="number" min="0" max="9999999999999999.99" step="0.01" value="{{ old('referral_commission_fixed_bdt', $referralCommissionType === 'fixed' ? $referralRate : '0') }}">@error('referral_commission_fixed_bdt')<small class="auth-error">{{ $message }}</small>@enderror</label>
                </div>
                <p class="admin-form-hint">{{ __('site.admin_settings.version_notice') }}</p>
            </details>
            <details class="admin-form-section admin-settings-section" id="site-currency" @if ($errors->any()) open @endif>
                <summary class="admin-form-heading"><span class="section-eyebrow">02</span><span class="admin-settings-heading-copy"><span class="admin-form-title" role="heading" aria-level="2">{{ __('site.admin_settings.site_currency') }}</span><span class="admin-form-description">{{ __('site.admin_settings.site_currency_help') }}</span></span></summary>
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
            </details>
            <details class="admin-form-section admin-settings-section" id="payment-instructions" @if ($errors->any()) open @endif>
                <summary class="admin-form-heading"><span class="section-eyebrow">03</span><span class="admin-settings-heading-copy"><span class="admin-form-title" role="heading" aria-level="2">{{ __('site.admin_settings.payment_instructions') }}</span><span class="admin-form-description">{{ __('site.admin_settings.payment_instructions_help') }}</span></span></summary>
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
            </details>
            <details class="admin-form-section admin-settings-section" id="homepage-content" @if ($errors->any()) open @endif>
                <summary class="admin-form-heading"><span class="section-eyebrow">04</span><span class="admin-settings-heading-copy"><span class="admin-form-title" role="heading" aria-level="2">{{ __('site.admin_settings.homepage_content') }}</span><span class="admin-form-description">{{ __('site.admin_settings.homepage_content_help') }}</span></span></summary>
                @foreach (['hero' => 'homepage_hero', 'about' => 'homepage_about', 'how_it_works' => 'homepage_how'] as $section => $sectionLabel)
                    <div class="homepage-content-section">
                        <h3>{{ __('site.admin_settings.'.$sectionLabel) }}</h3>
                        <div class="admin-form-grid">
                            @foreach ($homepage[$section] as $field => $translations)
                                @foreach (['en' => 'language_english', 'bn' => 'language_bengali'] as $locale => $languageLabel)
                                    @php($fieldLabel = __('site.admin_settings.homepage_fields.'.$field))
                                    <label class="admin-form-field admin-form-field-wide" for="homepage-{{ $section }}-{{ $field }}-{{ $locale }}">
                                        <span>{{ $fieldLabel }} — {{ __('site.admin_settings.'.$languageLabel) }}</span>
                                        @if ($field === 'description' || str_ends_with($field, '_description'))
                                            <textarea id="homepage-{{ $section }}-{{ $field }}-{{ $locale }}" name="homepage[{{ $section }}][{{ $field }}][{{ $locale }}]" rows="4" maxlength="5000" required>{{ old("homepage.{$section}.{$field}.{$locale}", $translations[$locale]) }}</textarea>
                                        @else
                                            <input id="homepage-{{ $section }}-{{ $field }}-{{ $locale }}" name="homepage[{{ $section }}][{{ $field }}][{{ $locale }}]" type="text" maxlength="255" value="{{ old("homepage.{$section}.{$field}.{$locale}", $translations[$locale]) }}" required>
                                        @endif
                                        @error("homepage.{$section}.{$field}.{$locale}")<small class="auth-error">{{ $message }}</small>@enderror
                                    </label>
                                @endforeach
                            @endforeach
                        </div>
                    </div>
                @endforeach
                <div class="homepage-content-section">
                    <h3>{{ __('site.admin_settings.homepage_slides') }}</h3>
                    <label class="admin-form-field admin-form-field-wide">
                        <span>{{ __('site.admin_settings.upload_hero_slides') }}</span>
                        <input type="file" name="hero_slides[]" accept="image/png,image/jpeg,image/webp" multiple>
                        <small>{{ __('site.admin_settings.homepage_slides_help') }}</small>
                        @error('hero_slides')<small class="auth-error">{{ $message }}</small>@enderror
                        @error('hero_slides.*')<small class="auth-error">{{ $message }}</small>@enderror
                    </label>
                    @if ($heroSlideUrls !== [])
                        <div class="admin-homepage-slides">
                            @foreach ($heroSlideUrls as $slide)
                                <figure>
                                    <img src="{{ $slide['url'] }}" alt="{{ __('site.hero.image_alt') }}">
                                    <label class="admin-slide-remove">
                                        <input type="checkbox" name="remove_hero_slides[]" value="{{ $slide['path'] }}">
                                        <span>{{ __('site.admin_settings.remove_slide') }}</span>
                                    </label>
                                </figure>
                            @endforeach
                        </div>
                    @endif
                </div>
            </details>
            <details class="admin-form-section admin-settings-section" id="terms-conditions" @if ($errors->any()) open @endif>
                <summary class="admin-form-heading"><span class="section-eyebrow">05</span><span class="admin-settings-heading-copy"><span class="admin-form-title" role="heading" aria-level="2">{{ __('site.admin_settings.terms_title') }}</span><span class="admin-form-description">{{ __('site.admin_settings.terms_help') }}</span></span></summary>
                <div class="admin-form-grid">
                    @foreach (['en' => 'language_english', 'bn' => 'language_bengali'] as $locale => $languageLabel)
                        <label class="admin-form-field admin-form-field-wide" for="terms-{{ $locale }}">
                            <span>{{ __('site.admin_settings.terms_content') }} — {{ __('site.admin_settings.'.$languageLabel) }}</span>
                            <textarea id="terms-{{ $locale }}" name="terms[{{ $locale }}]" rows="10" maxlength="20000" required>{{ old("terms.{$locale}", $terms[$locale]) }}</textarea>
                            @error("terms.{$locale}")<small class="auth-error">{{ $message }}</small>@enderror
                        </label>
                    @endforeach
                </div>
            </details>
            <details class="admin-form-section admin-settings-section" id="community-links" @if ($errors->any()) open @endif>
                <summary class="admin-form-heading"><span class="section-eyebrow">06</span><span class="admin-settings-heading-copy"><span class="admin-form-title" role="heading" aria-level="2">{{ __('site.admin_settings.homepage_social') }}</span><span class="admin-form-description">{{ __('site.admin_settings.homepage_social_help') }}</span></span></summary>
                <div class="admin-social-grid">
                    @foreach (['telegram', 'whatsapp', 'facebook', 'instagram', 'other'] as $platform)
                        <div class="admin-social-card">
                            <h3>{{ __('site.admin_settings.social_'.$platform) }}</h3>
                            @if ($platform === 'other')
                                <label class="admin-form-field">
                                    <span>{{ __('site.admin_settings.other_name') }}</span>
                                    <input name="social_links[other][label]" type="text" maxlength="40" value="{{ old('social_links.other.label', $socialLinks['other']['label']) }}">
                                    @error('social_links.other.label')<small class="auth-error">{{ $message }}</small>@enderror
                                </label>
                            @endif
                            <label class="admin-form-field">
                                <span>{{ __('site.admin_settings.url') }}</span>
                                <input name="social_links[{{ $platform }}][url]" type="url" maxlength="2048" value="{{ old("social_links.{$platform}.url", $socialLinks[$platform]['url']) }}" placeholder="https://">
                                @error("social_links.{$platform}.url")<small class="auth-error">{{ $message }}</small>@enderror
                            </label>
                            <label class="admin-form-field">
                                <span>{{ __('site.admin_settings.icon') }}</span>
                                <input name="social_links[{{ $platform }}][icon]" type="file" accept="image/png,image/jpeg,image/webp">
                                @error("social_links.{$platform}.icon")<small class="auth-error">{{ $message }}</small>@enderror
                            </label>
                            @if ($socialLinks[$platform]['icon_url'])
                                <div class="admin-social-current-icon">
                                    <img src="{{ $socialLinks[$platform]['icon_url'] }}" alt="{{ __('site.admin_settings.current_icon') }}">
                                    <label class="admin-slide-remove">
                                        <input type="checkbox" name="social_links[{{ $platform }}][remove_icon]" value="1">
                                        <span>{{ __('site.admin_settings.remove_icon') }}</span>
                                    </label>
                                </div>
                            @endif
                            <label class="admin-social-enabled">
                                <input type="hidden" name="social_links[{{ $platform }}][enabled]" value="0">
                                <input type="checkbox" name="social_links[{{ $platform }}][enabled]" value="1" @checked(old("social_links.{$platform}.enabled", $socialLinks[$platform]['enabled']))>
                                <span>{{ __('site.admin_settings.enabled') }}</span>
                            </label>
                        </div>
                    @endforeach
                </div>
            </details>
            <div class="admin-form-actions admin-settings-savebar"><button class="button button-dark" type="submit">{{ __('site.admin_settings.save') }} <span aria-hidden="true">→</span></button></div>
        </form>
    </section>
@endsection
