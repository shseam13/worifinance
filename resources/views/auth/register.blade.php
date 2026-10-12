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

                <label for="password">{{ __('site.auth.password') }}</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required>
                @error('password') <span class="auth-error">{{ $message }}</span> @enderror

                <label for="password_confirmation">{{ __('site.auth.confirm_password') }}</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                <button class="button button-dark auth-submit" type="submit">{{ __('site.auth.create_account') }} <span aria-hidden="true">→</span></button>
            </form>

            <p class="auth-switch">{{ __('site.auth.login_prompt') }} <a href="{{ route('login') }}">{{ __('site.auth.login') }}</a></p>
        </div>
    </section>
@endsection
