@extends('layouts.admin')

@section('title', __('site.admin_projects.title'))
@section('admin_breadcrumb', __('site.admin_projects.title'))

@section('content')
    <section class="admin-dashboard">
        <div class="portal-heading portal-heading-row">
            <div>
                <span class="section-eyebrow">{{ __('site.admin_projects.eyebrow') }}</span>
                <h1>{{ __('site.admin_projects.title') }}</h1>
                <p>{{ __('site.admin_projects.catalogue_description') }}</p>
            </div>
            <a class="button button-dark" href="{{ route('admin.projects.create') }}">{{ __('site.admin_projects.create') }} <span aria-hidden="true">+</span></a>
        </div>

        @if (session('status'))
            <div class="admin-flash" role="status">{{ session('status') }}</div>
        @endif
        @error('project')
            <div class="portal-notice portal-notice-warning" role="alert"><div><p>{{ $message }}</p></div></div>
        @enderror

        <div class="portal-notice portal-notice-warning">
            <div>
                <h2>{{ __('site.admin_projects.real_data_notice_title') }}</h2>
                <p>{{ __('site.admin_projects.real_data_notice') }}</p>
            </div>
        </div>

        <section class="demo-project-control">
            <div>
                <span class="section-eyebrow">{{ __('site.admin_projects.demo_label') }}</span>
                <h2>{{ __('site.admin_projects.demo_title') }}</h2>
                <p>{{ __('site.admin_projects.demo_description') }}</p>
                <small>{{ __('site.admin_projects.demo_terms_notice') }}</small>
            </div>
            <form action="{{ route('admin.projects.demo-projects') }}" method="post">
                @csrf
                <input type="hidden" name="enabled" value="{{ $demoProjectsEnabled ? '0' : '1' }}">
                <button class="button {{ $demoProjectsEnabled ? 'button-outline' : 'button-dark' }}" type="submit">
                    {{ $demoProjectsEnabled ? __('site.admin_projects.hide_demos') : __('site.admin_projects.enable_demos') }}
                </button>
            </form>
        </section>

        <section class="portal-panel">
            <div class="portal-panel-heading">
                <div>
                    <span class="section-eyebrow">{{ __('site.admin_projects.categories_eyebrow') }}</span>
                    <h2>{{ __('site.admin_projects.categories_title') }}</h2>
                    <p>{{ __('site.admin_projects.categories_help') }}</p>
                </div>
            </div>
            @error('category')<div class="portal-notice portal-notice-warning" role="alert"><div><p>{{ $message }}</p></div></div>@enderror
            @error('name')<div class="portal-notice portal-notice-warning" role="alert"><div><p>{{ $message }}</p></div></div>@enderror
            @error('name_bn')<div class="portal-notice portal-notice-warning" role="alert"><div><p>{{ $message }}</p></div></div>@enderror
            <form class="admin-filter-form" action="{{ route('admin.projects.categories.store') }}" method="post">
                @csrf
                <label class="admin-search-field">
                    <span>{{ __('site.admin_projects.category_name') }}</span>
                    <input name="name" maxlength="80" value="{{ old('name') }}" required>
                </label>
                <label class="admin-search-field">
                    <span>{{ __('site.admin_projects.category_name_bn') }}</span>
                    <input name="name_bn" maxlength="80" value="{{ old('name_bn') }}" lang="bn">
                </label>
                <button class="button button-dark button-small" type="submit">{{ __('site.admin_projects.add_category') }}</button>
            </form>
            <div class="admin-category-list">
                @foreach ($categories as $category)
                    <div class="admin-category-item">
                        <span>{{ $category->displayName() }}@if ($category->name_bn)<small>{{ app()->getLocale() === 'bn' ? $category->name : $category->name_bn }}</small>@endif</span>
                        <form action="{{ route('admin.projects.categories.destroy', $category) }}" method="post">
                            @csrf
                            @method('delete')
                            <button class="button button-outline button-small" type="submit">{{ __('site.admin_projects.delete_category') }}</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="portal-panel">
            <form class="admin-filter-form" action="{{ route('admin.projects.index') }}" method="get">
                <label class="admin-search-field">
                    <span>{{ __('site.admin_projects.search') }}</span>
                    <input type="search" name="search" value="{{ $search }}" placeholder="{{ __('site.admin_projects.search_placeholder') }}">
                </label>
                <label class="admin-search-field">
                    <span>{{ __('site.admin_projects.status') }}</span>
                    <select name="status">
                        <option value="">{{ __('site.admin_projects.all_statuses') }}</option>
                        @foreach (['draft', 'published', 'archived'] as $projectStatus)
                            <option value="{{ $projectStatus }}" @selected($status === $projectStatus)>{{ __('site.auth.statuses.'.$projectStatus) }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="button button-dark button-small" type="submit">{{ __('site.admin_projects.filter') }}</button>
                @if ($search !== '' || $status !== '')
                    <a class="button button-outline button-small" href="{{ route('admin.projects.index') }}">{{ __('site.admin_projects.clear_filters') }}</a>
                @endif
            </form>

            @if ($projects->isEmpty())
                <div class="admin-empty-state">
                    <h2>{{ __('site.admin_projects.empty_title') }}</h2>
                    <p>{{ __('site.admin_projects.empty_description') }}</p>
                    <a class="button button-dark" href="{{ route('admin.projects.create') }}">{{ __('site.admin_projects.create') }} <span aria-hidden="true">+</span></a>
                </div>
            @else
                <div class="admin-project-grid">
                    @foreach ($projects as $project)
                        <article class="admin-project-card">
                            <a class="admin-project-image" href="{{ route('admin.projects.edit', $project) }}">
                                @if ($project->primaryImage)
                                    <img src="{{ $project->primaryImage->url() }}" alt="{{ $project->primaryImage->alt_text ?? $project->name }}" loading="lazy">
                                @else
                                    <span class="admin-project-no-image">{{ __('site.admin_projects.no_cover') }}</span>
                                @endif
                                <span class="status-pill status-{{ \Illuminate\Support\Str::slug($project->status) }}">{{ __('site.auth.statuses.'.$project->status) }}</span>
                                @if ($project->is_sample)
                                    <span class="status-pill admin-sample-label">{{ __('site.projects.sample') }} · {{ __('site.admin_projects.read_only') }}</span>
                                @endif
                            </a>
                            <div class="admin-project-card-content">
                                <div>
                                    @if ($project->displayCategory() || $project->displayLocation())
                                        <span class="admin-project-category">{{ collect([$project->displayCategory(), $project->displayLocation()])->filter()->implode(' · ') }}</span>
                                    @endif
                                    <h2>{{ $project->displayName() }}</h2>
                                    <p>{{ $project->displaySummary() }}</p>
                                </div>
                                <div class="admin-project-card-footer">
                                    <small>{{ trans_choice('site.admin_projects.terms_count', $project->terms_count, ['count' => $project->terms_count]) }}</small>
                                    <div class="admin-project-actions">
                                        <a class="text-link" href="{{ route('admin.projects.edit', $project) }}">{{ $project->is_sample ? __('site.admin_projects.read_only') : __('site.admin_projects.edit') }} <span aria-hidden="true">→</span></a>
                                        <form action="{{ route('admin.projects.destroy', $project) }}" method="post" onsubmit="return confirm(@js(__('site.admin_projects.confirm_delete')))">
                                            @csrf
                                            @method('delete')
                                            <button class="button button-outline button-small admin-delete-button" type="submit">{{ __('site.admin_projects.delete_project') }}</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
                <div class="admin-pagination">{{ $projects->links() }}</div>
            @endif
        </section>
    </section>
@endsection
