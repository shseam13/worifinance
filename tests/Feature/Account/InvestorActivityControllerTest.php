<?php

namespace Tests\Feature\Account;

use App\Models\Project;
use App\Models\ProjectTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvestorActivityControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_profit_wallet_separates_posted_earnings_from_available_wallet_funds(): void
    {
        $investor = $this->createInvestor();
        $otherInvestor = $this->createInvestor();
        [$project, $term] = $this->createProjectAndTerm('Investor Wallet Project');
        $investmentId = $this->createInvestment($investor, $project, $term);
        $otherInvestmentId = $this->createInvestment($otherInvestor, $project, $term);
        $this->createWallet($investor, 'BDT', 100, 20);
        $this->createWallet($otherInvestor, 'BDT', 900, 0);

        $postedDistribution = $this->createDistribution($project, $term, 'posted', '2026-01-01', '2026-01-31', 'BDT');
        $approvedDistribution = $this->createDistribution($project, $term, 'approved', '2026-02-01', '2026-02-28', 'BDT');
        $otherDistribution = $this->createDistribution($project, $term, 'posted', '2026-03-01', '2026-03-31', 'BDT');
        $this->createDistributionItem($postedDistribution, $investmentId, 3.25, 'BDT');
        $this->createDistributionItem($approvedDistribution, $investmentId, 99, 'BDT');
        $this->createDistributionItem($otherDistribution, $otherInvestmentId, 88, 'BDT');

        $this->actingAs($investor)
            ->get(route('account.profit-wallet'))
            ->assertOk()
            ->assertSee('3.25 BDT')
            ->assertSee('80.00 BDT')
            ->assertDontSee('99.00 BDT')
            ->assertDontSee('88.00 BDT');
    }

    public function test_monthly_profit_history_filters_to_posted_distributions_for_the_investor(): void
    {
        $investor = $this->createInvestor();
        $otherInvestor = $this->createInvestor();
        [$project, $term] = $this->createProjectAndTerm('Monthly History Project');
        $investmentId = $this->createInvestment($investor, $project, $term);
        $otherInvestmentId = $this->createInvestment($otherInvestor, $project, $term);
        $bdtDistribution = $this->createDistribution($project, $term, 'posted', '2026-01-01', '2026-01-31', 'BDT');
        $usdtDistribution = $this->createDistribution($project, $term, 'posted', '2026-02-01', '2026-02-28', 'USDT');
        $otherDistribution = $this->createDistribution($project, $term, 'posted', '2026-03-01', '2026-03-31', 'USDT');
        $this->createDistributionItem($bdtDistribution, $investmentId, 3.25, 'BDT');
        $this->createDistributionItem($usdtDistribution, $investmentId, 2.5, 'USDT');
        $this->createDistributionItem($otherDistribution, $otherInvestmentId, 88, 'USDT');

        $this->actingAs($investor)
            ->get(route('account.profit-history', ['currency' => 'USDT']))
            ->assertOk()
            ->assertSee('2.50 USDT')
            ->assertSee('Feb 28, 2026')
            ->assertDontSee('Jan 31, 2026')
            ->assertDontSee('Mar 31, 2026');
    }

    public function test_transaction_history_only_shows_entries_for_the_investors_wallets(): void
    {
        $investor = $this->createInvestor();
        $otherInvestor = $this->createInvestor();
        $investorWalletId = $this->createWallet($investor, 'BDT', 100, 0);
        $otherWalletId = $this->createWallet($otherInvestor, 'BDT', 900, 0);
        $investorTransactionId = $this->createLedgerTransaction('OWN-LEDGER-DESCRIPTION');
        $otherTransactionId = $this->createLedgerTransaction('OTHER-LEDGER-DESCRIPTION');
        $this->createLedgerEntry($investorTransactionId, $investorWalletId, 25, 100);
        $this->createLedgerEntry($otherTransactionId, $otherWalletId, 75, 900);

        $this->actingAs($investor)
            ->get(route('account.transactions.index'))
            ->assertOk()
            ->assertSee('OWN-LEDGER-DESCRIPTION')
            ->assertDontSee('OTHER-LEDGER-DESCRIPTION');
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

    private function createInvestment(User $investor, Project $project, ProjectTerm $term): int
    {
        $now = now();

        return DB::table('investments')->insertGetId([
            'user_id' => $investor->id,
            'project_id' => $project->id,
            'project_term_id' => $term->id,
            'unit_count' => 1,
            'unit_price_bdt' => 1000,
            'amount_bdt' => 1000,
            'payment_currency' => 'BDT',
            'paid_amount' => 1000,
            'duration_months' => 6,
            'status' => 'confirmed',
            'confirmed_at' => $now,
            'matures_at' => $now->copy()->addMonths(6),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function createDistribution(Project $project, ProjectTerm $term, string $status, string $periodStart, string $periodEnd, string $currency): int
    {
        return DB::table('profit_distributions')->insertGetId([
            'project_id' => $project->id,
            'project_term_id' => $term->id,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'annual_rate_snapshot' => 5,
            'monthly_rate_snapshot' => 2.5,
            'payout_currency' => $currency,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createDistributionItem(int $distributionId, int $investmentId, float $amount, string $currency): void
    {
        DB::table('distribution_items')->insert([
            'profit_distribution_id' => $distributionId,
            'investment_id' => $investmentId,
            'eligible_principal_bdt' => 1000,
            'annual_rate_snapshot' => 5,
            'monthly_rate_snapshot' => 2.5,
            'eligible_days' => 30,
            'payout_currency' => $currency,
            'amount' => $amount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createWallet(User $investor, string $currency, float $balance, float $reserved): int
    {
        return DB::table('wallet_accounts')->insertGetId([
            'user_id' => $investor->id,
            'currency' => $currency,
            'balance' => $balance,
            'reserved_balance' => $reserved,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createLedgerTransaction(string $description): int
    {
        return DB::table('ledger_transactions')->insertGetId([
            'idempotency_key' => Str::uuid()->toString(),
            'type' => 'deposit',
            'status' => 'posted',
            'description' => $description,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createLedgerEntry(int $transactionId, int $walletId, float $amount, float $balance): void
    {
        DB::table('ledger_entries')->insert([
            'ledger_transaction_id' => $transactionId,
            'wallet_account_id' => $walletId,
            'entry_type' => 'credit',
            'amount' => $amount,
            'balance_after' => $balance,
            'reserved_after' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
