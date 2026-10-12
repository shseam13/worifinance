@props(['project'])

<article class="project-card">
    <a class="project-card-image" href="{{ route('projects.show', $project) }}" aria-label="{{ __('site.actions.details') }}: {{ $project->displayName() }}">
        <img src="{{ $project->primaryImage?->url() ?? asset('images/projects/coastal-residences.svg') }}" alt="{{ $project->displayName() }}" loading="lazy">
        @if ($project->is_sample)
            <span class="project-tag">{{ __('site.projects.sample') }}</span>
        @endif
        <span class="image-arrow" aria-hidden="true">↗</span>
    </a>
    <div class="project-card-content">
        <div class="project-card-location">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
            <span>{{ $project->displayLocation() }}</span>
            <span class="location-separator" aria-hidden="true">·</span>
            <span>{{ $project->displayCategory() }}</span>
        </div>
        <h3><a href="{{ route('projects.show', $project) }}">{{ $project->displayName() }}</a></h3>
        <p class="project-card-summary">{{ $project->displaySummary() }}</p>
        @if ($project->is_sample)
            <p class="project-sample-note">{{ __('site.projects.sample_notice') }}</p>
        @endif
        <div class="project-card-bottom">
            <div>
                <span class="field-label">{{ __('site.projects.unit_price') }}</span>
                @if ($project->currentTerms)
                    <span class="field-value">@money($project->currentTerms->unit_price_bdt, 'BDT') <small>/ {{ __('site.projects.units') }}</small></span>
                @else
                    <span class="field-value">{{ __('site.projects.return_not_set') }}</span>
                @endif
            </div>
            <a class="project-card-link" href="{{ route('projects.show', $project) }}" aria-label="{{ __('site.actions.details') }}: {{ $project->displayName() }}">
                <span aria-hidden="true">→</span>
            </a>
        </div>
    </div>
</article>
