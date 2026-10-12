@extends('layouts.app')

@section('title', __('site.hero.title_before').' '.__('site.hero.title_accent').' '.__('site.hero.title_after'))

@section('content')
    <section class="hero-section">
        <div class="hero-glow hero-glow-one" aria-hidden="true"></div>
        <div class="hero-glow hero-glow-two" aria-hidden="true"></div>
        <div class="hero-content">
            <span class="eyebrow"><span class="eyebrow-dot"></span>{{ __('site.hero.eyebrow') }}</span>
            <h1>{{ __('site.hero.title_before') }} <span>{{ __('site.hero.title_accent') }}</span> <em>{{ __('site.hero.title_after') }}</em></h1>
            <p class="hero-description">{{ __('site.hero.description') }}</p>
            <div class="hero-actions">
                <a class="button button-dark" href="{{ route('projects.index') }}">
                    {{ __('site.actions.explore') }}
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14m-7-7 7 7-7 7"/></svg>
                </a>
                <a class="text-link" href="#how-it-works">{{ __('site.actions.learn_more') }} <span aria-hidden="true">↘</span></a>
            </div>
            <div class="hero-proof">
                <span class="proof-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M12 3 4 6v5c0 5 3.5 8.5 8 10 4.5-1.5 8-5 8-10V6zm-3 9 2 2 4-4"/></svg>
                </span>
                <span>{{ __('site.hero.trust') }}</span>
            </div>
        </div>
        <div class="hero-visual">
            <div class="hero-art-frame">
                <img src="{{ asset('images/projects/coastal-residences.svg') }}" alt="{{ __('site.hero.image_alt') }}">
                <div class="hero-image-label">
                    <span class="hero-image-icon" aria-hidden="true">W</span>
                    <span><strong>{{ __('site.hero.card_title') }}</strong><small>{{ __('site.hero.card_description') }}</small></span>
                    <span class="hero-image-arrow" aria-hidden="true">↗</span>
                </div>
            </div>
            <span class="hero-image-caption"><span></span>{{ __('site.hero.caption') }}</span>
            <div class="floating-note">
                <span class="floating-note-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/><path d="M8 7h8m-8 4h6"/></svg>
                </span>
                <span><strong>{{ __('site.projects.profile') }}</strong><small>{{ __('site.hero.card_link') }}</small></span>
            </div>
        </div>
    </section>

    <section class="intro-strip" aria-label="{{ __('site.preview.title') }}">
        <div class="intro-copy">
            <span class="eyebrow eyebrow-muted">{{ __('site.preview.title') }}</span>
            <p>{{ __('site.preview.description') }}</p>
        </div>
        <div class="intro-facts">
            <div class="intro-fact">
                <span class="fact-icon fact-icon-green" aria-hidden="true">01</span>
                <span>{{ __('site.preview.projects_label') }}</span>
            </div>
            <div class="intro-fact">
                <span class="fact-icon fact-icon-blue" aria-hidden="true">{{ app(\App\Support\CurrencyFormatter::class)->displayCurrency() === 'USDT' ? '₮' : '৳' }}</span>
                <span>{{ __('site.preview.units_label') }}</span>
            </div>
            <div class="intro-fact">
                <span class="fact-icon fact-icon-lime" aria-hidden="true">↗</span>
                <span>{{ __('site.preview.currency_label') }}</span>
            </div>
        </div>
    </section>

    <section class="section section-projects" id="featured-projects">
        <div class="section-heading">
            <div>
                <span class="eyebrow eyebrow-muted">{{ __('site.projects.eyebrow') }}</span>
                <h2>{{ __('site.projects.title') }}</h2>
                <p>{{ __('site.projects.description') }}</p>
            </div>
            <a class="button button-outline desktop-only" href="{{ route('projects.index') }}">
                {{ __('site.projects.all') }} <span aria-hidden="true">↗</span>
            </a>
        </div>

        @if ($projects->isNotEmpty())
            <div class="project-grid">
                @foreach ($projects as $project)
                    <x-project-card :project="$project" />
                @endforeach
            </div>
            <div class="mobile-project-link">
                <a class="button button-outline" href="{{ route('projects.index') }}">{{ __('site.projects.all') }} <span aria-hidden="true">↗</span></a>
            </div>
        @else
            <div class="empty-state">
                <span class="empty-state-icon" aria-hidden="true">⌂</span>
                <h3>{{ __('site.projects.empty_title') }}</h3>
                <p>{{ __('site.projects.empty_description') }}</p>
            </div>
        @endif

        <p class="sample-disclaimer"><span aria-hidden="true">ⓘ</span> {{ __('site.footer.disclaimer') }}</p>
    </section>

    <section class="how-section" id="how-it-works">
        <div class="how-intro">
            <span class="eyebrow eyebrow-light">{{ __('site.how.eyebrow') }}</span>
            <h2>{{ __('site.how.title') }}</h2>
            <p>{{ __('site.how.description') }}</p>
            <a class="button button-light" href="{{ route('projects.index') }}">{{ __('site.actions.explore') }} <span aria-hidden="true">↗</span></a>
            <div class="how-decoration" aria-hidden="true">
                <span></span><span></span><span></span><span></span><span></span>
            </div>
        </div>
        <div class="steps-list">
            <article class="step-card">
                <span class="step-number">01</span>
                <div><h3>{{ __('site.how.step_one_title') }}</h3><p>{{ __('site.how.step_one_description') }}</p></div>
                <span class="step-arrow" aria-hidden="true">↗</span>
            </article>
            <article class="step-card">
                <span class="step-number">02</span>
                <div><h3>{{ __('site.how.step_two_title') }}</h3><p>{{ __('site.how.step_two_description') }}</p></div>
                <span class="step-arrow" aria-hidden="true">↗</span>
            </article>
            <article class="step-card">
                <span class="step-number">03</span>
                <div><h3>{{ __('site.how.step_three_title') }}</h3><p>{{ __('site.how.step_three_description') }}</p></div>
                <span class="step-arrow" aria-hidden="true">↗</span>
            </article>
        </div>
    </section>

    <section class="clarity-section" id="clarity">
        <div class="clarity-heading">
            <span class="eyebrow eyebrow-muted">{{ __('site.clarity.eyebrow') }}</span>
            <h2>{{ __('site.clarity.title') }}</h2>
            <p>{{ __('site.clarity.description') }}</p>
        </div>
        <div class="clarity-grid">
            <article class="clarity-card">
                <span class="clarity-icon clarity-icon-green" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M3 20V8l9-5 9 5v12M3 11h18M8 20v-5h8v5"/></svg>
                </span>
                <h3>{{ __('site.clarity.property_title') }}</h3>
                <p>{{ __('site.clarity.property_description') }}</p>
            </article>
            <article class="clarity-card">
                <span class="clarity-icon clarity-icon-blue" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="m12 3 8 4v5c0 4.5-3 7.5-8 9-5-1.5-8-4.5-8-9V7zm-3 9 2 2 4-4"/></svg>
                </span>
                <h3>{{ __('site.clarity.terms_title') }}</h3>
                <p>{{ __('site.clarity.terms_description') }}</p>
            </article>
            <article class="clarity-card">
                <span class="clarity-icon clarity-icon-yellow" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v18H6.5A2.5 2.5 0 0 1 4 18.5zm0 13A2.5 2.5 0 0 1 6.5 16H20M8 7h7m-7 4h5"/></svg>
                </span>
                <h3>{{ __('site.clarity.records_title') }}</h3>
                <p>{{ __('site.clarity.records_description') }}</p>
            </article>
        </div>
    </section>
@endsection
