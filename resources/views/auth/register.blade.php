@extends('layouts.app')

@section('title', __('site.auth.register'))

@section('content')
    <section class="auth-section">
        <div class="auth-card">
            <span class="section-eyebrow">{{ __('site.auth.welcome') }}</span>
            <h1>{{ __('site.auth.register_title') }}</h1>
            <p class="auth-description">{{ __('site.auth.register_description') }}</p>

            <form class="auth-form" action="{{ route('register.store') }}" method="post">
                @csrf
                <label for="name">{{ __('site.auth.name') }}</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required autofocus>
                @error('name') <span class="auth-error">{{ $message }}</span> @enderror

                <label for="email">{{ __('site.auth.email') }}</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
                @error('email') <span class="auth-error">{{ $message }}</span> @enderror

                <label for="phone">{{ __('site.auth.phone') }}</label>
                <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel">
                @error('phone') <span class="auth-error">{{ $message }}</span> @enderror

                <label for="referral_code">{{ __('site.auth.referral_code') }}</label>
                <input id="referral_code" name="referral_code" type="text" value="{{ old('referral_code', $referralCode) }}" maxlength="32" autocomplete="off">
                @error('referral_code') <span class="auth-error">{{ $message }}</span> @enderror

                <label for="password">{{ __('site.auth.password') }}</label>
                <x-password-input id="password" name="password" autocomplete="new-password" />
                @error('password') <span class="auth-error">{{ $message }}</span> @enderror

                <label for="password_confirmation">{{ __('site.auth.confirm_password') }}</label>
                <x-password-input id="password_confirmation" name="password_confirmation" autocomplete="new-password" />
                <input type="hidden" name="terms_version" value="{{ $terms['version'] }}">
                <section class="auth-terms" aria-label="{{ __('site.auth.terms_title') }}">
                    <label class="auth-terms-agreement" for="terms_accepted">
                        <input id="terms_accepted" name="terms_accepted" type="checkbox" value="1" required @checked(old('terms_accepted') && (string) old('terms_version') === (string) $terms['version'])>
                        <span>{{ __('site.auth.terms_agreement') }}</span>
                    </label>
                    <button class="auth-terms-link" type="button" data-terms-open aria-haspopup="dialog">
                        {{ __('site.auth.terms_open') }}
                    </button>
                    @error('terms_accepted') <span class="auth-error">{{ $message }}</span> @enderror
                    @error('terms_version') <span class="auth-error">{{ $message }}</span> @enderror
                </section>
                <button class="button button-dark auth-submit" type="submit">{{ __('site.auth.create_account') }} <span aria-hidden="true">→</span></button>
            </form>

            <dialog class="auth-terms-dialog" data-terms-dialog aria-labelledby="terms-title">
                <div class="auth-terms-dialog-heading">
                    <h2 id="terms-title">{{ __('site.auth.terms_title') }}</h2>
                    <button class="auth-terms-dialog-close" type="button" data-terms-close aria-label="{{ __('site.auth.terms_close') }}">×</button>
                </div>
                <div class="auth-terms-content">{{ $terms['content'] }}</div>
            </dialog>

            <p class="auth-switch">{{ __('site.auth.login_prompt') }} <a href="{{ route('login') }}">{{ __('site.auth.login') }}</a></p>
        </div>
    </section>
@endsection
