<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_investor_dashboard_reports_own_balances_investments_and_returns_only(): void
    {
        $this->seed();

        $investor = User::factory()->create(['role' => 'investor']);
        $investor->forceFill(['role' => 'investor', 'status' => 'active'])->save();
        $otherInvestor = User::factory()->create(['role' => 'investor']);
        $otherInvestor->forceFill(['role' => 'investor', 'status' => 'active'])->save();
        [$project, $term] = $this->createProjectAndTerm('Verified Dhaka Property');
        $confirmedInvestmentId = $this->createInvestment($investor, $project->id, $term->id, [
            'unit_count' => 2,
            'unit_price_bdt' => 500,
            'amount_bdt' => 1000,
            'paid_amount' => 1000,
            'payment_currency' => 'BDT',
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
        $this->createInvestment($investor, $project->id, $term->id, [
            'unit_count' => 1,
            'unit_price_bdt' => 500,
            'amount_bdt' => 500,
            'paid_amount' => 500,
            'payment_currency' => 'BDT',
            'status' => 'pending',
            'confirmed_at' => null,
        ]);
        $this->createInvestment($otherInvestor, $project->id, $term->id, [
            'unit_count' => 10,
            'unit_price_bdt' => 500,
            'amount_bdt' => 5000,
            'paid_amount' => 5000,
            'payment_currency' => 'BDT',
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        DB::table('wallet_accounts')->insert([
            [
                'user_id' => $investor->id,
                'currency' => 'BDT',
                'balance' => 1250,
                'reserved_balance' => 16,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $investor->id,
                'currency' => 'USDT',
                'balance' => 10.5,
                'reserved_balance' => 1.5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $approvedDistribution = $this->createDistribution($project->id, $term->id, 'approved', '2026-01-01', '2026-01-31');
        $postedDistribution = $this->createDistribution($project->id, $term->id, 'posted', '2026-02-01', '2026-02-28');
        $this->createDistributionItem($approvedDistribution, $confirmedInvestmentId, 12.5);
        $this->createDistributionItem($postedDistribution, $confirmedInvestmentId, 3.25);

        DB::table('referral_commissions')->insert([
            'referrer_user_id' => $investor->id,
            'referred_user_id' => $otherInvestor->id,
            'investment_id' => $this->createInvestment($otherInvestor, $project->id, $term->id, [
                'unit_count' => 1,
                'unit_price_bdt' => 500,
                'amount_bdt' => 500,
                'paid_amount' => 500,
                'payment_currency' => 'BDT',
                'status' => 'confirmed',
                'confirmed_at' => now(),
            ]),
            'commission_rate_snapshot' => 2,
            'basis_amount_bdt' => 500,
            'commission_amount_bdt' => 10,
            'status' => 'credited',
            'credited_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('deposit_requests')->insert([
            'user_id' => $investor->id,
            'currency' => 'BDT',
            'amount' => 250,
            'payment_reference' => 'INVESTOR-TEST-REF',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('withdrawal_requests')->insert([
            'user_id' => $investor->id,
            'currency' => 'USDT',
            'amount' => 2,
            'minimum_amount_snapshot' => 1,
            'destination' => 'T-test-destination',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($investor)
            ->get(route('account.dashboard'))
            ->assertOk()
            ->assertSee('1,000.00 BDT')
            ->assertSee('1,234.00')
            ->assertSee('9.0000')
            ->assertSee('3.25 BDT')
            ->assertSee('10.00 BDT')
            ->assertSee('Verified Dhaka Property')
            ->assertSee('INVESTOR-TEST-REF')
            ->assertSee('T-test-destination')
            ->assertDontSee('5,000.00 BDT');
    }

    public function test_admin_dashboard_reports_payable_profit_commissions_wallets_and_pending_queues(): void
    {
        $this->seed();

        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->forceFill(['role' => 'super_admin', 'status' => 'active'])->save();
        $investor = User::factory()->create(['role' => 'investor']);
        $investor->forceFill(['role' => 'investor', 'status' => 'active'])->save();
        [$project, $term] = $this->createProjectAndTerm('Verified Admin Property');
        $investmentId = $this->createInvestment($investor, $project->id, $term->id, [
            'unit_count' => 3,
            'unit_price_bdt' => 1000,
            'amount_bdt' => 3000,
            'paid_amount' => 3000,
            'payment_currency' => 'BDT',
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        DB::table('wallet_accounts')->insert([
            'user_id' => $investor->id,
            'currency' => 'BDT',
            'balance' => 2750,
            'reserved_balance' => 250,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $distribution = $this->createDistribution($project->id, $term->id, 'approved', '2026-03-01', '2026-03-31');
        $this->createDistributionItem($distribution, $investmentId, 125.75);

        DB::table('referral_commissions')->insert([
            'referrer_user_id' => $investor->id,
            'referred_user_id' => User::factory()->create()->id,
            'investment_id' => $this->createInvestment($investor, $project->id, $term->id, [
                'unit_count' => 1,
                'unit_price_bdt' => 100,
                'amount_bdt' => 100,
                'paid_amount' => 100,
                'payment_currency' => 'BDT',
                'status' => 'pending',
                'confirmed_at' => null,
            ]),
            'commission_rate_snapshot' => 2.5,
            'basis_amount_bdt' => 100,
            'commission_amount_bdt' => 2.5,
            'status' => 'pending',
            'credited_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('deposit_requests')->insert([
            'user_id' => $investor->id,
            'currency' => 'BDT',
            'amount' => 200,
            'payment_reference' => 'ADMIN-DASH-REF',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('withdrawal_requests')->insert([
            'user_id' => $investor->id,
            'currency' => 'BDT',
            'amount' => 100,
            'minimum_amount_snapshot' => 50,
            'destination' => 'Bank test account',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('3,000.00 BDT')
            ->assertSee('125.75')
            ->assertSee('2.50 BDT')
            ->assertSee('2,750.00')
            ->assertSee('Pending review')
            ->assertSee('ADMIN-DASH-REF')
            ->assertSee('Bank test account')
            ->assertSee('Verified Admin Property');
    }

    public function test_dashboards_use_the_selected_bengali_locale(): void
    {
        $investor = User::factory()->create();
        $investor->forceFill(['role' => 'investor', 'status' => 'active'])->save();

        $this->withSession(['locale' => 'bn'])
            ->actingAs($investor)
            ->get(route('account.dashboard'))
            ->assertOk()
            ->assertSee('আমার বিনিয়োগ')
            ->assertSee('জমার অনুরোধ');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createInvestment(User $user, int $projectId, int $projectTermId, array $attributes): int
    {
        $now = now();

        return DB::table('investments')->insertGetId([
            'user_id' => $user->id,
            'project_id' => $projectId,
            'project_term_id' => $projectTermId,
            'unit_count' => $attributes['unit_count'],
            'unit_price_bdt' => $attributes['unit_price_bdt'],
            'amount_bdt' => $attributes['amount_bdt'],
            'payment_currency' => $attributes['payment_currency'],
            'paid_amount' => $attributes['paid_amount'],
            'status' => $attributes['status'],
            'confirmed_at' => $attributes['confirmed_at'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
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
            'project_type' => 'Apartment',
            'location' => 'Dhaka',
            'summary' => 'Verified project summary.',
            'description' => 'Verified project description.',
            'target_units' => 100,
            'available_units' => 100,
            'status' => 'published',
            'published_at' => now(),
            'is_sample' => false,
        ])->save();

        $term = new ProjectTerm;
        $term->forceFill([
            'project_id' => $project->id,
            'version' => 1,
            'unit_price_bdt' => '1000.00',
            'effective_from' => now()->toDateString(),
        ])->save();

        return [$project, $term];
    }

    private function createDistribution(int $projectId, int $projectTermId, string $status, string $periodStart, string $periodEnd): int
    {
        return DB::table('profit_distributions')->insertGetId([
            'project_id' => $projectId,
            'project_term_id' => $projectTermId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'annual_rate_snapshot' => 5,
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
            'eligible_days' => 30,
            'payout_currency' => 'BDT',
            'amount' => $amount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
