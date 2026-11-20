@extends('layouts.app')

@section('title', __('site.about_page.hero_title'))
@section('meta_description', $homepage['about']['description'])

@section('content')
    <div class="landing-page about-page">
        <section class="about-hero" aria-labelledby="about-hero-title">
            <div class="about-hero-media" aria-hidden="true">
                <img src="{{ $heroImageUrl }}" alt="" fetchpriority="high">
            </div>
            <div class="about-hero-shade" aria-hidden="true"></div>
            <div class="about-hero-content">
                <span class="landing-kicker"><span></span>{{ __('site.about_page.hero_label') }}</span>
                <h1 id="about-hero-title">{{ __('site.about_page.hero_title') }}</h1>
                <p>{{ $homepage['about']['description'] }}</p>
            </div>
        </section>

        <div class="about-page-content">
            <section class="about-intro about-panel" aria-labelledby="about-intro-title">
                <div class="about-section-heading">
                    <span class="landing-eyebrow">{{ __('site.about_page.intro_label') }}</span>
                    <h2 id="about-intro-title">{{ $homepage['about']['title'] }}</h2>
                    <p>{{ __('site.about_page.intro_description') }}</p>
                </div>
                <div class="about-intro-grid">
                    <div class="about-intro-visual">
                        <img src="{{ $storyImageUrl }}" alt="{{ __('site.about_page.property_image_alt') }}" loading="lazy">
                        <span class="about-image-badge">
                            <img src="{{ asset('images/worifinance.jpg') }}" alt="" loading="lazy">
                        </span>
                    </div>
                    <div class="about-intro-copy">
                        <article class="about-company-card">
                            <img src="{{ asset('images/worifinance.jpg') }}" alt="" loading="lazy">
                            <div>
                                <h3>{{ __('site.about_page.company_card_title') }}</h3>
                                <p>{{ __('site.about_page.company_card_description') }}</p>
                            </div>
                        </article>
                        <div class="about-stat-grid">
                            <article>
                                <span class="about-stat-icon" aria-hidden="true">⌂</span>
                                <strong>{{ number_format($projectCount) }}</strong>
                                <span>{{ __('site.about_page.published_projects') }}</span>
                            </article>
                            <article>
                                <span class="about-stat-icon" aria-hidden="true">文</span>
                                <strong>{{ __('site.about_page.language_choice') }}</strong>
                                <span>{{ __('site.about_page.language_choice_description') }}</span>
                            </article>
                            <article>
                                <span class="about-stat-icon" aria-hidden="true">◈</span>
                                <strong>{{ __('site.about_page.project_terms') }}</strong>
                                <span>{{ __('site.about_page.project_terms_description') }}</span>
                            </article>
                        </div>
                    </div>
                </div>
            </section>

            <section class="about-projects" aria-labelledby="about-projects-title">
                <div class="about-section-heading">
                    <span class="landing-eyebrow">{{ __('site.about_page.projects_label') }}</span>
                    <h2 id="about-projects-title">{{ __('site.about_page.projects_title') }}</h2>
                    <p>{{ __('site.about_page.projects_description') }}</p>
                </div>
                @if ($projects->isNotEmpty())
                    <div class="landing-project-grid">
                        @foreach ($projects as $project)
                            <x-project-card :project="$project" />
                        @endforeach
                    </div>
                    <a class="landing-button landing-button-outline landing-projects-more" href="{{ route('projects.index') }}">
                        {{ __('site.actions.all_projects') }} <span aria-hidden="true">↗</span>
                    </a>
                @else
                    <div class="landing-project-empty">
                        <span aria-hidden="true">⌂</span>
                        <h3>{{ __('site.projects.empty_title') }}</h3>
                        <p>{{ __('site.projects.empty_description') }}</p>
                    </div>
                @endif
            </section>

            <section class="about-story about-panel" aria-labelledby="about-story-title">
                <div class="about-story-copy">
                    <span class="landing-eyebrow">{{ __('site.about_page.story_label') }}</span>
                    <h2 id="about-story-title">{{ __('site.about_page.story_title') }}</h2>
                    <p>{{ __('site.about_page.story_description') }}</p>
                    <a class="landing-button landing-button-primary" href="{{ route('projects.index') }}">
                        {{ __('site.actions.explore') }} <span aria-hidden="true">↗</span>
                    </a>
                </div>
                <figure class="about-story-visual">
                    <img src="{{ $storyImageUrl }}" alt="{{ __('site.about_page.story_image_alt') }}" loading="lazy">
                    <figcaption>{{ __('site.about_page.story_image_caption') }}</figcaption>
                </figure>
            </section>

            <section class="about-features about-section" aria-labelledby="about-features-title">
                <div class="about-section-heading">
                    <span class="landing-eyebrow">{{ __('site.about_page.features_label') }}</span>
                    <h2 id="about-features-title">{{ __('site.about_page.features_title') }}</h2>
                    <p>{{ __('site.about_page.features_description') }}</p>
                </div>
                <div class="about-feature-grid">
                    @foreach (['one', 'two', 'three', 'four'] as $index => $number)
                        <article class="about-feature-card">
                            <span class="landing-benefit-icon" aria-hidden="true">{{ ['⌂', '◈', '◎', '✓'][$index] }}</span>
                            <h3>{{ __("site.landing.benefit_{$number}_title") }}</h3>
                            <p>{{ __("site.landing.benefit_{$number}_description") }}</p>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="about-process about-panel" aria-labelledby="about-process-title">
                <div class="about-section-heading">
                    <span class="landing-eyebrow">{{ __('site.about_page.process_label') }}</span>
                    <h2 id="about-process-title">{{ __('site.about_page.process_title') }}</h2>
                    <p>{{ __('site.about_page.process_description') }}</p>
                </div>
                <ol class="about-step-grid">
                    @foreach (range(1, 4) as $step)
                        <li>
                            <span class="about-step-number">0{{ $step }}</span>
                            <h3>{{ __("site.about_page.step_{$step}_title") }}</h3>
                            <p>{{ __("site.about_page.step_{$step}_description") }}</p>
                        </li>
                    @endforeach
                </ol>
            </section>

            <section class="about-principles about-section" aria-labelledby="about-principles-title">
                <div class="about-section-heading">
                    <span class="landing-eyebrow">{{ __('site.about_page.principles_label') }}</span>
                    <h2 id="about-principles-title">{{ __('site.about_page.principles_title') }}</h2>
                </div>
                <div class="about-principle-grid">
                    @foreach (['one', 'two', 'three'] as $index => $number)
                        <article>
                            <span class="about-principle-icon" aria-hidden="true">{{ ['◉', '⌕', '✓'][$index] }}</span>
                            <h3>{{ __("site.about_page.principle_{$number}_title") }}</h3>
                            <p>{{ __("site.about_page.principle_{$number}_description") }}</p>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="about-choice about-panel" aria-labelledby="about-choice-title">
                <div class="about-section-heading">
                    <span class="landing-eyebrow">{{ __('site.about_page.choice_label') }}</span>
                    <h2 id="about-choice-title">{{ __('site.about_page.choice_title') }}</h2>
                </div>
                <div class="about-choice-grid">
                    @foreach (['one', 'two', 'three'] as $index => $number)
                        <article>
                            <span aria-hidden="true">{{ ['⌂', '◈', '◎'][$index] }}</span>
                            <h3>{{ __("site.about_page.choice_{$number}_title") }}</h3>
                            <p>{{ __("site.about_page.choice_{$number}_description") }}</p>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="about-cta" aria-labelledby="about-cta-title">
                <div>
                    <span class="landing-eyebrow">{{ __('site.about_page.cta_label') }}</span>
                    <h2 id="about-cta-title">{{ __('site.about_page.cta_title') }}</h2>
                    <p>{{ __('site.about_page.cta_description') }}</p>
                </div>
                <a class="landing-button landing-button-light" href="{{ route('projects.index') }}">
                    {{ __('site.actions.explore') }} <span aria-hidden="true">↗</span>
                </a>
            </section>
            <p class="about-risk-note">{{ __('site.landing.risk_notice') }}</p>
        </div>
    </div>
@endsection
