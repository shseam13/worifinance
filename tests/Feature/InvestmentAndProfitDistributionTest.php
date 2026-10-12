<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvestmentAndProfitDistributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_investment_request_reserves_wallet_and_units_until_admin_rejects_it(): void
    {
        $investor = $this->investor();
        $admin = $this->admin();
        $project = $this->project();
        $this->wallet($investor, 'BDT', '5000.00');

        $this->actingAs($investor)
            ->get(route('account.projects.invest.create', $project))
            ->assertOk()
            ->assertSee('Invest in this project')
            ->assertSee('1,000.00 BDT');

        $this->post(route('account.projects.invest.store', $project), [
            'units' => 2,
            'currency' => 'BDT',
        ])->assertRedirect(route('account.dashboard'));

        $investmentId = (int) DB::table('investments')->value('id');
        $this->assertDatabaseHas('investments', [
            'id' => $investmentId,
            'user_id' => $investor->id,
            'project_id' => $project->id,
            'unit_count' => 2,
            'amount_bdt' => '2000.00',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('wallet_accounts', [
            'user_id' => $investor->id,
            'currency' => 'BDT',
            'balance' => '5000.00000000',
            'reserved_balance' => '2000.00000000',
        ]);
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'available_units' => 3]);

        $this->actingAs($admin)
            ->get(route('admin.investments.index'))
            ->assertOk()
            ->assertSee($investor->email)
            ->assertSee('Confirm and debit');

        $this->actingAs($admin)
            ->post(route('admin.investments.review', $investmentId), ['decision' => 'rejected'])
            ->assertSessionHasErrors('review_note');

        $this->post(route('admin.investments.review', $investmentId), [
            'decision' => 'rejected',
            'review_note' => 'Please verify the request details.',
        ])->assertRedirect(route('admin.investments.index'));

        $this->assertDatabaseHas('investments', ['id' => $investmentId, 'status' => 'rejected']);
        $this->assertDatabaseHas('wallet_accounts', [
            'user_id' => $investor->id,
            'currency' => 'BDT',
            'balance' => '5000.00000000',
            'reserved_balance' => '0.00000000',
        ]);
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'available_units' => 5]);
        $this->assertDatabaseHas('ledger_transactions', [
            'idempotency_key' => 'investment-review:'.$investmentId,
            'type' => 'investment_release',
        ]);
        $this->post(route('admin.investments.review', $investmentId), [
            'decision' => 'rejected',
            'review_note' => 'Duplicate attempt.',
        ])->assertStatus(409);
    }

    public function test_admin_confirmation_debits_reserved_funds_and_preserves_project_reservation(): void
    {
        $investor = $this->investor();
        $admin = $this->admin();
        $project = $this->project();
        $this->wallet($investor, 'BDT', '5000.00');

        $this->actingAs($investor)->post(route('account.projects.invest.store', $project), [
            'units' => 2,
            'currency' => 'BDT',
        ])->assertRedirect(route('account.dashboard'));

        $investmentId = (int) DB::table('investments')->value('id');
        $this->actingAs($admin)
            ->post(route('admin.investments.review', $investmentId), ['decision' => 'confirmed'])
            ->assertRedirect(route('admin.investments.index'));

        $this->assertDatabaseHas('investments', [
            'id' => $investmentId,
            'status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('wallet_accounts', [
            'user_id' => $investor->id,
            'currency' => 'BDT',
            'balance' => '3000.00000000',
            'reserved_balance' => '0.00000000',
        ]);
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'available_units' => 3]);
        $this->assertDatabaseHas('ledger_entries', [
            'entry_type' => 'investment_debit',
            'amount' => '-2000.00000000',
            'balance_after' => '3000.00000000',
        ]);
        $this->post(route('admin.investments.review', $investmentId), ['decision' => 'confirmed'])
            ->assertStatus(409);
    }

    public function test_usdt_investment_keeps_the_exchange_rate_snapshot_and_uses_reserved_usdt(): void
    {
        $investor = $this->investor();
        $project = $this->project();
        $rateId = DB::table('exchange_rates')->insertGetId([
            'base_currency' => 'USDT',
            'quote_currency' => 'BDT',
            'quote_per_base' => '125.00000000',
            'version' => 1,
            'effective_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->wallet($investor, 'USDT', '100.00');

        $this->actingAs($investor)->post(route('account.projects.invest.store', $project), [
            'units' => 1,
            'currency' => 'USDT',
        ])->assertRedirect(route('account.dashboard'));

        $this->assertDatabaseHas('investments', [
            'user_id' => $investor->id,
            'project_id' => $project->id,
            'amount_bdt' => '1000.00',
            'payment_currency' => 'USDT',
            'paid_amount' => '8.00000000',
            'exchange_rate_id' => $rateId,
            'bdt_per_usdt_snapshot' => '125.00000000',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('wallet_accounts', [
            'user_id' => $investor->id,
            'currency' => 'USDT',
            'balance' => '100.00000000',
            'reserved_balance' => '8.00000000',
        ]);
    }

    public function test_confirmed_referred_investment_creates_a_snapshot_commission_for_admin_credit(): void
    {
        $admin = $this->admin();
        $referrer = $this->investor();
        $referredInvestor = $this->investor();
        $referredInvestor->forceFill(['referred_by_user_id' => $referrer->id])->save();
        $project = $this->project();
        $this->wallet($referredInvestor, 'BDT', '2000.00');
        DB::table('platform_settings')->insert([
            'key' => 'platform.referral_commission_percent',
            'version' => 1,
            'value' => '5.0000',
            'effective_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($referredInvestor)->post(route('account.projects.invest.store', $project), [
            'units' => 1,
            'currency' => 'BDT',
        ])->assertRedirect(route('account.dashboard'));
        $investmentId = (int) DB::table('investments')->value('id');

        $this->actingAs($admin)->post(route('admin.investments.review', $investmentId), [
            'decision' => 'confirmed',
        ])->assertRedirect(route('admin.investments.index'));

        $commission = DB::table('referral_commissions')->first();
        $this->assertNotNull($commission);
        $this->assertDatabaseHas('referral_commissions', [
            'id' => $commission->id,
            'referrer_user_id' => $referrer->id,
            'referred_user_id' => $referredInvestor->id,
            'investment_id' => $investmentId,
            'commission_rate_snapshot' => '5.0000',
            'basis_amount_bdt' => '1000.00',
            'commission_amount_bdt' => '50.00',
            'status' => 'pending',
        ]);

        $this->get(route('admin.referrals.index'))
            ->assertOk()
            ->assertSee($referrer->email)
            ->assertSee($referredInvestor->email)
            ->assertSee('Credit BDT wallet');

        $this->post(route('admin.referrals.credit', $commission->id), ['decision' => 'credit'])
            ->assertRedirect(route('admin.referrals.index'));

        $this->assertDatabaseHas('referral_commissions', [
            'id' => $commission->id,
            'status' => 'credited',
        ]);
        $this->assertDatabaseHas('wallet_accounts', [
            'user_id' => $referrer->id,
            'currency' => 'BDT',
            'balance' => '50.00000000',
        ]);
        $this->assertDatabaseHas('ledger_transactions', [
            'idempotency_key' => 'referral-commission:'.$commission->id,
            'type' => 'referral_commission',
            'created_by_user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'entry_type' => 'referral_commission_credit',
            'amount' => '50.00000000',
            'balance_after' => '50.00000000',
        ]);
        $this->post(route('admin.referrals.credit', $commission->id), ['decision' => 'credit'])
            ->assertStatus(409);
        $this->assertDatabaseCount('ledger_transactions', 3);
    }

    public function test_profit_distribution_uses_saved_rate_and_snapshot_and_posts_once(): void
    {
        $admin = $this->admin();
        $investor = $this->investor();
        $project = $this->project('12.0000');
        $oldTermId = (int) DB::table('project_terms')->where('project_id', $project->id)->value('id');
        $this->term($project, 2, '50.0000');
        $periodStart = today()->subDays(365);
        $periodEnd = today()->subDay();
        $investmentId = DB::table('investments')->insertGetId([
            'user_id' => $investor->id,
            'project_id' => $project->id,
            'project_term_id' => $oldTermId,
            'unit_count' => 10,
            'unit_price_bdt' => '1000.00',
            'amount_bdt' => '10000.00',
            'payment_currency' => 'BDT',
            'paid_amount' => '10000.00000000',
            'status' => 'confirmed',
            'confirmed_at' => $periodStart->copy()->startOfDay(),
            'created_at' => $periodStart,
            'updated_at' => now(),
        ]);
        $project->forceFill(['status' => 'archived'])->save();
        $exchangeRateId = $this->exchangeRate('120.00000000', 1);

        $this->actingAs($admin)
            ->get(route('admin.profit-distributions.create'))
            ->assertOk()
            ->assertSee($project->name);
        $this->post(route('admin.profit-distributions.store'), [
            'project_id' => $project->id,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'payout_currency' => 'USDT',
        ])->assertRedirect(route('admin.profit-distributions.index'));

        $distribution = DB::table('profit_distributions')->first();
        $this->assertNotNull($distribution);
        $this->assertEquals(50, $distribution->annual_rate_snapshot);
        $this->assertEquals(120, $distribution->bdt_per_usdt_snapshot);
        $this->assertSame($exchangeRateId, $distribution->exchange_rate_id);
        $this->assertDatabaseHas('distribution_items', [
            'profit_distribution_id' => $distribution->id,
            'investment_id' => $investmentId,
            'eligible_principal_bdt' => '10000.00',
            'annual_rate_snapshot' => '12.0000',
            'eligible_days' => 365,
            'payout_currency' => 'USDT',
            'amount' => '10.00000000',
            'bdt_per_usdt_snapshot' => '120.00000000',
        ]);
        $this->actingAs($admin)
            ->get(route('admin.profit-distributions.index'))
            ->assertOk()
            ->assertSee($investor->email)
            ->assertSee('12.0000%');

        $this->actingAs($investor)
            ->get(route('account.dashboard'))
            ->assertOk()
            ->assertSee(__('site.auth.no_profit_history'));

        $this->exchangeRate('130.00000000', 2);
        $this->actingAs($admin)->post(route('admin.profit-distributions.transition', $distribution->id), [
            'decision' => 'approve',
        ])->assertRedirect(route('admin.profit-distributions.index'));
        $this->post(route('admin.profit-distributions.transition', $distribution->id), [
            'decision' => 'post',
        ])->assertRedirect(route('admin.profit-distributions.index'));

        $this->assertDatabaseHas('profit_distributions', ['id' => $distribution->id, 'status' => 'posted']);
        $this->assertDatabaseHas('wallet_accounts', [
            'user_id' => $investor->id,
            'currency' => 'USDT',
            'balance' => '10.00000000',
        ]);
        $this->assertDatabaseHas('ledger_transactions', [
            'idempotency_key' => 'profit-distribution-item:'.DB::table('distribution_items')->value('id'),
            'exchange_rate_id' => $exchangeRateId,
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'entry_type' => 'profit_credit',
            'amount' => '10.00000000',
            'balance_after' => '10.00000000',
        ]);
        $this->post(route('admin.profit-distributions.transition', $distribution->id), [
            'decision' => 'post',
        ])->assertStatus(409);
        $this->assertDatabaseCount('ledger_transactions', 1);

        $this->actingAs($investor)
            ->get(route('account.dashboard'))
            ->assertOk()
            ->assertDontSee(__('site.auth.no_profit_history'));
    }

    public function test_profit_distribution_rejects_overlapping_periods(): void
    {
        $admin = $this->admin();
        $investor = $this->investor();
        $project = $this->project();
        $termId = (int) DB::table('project_terms')->where('project_id', $project->id)->value('id');
        $periodStart = today()->subDays(30);
        $periodEnd = today()->subDay();
        DB::table('investments')->insert([
            'user_id' => $investor->id,
            'project_id' => $project->id,
            'project_term_id' => $termId,
            'unit_count' => 1,
            'unit_price_bdt' => '1000.00',
            'amount_bdt' => '1000.00',
            'payment_currency' => 'BDT',
            'paid_amount' => '1000.00000000',
            'status' => 'confirmed',
            'confirmed_at' => $periodStart->copy()->startOfDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $payload = [
            'project_id' => $project->id,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'payout_currency' => 'BDT',
        ];

        $this->actingAs($admin)->post(route('admin.profit-distributions.store'), $payload)
            ->assertRedirect(route('admin.profit-distributions.index'));
        $payload['period_start'] = $periodStart->copy()->addDays(10)->toDateString();
        $this->post(route('admin.profit-distributions.store'), $payload)
            ->assertSessionHasErrors('period_start');

        $this->assertDatabaseCount('profit_distributions', 1);
    }

    private function investor(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'investor', 'status' => 'active'])->save();

        return $user;
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'super_admin', 'status' => 'active'])->save();

        return $user;
    }

    private function project(string $rate = '10.0000'): Project
    {
        $project = new Project;
        $project->forceFill([
            'slug' => Str::lower(Str::random(12)),
            'name' => 'Verified Investment Property',
            'category' => 'Residential',
            'location' => 'Dhaka',
            'status' => 'published',
            'target_units' => 10,
            'available_units' => 5,
            'is_sample' => false,
            'published_at' => now()->subMinute(),
        ])->save();
        $this->term($project, 1, $rate);

        return $project;
    }

    private function term(Project $project, int $version, string $rate): int
    {
        return DB::table('project_terms')->insertGetId([
            'project_id' => $project->id,
            'version' => $version,
            'unit_price_bdt' => '1000.00',
            'annual_rate_percent' => $rate,
            'effective_from' => today()->subYear()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function wallet(User $user, string $currency, string $balance): void
    {
        DB::table('wallet_accounts')->insert([
            'user_id' => $user->id,
            'currency' => $currency,
            'balance' => $balance,
            'reserved_balance' => '0.00000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function exchangeRate(string $rate, int $version): int
    {
        return DB::table('exchange_rates')->insertGetId([
            'base_currency' => 'USDT',
            'quote_currency' => 'BDT',
            'quote_per_base' => $rate,
            'version' => $version,
            'effective_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
