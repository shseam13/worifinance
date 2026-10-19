@extends('layouts.app')

@section('title', __('site.site_name').' — '.__('site.hero.title_before'))

@section('content')
    @php($spotlightProject = $projects->first())

    <div class="landing-page">
        <section class="landing-hero" aria-labelledby="landing-title">
            <div class="landing-hero-slides" data-hero-slider aria-label="{{ __('site.landing.carousel_label') }}">
                @forelse ($projects as $heroProject)
                    <div class="landing-hero-slide {{ $loop->first ? 'is-active' : '' }}" data-hero-slide role="group" aria-roledescription="slide" aria-label="{{ $loop->iteration }} / {{ $projects->count() }}: {{ $heroProject->displayName() }}" aria-hidden="{{ $loop->first ? 'false' : 'true' }}">
                        <img
                            class="landing-hero-image"
                            src="{{ $heroProject->primaryImage?->url() ?? asset('images/projects/urban-commercial.svg') }}"
                            alt="{{ $heroProject->displayName() }}"
                            @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif
                        >
                    </div>
                @empty
                    <div class="landing-hero-slide is-active" data-hero-slide role="group" aria-roledescription="slide" aria-label="{{ __('site.hero.image_alt') }}" aria-hidden="false">
                        <img class="landing-hero-image" src="{{ asset('images/projects/urban-commercial.svg') }}" alt="{{ __('site.hero.image_alt') }}" fetchpriority="high">
                    </div>
                @endforelse
            </div>
            <div class="landing-hero-shade" aria-hidden="true"></div>
            <div class="landing-hero-content">
                <span class="landing-kicker"><span></span>{{ __('site.hero.eyebrow') }}</span>
                <h1 id="landing-title">
                    {{ __('site.hero.title_before') }}<br>
                    <span>{{ __('site.hero.title_accent') }}</span><br>
                    {{ __('site.hero.title_after') }}
                </h1>
                <p>{{ __('site.hero.description') }}</p>
                <div class="landing-hero-actions">
                    <a class="landing-button landing-button-primary" href="{{ route('projects.index') }}">
                        {{ __('site.actions.explore') }} <span aria-hidden="true">↗</span>
                    </a>
                    @guest
                        <a class="landing-button landing-button-glass" href="{{ route('register') }}">{{ __('site.auth.register') }}</a>
                    @else
                        <a class="landing-button landing-button-glass" href="{{ auth()->user()->role === 'super_admin' ? route('admin.dashboard') : route('account.dashboard') }}">{{ __('site.auth.dashboard') }}</a>
                    @endguest
                </div>
                <div class="landing-hero-note">
                    <span class="landing-check" aria-hidden="true">✓</span>
                    <span>{{ __('site.hero.trust') }}</span>
                </div>
                @if ($projects->count() > 1)
                    <div class="landing-hero-controls" aria-label="{{ __('site.landing.carousel_label') }}">
                        <div class="landing-hero-dots">
                            @foreach ($projects as $heroProject)
                                <button
                                    class="landing-hero-dot {{ $loop->first ? 'is-active' : '' }}"
                                    type="button"
                                    data-hero-slide-to="{{ $loop->index }}"
                                    aria-label="{{ __('site.landing.slide_label', ['number' => $loop->iteration]) }}"
                                    aria-pressed="{{ $loop->first ? 'true' : 'false' }}"
                                ></button>
                            @endforeach
                        </div>
                        <button class="landing-hero-toggle" type="button" data-hero-toggle data-pause-label="{{ __('site.landing.pause_slides') }}" data-resume-label="{{ __('site.landing.resume_slides') }}" aria-label="{{ __('site.landing.pause_slides') }}" aria-pressed="false">
                            <span aria-hidden="true">Ⅱ</span>
                        </button>
                    </div>
                @endif
            </div>
            <div class="landing-hero-stats" aria-label="{{ __('site.preview.title') }}">
                <div>
                    <span class="landing-stat-icon" aria-hidden="true">↗</span>
                    <strong>{{ $projectCount }}</strong>
                    <small>{{ __('site.landing.published_count', ['count' => $projectCount]) }}</small>
                </div>
                <div>
                    <span class="landing-stat-icon" aria-hidden="true">⌂</span>
                    <strong>{{ __('site.projects.profile') }}</strong>
                    <small>{{ __('site.landing.platform_point_one') }}</small>
                </div>
                <div>
                    <span class="landing-stat-icon" aria-hidden="true">◈</span>
                    <strong>{{ __('site.clarity.terms_title') }}</strong>
                    <small>{{ __('site.landing.platform_point_two') }}</small>
                </div>
            </div>
            <div class="landing-hero-caption">{{ __('site.hero.caption') }}</div>
        </section>

        @if ($spotlightProject)
            <section class="landing-spotlight" aria-labelledby="spotlight-title">
                <div class="landing-spotlight-heading">
                    <span class="landing-eyebrow">{{ __('site.landing.showcase_label') }}</span>
                    <h2 id="spotlight-title">{{ __('site.landing.spotlight_title') }}</h2>
                    <p>{{ __('site.landing.spotlight_description') }}</p>
                </div>
                <article class="landing-spotlight-card">
                    <div class="landing-spotlight-image">
                        <a href="{{ route('projects.show', $spotlightProject) }}" aria-label="{{ __('site.landing.view_project') }}: {{ $spotlightProject->displayName() }}">
                            <img
                                src="{{ $spotlightProject->primaryImage?->url() ?? asset('images/projects/coastal-residences.svg') }}"
                                alt="{{ $spotlightProject->displayName() }}"
                                loading="lazy"
                            >
                        </a>
                        <span class="landing-image-badge">{{ __('site.landing.showcase_label') }}</span>
                    </div>
                    <div class="landing-spotlight-copy">
                        <div class="landing-spotlight-tags">
                            <span>{{ __('site.projects.profile') }}</span>
                            <span>{{ $spotlightProject->displayCategory() }}</span>
                        </div>
                        <h3>{{ $spotlightProject->displayName() }}</h3>
                        <p class="landing-spotlight-location">{{ $spotlightProject->displayLocation() }}</p>
                        <p class="landing-spotlight-summary">{{ $spotlightProject->displaySummary() ?: __('site.landing.spotlight_description') }}</p>
                        <div class="landing-spotlight-metrics">
                            <div>
                                <span>{{ __('site.projects.unit_price') }}</span>
                                <strong>
                                    @if ($spotlightProject->currentTerms)
                                        @money($spotlightProject->currentTerms->unit_price_bdt, 'BDT')
                                    @else
                                        {{ __('site.landing.terms_pending') }}
                                    @endif
                                </strong>
                            </div>
                            <div>
                                <span>{{ __('site.landing.availability') }}</span>
                                <strong>{{ __('site.projects.units_available', ['count' => $spotlightProject->available_units]) }}</strong>
                            </div>
                            @if ($spotlightProject->displayDeveloper())
                                <div>
                                    <span>{{ __('site.landing.developer_label') }}</span>
                                    <strong>{{ $spotlightProject->displayDeveloper() }}</strong>
                                </div>
                            @endif
                            @if ($spotlightProject->expected_handover_at)
                                <div>
                                    <span>{{ __('site.landing.handover_label') }}</span>
                                    <strong>{{ $spotlightProject->expected_handover_at->translatedFormat('M Y') }}</strong>
                                </div>
                            @endif
                        </div>
                        @if ($spotlightProject->currentTerms?->estimated_return_min_percent !== null || $spotlightProject->currentTerms?->estimated_return_max_percent !== null)
                            <p class="landing-spotlight-return">
                                {{ __('site.landing.published_return') }}:
                                <strong>
                                    {{ $spotlightProject->currentTerms->estimated_return_min_percent ?? '—' }}%–{{ $spotlightProject->currentTerms->estimated_return_max_percent ?? '—' }}%
                                </strong>
                            </p>
                        @endif
                        <a class="landing-spotlight-action" href="{{ route('projects.show', $spotlightProject) }}">{{ __('site.landing.view_project') }} <span aria-hidden="true">↗</span></a>
                    </div>
                </article>
            </section>
        @endif

        <section class="landing-platform" aria-labelledby="platform-title">
            <div class="landing-platform-copy">
                <span class="landing-eyebrow">{{ __('site.landing.platform_label') }}</span>
                <h2 id="platform-title">{{ __('site.landing.platform_title') }}</h2>
                <p>{{ __('site.landing.platform_description') }}</p>
                <ul class="landing-check-list">
                    <li><span aria-hidden="true">✓</span>{{ __('site.landing.platform_point_one') }}</li>
                    <li><span aria-hidden="true">✓</span>{{ __('site.landing.platform_point_two') }}</li>
                    <li><span aria-hidden="true">✓</span>{{ __('site.landing.platform_point_three') }}</li>
                </ul>
                <div class="landing-mini-stats">
                    <div><strong>{{ $projectCount }}</strong><span>{{ __('site.projects.profile') }}</span></div>
                    <div><strong>{{ __('site.clarity.terms_title') }}</strong><span>{{ __('site.preview.units_label') }}</span></div>
                    <div><strong>{{ __('site.auth.dashboard') }}</strong><span>{{ __('site.landing.account_label') }}</span></div>
                </div>
            </div>
            <div class="landing-device-scene" aria-label="{{ __('site.landing.platform_title') }}">
                <div class="landing-device-orbit landing-device-orbit-one"></div>
                <div class="landing-device-orbit landing-device-orbit-two"></div>
                <div class="landing-device">
                    <div class="landing-device-notch"></div>
                    <div class="landing-device-screen">
                        <div class="landing-device-top"><span>Wori<span>finance</span></span><i></i></div>
                        <div class="landing-device-welcome">{{ __('site.auth.welcome') }}</div>
                        <div class="landing-device-property">
                            <img src="{{ $spotlightProject?->primaryImage?->url() ?? asset('images/projects/greenfields-land.svg') }}" alt="" loading="lazy">
                            <span>{{ $spotlightProject?->displayName() ?? __('site.projects.profile') }}</span>
                        </div>
                        <div class="landing-device-lines"><i></i><i></i><i></i></div>
                        <div class="landing-device-action">{{ __('site.landing.view_project') }} <span>↗</span></div>
                    </div>
                </div>
                <span class="landing-device-leaf landing-device-leaf-one" aria-hidden="true">✳</span>
                <span class="landing-device-leaf landing-device-leaf-two" aria-hidden="true">✳</span>
            </div>
        </section>

        <section class="landing-projects" id="featured-projects" aria-labelledby="featured-title">
            <div class="landing-section-heading">
                <span class="landing-eyebrow">{{ __('site.projects.eyebrow') }}</span>
                <h2 id="featured-title">{{ __('site.projects.title') }}</h2>
                <p>{{ __('site.projects.description') }}</p>
            </div>
            @if ($projects->isNotEmpty())
                <div class="landing-project-grid">
                    @foreach ($projects as $project)
                        <x-project-card :project="$project" />
                    @endforeach
                </div>
                <a class="landing-button landing-button-outline landing-projects-more" href="{{ route('projects.index') }}">{{ __('site.projects.all') }} <span aria-hidden="true">↗</span></a>
            @else
                <div class="landing-project-empty">
                    <span aria-hidden="true">⌂</span>
                    <h3>{{ __('site.projects.empty_title') }}</h3>
                    <p>{{ __('site.projects.empty_description') }}</p>
                </div>
            @endif
            <p class="landing-risk-note">{{ __('site.landing.risk_notice') }}</p>
        </section>

        <section class="landing-benefits" aria-labelledby="benefits-title">
            <div class="landing-section-heading">
                <span class="landing-eyebrow">{{ __('site.landing.features_label') }}</span>
                <h2 id="benefits-title">{{ __('site.landing.features_title') }}</h2>
                <p>{{ __('site.landing.features_description') }}</p>
            </div>
            <div class="landing-benefit-grid">
                @foreach (range(1, 4) as $number)
                    <article class="landing-benefit-card">
                        <span class="landing-benefit-icon" aria-hidden="true">{{ ['⌂', '◈', '◎', '✓'][$number - 1] }}</span>
                        <h3>{{ __("site.landing.benefit_{$number}_title") }}</h3>
                        <p>{{ __("site.landing.benefit_{$number}_description") }}</p>
                    </article>
                @endforeach
            </div>
            <div class="landing-trust-strip">
                <div><strong>{{ __('site.landing.features_title') }}</strong><span>{{ __('site.landing.features_description') }}</span></div>
                <a class="landing-button landing-button-primary" href="{{ route('projects.index') }}">{{ __('site.actions.explore') }} <span aria-hidden="true">↗</span></a>
            </div>
        </section>

        <section class="landing-process" id="how-it-works" aria-labelledby="steps-title">
            <div class="landing-process-copy">
                <span class="landing-eyebrow">{{ __('site.landing.steps_label') }}</span>
                <h2 id="steps-title">{{ __('site.landing.steps_title') }}</h2>
                <p>{{ __('site.landing.steps_description') }}</p>
                <ol class="landing-step-list">
                    @foreach (range(1, 3) as $number)
                        <li>
                            <span class="landing-step-number">0{{ $number }}</span>
                            <div><h3>{{ __("site.landing.step_{$number}_title") }}</h3><p>{{ __("site.landing.step_{$number}_description") }}</p></div>
                        </li>
                    @endforeach
                </ol>
                <a class="landing-button landing-button-primary" href="{{ route('projects.index') }}">{{ __('site.actions.explore') }} <span aria-hidden="true">↗</span></a>
            </div>
            <div class="landing-process-art">
                <div class="landing-process-image">
                    <img src="{{ $spotlightProject?->primaryImage?->url() ?? asset('images/projects/urban-commercial.svg') }}" alt="{{ __('site.hero.image_alt') }}" loading="lazy">
                    <div class="landing-process-card"><span>✓</span><div><strong>{{ __('site.projects.profile') }}</strong><small>{{ __('site.landing.platform_point_two') }}</small></div></div>
                </div>
            </div>
        </section>

        <section class="landing-account" aria-labelledby="account-title">
            <div class="landing-account-copy">
                <span class="landing-eyebrow">{{ __('site.landing.account_label') }}</span>
                <h2 id="account-title">{{ __('site.landing.account_title') }}</h2>
                <p>{{ __('site.landing.account_description') }}</p>
                @guest
                    <a class="landing-button landing-button-light" href="{{ route('register') }}">{{ __('site.auth.register') }} <span aria-hidden="true">↗</span></a>
                @else
                    <a class="landing-button landing-button-light" href="{{ auth()->user()->role === 'super_admin' ? route('admin.dashboard') : route('account.dashboard') }}">{{ __('site.auth.dashboard') }} <span aria-hidden="true">↗</span></a>
                @endguest
            </div>
            <ul class="landing-account-list">
                @foreach (range(1, 4) as $number)
                    <li><span aria-hidden="true">✓</span>{{ __("site.landing.account_point_".['one', 'two', 'three', 'four'][$number - 1]) }}</li>
                @endforeach
            </ul>
        </section>

        <section class="landing-about" id="about-worifinance" aria-labelledby="about-title">
            <div class="landing-about-visual">
                <img src="{{ asset('images/projects/coastal-residences.svg') }}" alt="{{ __('site.hero.image_alt') }}" loading="lazy">
                <span class="landing-about-mark"><img src="{{ asset('images/worifinance.jpg') }}" alt="" loading="lazy"></span>
            </div>
            <div class="landing-about-copy">
                <span class="landing-eyebrow">{{ __('site.landing.about_label') }}</span>
                <h2 id="about-title">{{ __('site.landing.about_title') }}</h2>
                <p>{{ __('site.landing.about_description') }}</p>
                <a class="landing-text-link" href="{{ route('projects.index') }}">{{ __('site.actions.explore') }} <span aria-hidden="true">↗</span></a>
            </div>
        </section>

        <section class="landing-types" aria-labelledby="types-title">
            <div class="landing-section-heading">
                <span class="landing-eyebrow">{{ __('site.landing.types_label') }}</span>
                <h2 id="types-title">{{ __('site.landing.types_title') }}</h2>
                <p>{{ __('site.landing.types_description') }}</p>
            </div>
            <div class="landing-type-grid">
                @foreach (['residential', 'hospitality', 'commercial', 'land'] as $category)
                    <a class="landing-type-card" href="{{ route('projects.index') }}">
                        <span class="landing-type-icon" aria-hidden="true">{{ ['⌂', '✦', '▥', '⌁'][$loop->index] }}</span>
                        <strong>{{ __("site.landing.type_{$category}") }}</strong>
                        <span aria-hidden="true">↗</span>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="landing-ready">
            <div>
                <span class="landing-eyebrow">{{ __('site.landing.ready_title') }}</span>
                <h2>{{ __('site.landing.ready_title') }}</h2>
                <p>{{ __('site.landing.ready_description') }}</p>
            </div>
            <a class="landing-button landing-button-light" href="{{ route('projects.index') }}">{{ __('site.landing.ready_action') }} <span aria-hidden="true">↗</span></a>
        </section>
    </div>
@endsection
