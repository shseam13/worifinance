<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectImage;
use App\Models\ProjectTerm;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ProjectManagementController extends Controller
{
    public function index(Request $request): View
    {
        $query = Project::query()->with('primaryImage')->withCount('terms');

        if ($request->filled('status') && in_array($request->string('status')->toString(), ['draft', 'published', 'archived'], true)) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->trim().'%';
            $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', $search)
                    ->orWhere('location', 'like', $search)
                    ->orWhere('developer', 'like', $search);
            });
        }

        return view('admin.projects.index', [
            ...$this->adminLayoutCounts(),
            'projects' => $query->latest('updated_at')->paginate(12)->withQueryString(),
            'demoProjectsEnabled' => Project::query()->where('is_sample', true)->where('status', 'published')->exists(),
            'status' => $request->string('status')->toString(),
            'search' => $request->string('search')->toString(),
        ]);
    }

    public function create(): View
    {
        $project = new Project;
        $project->forceFill([
            'status' => 'draft',
            'target_units' => 1,
            'available_units' => 1,
        ]);
        $term = new ProjectTerm;
        $term->forceFill([
            'unit_price_bdt' => null,
            'effective_from' => now()->toDateString(),
        ]);

        return view('admin.projects.form', [
            ...$this->adminLayoutCounts(),
            'project' => $project,
            'term' => $term,
            'editing' => false,
        ]);
    }

    public function toggleDemoProjects(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);
        $enabled = (bool) $validated['enabled'];

        DB::transaction(function () use ($request, $enabled): void {
            $publishedBefore = Project::query()->where('is_sample', true)->where('status', 'published')->count();

            if ($enabled) {
                foreach ($this->demoProjects() as $attributes) {
                    $project = Project::query()->where('slug', $attributes['slug'])->first();

                    if ($project === null) {
                        $project = new Project;
                        $project->forceFill([
                            ...array_diff_key($attributes, ['image' => true]),
                            'is_sample' => true,
                            'status' => 'draft',
                            'published_at' => null,
                        ])->save();
                    } elseif (! $project->is_sample) {
                        throw ValidationException::withMessages([
                            'enabled' => __('site.admin_projects.demo_slug_conflict', ['name' => $attributes['name']]),
                        ]);
                    }

                    if (! $project->terms()->exists()) {
                        $term = new ProjectTerm;
                        $term->forceFill([
                            'project_id' => $project->id,
                            'version' => 1,
                            'unit_price_bdt' => '1000.00',
                            'effective_from' => now()->toDateString(),
                        ])->save();
                    }

                    if (! $project->images()->exists()) {
                        $image = new ProjectImage;
                        $image->forceFill([
                            'project_id' => $project->id,
                            'path' => $attributes['image'],
                            'alt_text' => $attributes['name'],
                            'sort_order' => 0,
                            'is_primary' => true,
                        ])->save();
                    }
                }
            }

            Project::query()
                ->where('is_sample', true)
                ->update([
                    'status' => $enabled ? 'published' : 'draft',
                    'published_at' => $enabled ? now() : null,
                    'updated_at' => now(),
                ]);

            DB::table('admin_audit_logs')->insert([
                'admin_user_id' => $request->user()->id,
                'action' => $enabled ? 'demo_projects.enabled' : 'demo_projects.disabled',
                'subject_type' => 'demo_project_collection',
                'subject_id' => null,
                'before_values' => json_encode([
                    'published_count' => $publishedBefore,
                ], JSON_THROW_ON_ERROR),
                'after_values' => json_encode([
                    'enabled' => $enabled,
                    'published_count' => Project::query()->where('is_sample', true)->where('status', 'published')->count(),
                ], JSON_THROW_ON_ERROR),
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('admin.projects.index')->with(
            'status',
            __($enabled ? 'site.admin_projects.demo_enabled' : 'site.admin_projects.demo_disabled'),
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedProject($request);
        $cover = $request->file('cover_image');
        $gallery = $request->file('gallery') ?? [];
        $paths = $this->storeUploads($cover, $gallery);

        try {
            $project = DB::transaction(function () use ($validated, $cover, $gallery, $paths, $request): Project {
                $project = new Project;
                $project->forceFill([
                    ...$this->projectAttributes($validated),
                    'slug' => $this->uniqueSlug($validated['name']),
                    'is_sample' => false,
                    'status' => 'draft',
                    'published_at' => null,
                ])->save();

                $term = $this->saveTerm($project, $validated, $request->user()->id);
                $this->saveImages($project, $cover, $gallery, $paths);

                if ($validated['status'] === 'published' && ! $project->images()->where('is_primary', true)->exists()) {
                    throw ValidationException::withMessages([
                        'cover_image' => __('site.admin_projects.cover_required'),
                    ]);
                }

                $project->forceFill([
                    'status' => $validated['status'],
                    'published_at' => $validated['status'] === 'published' ? now() : null,
                ])->save();

                $this->writeAuditLog($request, 'project.created', $project, null, [
                    'name' => $project->name,
                    'slug' => $project->slug,
                    'status' => $project->status,
                    'project_term_version' => $term->version,
                    'images_added' => count($paths),
                ]);

                return $project;
            });
        } catch (Throwable $exception) {
            $this->deleteStoredPaths($paths);
            throw $exception;
        }

        return redirect()->route('admin.projects.edit', $project)->with('status', __('site.admin_projects.created'));
    }

    public function edit(Project $project): View
    {
        $project->load(['images', 'currentTerms']);
        $term = $project->currentTerms;

        if ($term === null) {
            $term = new ProjectTerm;
            $term->forceFill(['effective_from' => now()->toDateString()]);
        }

        return view('admin.projects.form', [
            ...$this->adminLayoutCounts(),
            'project' => $project,
            'term' => $term,
            'editing' => true,
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        abort_if($project->is_sample, 403, __('site.admin_projects.sample_read_only'));

        $project->load(['images', 'currentTerms']);
        $validated = $this->validatedProject($request, $project);
        $cover = $request->file('cover_image');
        $gallery = $request->file('gallery') ?? [];
        $paths = $this->storeUploads($cover, $gallery);
        $before = [
            'name' => $project->name,
            'status' => $project->status,
            'unit_price_bdt' => $project->currentTerms?->unit_price_bdt,
            'images_count' => $project->images->count(),
        ];

        $removedPaths = [];

        try {
            $removedPaths = DB::transaction(function () use ($project, $validated, $cover, $gallery, $paths, $request, $before): array {
                $project = Project::query()->lockForUpdate()->findOrFail($project->id);
                $committedUnits = (int) DB::table('investments')
                    ->where('project_id', $project->id)
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->sum('unit_count');

                if ((int) $validated['available_units'] + $committedUnits > (int) $validated['target_units']) {
                    throw ValidationException::withMessages([
                        'target_units' => __('site.admin_projects.committed_units_exceed_target'),
                    ]);
                }

                $project->load('images');
                $project->forceFill($this->projectAttributes($validated));
                $project->forceFill([
                    'status' => $validated['status'],
                    'published_at' => $validated['status'] === 'published'
                        ? ($project->published_at ?? now())
                        : null,
                ])->save();

                $removedPaths = $this->removeImages(
                    $project,
                    $validated['delete_images'] ?? [],
                    $cover !== null,
                );
                $term = $this->saveTerm($project, $validated, $request->user()->id);
                $this->saveImages($project, $cover, $gallery, $paths);

                if ($validated['status'] === 'published' && ! $project->images()->where('is_primary', true)->exists()) {
                    throw ValidationException::withMessages([
                        'cover_image' => __('site.admin_projects.cover_required'),
                    ]);
                }

                $this->writeAuditLog($request, 'project.updated', $project, $before, [
                    'name' => $project->name,
                    'slug' => $project->slug,
                    'status' => $project->status,
                    'project_term_version' => $term->version,
                    'images_added' => count($paths),
                    'images_removed' => count($validated['delete_images'] ?? []),
                ]);

                return $removedPaths;
            });
        } catch (Throwable $exception) {
            $this->deleteStoredPaths($paths);
            throw $exception;
        }

        $this->deleteStoredPaths($removedPaths);

        return redirect()->route('admin.projects.edit', $project)->with('status', __('site.admin_projects.updated'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedProject(Request $request, ?Project $project = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'category' => ['required', Rule::in(['Residential', 'Commercial', 'Hospitality', 'Land', 'Mixed-use'])],
            'project_type' => ['required', 'string', 'max:80'],
            'location' => ['required', 'string', 'max:255'],
            'location_bn' => ['nullable', 'string', 'max:255'],
            'developer' => ['nullable', 'string', 'max:255'],
            'developer_bn' => ['nullable', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:1000'],
            'summary_bn' => ['nullable', 'string', 'max:1000'],
            'description' => ['required', 'string', 'max:20000'],
            'description_bn' => ['nullable', 'string', 'max:20000'],
            'target_units' => ['required', 'integer', 'min:1'],
            'available_units' => ['required', 'integer', 'min:0', 'lte:target_units'],
            'expected_handover_at' => ['nullable', 'date'],
            'unit_price_bdt' => ['required', 'numeric', 'gt:0', 'max:999999999999999999', 'regex:/^\d{1,18}(?:\.\d{1,2})?$/'],
            'annual_rate_percent' => ['nullable', 'numeric', 'between:0,100', 'regex:/^\d{1,3}(?:\.\d{1,4})?$/'],
            'estimated_return_min_percent' => ['nullable', 'numeric', 'between:0,100', 'regex:/^\d{1,3}(?:\.\d{1,4})?$/'],
            'estimated_return_max_percent' => ['nullable', 'numeric', 'between:0,100', 'regex:/^\d{1,3}(?:\.\d{1,4})?$/'],
            'effective_from' => ['required', 'date'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'gallery' => ['nullable', 'array', 'max:8'],
            'gallery.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'delete_images' => ['sometimes', 'array'],
            'delete_images.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('project_images', 'id')->where('project_id', $project?->id ?? 0),
            ],
        ]);

        if (
            $validated['estimated_return_min_percent'] !== null
            && $validated['estimated_return_max_percent'] !== null
            && (float) $validated['estimated_return_min_percent'] > (float) $validated['estimated_return_max_percent']
        ) {
            throw ValidationException::withMessages([
                'estimated_return_max_percent' => __('site.admin_projects.return_range_invalid'),
            ]);
        }

        $remainingImageCount = $project?->images()
            ->when(
                ! empty($validated['delete_images']),
                fn ($query) => $query->whereNotIn('id', $validated['delete_images']),
            )
            ->count() ?? 0;

        if ($validated['status'] === 'published' && $request->file('cover_image') === null && $remainingImageCount === 0) {
            throw ValidationException::withMessages([
                'cover_image' => __('site.admin_projects.cover_required'),
            ]);
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function projectAttributes(array $validated): array
    {
        return [
            'name' => $validated['name'],
            'name_bn' => $validated['name_bn'] ?? null,
            'category' => $validated['category'],
            'project_type' => $validated['project_type'],
            'location' => $validated['location'],
            'location_bn' => $validated['location_bn'] ?? null,
            'developer' => $validated['developer'] ?? null,
            'developer_bn' => $validated['developer_bn'] ?? null,
            'summary' => $validated['summary'],
            'summary_bn' => $validated['summary_bn'] ?? null,
            'description' => $validated['description'],
            'description_bn' => $validated['description_bn'] ?? null,
            'target_units' => $validated['target_units'],
            'available_units' => $validated['available_units'],
            'expected_handover_at' => $validated['expected_handover_at'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function saveTerm(Project $project, array $validated, int $adminId): ProjectTerm
    {
        $current = $project->terms()->latest('version')->first();
        $terms = [
            'unit_price_bdt' => number_format((float) $validated['unit_price_bdt'], 2, '.', ''),
            'annual_rate_percent' => $validated['annual_rate_percent'] ?? null,
            'estimated_return_min_percent' => $validated['estimated_return_min_percent'] ?? null,
            'estimated_return_max_percent' => $validated['estimated_return_max_percent'] ?? null,
            'effective_from' => $validated['effective_from'],
        ];

        if ($current !== null && collect($terms)->every(
            fn (mixed $value, string $key): bool => (string) $current->{$key} === (string) $value,
        )) {
            return $current;
        }

        $term = new ProjectTerm;
        $term->forceFill([
            ...$terms,
            'version' => ($current?->version ?? 0) + 1,
            'project_id' => $project->id,
            'created_by_user_id' => $adminId,
        ])->save();

        return $term;
    }

    /**
     * @param  array<int, UploadedFile>  $gallery
     * @return array<int, string>
     */
    private function storeUploads(?UploadedFile $cover, array $gallery): array
    {
        $paths = [];

        try {
            foreach (array_filter([$cover, ...$gallery]) as $image) {
                $path = $image->storePublicly('projects', 'public');

                if (! is_string($path)) {
                    throw new RuntimeException(__('site.admin_projects.upload_failed'));
                }

                $paths[] = $path;
            }
        } catch (Throwable $exception) {
            $this->deleteStoredPaths($paths);
            throw $exception;
        }

        return $paths;
    }

    /**
     * @param  array<int, UploadedFile>  $gallery
     * @param  array<int, string>  $paths
     */
    private function saveImages(Project $project, ?UploadedFile $cover, array $gallery, array $paths): void
    {
        $pathIndex = 0;

        if ($cover !== null) {
            $project->images()->update(['is_primary' => false]);

            $image = new ProjectImage;
            $image->forceFill([
                'project_id' => $project->id,
                'path' => $paths[$pathIndex++],
                'alt_text' => $project->name,
                'sort_order' => 0,
                'is_primary' => true,
            ])->save();
        }

        $sortOrder = (int) $project->images()->max('sort_order');

        foreach ($gallery as $imageFile) {
            $image = new ProjectImage;
            $image->forceFill([
                'project_id' => $project->id,
                'path' => $paths[$pathIndex++],
                'alt_text' => $project->name,
                'sort_order' => ++$sortOrder,
                'is_primary' => false,
            ])->save();
        }
    }

    /**
     * @param  array<int, int|string>  $imageIds
     * @return array<int, string>
     */
    private function removeImages(Project $project, array $imageIds, bool $replacingCover): array
    {
        if ($imageIds === []) {
            return [];
        }

        $images = $project->images()
            ->whereIn('id', $imageIds)
            ->lockForUpdate()
            ->get();
        $removedPrimary = $images->contains(fn (ProjectImage $image): bool => $image->is_primary);
        $paths = $images->pluck('path')
            ->filter(fn (string $path): bool => ! str_starts_with($path, 'images/'))
            ->values()
            ->all();

        $images->each->delete();

        if ($removedPrimary && ! $replacingCover) {
            $replacement = $project->images()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();

            $replacement?->forceFill(['is_primary' => true])->save();
        }

        return $paths;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Project::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $after
     */
    private function writeAuditLog(Request $request, string $action, Project $project, ?array $before, array $after): void
    {
        DB::table('admin_audit_logs')->insert([
            'admin_user_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => Project::class,
            'subject_id' => $project->id,
            'before_values' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after_values' => json_encode($after, JSON_THROW_ON_ERROR),
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function deleteStoredPaths(array $paths): void
    {
        foreach ($paths as $path) {
            if (! Storage::disk('public')->delete($path)) {
                report(new RuntimeException("Unable to remove unlinked project image {$path}."));
            }
        }
    }

    /**
     * @return array{pendingDeposits: int, pendingWithdrawals: int}
     */
    private function adminLayoutCounts(): array
    {
        return [
            'pendingDeposits' => DB::table('deposit_requests')->where('status', 'pending')->count(),
            'pendingWithdrawals' => DB::table('withdrawal_requests')->where('status', 'pending')->count(),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function demoProjects(): array
    {
        $disclaimer = 'Illustrative sample only. This is not a real property, verified ownership, or investment offer.';

        return [
            [
                'slug' => 'coxs-bazar-coastal-residences',
                'name' => "Cox's Bazar Coastal Residences",
                'name_bn' => 'কক্সবাজার কোস্টাল রেসিডেন্সেস',
                'category' => 'Hospitality',
                'project_type' => 'Hotel',
                'location' => "Kolatoli, Cox's Bazar",
                'location_bn' => 'কলাতলী, কক্সবাজার',
                'developer' => 'Illustrative sample profile',
                'developer_bn' => 'নমুনা প্রোফাইল',
                'summary' => 'An illustrative property profile showing the public project and image gallery experience.',
                'summary_bn' => 'পাবলিক প্রকল্প ও ছবির গ্যালারির অভিজ্ঞতা দেখানোর নমুনা প্রোফাইল।',
                'description' => $disclaimer,
                'description_bn' => 'শুধু উদাহরণ হিসেবে দেখানো হয়েছে। এটি প্রকৃত সম্পত্তি, যাচাইকৃত মালিকানা বা বিনিয়োগ প্রস্তাব নয়।',
                'target_units' => '10000',
                'available_units' => '10000',
                'image' => 'images/projects/coastal-residences.svg',
            ],
            [
                'slug' => 'dhaka-urban-commercial',
                'name' => 'Dhaka Urban Commercial',
                'name_bn' => 'ঢাকা আরবান কমার্শিয়াল',
                'category' => 'Commercial',
                'project_type' => 'Commercial',
                'location' => 'Purbachal, Dhaka',
                'location_bn' => 'পূর্বাচল, ঢাকা',
                'developer' => 'Illustrative sample profile',
                'developer_bn' => 'নমুনা প্রোফাইল',
                'summary' => 'An illustrative commercial property profile for the Worifinance demo catalogue.',
                'summary_bn' => 'ওরিফাইন্যান্সের নমুনা তালিকার জন্য একটি উদাহরণ বাণিজ্যিক সম্পত্তির প্রোফাইল।',
                'description' => $disclaimer,
                'description_bn' => 'শুধু উদাহরণ হিসেবে দেখানো হয়েছে। এটি প্রকৃত সম্পত্তি, যাচাইকৃত মালিকানা বা বিনিয়োগ প্রস্তাব নয়।',
                'target_units' => '10000',
                'available_units' => '10000',
                'image' => 'images/projects/urban-commercial.svg',
            ],
            [
                'slug' => 'greenfields-land-project',
                'name' => 'Greenfields Land Project',
                'name_bn' => 'গ্রিনফিল্ডস ল্যান্ড প্রজেক্ট',
                'category' => 'Land',
                'project_type' => 'Land',
                'location' => 'Greater Dhaka',
                'location_bn' => 'বৃহত্তর ঢাকা',
                'developer' => 'Illustrative sample profile',
                'developer_bn' => 'নমুনা প্রোফাইল',
                'summary' => 'An illustrative land project profile demonstrating bilingual project information.',
                'summary_bn' => 'দ্বিভাষিক প্রকল্পের তথ্য দেখানোর জন্য একটি নমুনা জমি প্রকল্পের প্রোফাইল।',
                'description' => $disclaimer,
                'description_bn' => 'শুধু উদাহরণ হিসেবে দেখানো হয়েছে। এটি প্রকৃত সম্পত্তি, যাচাইকৃত মালিকানা বা বিনিয়োগ প্রস্তাব নয়।',
                'target_units' => '10000',
                'available_units' => '10000',
                'image' => 'images/projects/greenfields-land.svg',
            ],
        ];
    }
}
