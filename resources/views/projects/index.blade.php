@extends('layouts.app')

@section('title', __('site.nav.projects'))

@section('content')
    <section class="listing-hero">
        <span class="eyebrow"><span class="eyebrow-dot"></span>{{ __('site.projects.eyebrow') }}</span>
        <h1>{{ __('site.projects.title') }}</h1>
        <p>{{ __('site.projects.description') }}</p>
        <div class="listing-breadcrumb"><a href="{{ route('home') }}">{{ __('site.nav.home') }}</a><span aria-hidden="true">/</span><span>{{ __('site.nav.projects') }}</span></div>
    </section>
    <section class="section listing-section">
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
                <h2>{{ __('site.projects.empty_title') }}</h2>
                <p>{{ __('site.projects.empty_description') }}</p>
            </div>
        @endif
        <p class="sample-disclaimer"><span aria-hidden="true">ⓘ</span> {{ __('site.projects.not_advice') }}</p>
    </section>
@endsection
