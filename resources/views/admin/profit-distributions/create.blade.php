@extends('layouts.admin')

@section('title', __('site.profit_distributions.create_title'))
@section('admin_breadcrumb', __('site.profit_distributions.create_title'))

@section('content')
    <section class="admin-dashboard">
        <div class="portal-heading">
            <a class="admin-back-link" href="{{ route('admin.profit-distributions.index') }}">← {{ __('site.profit_distributions.back') }}</a>
            <span class="section-eyebrow">{{ __('site.profit_distributions.eyebrow') }}</span>
            <h1>{{ __('site.profit_distributions.create_title') }}</h1>
            <p>{{ __('site.profit_distributions.create_description') }}</p>
        </div>

        @if ($errors->any())
            <div class="portal-notice portal-notice-warning" role="alert"><div><h2>{{ __('site.profit_distributions.check_request') }}</h2><p>{{ $errors->first() }}</p></div></div>
        @endif

        <div class="portal-notice portal-notice-warning">
            <div><h2>{{ __('site.profit_distributions.calculation_title') }}</h2><p>{{ __('site.profit_distributions.calculation_notice') }}</p></div>
        </div>

        <form class="admin-project-form" action="{{ route('admin.profit-distributions.store') }}" method="post">
            @csrf
            <section class="admin-form-section">
                <div class="admin-form-heading"><span class="section-eyebrow">01</span><div><h2>{{ __('site.profit_distributions.period_setup') }}</h2><p>{{ __('site.profit_distributions.period_help') }}</p></div></div>
                <div class="admin-form-grid">
                    <label class="admin-form-field admin-form-field-wide">
                        <span>{{ __('site.auth.project') }} <b>*</b></span>
                        <select name="project_id" required>
                            <option value="">{{ __('site.profit_distributions.choose_project') }}</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}" @selected((string) old('project_id') === (string) $project->id)>{{ $project->displayName() }}</option>
                            @endforeach
                        </select>
                        @error('project_id')<small class="auth-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="admin-form-field">
                        <span>{{ __('site.profit_distributions.period_start') }} <b>*</b></span>
                        <input type="date" name="period_start" value="{{ old('period_start', $defaultStart) }}" required>
                        @error('period_start')<small class="auth-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="admin-form-field">
                        <span>{{ __('site.profit_distributions.period_end') }} <b>*</b></span>
                        <input type="date" name="period_end" value="{{ old('period_end', $defaultEnd) }}" max="{{ today()->toDateString() }}" required>
                        @error('period_end')<small class="auth-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="admin-form-field">
                        <span>{{ __('site.profit_distributions.currency') }} <b>*</b></span>
                        <select name="payout_currency" required>
                            <option value="BDT" @selected(old('payout_currency', 'BDT') === 'BDT')>BDT</option>
                            <option value="USDT" @disabled($exchangeRate === null || (float) $exchangeRate <= 0) @selected(old('payout_currency') === 'USDT')>USDT</option>
                        </select>
                        @error('payout_currency')<small class="auth-error">{{ $message }}</small>@enderror
                        @if ($exchangeRate === null || (float) $exchangeRate <= 0)
                            <small>{{ __('site.profit_distributions.usdt_disabled') }}</small>
                        @endif
                    </label>
                </div>
                @if ($projects->isEmpty())
                    <p class="portal-empty">{{ __('site.profit_distributions.no_projects') }}</p>
                @endif
            </section>
            <div class="admin-form-actions">
                <button class="button button-dark" type="submit" @disabled($projects->isEmpty())>{{ __('site.profit_distributions.create_draft') }} <span aria-hidden="true">→</span></button>
            </div>
        </form>
    </section>
@endsection
