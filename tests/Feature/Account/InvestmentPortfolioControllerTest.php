<?php

namespace Tests\Feature\Account;

use App\Models\Project;
use App\Models\ProjectTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvestmentPortfolioControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_filter_limits_results_to_the_investor_and_shows_progress(): void
    {
        $this->travelTo('2026-04-02 12:00:00');
        $investor = $this->createInvestor();
        $otherInvestor = $this->createInvestor();
        [$activeProject, $activeTerm] = $this->createProjectAndTerm('Active Portfolio Project');
        [$pendingProject, $pendingTerm] = $this->createProjectAndTerm('Pending Portfolio Project');
        [$completedProject, $completedTerm] = $this->createProjectAndTerm('Completed Portfolio Project');
        [$otherProject, $otherTerm] = $this->createProjectAndTerm('Other Investor Project');

        $this->createInvestment($investor, $activeProject, $activeTerm, [
            'amount_bdt' => 1000,
            'confirmed_at' => '2026-01-01 00:00:00',
            'matures_at' => '2026-07-01 00:00:00',
            'status' => 'confirmed',
        ]);
        $this->createInvestment($investor, $pendingProject, $pendingTerm, [
            'amount_bdt' => 2000,
            'status' => 'pending',
        ]);
        $this->createInvestment($investor, $completedProject, $completedTerm, [
            'amount_bdt' => 3000,
            'confirmed_at' => '2025-01-01 00:00:00',
            'matures_at' => '2025-07-01 00:00:00',
            'principal_returned_at' => '2025-07-02 00:00:00',
            'status' => 'confirmed',
        ]);
        $this->createInvestment($otherInvestor, $otherProject, $otherTerm, [
            'amount_bdt' => 4000,
            'confirmed_at' => '2026-01-01 00:00:00',
            'matures_at' => '2026-07-01 00:00:00',
            'status' => 'confirmed',
        ]);

        $this->actingAs($investor)
            ->get(route('account.investments.index', ['status' => 'active']))
            ->assertOk()
            ->assertSee('Active Portfolio Project')
            ->assertSee('50%')
            ->assertSee('1,000.00 BDT')
            ->assertSee('status-pill status-confirmed">Active', false)
            ->assertDontSee('Pending Portfolio Project')
            ->assertDontSee('Completed Portfolio Project')
            ->assertDontSee('Other Investor Project');
    }

    public function test_completed_filter_only_shows_investments_with_returned_principal(): void
    {
        $investor = $this->createInvestor();
        [$project, $term] = $this->createProjectAndTerm('Returned Principal Project');
        [$activeProject, $activeTerm] = $this->createProjectAndTerm('Unreturned Principal Project');

        $this->createInvestment($investor, $project, $term, [
            'amount_bdt' => 3000,
            'confirmed_at' => now()->subMonths(7),
            'matures_at' => now()->subMonth(),
            'principal_returned_at' => now()->subDays(10),
            'status' => 'confirmed',
        ]);
        $this->createInvestment($investor, $activeProject, $activeTerm, [
            'amount_bdt' => 1000,
            'confirmed_at' => now()->subMonths(7),
            'matures_at' => now()->subMonth(),
            'status' => 'confirmed',
        ]);

        $this->actingAs($investor)
            ->get(route('account.investments.index', ['status' => 'completed']))
            ->assertOk()
            ->assertSee('Returned Principal Project')
            ->assertDontSee('Unreturned Principal Project')
            ->assertSee('status-pill status-confirmed">Completed', false)
            ->assertSee('100%');
    }

    public function test_statement_shows_posted_profits_and_print_action(): void
    {
        $investor = $this->createInvestor();
        [$project, $term] = $this->createProjectAndTerm('Statement Portfolio Project');
        $investmentId = $this->createInvestment($investor, $project, $term, [
            'amount_bdt' => 2500,
            'confirmed_at' => now()->subMonths(2),
            'matures_at' => now()->addMonths(4),
            'status' => 'confirmed',
        ]);
        $postedDistribution = $this->createDistribution($project, $term, 'posted', '2026-01-01', '2026-01-31');
        $approvedDistribution = $this->createDistribution($project, $term, 'approved', '2026-02-01', '2026-02-28');
        $this->createDistributionItem($postedDistribution, $investmentId, 3.25);
        $this->createDistributionItem($approvedDistribution, $investmentId, 99);

        $this->actingAs($investor)
            ->get(route('account.investments.statement', $investmentId))
            ->assertOk()
            ->assertSee('Print / Save as PDF')
            ->assertSee('Statement Portfolio Project')
            ->assertSee('3.25 BDT')
            ->assertDontSee('99.00 BDT');
    }

    public function test_investor_cannot_view_another_investors_investment(): void
    {
        $investor = $this->createInvestor();
        $otherInvestor = $this->createInvestor();
        [$project, $term] = $this->createProjectAndTerm('Private Portfolio Project');
        $investmentId = $this->createInvestment($otherInvestor, $project, $term, [
            'confirmed_at' => now(),
            'matures_at' => now()->addMonths(6),
            'status' => 'confirmed',
        ]);

        $this->actingAs($investor)
            ->get(route('account.investments.show', $investmentId))
            ->assertNotFound();
    }

    public function test_investor_cannot_view_another_investors_statement(): void
    {
        $investor = $this->createInvestor();
        $otherInvestor = $this->createInvestor();
        [$project, $term] = $this->createProjectAndTerm('Private Statement Project');
        $investmentId = $this->createInvestment($otherInvestor, $project, $term, [
            'confirmed_at' => now(),
            'matures_at' => now()->addMonths(6),
            'status' => 'confirmed',
        ]);

        $this->actingAs($investor)
            ->get(route('account.investments.statement', $investmentId))
            ->assertNotFound();
    }

    private function createInvestor(): User
    {
        $investor = User::factory()->create(['role' => 'investor']);
        $investor->forceFill(['role' => 'investor', 'status' => 'active'])->save();

        return $investor;
    }

    /**
     * @return array{Project, ProjectTerm}
     */
    private function createProjectAndTerm(string $name): array
    {
        $project = new Project;
        $project->forceFill([
            'slug' => Str::slug($name),
            'name' => $name,
            'category' => 'Residential',
            'location' => 'Dhaka',
            'status' => 'published',
            'target_units' => 100,
            'available_units' => 100,
            'published_at' => now(),
        ])->save();
        $term = new ProjectTerm;
        $term->forceFill([
            'project_id' => $project->id,
            'version' => 1,
            'unit_price_bdt' => 1000,
            'annual_rate_percent' => 5,
            'monthly_rate_percent' => 2.5,
            'effective_from' => now()->toDateString(),
        ])->save();

        return [$project, $term];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createInvestment(User $investor, Project $project, ProjectTerm $term, array $attributes = []): int
    {
        $now = now();

        return DB::table('investments')->insertGetId(array_merge([
            'user_id' => $investor->id,
            'project_id' => $project->id,
            'project_term_id' => $term->id,
            'unit_count' => 1,
            'unit_price_bdt' => 1000,
            'amount_bdt' => 1000,
            'payment_currency' => 'BDT',
            'paid_amount' => 1000,
            'duration_months' => 6,
            'status' => 'pending',
            'confirmed_at' => null,
            'matures_at' => null,
            'principal_returned_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $attributes));
    }

    private function createDistribution(Project $project, ProjectTerm $term, string $status, string $periodStart, string $periodEnd): int
    {
        return DB::table('profit_distributions')->insertGetId([
            'project_id' => $project->id,
            'project_term_id' => $term->id,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'annual_rate_snapshot' => 5,
            'monthly_rate_snapshot' => 2.5,
            'payout_currency' => 'BDT',
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createDistributionItem(int $distributionId, int $investmentId, float $amount): void
    {
        DB::table('distribution_items')->insert([
            'profit_distribution_id' => $distributionId,
            'investment_id' => $investmentId,
            'eligible_principal_bdt' => 1000,
            'annual_rate_snapshot' => 5,
            'monthly_rate_snapshot' => 2.5,
            'eligible_days' => 30,
            'payout_currency' => 'BDT',
            'amount' => $amount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
