@extends('layouts.admin')

@section('title', $editing ? __('site.admin_projects.edit_project') : __('site.admin_projects.create_project'))
@section('admin_breadcrumb', __('site.admin_projects.title'))

@section('content')
    <section class="admin-dashboard">
        <div class="portal-heading">
            <a class="admin-back-link" href="{{ route('admin.projects.index') }}">← {{ __('site.admin_projects.back_to_projects') }}</a>
            <span class="section-eyebrow">{{ __('site.admin_projects.eyebrow') }}</span>
            <h1>{{ $editing ? __('site.admin_projects.edit_project') : __('site.admin_projects.create_project') }}</h1>
            <p>{{ __('site.admin_projects.form_description') }}</p>
        </div>

        @if (session('status'))
            <div class="admin-flash" role="status">{{ session('status') }}</div>
        @endif
        @if ($project->is_sample)
            <div class="portal-notice portal-notice-warning"><div><h2>{{ __('site.admin_projects.sample_read_only') }}</h2><p>{{ __('site.admin_projects.sample_read_only_notice') }}</p></div></div>
        @else
            <form class="admin-project-form" action="{{ $editing ? route('admin.projects.update', $project) : route('admin.projects.store') }}" method="post" enctype="multipart/form-data">
                @csrf
                @if ($editing)
                    @method('put')
                @endif

                <section class="admin-form-section">
                    <div class="admin-form-heading">
                        <span class="section-eyebrow">01</span>
                        <div><h2>{{ __('site.admin_projects.property_information') }}</h2><p>{{ __('site.admin_projects.property_information_help') }}</p></div>
                    </div>
                    <div class="admin-form-grid">
                        <label class="admin-form-field">
                            <span>{{ __('site.admin_projects.project_name') }} <b>*</b></span>
                            <input name="name" value="{{ old('name', $project->name) }}" maxlength="255" required>
                            @error('name') <small class="auth-error">{{ $message }}</small> @enderror
                        </label>
                        <label class="admin-form-field">
                            <span>{{ __('site.admin_projects.project_name_bn') }}</span>
                            <input name="name_bn" value="{{ old('name_bn', $project->name_bn) }}" maxlength="255" lang="bn">
                            @error('name_bn') <small class="auth-error">{{ $message }}</small> @enderror
                        </label>
                        <label class="admin-form-field">
                            <span>{{ __('site.admin_projects.category') }} <b>*</b></span>
                            <select name="category" required>
                                @foreach ([
                                    'Residential' => __('site.categories.residential'),
                                    'Commercial' => __('site.categories.commercial'),
                                    'Hospitality' => __('site.categories.hospitality'),
                                    'Land' => __('site.categories.land'),
                                    'Mixed-use' => __('site.categories.mixed_use'),
                                ] as $category => $label)
                                    <option value="{{ $category }}" @selected(old('category', $project->category) === $category)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('category') <small class="auth-error">{{ $message }}</small> @enderror
                        </label>
                        <label class="admin-form-field">
                            <span>{{ __('site.admin_projects.project_type') }} <b>*</b></span>
                            <input name="project_type" value="{{ old('project_type', $project->project_type) }}" maxlength="80" required>
                            @error('project_type') <small class="auth-error">{{ $message }}</small> @enderror
                        </label>
                        <label class="admin-form-field">
                            <span>{{ __('site.admin_projects.location') }} <b>*</b></span>
                            <input name="location" value="{{ old('location', $project->location) }}" maxlength="255" required>
                            @error('location') <small class="auth-error">{{ $message }}</small> @enderror
                        </label>
                        <label class="admin-form-field">
                            <span>{{ __('site.admin_projects.location_bn') }}</span>
                            <input name="location_bn" value="{{ old('location_bn', $project->location_bn) }}" maxlength="255" lang="bn">
                            @error('location_bn') <small class="auth-error">{{ $message }}</small> @enderror
                        </label>
                        <label class="admin-form-field">
                            <span>{{ __('site.admin_projects.developer') }}</span>
                            <input name="developer" value="{{ old('developer', $project->developer) }}" maxlength="255">
                            @error('developer') <small class="auth-error">{{ $message }}</small> @enderror
                        </label>
                        <label class="admin-form-field">
                            <span>{{ __('site.admin_projects.developer_bn') }}</span>
                            <input name="developer_bn" value="{{ old('developer_bn', $project->developer_bn) }}" maxlength="255" lang="bn">
                            @error('developer_bn') <small class="auth-error">{{ $message }}</small> @enderror
                        </label>
                        <label class="admin-form-field admin-form-field-wide">
                            <span>{{ __('site.admin_projects.summary') }} <b>*</b></span>
                            <textarea name="summary" rows="3" maxlength="1000" required>{{ old('summary', $project->summary) }}</textarea>
                            @error('summary') <small class="auth-error">{{ $message }}</small> @enderror
                        </label>
                        <label class="admin-form-field admin-form-field-wide">
                            <span>{{ __('site.admin_projects.summary_bn') }}</span>
                            <textarea name="summary_bn" rows="3" maxlength="1000" lang="bn">{{ old('summary_bn', $project->summary_bn) }}</textarea>
                            @error('summary_bn') <small class="auth-error">{{ $message }}</small> @enderror
                        </label>
                        <label class="admin-form-field admin-form-field-wide">
                            <span>{{ __('site.admin_projects.full_description') }} <b>*</b></span>
                            <textarea name="description" rows="7" maxlength="20000" required>{{ old('description', $project->description) }}</textarea>
                            @error('description') <small class="auth-error">{{ $message }}</small> @enderror
                        </label>
                        <label class="admin-form-field admin-form-field-wide">
                            <span>{{ __('site.admin_projects.description_bn') }}</span>
                            <textarea name="description_bn" rows="7" maxlength="20000" lang="bn">{{ old('description_bn', $project->description_bn) }}</textarea>
                            @error('description_bn') <small class="auth-error">{{ $message }}</small> @enderror
                        </label>
                    </div>
                </section>

                <section class="admin-form-section">
                    <div class="admin-form-heading">
                        <span class="section-eyebrow">02</span>
                        <div><h2>{{ __('site.admin_projects.availability') }}</h2><p>{{ __('site.admin_projects.availability_help') }}</p></div>
                    </div>
                    <div class="admin-form-grid">
                        <label class="admin-form-field">
                            <span>{{ __('site.admin_projects.target_units') }} <b>*</b></span>
                            <input type="number" name="target_units" min="1" step="1" value="{{ old('target_units', $project->target_units) }}" required>
                            @error('target_units') <small class="auth-error">{{ $message }}</small> @enderror
                        </label>
                        <label class="admin-form-field">
                            <span>{{ __('site.admin_projects.available_units') }} <b>*</b></span>
                            <input type="number" name="available_units" min="0" step="1" value="{{ old('available_units', $project->available_units) }}" required>
                            @error('available_units') <small class="auth-error">{{ $message }}</small> @enderror
                        </label>
                        <label class="admin-form-field">
                            <span>{{ __('site.admin_projects.expected_handover') }}</span>
                            <input type="date" name="expected_handover_at" value="{{ old('expected_handover_at', $project->expected_handover_at?->format('Y-m-d')) }}">
                            @error('expected_handover_at') <small class="auth-error">{{ $message }}</small> @enderror
                        </label>
                    </div>
                </section>

                <section class="admin-form-section">
                    <div class="admin-form-heading">
                        <span class="section-eyebrow">03</span>
                        <div><h2>{{ __('site.admin_projects.investment_terms') }}</h2><p>{{ __('site.admin_projects.investment_terms_help') }}</p></div>
                    </div>
                    <div class="admin-form-grid">
                        <label class="admin-form-field">
                            <span>{{ __('site.admin_projects.unit_price_bdt') }} <b>*</b></span>
                            <input type="number" name="unit_price_bdt" min="0.01" step="0.01" value="{{ old('unit_price_bdt', $term->unit_price_bdt) }}" required>
                            @error('unit_price_bdt') <small class="auth-error">{{ $message }}</small> @enderror
                        </label>
                        <label class="admin-form-field">
                            <span>{{ __('site.admin_projects.annual_rate_percent') }}</span>
                            <input type="number" name="annual_rate_percent" min="0" max="100" step="0.0001" value="{{ old('annual_rate_percent', $term->annual_rate_percent) }}">
                            @error('annual_rate_percent') <small class="auth-error">{{ $message }}</small> @enderror
                        </label>
                        <label class="admin-form-field">
                            <span>{{ __('site.admin_projects.return_min') }}</span>
                            <input type="number" name="estimated_return_min_percent" min="0" max="100" step="0.0001" value="{{ old('estimated_return_min_percent', $term->estimated_return_min_percent) }}">
                            @error('estimated_return_min_percent') <small class="auth-error">{{ $message }}</small> @enderror
                        </label>
                        <label class="admin-form-field">
                            <span>{{ __('site.admin_projects.return_max') }}</span>
                            <input type="number" name="estimated_return_max_percent" min="0" max="100" step="0.0001" value="{{ old('estimated_return_max_percent', $term->estimated_return_max_percent) }}">
                            @error('estimated_return_max_percent') <small class="auth-error">{{ $message }}</small> @enderror
                        </label>
                        <label class="admin-form-field">
                            <span>{{ __('site.admin_projects.terms_effective_from') }} <b>*</b></span>
                            <input type="date" name="effective_from" value="{{ old('effective_from', $term->effective_from?->format('Y-m-d') ?? now()->toDateString()) }}" required>
                            @error('effective_from') <small class="auth-error">{{ $message }}</small> @enderror
                        </label>
                    </div>
                    <p class="admin-form-hint">{{ __('site.admin_projects.terms_version_notice') }}</p>
                </section>

                <section class="admin-form-section">
                    <div class="admin-form-heading">
                        <span class="section-eyebrow">04</span>
                        <div><h2>{{ __('site.admin_projects.project_photos') }}</h2><p>{{ __('site.admin_projects.project_photos_help') }}</p></div>
                    </div>
                    @if ($project->images->isNotEmpty())
                        <div class="admin-existing-images">
                            @foreach ($project->images as $image)
                                <figure>
                                    <img src="{{ $image->url() }}" alt="{{ $image->alt_text ?? $project->name }}">
                                    <figcaption>{{ $image->is_primary ? __('site.admin_projects.current_cover') : __('site.admin_projects.gallery_image') }}</figcaption>
                                    <label class="admin-image-remove" title="{{ __('site.admin_projects.remove_image') }}">
                                        <input type="checkbox" name="delete_images[]" value="{{ $image->id }}" aria-label="{{ __('site.admin_projects.remove_image') }}" @checked(is_array(old('delete_images')) && in_array((string) $image->id, old('delete_images'), true))>
                                        <span aria-hidden="true">×</span>
                                    </label>
                                </figure>
                            @endforeach
                        </div>
                    @endif
                    <div class="admin-form-grid">
                        <label class="admin-form-field admin-form-field-wide">
                            <span>{{ __('site.admin_projects.cover_image') }} @unless ($project->primaryImage)<b>*</b>@endunless</span>
                            <input type="file" name="cover_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                            <small>{{ __('site.admin_projects.cover_image_help') }}</small>
                            @error('cover_image') <small class="auth-error">{{ $message }}</small> @enderror
                        </label>
                        <label class="admin-form-field admin-form-field-wide">
                            <span>{{ __('site.admin_projects.gallery_images') }}</span>
                            <input type="file" name="gallery[]" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple>
                            <small>{{ __('site.admin_projects.gallery_images_help') }}</small>
                            @error('gallery') <small class="auth-error">{{ $message }}</small> @enderror
                            @foreach ($errors->get('gallery.*') as $messages)
                                @foreach ($messages as $message)<small class="auth-error">{{ $message }}</small>@endforeach
                            @endforeach
                        </label>
                    </div>
                </section>

                <section class="admin-form-section">
                    <div class="admin-form-heading">
                        <span class="section-eyebrow">05</span>
                        <div><h2>{{ __('site.admin_projects.publishing') }}</h2><p>{{ __('site.admin_projects.publishing_help') }}</p></div>
                    </div>
                    <label class="admin-form-field admin-status-field">
                        <span>{{ __('site.admin_projects.project_status') }} <b>*</b></span>
                        <select name="status" required>
                            @foreach (['draft', 'published', 'archived'] as $projectStatus)
                                <option value="{{ $projectStatus }}" @selected(old('status', $project->status) === $projectStatus)>{{ __('site.auth.statuses.'.$projectStatus) }}</option>
                            @endforeach
                        </select>
                        @error('status') <small class="auth-error">{{ $message }}</small> @enderror
                    </label>
                </section>

                <div class="admin-form-actions">
                    <a class="button button-outline" href="{{ route('admin.projects.index') }}">{{ __('site.admin_projects.cancel') }}</a>
                    <button class="button button-dark" type="submit">{{ $editing ? __('site.admin_projects.save_changes') : __('site.admin_projects.create_project') }} <span aria-hidden="true">→</span></button>
                </div>
            </form>
        @endif
    </section>
@endsection
