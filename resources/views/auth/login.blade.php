@extends('layouts.app')

@section('title', $portal === 'admin' ? __('site.auth.admin_login') : __('site.auth.login'))

@section('content')
    <section class="auth-section">
        <div class="auth-card">
            <span class="section-eyebrow">{{ __('site.auth.welcome') }}</span>
            <h1>{{ $portal === 'admin' ? __('site.auth.admin_login') : __('site.auth.login_title') }}</h1>
            <p class="auth-description">{{ $portal === 'admin' ? __('site.auth.admin_description') : __('site.auth.login_description') }}</p>
            @if (session('status'))
                <div class="admin-flash" role="status">{{ session('status') }}</div>
            @endif

            <form class="auth-form" action="{{ $portal === 'admin' ? route('admin.login.store') : route('login.store') }}" method="post">
                @csrf
                <label for="email">{{ __('site.auth.email') }}</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
                @error('email') <span class="auth-error">{{ $message }}</span> @enderror

                <label for="password">{{ __('site.auth.password') }}</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
                @error('password') <span class="auth-error">{{ $message }}</span> @enderror
                <p class="auth-switch"><a href="{{ route('password.request') }}">{{ __('site.password_reset.forgot_password') }}</a></p>

                <label class="auth-remember" for="remember">
                    <input id="remember" name="remember" type="checkbox" value="1">
                    <span>{{ __('site.auth.remember') }}</span>
                </label>
                <button class="button button-dark auth-submit" type="submit">{{ __('site.auth.login') }} <span aria-hidden="true">→</span></button>
            </form>

            @if ($portal === 'admin')
                <p class="auth-switch">{{ __('site.auth.investor_login_prompt') }} <a href="{{ route('login') }}">{{ __('site.auth.investor_login_link') }}</a></p>
            @else
                <p class="auth-switch">{{ __('site.auth.register_prompt') }} <a href="{{ route('register') }}">{{ __('site.auth.register') }}</a></p>
                <p class="auth-switch"><a href="{{ route('admin.login') }}">{{ __('site.auth.admin_login') }}</a></p>
            @endif
        </div>
    </section>
@endsection
