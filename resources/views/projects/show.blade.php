@extends('layouts.app')

@section('title', $project->displayName())
@section('meta_description', $project->displaySummary() ?? __('site.meta_description'))

@section('content')
    <section class="detail-hero">
        <div class="detail-breadcrumb"><a href="{{ route('home') }}">{{ __('site.nav.home') }}</a><span aria-hidden="true">/</span><a href="{{ route('projects.index') }}">{{ __('site.nav.projects') }}</a><span aria-hidden="true">/</span><span>{{ $project->displayName() }}</span></div>
        <div class="detail-heading">
            <div>
                <span class="eyebrow"><span class="eyebrow-dot"></span>{{ __('site.projects.profile') }}</span>
                <h1>{{ $project->displayName() }}</h1>
                <p class="detail-location">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                    {{ $project->displayLocation() }}
                </p>
            </div>
            @if ($project->is_sample)
                <span class="project-tag project-tag-static">{{ __('site.projects.sample') }}</span>
            @endif
        </div>
    </section>
    <section class="detail-layout">
        <div class="detail-main">
            <div class="detail-gallery" data-project-gallery>
                @php
                    $galleryImages = $project->images;
                    $coverImage = $galleryImages->first();
                    $coverUrl = $coverImage?->url() ?? asset('images/projects/coastal-residences.svg');
                    $coverAlt = $coverImage?->alt_text ?? $project->displayName();
                @endphp
                <div class="gallery-stage">
                    <button class="gallery-image-trigger" type="button" data-gallery-open aria-label="{{ __('site.projects.open_image') }}">
                        <img src="{{ $coverUrl }}" alt="{{ $coverAlt }}" data-gallery-main>
                    </button>
                    @if ($galleryImages->count() > 1)
                        <button class="gallery-arrow gallery-arrow-previous" type="button" data-gallery-previous aria-label="{{ __('site.projects.previous_image') }}">←</button>
                        <button class="gallery-arrow gallery-arrow-next" type="button" data-gallery-next aria-label="{{ __('site.projects.next_image') }}">→</button>
                    @endif
                </div>
                @if ($galleryImages->count() > 1)
                    <div class="gallery-thumbnails" role="group" aria-label="{{ __('site.projects.gallery_thumbnails') }}">
                        @foreach ($galleryImages as $index => $image)
                            <button class="gallery-thumbnail @if ($index === 0) is-active @endif" type="button" data-gallery-thumbnail="{{ $index }}" aria-label="{{ __('site.projects.select_image', ['number' => $index + 1]) }}" aria-pressed="{{ $index === 0 ? 'true' : 'false' }}">
                                <img src="{{ $image->url() }}" alt="" loading="lazy">
                            </button>
                        @endforeach
                    </div>
                @endif
                <dialog class="image-lightbox" data-gallery-lightbox aria-label="{{ __('site.projects.image_viewer') }}">
                    <button class="lightbox-close" type="button" data-gallery-close aria-label="{{ __('site.projects.close_viewer') }}">×</button>
                    @if ($galleryImages->count() > 1)
                        <button class="gallery-arrow lightbox-arrow lightbox-arrow-previous" type="button" data-gallery-previous aria-label="{{ __('site.projects.previous_image') }}">←</button>
                    @endif
                    <img src="{{ $coverUrl }}" alt="{{ $coverAlt }}" data-lightbox-image>
                    @if ($galleryImages->count() > 1)
                        <button class="gallery-arrow lightbox-arrow lightbox-arrow-next" type="button" data-gallery-next aria-label="{{ __('site.projects.next_image') }}">→</button>
                    @endif
                    <p class="lightbox-counter" data-gallery-counter aria-live="polite">{{ $galleryImages->isEmpty() ? 1 : 1 }} / {{ max($galleryImages->count(), 1) }}</p>
                </dialog>
                <script type="application/json" data-gallery-images>@json($galleryImages->map(fn ($image) => ['src' => $image->url(), 'alt' => $image->alt_text ?? $project->displayName()])->values())</script>
            </div>
            @if ($project->is_sample)
                <div class="sample-project-notice">{{ __('site.projects.sample_notice') }}</div>
            @endif
            <section class="detail-copy">
                <span class="eyebrow eyebrow-muted">{{ __('site.projects.overview') }}</span>
                <h2>{{ __('site.projects.profile') }}</h2>
                <p>{{ $project->displayDescription() ?? $project->displaySummary() }}</p>
            </section>
            <section class="detail-documents">
                <span class="eyebrow eyebrow-muted">{{ __('site.projects.documents') }}</span>
                <p>{{ __('site.projects.documents_notice') }}</p>
            </section>
        </div>
        <aside class="detail-sidebar">
            <div class="terms-card">
                <span class="eyebrow eyebrow-muted">{{ __('site.projects.investment_terms') }}</span>
                <h2>{{ $project->displayType() }}</h2>
                <dl class="terms-list">
                    <div><dt>{{ __('site.projects.location') }}</dt><dd>{{ $project->displayLocation() }}</dd></div>
                    <div><dt>{{ __('site.projects.project_type') }}</dt><dd>{{ $project->displayType() }}</dd></div>
                    <div><dt>{{ __('site.projects.developer') }}</dt><dd>{{ $project->displayDeveloper() ?? '—' }}</dd></div>
                    @if ($project->expected_handover_at)
                        <div><dt>{{ __('site.projects.handover') }}</dt><dd>{{ $project->expected_handover_at->format('d M Y') }}</dd></div>
                    @endif
                    @if ($project->currentTerms)
                        <div><dt>{{ __('site.projects.unit_price') }}</dt><dd>@money($project->currentTerms->unit_price_bdt, 'BDT')</dd></div>
                        @if ($project->currentTerms->estimated_return_min_percent !== null && $project->currentTerms->estimated_return_max_percent !== null)
                            <div><dt>{{ __('site.projects.return_range') }}</dt><dd>{{ number_format((float) $project->currentTerms->estimated_return_min_percent, 2) }}%–{{ number_format((float) $project->currentTerms->estimated_return_max_percent, 2) }}%</dd></div>
                        @else
                            <div><dt>{{ __('site.projects.return_range') }}</dt><dd>{{ __('site.projects.return_not_set') }}</dd></div>
                        @endif
                    @endif
                    <div><dt>{{ __('site.projects.units') }}</dt><dd>{{ number_format($project->available_units) }}</dd></div>
                </dl>
                @if ($project->currentTerms === null)
                    <div class="terms-notice">{{ __('site.projects.terms_notice') }}</div>
                @endif
                @if (! $project->is_sample && $project->available_units > 0 && $project->currentTerms?->annual_rate_percent !== null)
                    @auth
                        @if (auth()->user()->role === 'investor')
                            <a class="button button-dark button-full investment-cta" href="{{ route('account.projects.invest.create', $project) }}">{{ __('site.investments.invest_now') }} <span aria-hidden="true">→</span></a>
                        @endif
                    @else
                        <a class="button button-dark button-full investment-cta" href="{{ route('login') }}">{{ __('site.investments.login_to_invest') }} <span aria-hidden="true">→</span></a>
                    @endauth
                @endif
                <a class="button button-dark button-full" href="{{ route('projects.index') }}">{{ __('site.actions.back_projects') }} <span aria-hidden="true">↗</span></a>
            </div>
            <p class="detail-risk-note">{{ __('site.projects.not_advice') }}</p>
        </aside>
    </section>
@endsection
