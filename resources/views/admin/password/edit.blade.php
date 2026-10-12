@extends('layouts.admin')

@section('title', __('site.admin_password.title'))
@section('admin_breadcrumb', __('site.admin_password.title'))

@section('content')
    <section class="admin-dashboard">
        <div class="portal-heading">
            <span class="section-eyebrow">{{ __('site.admin_password.eyebrow') }}</span>
            <h1>{{ __('site.admin_password.title') }}</h1>
            <p>{{ __('site.admin_password.description') }}</p>
        </div>
        @if (session('status'))
            <div class="admin-flash" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="portal-notice portal-notice-warning" role="alert"><div><p>{{ $errors->first() }}</p></div></div>
        @endif
        <section class="portal-panel">
            <form class="admin-form-grid" action="{{ route('admin.password.update') }}" method="post">
                @csrf
                @method('put')
                <label class="admin-form-field admin-form-field-wide">
                    <span>{{ __('site.admin_password.current_password') }}</span>
                    <input name="current_password" type="password" autocomplete="current-password" required>
                </label>
                <label class="admin-form-field admin-form-field-wide">
                    <span>{{ __('site.admin_password.new_password') }}</span>
                    <input name="password" type="password" autocomplete="new-password" required>
                    <small>{{ __('site.admin_password.password_help') }}</small>
                </label>
                <label class="admin-form-field admin-form-field-wide">
                    <span>{{ __('site.admin_password.confirm_password') }}</span>
                    <input name="password_confirmation" type="password" autocomplete="new-password" required>
                </label>
                <div class="admin-form-actions admin-form-field-wide">
                    <button class="button button-dark" type="submit">{{ __('site.admin_password.update_password') }}</button>
                </div>
            </form>
        </section>
    </section>
@endsection
