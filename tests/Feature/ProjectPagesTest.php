<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_does_not_present_fabricated_projects_as_real_listings(): void
    {
        $this->seed();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('New project profiles are on the way.')
            ->assertDontSee("Cox's Bazar Coastal Residences");
    }

    public function test_projects_have_a_readable_project_detail_page(): void
    {
        $project = $this->createProject();

        $this->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Project profile')
            ->assertSee('Project terms have not been published. Returns are never guaranteed.');
    }

    public function test_project_prices_use_site_wide_currency_without_changing_stored_terms(): void
    {
        $project = $this->createProject();
        DB::table('project_terms')->insert([
            'project_id' => $project->id,
            'version' => 1,
            'unit_price_bdt' => '1200.00',
            'effective_from' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('platform_settings')->insert([
            'key' => 'platform.display_currency',
            'version' => 1,
            'value' => 'USDT',
            'currency' => 'USDT',
            'effective_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('exchange_rates')->insert([
            'base_currency' => 'USDT',
            'quote_currency' => 'BDT',
            'quote_per_base' => '80.00000000',
            'version' => 1,
            'effective_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('15.0000 USDT')
            ->assertDontSee('BDT 1,200.00');

        $this->assertDatabaseHas('project_terms', [
            'project_id' => $project->id,
            'unit_price_bdt' => '1200.00',
        ]);
    }

    public function test_project_directory_lists_only_published_projects(): void
    {
        $published = $this->createProject('Verified Property One');
        $this->createProject('Verified Property Two');
        $this->createProject('not-a-published-project', 'draft');

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Verified Property One')
            ->assertSee('Verified Property Two')
            ->assertDontSee('not-a-published-project');
    }

    public function test_language_can_be_switched_to_bengali(): void
    {
        $this->from(route('home'))
            ->post(route('language.update'), ['locale' => 'bn'])
            ->assertRedirect(route('home'))
            ->assertSessionHas('locale', 'bn');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('প্রকল্পসমূহ');
    }

    public function test_language_switch_rejects_unsupported_locales(): void
    {
        $this->from(route('home'))
            ->post(route('language.update'), ['locale' => 'fr'])
            ->assertSessionHasErrors('locale');
    }

    public function test_unpublished_projects_are_not_publicly_accessible(): void
    {
        $this->createProject('not-a-published-project', 'draft');

        $this->get('/projects/not-a-published-project')->assertNotFound();
    }

    private function createProject(string $name = 'Verified Property', string $status = 'published'): Project
    {
        $project = new Project;
        $project->forceFill([
            'slug' => Str::slug($name),
            'name' => $name,
            'category' => 'Residential',
            'project_type' => 'Apartment',
            'location' => 'Verified location',
            'status' => $status,
            'summary' => 'Verified property summary.',
            'description' => 'Verified property description.',
            'target_units' => 10,
            'available_units' => 8,
            'is_sample' => false,
            'published_at' => $status === 'published' ? now() : null,
        ])->save();

        return $project;
    }

    public function test_wallet_can_store_usdt_currency_code(): void
    {
        $user = User::factory()->create();

        DB::table('wallet_accounts')->insert([
            'user_id' => $user->id,
            'currency' => 'USDT',
            'balance' => 0,
            'reserved_balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('wallet_accounts', [
            'user_id' => $user->id,
            'currency' => 'USDT',
        ]);
    }
}
