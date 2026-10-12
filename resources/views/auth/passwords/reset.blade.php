@extends('layouts.app')

@section('title', __('site.password_reset.reset_password'))

@section('content')
    <section class="auth-section">
        <div class="auth-card">
            <span class="section-eyebrow">{{ __('site.auth.welcome') }}</span>
            <h1>{{ __('site.password_reset.reset_password') }}</h1>
            <p class="auth-description">{{ __('site.password_reset.reset_help') }}</p>
            <form class="auth-form" action="{{ route('password.update') }}" method="post">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <label for="email">{{ __('site.auth.email') }}</label>
                <input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required>
                @error('email') <span class="auth-error">{{ $message }}</span> @enderror

                <label for="password">{{ __('site.auth.password') }}</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required>
                @error('password') <span class="auth-error">{{ $message }}</span> @enderror

                <label for="password_confirmation">{{ __('site.auth.confirm_password') }}</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                <button class="button button-dark auth-submit" type="submit">{{ __('site.password_reset.reset_password') }} <span aria-hidden="true">→</span></button>
            </form>
        </div>
    </section>
@endsection
