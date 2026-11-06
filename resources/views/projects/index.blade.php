@extends('layouts.app')

@section('title', __('site.nav.projects'))

@section('content')
    <section class="listing-hero" @if ($heroImageUrl) style="--listing-hero-image: url('{{ $heroImageUrl }}')" @endif>
        <span class="eyebrow"><span class="eyebrow-dot"></span>{{ __('site.projects.eyebrow') }}</span>
        <h1>{{ __('site.projects.title') }}</h1>
        <p>{{ __('site.projects.description') }}</p>
        <div class="listing-breadcrumb"><a href="{{ route('home') }}">{{ __('site.nav.home') }}</a><span aria-hidden="true">/</span><span>{{ __('site.nav.projects') }}</span></div>
    </section>
    <section class="section listing-section">
        <form class="project-filters" action="{{ route('projects.index') }}" method="get" aria-label="{{ __('site.projects.filters_label') }}">
            <label class="project-filter-field project-search-field">
                <span>{{ __('site.projects.search_label') }}</span>
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="{{ __('site.projects.search_placeholder') }}">
            </label>
            <label class="project-filter-field">
                <span>{{ __('site.projects.category_filter') }}</span>
                <select name="category">
                    <option value="">{{ __('site.projects.all_categories') }}</option>
                    @foreach ($categories as $value => $translationKey)
                        <option value="{{ $value }}" @selected(($filters['category'] ?? '') === $value)>{{ __($translationKey) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="project-filter-field">
                <span>{{ __('site.projects.budget_filter') }}</span>
                <select name="budget">
                    <option value="">{{ __('site.projects.all_budgets') }}</option>
                    @foreach ($budgetOptions as $value => $translationKey)
                        <option value="{{ $value }}" @selected(($filters['budget'] ?? '') === $value)>{{ __($translationKey) }}</option>
                    @endforeach
                </select>
            </label>
            <button class="button button-dark project-filter-submit" type="submit">{{ __('site.projects.apply_filters') }}</button>
            @if (filled($filters['q'] ?? null) || filled($filters['category'] ?? null) || filled($filters['budget'] ?? null))
                <a class="project-filter-clear" href="{{ route('projects.index') }}">{{ __('site.projects.clear_filters') }}</a>
            @endif
        </form>
        @if ($projects->isNotEmpty())
            <div class="project-grid">
                @foreach ($projects as $project)
                    <x-project-card :project="$project" />
                @endforeach
            </div>
            <div class="pagination-wrap">{{ $projects->links() }}</div>
        @else
            <div class="empty-state">
                <span class="empty-state-icon" aria-hidden="true">⌂</span>
                @if (filled($filters['q'] ?? null) || filled($filters['category'] ?? null) || filled($filters['budget'] ?? null))
                    <h2>{{ __('site.projects.no_results_title') }}</h2>
                    <p>{{ __('site.projects.no_results_description') }}</p>
                @else
                    <h2>{{ __('site.projects.empty_title') }}</h2>
                    <p>{{ __('site.projects.empty_description') }}</p>
                @endif
            </div>
        @endif
        <p class="sample-disclaimer"><span aria-hidden="true">ⓘ</span> {{ __('site.projects.not_advice') }}</p>
    </section>
@endsection
