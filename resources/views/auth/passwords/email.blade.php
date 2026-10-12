@extends('layouts.app')

@section('title', __('site.password_reset.forgot_password'))

@section('content')
    <section class="auth-section">
        <div class="auth-card">
            <span class="section-eyebrow">{{ __('site.auth.welcome') }}</span>
            <h1>{{ __('site.password_reset.forgot_password') }}</h1>
            <p class="auth-description">{{ __('site.password_reset.request_help') }}</p>
            @if (session('status'))
                <div class="admin-flash" role="status">{{ session('status') }}</div>
            @endif
            <form class="auth-form" action="{{ route('password.email') }}" method="post">
                @csrf
                <label for="email">{{ __('site.auth.email') }}</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
                @error('email') <span class="auth-error">{{ $message }}</span> @enderror
                <button class="button button-dark auth-submit" type="submit">{{ __('site.password_reset.send_link') }} <span aria-hidden="true">→</span></button>
            </form>
            <p class="auth-switch"><a href="{{ route('login') }}">{{ __('site.password_reset.back_to_login') }}</a></p>
        </div>
    </section>
@endsection
