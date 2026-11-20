<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
            ->assertSee('1,000.00 BDT')
            ->assertSee('Monthly return rate')
            ->assertSee('USD 10.00')
            ->assertSee('name="duration_months"', false);

        $this->post(route('account.projects.invest.store', $project), [
            'units' => 2,
            'currency' => 'BDT',
            'duration_months' => 3,
        ])->assertRedirect(route('account.dashboard'));

        $investmentId = (int) DB::table('investments')->value('id');
        $this->assertDatabaseHas('investments', [
            'id' => $investmentId,
            'user_id' => $investor->id,
            'project_id' => $project->id,
            'unit_count' => 2,
            'amount_bdt' => '2000.00',
            'duration_months' => 3,
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
            'duration_months' => 3,
        ])->assertRedirect(route('account.dashboard'));

        $investmentId = (int) DB::table('investments')->value('id');
        $this->actingAs($admin)
            ->post(route('admin.investments.review', $investmentId), ['decision' => 'confirmed'])
            ->assertRedirect(route('admin.investments.index'));

        $this->assertDatabaseHas('investments', [
            'id' => $investmentId,
            'status' => 'confirmed',
            'duration_months' => 3,
            'matures_at' => now()->copy()->addMonthsNoOverflow(3)->toDateTimeString(),
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
            'duration_months' => 3,
        ])->assertRedirect(route('account.dashboard'));

        $this->assertDatabaseHas('investments', [
            'user_id' => $investor->id,
            'project_id' => $project->id,
            'amount_bdt' => '1000.00',
            'payment_currency' => 'USDT',
            'paid_amount' => '8.00000000',
            'exchange_rate_id' => $rateId,
            'bdt_per_usdt_snapshot' => '125.00000000',
            'usd_bdt_rate_snapshot' => '100.00000000',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('wallet_accounts', [
            'user_id' => $investor->id,
            'currency' => 'USDT',
            'balance' => '100.00000000',
            'reserved_balance' => '8.00000000',
        ]);
    }

    public function test_investment_enforces_usd_amount_and_duration_limits_using_the_submission_rate(): void
    {
        $investor = $this->investor();
        $project = $this->project();
        DB::table('project_terms')->where('project_id', $project->id)->update([
            'minimum_investment_usd' => '20.00',
            'maximum_investment_usd' => '30.00',
            'minimum_duration_months' => 6,
            'maximum_duration_months' => 12,
        ]);
        $this->wallet($investor, 'BDT', '10000.00');

        $this->actingAs($investor)->post(route('account.projects.invest.store', $project), [
            'units' => 1,
            'currency' => 'BDT',
            'duration_months' => 6,
        ])->assertSessionHasErrors('units');

        $this->post(route('account.projects.invest.store', $project), [
            'units' => 4,
            'currency' => 'BDT',
            'duration_months' => 6,
        ])->assertSessionHasErrors('units');

        $this->post(route('account.projects.invest.store', $project), [
            'units' => 2,
            'currency' => 'BDT',
            'duration_months' => 3,
        ])->assertSessionHasErrors('duration_months');

        $usdRateId = DB::table('exchange_rates')->insertGetId([
            'base_currency' => 'USD',
            'quote_currency' => 'BDT',
            'quote_per_base' => '50.00000000',
            'version' => 2,
            'effective_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('project_terms')->where('project_id', $project->id)->update([
            'maximum_investment_usd' => '40.00',
        ]);

        $this->post(route('account.projects.invest.store', $project), [
            'units' => 2,
            'currency' => 'BDT',
            'duration_months' => 12,
        ])->assertRedirect(route('account.dashboard'));

        $this->assertDatabaseHas('investments', [
            'user_id' => $investor->id,
            'project_id' => $project->id,
            'amount_bdt' => '2000.00',
            'duration_months' => 12,
            'usd_exchange_rate_id' => $usdRateId,
            'usd_bdt_rate_snapshot' => '50.00000000',
        ]);
        $this->assertDatabaseHas('wallet_accounts', [
            'user_id' => $investor->id,
            'currency' => 'BDT',
            'reserved_balance' => '2000.00000000',
        ]);
    }

    public function test_confirmed_investment_matures_and_super_admin_returns_principal_once(): void
    {
        $this->travelTo(Carbon::parse('2026-01-31 12:00:00'));

        $investor = $this->investor();
        $admin = $this->admin();
        $project = $this->project();
        $this->wallet($investor, 'BDT', '5000.00');

        $this->actingAs($investor)->post(route('account.projects.invest.store', $project), [
            'units' => 1,
            'currency' => 'BDT',
            'duration_months' => 3,
        ])->assertRedirect(route('account.dashboard'));
        $investmentId = (int) DB::table('investments')->value('id');

        $this->actingAs($admin)->post(route('admin.investments.review', $investmentId), [
            'decision' => 'confirmed',
        ])->assertRedirect(route('admin.investments.index'));

        $this->assertDatabaseHas('investments', [
            'id' => $investmentId,
            'status' => 'confirmed',
            'matures_at' => '2026-04-30 12:00:00',
        ]);
        $this->actingAs($admin)
            ->get(route('admin.investments.index', ['status' => 'matured']))
            ->assertOk()
            ->assertSee('There are no investment requests in this list.')
            ->assertDontSee('Review and return principal');

        $this->post(route('admin.investments.return-principal', $investmentId))
            ->assertStatus(409);

        $this->travelTo(Carbon::parse('2026-04-30 12:00:01'));
        $this->get(route('admin.investments.index', ['status' => 'matured']))
            ->assertOk()
            ->assertSee('Review and return principal');

        $this->post(route('admin.investments.return-principal', $investmentId))
            ->assertRedirect(route('admin.investments.index', ['status' => 'matured']));

        $this->assertDatabaseHas('investments', [
            'id' => $investmentId,
            'principal_returned_at' => '2026-04-30 12:00:01',
        ]);
        $this->assertDatabaseHas('wallet_accounts', [
            'user_id' => $investor->id,
            'currency' => 'BDT',
            'balance' => '5000.00000000',
        ]);
        $this->assertDatabaseHas('ledger_transactions', [
            'idempotency_key' => 'investment-principal-return:'.$investmentId,
            'type' => 'investment_principal_return',
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'entry_type' => 'investment_principal_return',
            'amount' => '1000.00000000',
            'balance_after' => '5000.00000000',
        ]);

        $this->post(route('admin.investments.return-principal', $investmentId))
            ->assertStatus(409);
        $this->assertDatabaseCount('ledger_transactions', 3);
    }

    public function test_confirmed_referred_investment_creates_a_snapshot_commission_that_becomes_eligible_after_the_hold_period(): void
    {
        $this->travelTo(Carbon::parse('2026-04-20 12:00:00'));

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
        $this->approvedDeposit($referredInvestor, '2026-01-15 12:00:00');
        $this->paidWithdrawal($referredInvestor, '2026-04-16 12:00:00');

        $this->actingAs($referredInvestor)->post(route('account.projects.invest.store', $project), [
            'units' => 1,
            'currency' => 'BDT',
            'duration_months' => 3,
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
            ->assertSee('5.0000%')
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

    public function test_referral_commissions_wait_for_three_months_after_the_next_approved_deposit_following_a_withdrawal(): void
    {
        $this->travelTo(Carbon::parse('2026-04-20 12:00:00'));

        $admin = $this->admin();
        $referrer = $this->investor();
        $referredInvestor = $this->investor();
        $referredInvestor->forceFill(['referred_by_user_id' => $referrer->id])->save();
        $project = $this->project();
        $this->wallet($referredInvestor, 'BDT', '3000.00');
        DB::table('platform_settings')->insert([
            'key' => 'platform.referral_commission_percent',
            'version' => 1,
            'value' => '5.0000',
            'effective_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $investmentIds = [
            $this->confirmReferredInvestment($admin, $referredInvestor, $project),
            $this->confirmReferredInvestment($admin, $referredInvestor, $project),
        ];
        $commissionIds = DB::table('referral_commissions')
            ->whereIn('investment_id', $investmentIds)
            ->pluck('id')
            ->all();
        $ledgerTransactionCount = DB::table('ledger_transactions')->count();

        $this->actingAs($admin)
            ->post(route('admin.referrals.credit', $commissionIds[0]), ['decision' => 'credit'])
            ->assertStatus(409);

        $this->approvedDeposit($referredInvestor, '2026-01-15 12:00:00');
        $this->paidWithdrawal($referredInvestor, '2026-02-01 12:00:00');
        $this->approvedDeposit($referredInvestor, '2026-03-01 12:00:00');

        foreach ($commissionIds as $commissionId) {
            $this->actingAs($admin)
                ->post(route('admin.referrals.credit', $commissionId), ['decision' => 'credit'])
                ->assertStatus(409);
            $this->assertDatabaseHas('referral_commissions', [
                'id' => $commissionId,
                'status' => 'pending',
            ]);
        }

        $this->assertDatabaseMissing('wallet_accounts', [
            'user_id' => $referrer->id,
            'currency' => 'BDT',
        ]);
        $this->assertSame($ledgerTransactionCount, DB::table('ledger_transactions')->count());

        $this->travelTo(Carbon::parse('2026-06-01 12:00:00'));

        foreach ($commissionIds as $commissionId) {
            $this->actingAs($admin)
                ->post(route('admin.referrals.credit', $commissionId), ['decision' => 'credit'])
                ->assertRedirect(route('admin.referrals.index'));
        }

        $this->assertDatabaseCount('referral_commissions', 2);
        $this->assertDatabaseHas('wallet_accounts', [
            'user_id' => $referrer->id,
            'currency' => 'BDT',
            'balance' => '100.00000000',
        ]);
        $this->assertSame($ledgerTransactionCount + 2, DB::table('ledger_transactions')->count());
    }

    public function test_confirmed_referred_investment_creates_a_fixed_bdt_commission_snapshot(): void
    {
        $this->travelTo(Carbon::parse('2026-04-15 12:00:00'));

        $admin = $this->admin();
        $referrer = $this->investor();
        $referredInvestor = $this->investor();
        $referredInvestor->forceFill(['referred_by_user_id' => $referrer->id])->save();
        $project = $this->project();
        $this->wallet($referredInvestor, 'BDT', '2000.00');
        DB::table('platform_settings')->insert([
            [
                'key' => 'platform.referral_commission_type',
                'version' => 1,
                'value' => 'fixed',
                'effective_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'platform.referral_commission_percent',
                'version' => 1,
                'value' => '125.50',
                'effective_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $this->approvedDeposit($referredInvestor, '2026-01-15 12:00:00');

        $this->actingAs($referredInvestor)->post(route('account.projects.invest.store', $project), [
            'units' => 1,
            'currency' => 'BDT',
            'duration_months' => 3,
        ])->assertRedirect(route('account.dashboard'));
        $investmentId = (int) DB::table('investments')->value('id');

        $this->actingAs($admin)->post(route('admin.investments.review', $investmentId), [
            'decision' => 'confirmed',
        ])->assertRedirect(route('admin.investments.index'));

        $commission = DB::table('referral_commissions')->first();
        $this->assertNotNull($commission);
        $this->assertDatabaseHas('referral_commissions', [
            'id' => $commission->id,
            'commission_type_snapshot' => 'fixed',
            'commission_value_snapshot' => '125.5000',
            'commission_amount_bdt' => '125.50',
            'status' => 'pending',
        ]);

        $this->get(route('admin.referrals.index'))
            ->assertOk()
            ->assertSee('125.50 BDT');

        $this->post(route('admin.referrals.credit', $commission->id), ['decision' => 'credit'])
            ->assertRedirect(route('admin.referrals.index'));

        $this->assertDatabaseHas('wallet_accounts', [
            'user_id' => $referrer->id,
            'currency' => 'BDT',
            'balance' => '125.50000000',
        ]);
    }

    public function test_profit_distribution_uses_saved_rate_and_snapshot_and_posts_once(): void
    {
        $admin = $this->admin();
        $investor = $this->investor();
        $project = $this->project('12.0000', legacyAnnual: true);
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
            ->assertSee('12.00% / year');

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

    public function test_monthly_profit_distribution_prorates_first_and_final_months_and_stops_at_maturity(): void
    {
        $this->travelTo(Carbon::parse('2026-05-01 10:00:00'));

        $admin = $this->admin();
        $project = $this->project('10.0000');
        $termId = (int) DB::table('project_terms')->where('project_id', $project->id)->value('id');
        $firstMonthInvestor = $this->investor();
        $finalMonthInvestor = $this->investor();
        $investments = [
            [
                'user_id' => $firstMonthInvestor->id,
                'confirmed_at' => '2026-04-15 12:00:00',
                'matures_at' => '2026-07-15 12:00:00',
            ],
            [
                'user_id' => $finalMonthInvestor->id,
                'confirmed_at' => '2026-01-15 12:00:00',
                'matures_at' => '2026-04-15 12:00:00',
            ],
        ];

        foreach ($investments as $investment) {
            DB::table('investments')->insert([
                ...$investment,
                'project_id' => $project->id,
                'project_term_id' => $termId,
                'unit_count' => 10,
                'unit_price_bdt' => '1000.00',
                'amount_bdt' => '10000.00',
                'payment_currency' => 'BDT',
                'paid_amount' => '10000.00000000',
                'duration_months' => 3,
                'status' => 'confirmed',
                'created_at' => $investment['confirmed_at'],
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($admin)->post(route('admin.profit-distributions.store'), [
            'project_id' => $project->id,
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
            'payout_currency' => 'BDT',
        ])->assertRedirect(route('admin.profit-distributions.index'));

        $distribution = DB::table('profit_distributions')->first();
        $this->assertNotNull($distribution);
        $this->assertDatabaseHas('profit_distributions', [
            'id' => $distribution->id,
            'annual_rate_snapshot' => null,
            'monthly_rate_snapshot' => '10.0000',
        ]);
        $this->assertDatabaseHas('distribution_items', [
            'profit_distribution_id' => $distribution->id,
            'investment_id' => DB::table('investments')->where('user_id', $firstMonthInvestor->id)->value('id'),
            'annual_rate_snapshot' => null,
            'monthly_rate_snapshot' => '10.0000',
            'eligible_days' => 16,
            'amount' => '533.33',
        ]);
        $this->assertDatabaseHas('distribution_items', [
            'profit_distribution_id' => $distribution->id,
            'investment_id' => DB::table('investments')->where('user_id', $finalMonthInvestor->id)->value('id'),
            'monthly_rate_snapshot' => '10.0000',
            'eligible_days' => 14,
            'amount' => '466.67',
        ]);
    }

    public function test_monthly_profit_distribution_requires_a_complete_calendar_month(): void
    {
        $this->travelTo(Carbon::parse('2026-05-01 10:00:00'));

        $admin = $this->admin();
        $investor = $this->investor();
        $project = $this->project();
        DB::table('investments')->insert([
            'user_id' => $investor->id,
            'project_id' => $project->id,
            'project_term_id' => $project->terms()->value('id'),
            'unit_count' => 1,
            'unit_price_bdt' => '1000.00',
            'amount_bdt' => '1000.00',
            'payment_currency' => 'BDT',
            'paid_amount' => '1000.00000000',
            'status' => 'confirmed',
            'confirmed_at' => '2026-04-01 00:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.profit-distributions.store'), [
            'project_id' => $project->id,
            'period_start' => '2026-04-02',
            'period_end' => '2026-04-30',
            'payout_currency' => 'BDT',
        ])->assertSessionHasErrors('period_start');

        $this->assertDatabaseCount('profit_distributions', 0);
    }

    public function test_profit_distribution_rejects_overlapping_periods(): void
    {
        $admin = $this->admin();
        $investor = $this->investor();
        $project = $this->project();
        $termId = (int) DB::table('project_terms')->where('project_id', $project->id)->value('id');
        $periodStart = today()->startOfMonth()->subMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();
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

    private function project(string $rate = '10.0000', bool $legacyAnnual = false): Project
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
            'published_at' => now()->subMinute(),
        ])->save();
        $this->usdExchangeRate();
        if ($legacyAnnual) {
            $this->term($project, 1, $rate);
        } else {
            $this->monthlyTerm($project, 1, $rate);
        }

        return $project;
    }

    private function monthlyTerm(Project $project, int $version, string $rate): int
    {
        return DB::table('project_terms')->insertGetId([
            'project_id' => $project->id,
            'version' => $version,
            'unit_price_bdt' => '1000.00',
            'monthly_rate_percent' => $rate,
            'minimum_investment_usd' => '10.00',
            'maximum_investment_usd' => '10000.00',
            'minimum_duration_months' => 3,
            'maximum_duration_months' => 24,
            'effective_from' => today()->subYear()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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

    private function approvedDeposit(User $user, string $reviewedAt): void
    {
        DB::table('deposit_requests')->insert([
            'user_id' => $user->id,
            'currency' => 'BDT',
            'amount' => '1000.00000000',
            'payment_reference' => 'referral-deposit-'.$user->id.'-'.Str::random(8),
            'status' => 'approved',
            'reviewed_at' => $reviewedAt,
            'created_at' => $reviewedAt,
            'updated_at' => $reviewedAt,
        ]);
    }

    private function paidWithdrawal(User $user, string $reviewedAt): void
    {
        DB::table('withdrawal_requests')->insert([
            'user_id' => $user->id,
            'currency' => 'BDT',
            'amount' => '100.00000000',
            'minimum_amount_snapshot' => '100.00',
            'destination' => 'Test withdrawal destination',
            'status' => 'paid',
            'payout_reference' => 'referral-withdrawal-'.$user->id.'-'.Str::random(8),
            'reviewed_at' => $reviewedAt,
            'created_at' => $reviewedAt,
            'updated_at' => $reviewedAt,
        ]);
    }

    private function confirmReferredInvestment(User $admin, User $referredInvestor, Project $project): int
    {
        $this->actingAs($referredInvestor)
            ->post(route('account.projects.invest.store', $project), [
                'units' => 1,
                'currency' => 'BDT',
                'duration_months' => 3,
            ])
            ->assertRedirect(route('account.dashboard'));

        $investmentId = (int) DB::table('investments')
            ->where('user_id', $referredInvestor->id)
            ->latest('id')
            ->value('id');

        $this->actingAs($admin)
            ->post(route('admin.investments.review', $investmentId), ['decision' => 'confirmed'])
            ->assertRedirect(route('admin.investments.index'));

        return $investmentId;
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

    private function usdExchangeRate(): void
    {
        if (DB::table('exchange_rates')->where('base_currency', 'USD')->exists()) {
            return;
        }

        DB::table('exchange_rates')->insert([
            'base_currency' => 'USD',
            'quote_currency' => 'BDT',
            'quote_per_base' => '100.00000000',
            'version' => 1,
            'effective_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
