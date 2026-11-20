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
        @if ($editing && $term->monthly_rate_percent === null && $term->annual_rate_percent !== null)
            <div class="portal-notice portal-notice-warning"><div><p>{{ __('site.admin_projects.legacy_rate_notice') }}</p></div></div>
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
                            <span>{{ __('site.admin_projects.category') }}</span>
                            <select name="category">
                                <option value="">{{ __('site.admin_projects.optional_field') }}</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->name }}" @selected(old('category', $project->category) === $category->name)>{{ $category->displayName() }}</option>
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
                            <span>{{ __('site.admin_projects.location') }}</span>
                            <input name="location" value="{{ old('location', $project->location) }}" maxlength="255">
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
                        <div class="admin-form-field admin-form-field-wide" data-rich-text>
                            <span id="description-label">{{ __('site.admin_projects.full_description') }} <b>*</b></span>
                            <textarea id="description-source" name="description" rows="7" maxlength="20000" required data-rich-text-source aria-labelledby="description-label">{{ $descriptionText }}</textarea>
                            <div class="rich-text-widget" data-rich-text-widget hidden>
                                <div class="rich-text-toolbar" role="toolbar" aria-label="{{ __('site.admin_projects.description_toolbar') }}">
                                    <button type="button" data-rich-text-command="bold" aria-label="{{ __('site.admin_projects.description_bold') }}" title="{{ __('site.admin_projects.description_bold') }}"><strong>B</strong></button>
                                    <button type="button" data-rich-text-command="italic" aria-label="{{ __('site.admin_projects.description_italic') }}" title="{{ __('site.admin_projects.description_italic') }}"><em>I</em></button>
                                    <button type="button" data-rich-text-command="formatBlock" data-rich-text-value="p" aria-label="{{ __('site.admin_projects.description_paragraph') }}" title="{{ __('site.admin_projects.description_paragraph') }}">¶</button>
                                    <button type="button" data-rich-text-command="formatBlock" data-rich-text-value="h3" aria-label="{{ __('site.admin_projects.description_heading') }}" title="{{ __('site.admin_projects.description_heading') }}">H</button>
                                    <button type="button" data-rich-text-command="insertUnorderedList" aria-label="{{ __('site.admin_projects.description_bulleted_list') }}" title="{{ __('site.admin_projects.description_bulleted_list') }}">•</button>
                                    <button type="button" data-rich-text-command="insertOrderedList" aria-label="{{ __('site.admin_projects.description_numbered_list') }}" title="{{ __('site.admin_projects.description_numbered_list') }}">1.</button>
                                    <button type="button" data-rich-text-command="removeFormat" aria-label="{{ __('site.admin_projects.description_clear_formatting') }}" title="{{ __('site.admin_projects.description_clear_formatting') }}">Tx</button>
                                </div>
                                <div class="rich-text-content" data-rich-text-editor contenteditable="true" role="textbox" aria-multiline="true" aria-labelledby="description-label" lang="en">{!! $descriptionHtml !!}</div>
                            </div>
                            <small>{{ __('site.admin_projects.description_editor_help') }}</small>
                            @error('description') <small class="auth-error">{{ $message }}</small> @enderror
                        </div>
                        <div class="admin-form-field admin-form-field-wide" data-rich-text>
                            <span id="description-bn-label">{{ __('site.admin_projects.description_bn') }}</span>
                            <textarea id="description-bn-source" name="description_bn" rows="7" maxlength="20000" lang="bn" data-rich-text-source aria-labelledby="description-bn-label">{{ $descriptionBnText }}</textarea>
                            <div class="rich-text-widget" data-rich-text-widget hidden>
                                <div class="rich-text-toolbar" role="toolbar" aria-label="{{ __('site.admin_projects.description_toolbar') }}">
                                    <button type="button" data-rich-text-command="bold" aria-label="{{ __('site.admin_projects.description_bold') }}" title="{{ __('site.admin_projects.description_bold') }}"><strong>B</strong></button>
                                    <button type="button" data-rich-text-command="italic" aria-label="{{ __('site.admin_projects.description_italic') }}" title="{{ __('site.admin_projects.description_italic') }}"><em>I</em></button>
                                    <button type="button" data-rich-text-command="formatBlock" data-rich-text-value="p" aria-label="{{ __('site.admin_projects.description_paragraph') }}" title="{{ __('site.admin_projects.description_paragraph') }}">¶</button>
                                    <button type="button" data-rich-text-command="formatBlock" data-rich-text-value="h3" aria-label="{{ __('site.admin_projects.description_heading') }}" title="{{ __('site.admin_projects.description_heading') }}">H</button>
                                    <button type="button" data-rich-text-command="insertUnorderedList" aria-label="{{ __('site.admin_projects.description_bulleted_list') }}" title="{{ __('site.admin_projects.description_bulleted_list') }}">•</button>
                                    <button type="button" data-rich-text-command="insertOrderedList" aria-label="{{ __('site.admin_projects.description_numbered_list') }}" title="{{ __('site.admin_projects.description_numbered_list') }}">1.</button>
                                    <button type="button" data-rich-text-command="removeFormat" aria-label="{{ __('site.admin_projects.description_clear_formatting') }}" title="{{ __('site.admin_projects.description_clear_formatting') }}">Tx</button>
                                </div>
                                <div class="rich-text-content" data-rich-text-editor contenteditable="true" role="textbox" aria-multiline="true" aria-labelledby="description-bn-label" lang="bn">{!! $descriptionBnHtml !!}</div>
                            </div>
                            <small>{{ __('site.admin_projects.description_editor_help') }}</small>
                            @error('description_bn') <small class="auth-error">{{ $message }}</small> @enderror
                        </div>
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
                            <span>{{ __('site.admin_projects.monthly_rate_percent') }} <b>*</b></span>
                            <input type="number" name="monthly_rate_percent" min="0" max="100" step="0.01" value="{{ old('monthly_rate_percent', $term->monthly_rate_percent !== null ? number_format((float) $term->monthly_rate_percent, 2, '.', '') : ($editing ? '' : '5.00')) }}" required>
                            @error('monthly_rate_percent') <small class="auth-error">{{ $message }}</small> @enderror
                        </label>
                        <label class="admin-form-field">
                            <span>{{ __('site.admin_projects.minimum_investment_usd') }} <b>*</b></span>
                            <input type="number" name="minimum_investment_usd" min="0.01" step="0.01" value="{{ old('minimum_investment_usd', $term->minimum_investment_usd) }}" required>
                            @error('minimum_investment_usd') <small class="auth-error">{{ $message }}</small> @enderror
                        </label>
                        <label class="admin-form-field">
                            <span>{{ __('site.admin_projects.maximum_investment_usd') }} <b>*</b></span>
                            <input type="number" name="maximum_investment_usd" min="0.01" step="0.01" value="{{ old('maximum_investment_usd', $term->maximum_investment_usd) }}" required>
                            @error('maximum_investment_usd') <small class="auth-error">{{ $message }}</small> @enderror
                        </label>
                        <label class="admin-form-field">
                            <span>{{ __('site.admin_projects.minimum_duration_months') }} <b>*</b></span>
                            <input type="number" name="minimum_duration_months" min="3" max="24" step="1" value="{{ old('minimum_duration_months', $term->minimum_duration_months ?? 3) }}" required>
                            @error('minimum_duration_months') <small class="auth-error">{{ $message }}</small> @enderror
                        </label>
                        <label class="admin-form-field">
                            <span>{{ __('site.admin_projects.maximum_duration_months') }} <b>*</b></span>
                            <input type="number" name="maximum_duration_months" min="3" max="24" step="1" value="{{ old('maximum_duration_months', $term->maximum_duration_months ?? 24) }}" required>
                            @error('maximum_duration_months') <small class="auth-error">{{ $message }}</small> @enderror
                        </label>
                        <label class="admin-form-field">
                            <span>{{ __('site.admin_projects.return_min') }}</span>
                            <input type="number" name="estimated_return_min_percent" min="0" max="100" step="0.01" value="{{ old('estimated_return_min_percent', $term->estimated_return_min_percent !== null ? number_format((float) $term->estimated_return_min_percent, 2, '.', '') : '') }}">
                            @error('estimated_return_min_percent') <small class="auth-error">{{ $message }}</small> @enderror
                        </label>
                        <label class="admin-form-field">
                            <span>{{ __('site.admin_projects.return_max') }}</span>
                            <input type="number" name="estimated_return_max_percent" min="0" max="100" step="0.01" value="{{ old('estimated_return_max_percent', $term->estimated_return_max_percent !== null ? number_format((float) $term->estimated_return_max_percent, 2, '.', '') : '') }}">
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
