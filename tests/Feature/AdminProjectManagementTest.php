<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_project_creation_form(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.projects.create'))
            ->assertOk()
            ->assertSee('Property information')
            ->assertSee('Project photos')
            ->assertSee('Residential')
            ->assertSee('value="5.00"', false)
            ->assertSee('value="3"', false)
            ->assertSee('value="24"', false)
            ->assertSee('Create project');
    }

    public function test_admin_can_create_and_delete_an_unused_project_category(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.projects.categories.store'), [
                'name' => ' Waterfront ',
                'name_bn' => 'নদীতীর',
            ])
            ->assertRedirect(route('admin.projects.index'));

        $category = ProjectCategory::query()->where('name', 'Waterfront')->firstOrFail();
        $this->assertSame('নদীতীর', $category->name_bn);
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_user_id' => $admin->id,
            'action' => 'project_category.created',
            'subject_id' => $category->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.projects.categories.destroy', $category))
            ->assertRedirect(route('admin.projects.index'));

        $this->assertDatabaseMissing('project_categories', ['id' => $category->id]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_user_id' => $admin->id,
            'action' => 'project_category.deleted',
            'subject_id' => $category->id,
        ]);
    }

    public function test_admin_cannot_delete_a_project_category_that_is_in_use(): void
    {
        $project = $this->createDraftProject();
        $category = ProjectCategory::query()->where('name', 'Residential')->firstOrFail();

        $this->actingAs($this->admin())
            ->from(route('admin.projects.index'))
            ->delete(route('admin.projects.categories.destroy', $category))
            ->assertRedirect(route('admin.projects.index'))
            ->assertSessionHasErrors([
                'category' => __('site.admin_projects.category_in_use'),
            ]);

        $this->assertDatabaseHas('project_categories', ['id' => $category->id]);
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'category' => 'Residential']);
    }

    public function test_admin_can_delete_a_project_without_financial_history_and_its_uploaded_images(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $project = $this->createDraftProject();
        $image = $project->images()->make();
        $image->forceFill([
            'path' => 'projects/delete-cover.png',
            'alt_text' => $project->name,
            'sort_order' => 0,
            'is_primary' => true,
        ])->save();
        Storage::disk('public')->put($image->path, 'cover');

        $this->actingAs($admin)
            ->delete(route('admin.projects.destroy', $project))
            ->assertRedirect(route('admin.projects.index'))
            ->assertSessionHas('status', __('site.admin_projects.deleted'));

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        $this->assertDatabaseMissing('project_terms', ['project_id' => $project->id]);
        $this->assertDatabaseMissing('project_images', ['id' => $image->id]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_user_id' => $admin->id,
            'action' => 'project.deleted',
            'subject_id' => $project->id,
        ]);
        Storage::disk('public')->assertMissing($image->path);
    }

    public function test_admin_cannot_delete_a_project_with_investment_history(): void
    {
        $project = $this->createDraftProject();
        $investor = User::factory()->create();
        DB::table('investments')->insert([
            'user_id' => $investor->id,
            'project_id' => $project->id,
            'project_term_id' => $project->terms()->value('id'),
            'unit_count' => 1,
            'unit_price_bdt' => '1200.00',
            'amount_bdt' => '1200.00',
            'payment_currency' => 'BDT',
            'paid_amount' => '1200.00000000',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->from(route('admin.projects.index'))
            ->delete(route('admin.projects.destroy', $project))
            ->assertRedirect(route('admin.projects.index'))
            ->assertSessionHasErrors([
                'project' => __('site.admin_projects.delete_financial_history'),
            ]);

        $this->assertDatabaseHas('projects', ['id' => $project->id]);
        $this->assertDatabaseMissing('admin_audit_logs', ['action' => 'project.deleted']);
    }

    public function test_admin_cannot_delete_a_project_with_profit_distribution_history(): void
    {
        $project = $this->createDraftProject();
        DB::table('profit_distributions')->insert([
            'project_id' => $project->id,
            'project_term_id' => $project->terms()->value('id'),
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'annual_rate_snapshot' => 5,
            'payout_currency' => 'BDT',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->from(route('admin.projects.index'))
            ->delete(route('admin.projects.destroy', $project))
            ->assertRedirect(route('admin.projects.index'))
            ->assertSessionHasErrors([
                'project' => __('site.admin_projects.delete_financial_history'),
            ]);

        $this->assertDatabaseHas('projects', ['id' => $project->id]);
        $this->assertDatabaseMissing('admin_audit_logs', ['action' => 'project.deleted']);
    }

    public function test_non_admin_cannot_delete_a_project(): void
    {
        $project = $this->createDraftProject();

        $this->actingAs(User::factory()->create())
            ->delete(route('admin.projects.destroy', $project))
            ->assertForbidden();

        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    public function test_admin_project_catalogue_no_longer_offers_demo_projects(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.projects.index'))
            ->assertOk()
            ->assertDontSee('Enable demo projects')
            ->assertDontSee('Populate the catalogue with illustrative examples');

        $this->post('/admin/projects/demo-projects', ['enabled' => '1'])
            ->assertMethodNotAllowed();

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_cleanup_migration_removes_sample_projects_and_preserves_categories(): void
    {
        $project = $this->createDraftProject();
        $project->forceFill(['is_sample' => true])->save();
        $category = ProjectCategory::query()->where('name', 'Residential')->firstOrFail();
        $migration = include database_path('migrations/2026_11_19_234438_remove_sample_projects.php');

        $migration->up();

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        $this->assertDatabaseMissing('project_terms', ['project_id' => $project->id]);
        $this->assertDatabaseHas('project_categories', ['id' => $category->id]);

        $this->actingAs($this->admin())
            ->delete(route('admin.projects.categories.destroy', $category))
            ->assertRedirect(route('admin.projects.index'));

        $this->assertDatabaseMissing('project_categories', ['id' => $category->id]);
    }

    public function test_cleanup_migration_refuses_to_remove_sample_projects_with_financial_history(): void
    {
        $project = $this->createDraftProject();
        $project->forceFill(['is_sample' => true])->save();
        $investor = User::factory()->create();
        $projectTermId = $project->terms()->value('id');
        DB::table('investments')->insert([
            'user_id' => $investor->id,
            'project_id' => $project->id,
            'project_term_id' => $projectTermId,
            'unit_count' => 1,
            'unit_price_bdt' => '1200.00',
            'amount_bdt' => '1200.00',
            'payment_currency' => 'BDT',
            'paid_amount' => '1200.00000000',
            'status' => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('profit_distributions')->insert([
            'project_id' => $project->id,
            'project_term_id' => $projectTermId,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'annual_rate_snapshot' => 5,
            'payout_currency' => 'BDT',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $migration = include database_path('migrations/2026_11_19_234438_remove_sample_projects.php');

        try {
            $migration->up();
            $this->fail('The cleanup migration should refuse to delete projects with financial history.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'Sample projects with financial history cannot be removed by this migration.',
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseHas('projects', ['id' => $project->id]);
        $this->assertDatabaseHas('investments', ['project_id' => $project->id]);
        $this->assertDatabaseHas('profit_distributions', ['project_id' => $project->id]);
    }

    public function test_admin_cannot_create_a_duplicate_project_category(): void
    {
        $this->actingAs($this->admin())
            ->from(route('admin.projects.index'))
            ->post(route('admin.projects.categories.store'), [
                'name' => 'Residential',
            ])
            ->assertRedirect(route('admin.projects.index'))
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('project_categories', 5);
    }

    public function test_admin_can_create_and_publish_a_project_with_public_images(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.projects.store'), [
                ...$this->projectData(),
                'status' => 'published',
                'cover_image' => $this->image('cover.png'),
                'gallery' => [$this->image('gallery.png')],
            ])
            ->assertRedirect();

        $project = Project::query()->where('slug', 'verified-dhaka-property')->firstOrFail();
        $this->assertSame('published', $project->status);
        $this->assertDatabaseHas('project_terms', [
            'project_id' => $project->id,
            'version' => 1,
            'unit_price_bdt' => '1200.00',
            'annual_rate_percent' => null,
            'monthly_rate_percent' => '8.5000',
            'minimum_investment_usd' => '10.00',
            'maximum_investment_usd' => '1000.00',
            'minimum_duration_months' => 3,
            'maximum_duration_months' => 24,
        ]);
        $this->assertDatabaseCount('project_images', 2);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'project.created']);

        $cover = $project->primaryImage()->firstOrFail();
        Storage::disk('public')->assertExists($cover->path);
        $this->assertStringContainsString('/storage/projects/', $cover->url());
        $this->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Verified Dhaka Property')
            ->assertSee('Monthly return rate')
            ->assertSee('8.50%')
            ->assertSee('data-project-gallery')
            ->assertSee('data-gallery-thumbnail');
    }

    public function test_admin_can_publish_sanitized_rich_descriptions_in_english_and_bengali(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->post(route('admin.projects.store'), [
                ...$this->projectData(),
                'description' => '<p onclick="alert(1)">English <strong>bold</strong></p><ul><li>First point</li></ul><script>alert("unsafe")</script>',
                'description_bn' => '<p>বাংলা <strong>গাঢ়</strong></p><ol><li>প্রথম পয়েন্ট</li></ol>',
                'status' => 'published',
                'cover_image' => $this->image('cover.png'),
            ])
            ->assertRedirect();

        $project = Project::query()->where('slug', 'verified-dhaka-property')->firstOrFail();
        $this->assertStringContainsString('<strong>bold</strong>', $project->description);
        $this->assertStringNotContainsString('onclick', $project->description);
        $this->assertStringNotContainsString('<script', $project->description);
        $this->assertStringContainsString('<strong>গাঢ়</strong>', $project->description_bn);
        $this->assertStringContainsString('<ol><li>প্রথম পয়েন্ট</li></ol>', $project->description_bn);

        $this->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('<p>English <strong>bold</strong></p>', false)
            ->assertSee('<ul><li>First point</li></ul>', false)
            ->assertDontSee('alert(', false);

        $this->withSession(['locale' => 'bn'])
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('<p>বাংলা <strong>গাঢ়</strong></p>', false)
            ->assertSee('<ol><li>প্রথম পয়েন্ট</li></ol>', false);
    }

    public function test_public_project_page_sanitizes_legacy_description_html(): void
    {
        $project = $this->createDraftProject();
        $project->forceFill([
            'description' => '<p>Legacy <strong>formatted</strong> content.</p><img src="x" onerror="alert(1)"><script>alert("unsafe")</script>',
            'status' => 'published',
            'published_at' => now(),
        ])->save();

        $this->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('<p>Legacy <strong>formatted</strong> content.</p>', false)
            ->assertDontSee('onerror=', false)
            ->assertDontSee('alert("unsafe")', false);
    }

    public function test_public_project_page_formats_legacy_plain_text_descriptions(): void
    {
        $project = $this->createDraftProject();
        $project->forceFill([
            'description' => "Legacy first paragraph.\n\nLegacy second paragraph.",
            'status' => 'published',
            'published_at' => now(),
        ])->save();

        $this->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('<p>Legacy first paragraph.</p><p>Legacy second paragraph.</p>', false);
    }

    public function test_admin_can_publish_a_project_without_category_location_or_developer(): void
    {
        Storage::fake('public');
        $projectData = $this->projectData();
        unset($projectData['category'], $projectData['location'], $projectData['developer']);

        $this->actingAs($this->admin())
            ->post(route('admin.projects.store'), [
                ...$projectData,
                'status' => 'published',
                'cover_image' => $this->image('cover.png'),
            ])
            ->assertRedirect();

        $project = Project::query()->where('slug', 'verified-dhaka-property')->firstOrFail();
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'category' => null,
            'location' => null,
            'developer' => null,
        ]);

        $this->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Verified Dhaka Property')
            ->assertDontSee('<dt>Location</dt>', false);
    }

    public function test_project_term_percentages_reject_more_than_two_decimal_places(): void
    {
        $projectData = $this->projectData();
        $projectData['monthly_rate_percent'] = '8.555';
        $projectData['estimated_return_min_percent'] = '5.555';
        $projectData['estimated_return_max_percent'] = '12.555';

        $this->actingAs($this->admin())
            ->from(route('admin.projects.create'))
            ->post(route('admin.projects.store'), [
                ...$projectData,
                'status' => 'draft',
            ])
            ->assertRedirect(route('admin.projects.create'))
            ->assertSessionHasErrors([
                'monthly_rate_percent',
                'estimated_return_min_percent',
                'estimated_return_max_percent',
            ]);

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_project_terms_reject_reversed_usd_limits_and_duration_ranges(): void
    {
        $projectData = $this->projectData();
        $projectData['minimum_investment_usd'] = '100.00';
        $projectData['maximum_investment_usd'] = '50.00';
        $projectData['minimum_duration_months'] = '12';
        $projectData['maximum_duration_months'] = '6';

        $this->actingAs($this->admin())
            ->from(route('admin.projects.create'))
            ->post(route('admin.projects.store'), [
                ...$projectData,
                'status' => 'draft',
            ])
            ->assertRedirect(route('admin.projects.create'))
            ->assertSessionHasErrors(['maximum_investment_usd', 'minimum_duration_months', 'maximum_duration_months']);

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_admin_project_search_matches_names_but_not_location_or_developer(): void
    {
        $project = $this->createDraftProject();
        $project->forceFill([
            'location' => 'Searchable Location',
            'developer' => 'Searchable Developer',
        ])->save();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.projects.index', ['search' => 'Verified Dhaka']))
            ->assertOk()
            ->assertSee($project->name);

        $this->get(route('admin.projects.index', ['search' => 'Searchable Location']))
            ->assertOk()
            ->assertDontSee($project->name);

        $this->get(route('admin.projects.index', ['search' => 'Searchable Developer']))
            ->assertOk()
            ->assertDontSee($project->name);
    }

    public function test_admin_can_upload_project_images_over_five_megabytes(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->post(route('admin.projects.store'), [
                ...$this->projectData(),
                'status' => 'draft',
                'cover_image' => $this->image('large-cover.png', 6 * 1024),
                'gallery' => [$this->image('large-gallery.png', 6 * 1024)],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('project_images', 2);
        Storage::disk('public')->assertExists(Project::query()->firstOrFail()->primaryImage()->firstOrFail()->path);
    }

    public function test_admin_cannot_upload_project_images_over_twenty_megabytes(): void
    {
        $this->actingAs($this->admin())
            ->from(route('admin.projects.create'))
            ->post(route('admin.projects.store'), [
                ...$this->projectData(),
                'status' => 'draft',
                'cover_image' => $this->image('oversized-cover.png', (20 * 1024) + 1),
                'gallery' => [$this->image('oversized-gallery.png', (20 * 1024) + 1)],
            ])
            ->assertSessionHasErrors(['cover_image', 'gallery.0']);

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_project_terms_are_versioned_when_admin_changes_them(): void
    {
        $admin = $this->admin();
        $project = $this->createDraftProject();

        $this->actingAs($admin)
            ->put(route('admin.projects.update', $project), [
                ...$this->projectData(),
                'name' => 'Updated Dhaka Property',
                'unit_price_bdt' => '1350.00',
                'status' => 'draft',
            ])
            ->assertRedirect(route('admin.projects.edit', $project));

        $this->assertDatabaseCount('project_terms', 2);
        $this->assertDatabaseHas('project_terms', [
            'project_id' => $project->id,
            'version' => 1,
            'unit_price_bdt' => '1200.00',
        ]);
        $this->assertDatabaseHas('project_terms', [
            'project_id' => $project->id,
            'version' => 2,
            'unit_price_bdt' => '1350.00',
        ]);
        $this->assertDatabaseCount('admin_audit_logs', 1);
    }

    public function test_admin_cannot_reduce_project_capacity_below_available_and_committed_units(): void
    {
        $admin = $this->admin();
        $project = $this->createDraftProject();
        $investor = User::factory()->create();
        $termId = $project->terms()->value('id');
        DB::table('investments')->insert([
            'user_id' => $investor->id,
            'project_id' => $project->id,
            'project_term_id' => $termId,
            'unit_count' => 9,
            'unit_price_bdt' => '1200.00',
            'amount_bdt' => '10800.00',
            'payment_currency' => 'BDT',
            'paid_amount' => '10800.00000000',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->put(route('admin.projects.update', $project), [
                ...$this->projectData(),
                'target_units' => 18,
                'available_units' => 10,
                'status' => 'draft',
            ])
            ->assertSessionHasErrors('target_units');

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'target_units' => 20,
            'available_units' => 10,
        ]);
    }

    public function test_admin_can_remove_uploaded_images_and_primary_image_is_replaced(): void
    {
        Storage::fake('public');
        $project = $this->createDraftProject();
        $cover = $project->images()->make();
        $cover->forceFill([
            'path' => 'projects/cover.png',
            'alt_text' => $project->name,
            'sort_order' => 0,
            'is_primary' => true,
        ]);
        $cover->save();
        $gallery = $project->images()->make();
        $gallery->forceFill([
            'path' => 'projects/gallery.png',
            'alt_text' => $project->name,
            'sort_order' => 1,
            'is_primary' => false,
        ]);
        $gallery->save();
        Storage::disk('public')->put($cover->path, 'cover');
        Storage::disk('public')->put($gallery->path, 'gallery');

        $this->actingAs($this->admin())
            ->put(route('admin.projects.update', $project), [
                ...$this->projectData(),
                'status' => 'draft',
                'delete_images' => [(string) $cover->id],
            ])
            ->assertRedirect(route('admin.projects.edit', $project));

        $this->assertDatabaseMissing('project_images', ['id' => $cover->id]);
        $this->assertDatabaseHas('project_images', [
            'id' => $gallery->id,
            'is_primary' => true,
        ]);
        Storage::disk('public')->assertMissing($cover->path);
        Storage::disk('public')->assertExists($gallery->path);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'project.updated',
        ]);
    }

    public function test_admin_cannot_remove_an_image_from_another_project(): void
    {
        $project = $this->createDraftProject();
        $otherProject = new Project;
        $otherProject->forceFill([
            'slug' => 'another-property',
            'name' => 'Another Property',
            'category' => 'Residential',
            'project_type' => 'Apartment',
            'location' => 'Dhaka',
            'summary' => 'Summary.',
            'description' => 'Description.',
            'target_units' => 1,
            'available_units' => 1,
            'status' => 'draft',
        ])->save();
        $otherImage = $otherProject->images()->make();
        $otherImage->forceFill([
            'path' => 'projects/other.png',
            'alt_text' => 'Another Property',
            'sort_order' => 0,
            'is_primary' => true,
        ]);
        $otherImage->save();

        $this->actingAs($this->admin())
            ->put(route('admin.projects.update', $project), [
                ...$this->projectData(),
                'status' => 'draft',
                'delete_images' => [(string) $otherImage->id],
            ])
            ->assertSessionHasErrors('delete_images.0');

        $this->assertDatabaseHas('project_images', ['id' => $otherImage->id]);
    }

    public function test_published_project_must_keep_or_replace_its_last_image(): void
    {
        $project = $this->createDraftProject();
        $cover = $project->images()->make();
        $cover->forceFill([
            'path' => 'projects/published-cover.png',
            'alt_text' => $project->name,
            'sort_order' => 0,
            'is_primary' => true,
        ]);
        $cover->save();

        $this->actingAs($this->admin())
            ->put(route('admin.projects.update', $project), [
                ...$this->projectData(),
                'status' => 'published',
                'delete_images' => [(string) $cover->id],
            ])
            ->assertSessionHasErrors('cover_image');

        $this->assertDatabaseHas('project_images', ['id' => $cover->id]);
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'status' => 'draft']);
    }

    public function test_project_management_is_super_admin_only(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.projects.index'))
            ->assertForbidden();
    }

    public function test_project_cannot_be_published_without_a_cover_image(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.projects.store'), [
                ...$this->projectData(),
                'status' => 'published',
            ])
            ->assertSessionHasErrors('cover_image');

        $this->assertDatabaseCount('projects', 0);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'super_admin', 'status' => 'active'])->save();

        return $admin;
    }

    private function createDraftProject(): Project
    {
        $project = new Project;
        $project->forceFill([
            'slug' => 'verified-dhaka-property',
            'name' => 'Verified Dhaka Property',
            'category' => 'Residential',
            'project_type' => 'Apartment',
            'location' => 'Dhaka',
            'summary' => 'Verified summary.',
            'description' => 'Verified description.',
            'target_units' => 20,
            'available_units' => 10,
            'status' => 'draft',
        ])->save();

        $term = new ProjectTerm;
        $term->forceFill([
            'project_id' => $project->id,
            'version' => 1,
            'unit_price_bdt' => '1200.00',
            'effective_from' => now()->toDateString(),
        ])->save();

        return $project;
    }

    /**
     * @return array<string, mixed>
     */
    private function projectData(): array
    {
        return [
            'name' => 'Verified Dhaka Property',
            'name_bn' => 'যাচাইকৃত ঢাকা সম্পত্তি',
            'category' => 'Residential',
            'project_type' => 'Apartment',
            'location' => 'Dhaka',
            'developer' => 'Verified developer',
            'summary' => 'Verified property summary.',
            'description' => 'Verified property description.',
            'target_units' => 20,
            'available_units' => 10,
            'unit_price_bdt' => '1200.00',
            'monthly_rate_percent' => '8.5',
            'minimum_investment_usd' => '10.00',
            'maximum_investment_usd' => '1000.00',
            'minimum_duration_months' => '3',
            'maximum_duration_months' => '24',
            'estimated_return_min_percent' => '5',
            'estimated_return_max_percent' => '12',
            'effective_from' => now()->toDateString(),
        ];
    }

    private function image(string $name, ?int $sizeKilobytes = null): UploadedFile
    {
        $content = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/aWQAAAAASUVORK5CYII=',
            true,
        );

        if ($content === false) {
            throw new \LogicException('Invalid PNG test fixture.');
        }

        $image = UploadedFile::fake()->createWithContent($name, $content);

        if ($sizeKilobytes !== null) {
            $image->size($sizeKilobytes);
        }

        return $image;
    }
}
