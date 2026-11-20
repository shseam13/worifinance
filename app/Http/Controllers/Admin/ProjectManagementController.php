<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectImage;
use App\Models\ProjectTerm;
use App\Support\ProjectDescriptionFormatter;
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
    public function __construct(private ProjectDescriptionFormatter $descriptionFormatter) {}

    public function index(Request $request): View
    {
        $query = Project::query()->with(['primaryImage', 'categoryRecord'])->withCount('terms');

        if ($request->filled('status') && in_array($request->string('status')->toString(), ['draft', 'published', 'archived'], true)) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->trim().'%';
            $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', $search)
                    ->orWhere('name_bn', 'like', $search);
            });
        }

        return view('admin.projects.index', [
            ...$this->adminLayoutCounts(),
            'projects' => $query->latest('updated_at')->paginate(12)->withQueryString(),
            'categories' => ProjectCategory::query()->orderBy('name')->get(),
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
            'monthly_rate_percent' => '5.0000',
            'minimum_duration_months' => 3,
            'maximum_duration_months' => 24,
            'effective_from' => now()->toDateString(),
        ]);

        return view('admin.projects.form', [
            ...$this->adminLayoutCounts(),
            'project' => $project,
            'term' => $term,
            'editing' => false,
            'categories' => ProjectCategory::query()->orderBy('name')->get(),
            ...$this->descriptionEditorData($project),
        ]);
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
            'categories' => ProjectCategory::query()->orderBy('name')->get(),
            ...$this->descriptionEditorData($project),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $request->merge([
            'name' => is_string($request->input('name')) ? trim($request->input('name')) : $request->input('name'),
            'name_bn' => is_string($request->input('name_bn')) ? trim($request->input('name_bn')) : $request->input('name_bn'),
        ]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('project_categories', 'name')],
            'name_bn' => ['nullable', 'string', 'max:80'],
        ]);

        DB::transaction(function () use ($request, $validated): void {
            $category = new ProjectCategory;
            $category->forceFill($validated)->save();

            DB::table('admin_audit_logs')->insert([
                'admin_user_id' => $request->user()->id,
                'action' => 'project_category.created',
                'subject_type' => ProjectCategory::class,
                'subject_id' => $category->id,
                'before_values' => null,
                'after_values' => json_encode([
                    'name' => $category->name,
                    'name_bn' => $category->name_bn,
                ], JSON_THROW_ON_ERROR),
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('admin.projects.index')->with('status', __('site.admin_projects.category_created'));
    }

    public function destroyCategory(Request $request, ProjectCategory $category): RedirectResponse
    {
        DB::transaction(function () use ($request, $category): void {
            if ($category->projects()->exists()) {
                throw ValidationException::withMessages([
                    'category' => __('site.admin_projects.category_in_use'),
                ]);
            }

            $category->delete();
            DB::table('admin_audit_logs')->insert([
                'admin_user_id' => $request->user()->id,
                'action' => 'project_category.deleted',
                'subject_type' => ProjectCategory::class,
                'subject_id' => $category->id,
                'before_values' => json_encode([
                    'name' => $category->name,
                    'name_bn' => $category->name_bn,
                ], JSON_THROW_ON_ERROR),
                'after_values' => null,
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('admin.projects.index')->with('status', __('site.admin_projects.category_deleted'));
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

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $imagePaths = [];

        DB::transaction(function () use ($request, $project, &$imagePaths): void {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            $hasInvestments = DB::table('investments')->where('project_id', $project->id)->exists();
            $hasDistributions = DB::table('profit_distributions')->where('project_id', $project->id)->exists();

            if ($hasInvestments || $hasDistributions) {
                throw ValidationException::withMessages([
                    'project' => __('site.admin_projects.delete_financial_history'),
                ]);
            }

            $imagePaths = $project->images()
                ->where('path', 'not like', 'images/%')
                ->pluck('path')
                ->all();

            $before = [
                'name' => $project->name,
                'slug' => $project->slug,
                'status' => $project->status,
            ];
            $projectId = $project->id;
            $project->delete();

            DB::table('admin_audit_logs')->insert([
                'admin_user_id' => $request->user()->id,
                'action' => 'project.deleted',
                'subject_type' => Project::class,
                'subject_id' => $projectId,
                'before_values' => json_encode($before, JSON_THROW_ON_ERROR),
                'after_values' => null,
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->deleteStoredPaths($imagePaths);

        return redirect()->route('admin.projects.index')->with('status', __('site.admin_projects.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedProject(Request $request, ?Project $project = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:80', Rule::exists('project_categories', 'name')],
            'project_type' => ['required', 'string', 'max:80'],
            'location' => ['nullable', 'string', 'max:255'],
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
            'monthly_rate_percent' => ['required', 'numeric', 'between:0,100', 'regex:/^\d{1,3}(?:\.\d{1,2})?$/'],
            'minimum_investment_usd' => ['required', 'numeric', 'gt:0', 'max:999999999999999999.99', 'regex:/^\d{1,18}(?:\.\d{1,2})?$/'],
            'maximum_investment_usd' => ['required', 'numeric', 'gte:minimum_investment_usd', 'max:999999999999999999.99', 'regex:/^\d{1,18}(?:\.\d{1,2})?$/'],
            'minimum_duration_months' => ['required', 'integer', 'min:3', 'max:24', 'lte:maximum_duration_months'],
            'maximum_duration_months' => ['required', 'integer', 'min:3', 'max:24', 'gte:minimum_duration_months'],
            'estimated_return_min_percent' => ['nullable', 'numeric', 'between:0,100', 'regex:/^\d{1,3}(?:\.\d{1,2})?$/'],
            'estimated_return_max_percent' => ['nullable', 'numeric', 'between:0,100', 'regex:/^\d{1,3}(?:\.\d{1,2})?$/'],
            'effective_from' => ['required', 'date'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
            'gallery' => ['nullable', 'array', 'max:8'],
            'gallery.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
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

        $validated['description'] = $this->descriptionFormatter->sanitize($validated['description']);
        $validated['description_bn'] = isset($validated['description_bn'])
            ? $this->descriptionFormatter->sanitize($validated['description_bn'])
            : null;

        return $validated;
    }

    /**
     * @return array<string, string>
     */
    private function descriptionEditorData(Project $project): array
    {
        $description = (string) old('description', $project->description ?? '');
        $descriptionBn = (string) old('description_bn', $project->description_bn ?? '');

        return [
            'descriptionHtml' => $this->descriptionFormatter->sanitize($description),
            'descriptionText' => $this->descriptionFormatter->plainText($description),
            'descriptionBnHtml' => $this->descriptionFormatter->sanitize($descriptionBn),
            'descriptionBnText' => $this->descriptionFormatter->plainText($descriptionBn),
        ];
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
            'category' => $validated['category'] ?? null,
            'project_type' => $validated['project_type'],
            'location' => $validated['location'] ?? null,
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
            'unit_price_bdt' => $this->fixedDecimal($validated['unit_price_bdt'], 2),
            'annual_rate_percent' => null,
            'monthly_rate_percent' => $this->fixedDecimal($validated['monthly_rate_percent'], 4),
            'minimum_investment_usd' => $this->fixedDecimal($validated['minimum_investment_usd'], 2),
            'maximum_investment_usd' => $this->fixedDecimal($validated['maximum_investment_usd'], 2),
            'minimum_duration_months' => $validated['minimum_duration_months'],
            'maximum_duration_months' => $validated['maximum_duration_months'],
            'estimated_return_min_percent' => isset($validated['estimated_return_min_percent'])
                ? $this->fixedDecimal($validated['estimated_return_min_percent'], 4)
                : null,
            'estimated_return_max_percent' => isset($validated['estimated_return_max_percent'])
                ? $this->fixedDecimal($validated['estimated_return_max_percent'], 4)
                : null,
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

    private function fixedDecimal(string|int|float $value, int $scale): string
    {
        [$integer, $fraction] = array_pad(explode('.', (string) $value, 2), 2, '');

        return (ltrim($integer, '0') ?: '0')
            .'.'.str_pad(substr($fraction, 0, $scale), $scale, '0');
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
}
