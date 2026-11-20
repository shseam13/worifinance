<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminFinancialOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_completed_investments_separately(): void
    {
        $admin = $this->admin();
        $completedInvestor = $this->investor('Completed Investor', 'completed@example.com');
        $activeInvestor = $this->investor('Active Investor', 'active@example.com');
        $this->investment($completedInvestor, 'confirmed', '2026-05-01 10:00:00');
        $this->investment($activeInvestor, 'confirmed');

        $this->actingAs($admin)
            ->get(route('admin.investments.index', ['status' => 'completed']))
            ->assertOk()
            ->assertSee($completedInvestor->email)
            ->assertDontSee($activeInvestor->email);

        $this->get(route('admin.investments.index', ['status' => 'active']))
            ->assertOk()
            ->assertSee($activeInvestor->email)
            ->assertDontSee($completedInvestor->email);
    }

    public function test_admin_can_search_ledger_transactions_by_investor_and_view_wallet_entries(): void
    {
        $admin = $this->admin();
        $investor = $this->investor('Ledger Investor', 'ledger@example.com');
        $walletId = DB::table('wallet_accounts')->insertGetId([
            'user_id' => $investor->id,
            'currency' => 'BDT',
            'balance' => '1250.00000000',
            'reserved_balance' => '0.00000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $transactionId = DB::table('ledger_transactions')->insertGetId([
            'idempotency_key' => 'profit-report-test',
            'type' => 'profit_distribution',
            'status' => 'posted',
            'description' => 'Monthly profit credit',
            'metadata' => json_encode(['period' => '2026-04'], JSON_THROW_ON_ERROR),
            'created_by_user_id' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ledger_entries')->insert([
            'ledger_transaction_id' => $transactionId,
            'wallet_account_id' => $walletId,
            'entry_type' => 'profit_credit',
            'amount' => '250.00000000',
            'balance_after' => '1250.00000000',
            'reserved_after' => '0.00000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.transactions.index', ['search' => $investor->email, 'type' => 'profit_distribution']))
            ->assertOk()
            ->assertSee($investor->email)
            ->assertSee('profit-report-test')
            ->assertSee('250.00 BDT');

        $this->actingAs($investor)
            ->get(route('admin.transactions.index'))
            ->assertForbidden();
    }

    public function test_admin_can_filter_audit_logs_by_action_and_date(): void
    {
        $admin = $this->admin();
        DB::table('admin_audit_logs')->insert([
            [
                'admin_user_id' => $admin->id,
                'action' => 'investment.confirmed',
                'subject_type' => 'investment',
                'subject_id' => 17,
                'before_values' => json_encode(['status' => 'pending'], JSON_THROW_ON_ERROR),
                'after_values' => json_encode(['status' => 'confirmed'], JSON_THROW_ON_ERROR),
                'ip_address' => '192.0.2.1',
                'created_at' => '2026-04-15 10:00:00',
                'updated_at' => '2026-04-15 10:00:00',
            ],
            [
                'admin_user_id' => $admin->id,
                'action' => 'withdrawal_request.paid',
                'subject_type' => 'withdrawal_request',
                'subject_id' => 21,
                'before_values' => null,
                'after_values' => json_encode(['status' => 'paid'], JSON_THROW_ON_ERROR),
                'ip_address' => '192.0.2.1',
                'created_at' => '2026-05-15 10:00:00',
                'updated_at' => '2026-05-15 10:00:00',
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index', [
                'action' => 'investment.confirmed',
                'from' => '2026-04-01',
                'to' => '2026-04-30',
            ]))
            ->assertOk()
            ->assertSee('investment.confirmed')
            ->assertSee('pending')
            ->assertDontSee('withdrawal_request #21');

        $this->get(route('admin.audit-logs.index', [
            'from' => '2026-05-01',
            'to' => '2026-04-30',
        ]))->assertInvalid(['to']);
    }

    public function test_admin_can_generate_and_export_a_filtered_investment_report(): void
    {
        $admin = $this->admin();
        $completedInvestor = $this->investor('=HYPERLINK("https://example.invalid","Open")', 'report-completed@example.com');
        $activeInvestor = $this->investor('Active Report Investor', 'report-active@example.com');
        $this->investment($completedInvestor, 'confirmed', '2026-04-12 10:00:00', '2026-04-12 10:00:00');
        $this->investment($activeInvestor, 'confirmed', null, '2026-04-13 10:00:00');

        $filters = [
            'status' => 'completed',
            'from' => '2026-04-01',
            'to' => '2026-04-30',
        ];
        $this->actingAs($admin)
            ->get(route('admin.reports.investments.index', $filters))
            ->assertOk()
            ->assertSee('report-completed@example.com')
            ->assertDontSee('report-active@example.com')
            ->assertSee('1,000.00 BDT');

        $download = $this->get(route('admin.reports.investments.export', $filters));
        $download->assertDownload('investment-report-'.today()->format('Y-m-d').'.csv');
        $this->assertStringContainsString('report-completed@example.com', $download->streamedContent());
        $this->assertStringNotContainsString('report-active@example.com', $download->streamedContent());
        $this->assertStringContainsString("'=HYPERLINK", $download->streamedContent());
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_user_id' => $admin->id,
            'action' => 'investment_report.export_requested',
            'subject_type' => 'investment_report',
        ]);
    }

    public function test_investor_cannot_access_admin_reports_or_audit_logs(): void
    {
        $investor = $this->investor('Restricted Investor', 'restricted@example.com');

        $this->actingAs($investor)
            ->get(route('admin.reports.investments.index'))
            ->assertForbidden();

        $this->get(route('admin.audit-logs.index'))
            ->assertForbidden();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'super_admin', 'status' => 'active'])->save();

        return $user;
    }

    private function investor(string $name, string $email): User
    {
        $user = User::factory()->create(['name' => $name, 'email' => $email]);
        $user->forceFill(['role' => 'investor', 'status' => 'active'])->save();

        return $user;
    }

    private function investment(
        User $investor,
        string $status,
        ?string $principalReturnedAt = null,
        string $createdAt = '2026-04-10 10:00:00',
    ): int {
        $project = new Project;
        $project->forceFill([
            'slug' => Str::lower(Str::random(12)),
            'name' => 'Financial Report Project',
            'category' => 'Residential',
            'location' => 'Dhaka',
            'status' => 'published',
            'target_units' => 10,
            'available_units' => 5,
            'published_at' => now()->subMinute(),
        ])->save();
        $termId = DB::table('project_terms')->insertGetId([
            'project_id' => $project->id,
            'version' => 1,
            'unit_price_bdt' => '1000.00',
            'monthly_rate_percent' => '7.5000',
            'minimum_investment_usd' => '10.00',
            'maximum_investment_usd' => '10000.00',
            'minimum_duration_months' => 3,
            'maximum_duration_months' => 24,
            'effective_from' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('investments')->insertGetId([
            'user_id' => $investor->id,
            'project_id' => $project->id,
            'project_term_id' => $termId,
            'unit_count' => 1,
            'unit_price_bdt' => '1000.00',
            'amount_bdt' => '1000.00',
            'payment_currency' => 'BDT',
            'paid_amount' => '1000.00000000',
            'status' => $status,
            'confirmed_at' => $status === 'confirmed' ? $createdAt : null,
            'duration_months' => 6,
            'matures_at' => $principalReturnedAt !== null ? '2026-04-11 10:00:00' : null,
            'principal_returned_at' => $principalReturnedAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
