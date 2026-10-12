<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DepositWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_investor_can_submit_deposit_request_without_wallet_credit(): void
    {
        $this->setInstructions('BDT', "Send BDT to the approved receiving account.\nUse the reference from your provider.");
        $investor = $this->investor();

        $this->actingAs($investor)
            ->get(route('account.deposits.create'))
            ->assertOk()
            ->assertSee('Send BDT to the approved receiving account.')
            ->assertSee('Transaction reference / ID');

        $this->post(route('account.deposits.store'), [
            'currency' => 'BDT',
            'amount' => '150.25',
            'payment_reference' => 'BANK-TXN-001',
            'bdt_payment_instructions_version' => 1,
        ])->assertRedirect(route('account.dashboard'));

        $this->assertDatabaseHas('deposit_requests', [
            'user_id' => $investor->id,
            'currency' => 'BDT',
            'amount' => '150.25000000',
            'bdt_amount' => '150.25',
            'payment_reference' => 'BANK-TXN-001',
            'payment_instructions_version' => 1,
            'payment_instructions_snapshot' => "Send BDT to the approved receiving account.\nUse the reference from your provider.",
            'status' => 'pending',
        ]);
        $this->assertDatabaseCount('wallet_accounts', 0);
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'deposit_request.submitted',
            'subject_type' => 'deposit_request',
        ]);
    }

    public function test_super_admin_approval_credits_wallet_and_is_idempotent(): void
    {
        $investor = $this->investor();
        $admin = $this->admin();
        $depositId = $this->createPendingDeposit($investor, 'BDT', '250.75', 'CASH-TXN-002');

        $this->actingAs($admin)
            ->get(route('admin.deposits.index'))
            ->assertOk()
            ->assertSee('CASH-TXN-002');

        $reviewUrl = route('admin.deposits.review', $depositId);
        $this->post($reviewUrl, ['decision' => 'approved'])
            ->assertRedirect(route('admin.deposits.index'));

        $this->assertDatabaseHas('deposit_requests', [
            'id' => $depositId,
            'status' => 'approved',
            'reviewed_by_user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('wallet_accounts', [
            'user_id' => $investor->id,
            'currency' => 'BDT',
            'balance' => '250.75000000',
            'reserved_balance' => '0.00000000',
        ]);
        $this->assertDatabaseHas('ledger_transactions', [
            'idempotency_key' => 'deposit-request:'.$depositId,
            'type' => 'deposit',
            'status' => 'posted',
            'created_by_user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'entry_type' => 'deposit_credit',
            'amount' => '250.75000000',
            'balance_after' => '250.75000000',
        ]);

        $this->post($reviewUrl, ['decision' => 'approved'])->assertStatus(409);
        $this->assertDatabaseCount('ledger_transactions', 1);
        $this->assertDatabaseHas('wallet_accounts', [
            'user_id' => $investor->id,
            'currency' => 'BDT',
            'balance' => '250.75000000',
        ]);
    }

    public function test_rejected_deposit_requires_a_note_and_never_credits_the_wallet(): void
    {
        $investor = $this->investor();
        $admin = $this->admin();
        $depositId = $this->createPendingDeposit($investor, 'USDT', '3.12500000', 'USDT-TXN-003');

        $this->actingAs($admin)
            ->post(route('admin.deposits.review', $depositId), ['decision' => 'rejected'])
            ->assertSessionHasErrors('review_note');

        $this->post(route('admin.deposits.review', $depositId), [
            'decision' => 'rejected',
            'review_note' => 'The transfer does not appear in the receiving wallet.',
        ])->assertRedirect(route('admin.deposits.index'));

        $this->assertDatabaseHas('deposit_requests', [
            'id' => $depositId,
            'status' => 'rejected',
            'review_note' => 'The transfer does not appear in the receiving wallet.',
        ]);
        $this->assertDatabaseCount('wallet_accounts', 0);
        $this->assertDatabaseCount('ledger_transactions', 0);
    }

    public function test_only_configured_payment_currencies_can_be_used_for_deposits(): void
    {
        $investor = $this->investor();
        $this->setInstructions('BDT', 'Send BDT using the approved instructions.');

        $this->actingAs($investor)
            ->post(route('account.deposits.store'), [
                'currency' => 'USDT',
                'amount' => '1.00000000',
                'payment_reference' => 'UNCONFIGURED-USDT',
            ])
            ->assertSessionHasErrors('currency');

        $this->assertDatabaseCount('deposit_requests', 0);
    }

    public function test_submission_rejects_payment_instructions_that_changed_after_page_load(): void
    {
        $investor = $this->investor();
        $this->setInstructions('BDT', 'Old receiving account details.');
        DB::table('platform_settings')->insert([
            'key' => 'platform.payment_instructions.bdt',
            'version' => 2,
            'value' => 'New receiving account details.',
            'currency' => 'BDT',
            'effective_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($investor)
            ->post(route('account.deposits.store'), [
                'currency' => 'BDT',
                'amount' => '50.00',
                'payment_reference' => 'OLD-ADDRESS-TXN',
                'bdt_payment_instructions_version' => 1,
            ])
            ->assertSessionHasErrors('currency');

        $this->assertDatabaseCount('deposit_requests', 0);
    }

    public function test_usdt_deposit_keeps_the_rate_snapshot_and_credits_usdt_not_bdt(): void
    {
        $this->setInstructions('USDT', 'Send USDT using the specified network and address.');
        $investor = $this->investor();
        $admin = $this->admin();
        $rateId = DB::table('exchange_rates')->insertGetId([
            'base_currency' => 'USDT',
            'quote_currency' => 'BDT',
            'quote_per_base' => '125.50000000',
            'version' => 1,
            'effective_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($investor)
            ->post(route('account.deposits.store'), [
                'currency' => 'USDT',
                'amount' => '1.23456789',
                'payment_reference' => 'USDT-TXN-004',
                'usdt_payment_instructions_version' => 1,
            ])
            ->assertRedirect(route('account.dashboard'));

        $depositId = (int) DB::table('deposit_requests')->where('payment_reference', 'USDT-TXN-004')->value('id');
        $this->assertDatabaseHas('deposit_requests', [
            'id' => $depositId,
            'currency' => 'USDT',
            'amount' => '1.23456789',
            'exchange_rate_id' => $rateId,
            'bdt_per_usdt_snapshot' => '125.50000000',
            'payment_instructions_version' => 1,
            'payment_instructions_snapshot' => 'Send USDT using the specified network and address.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.deposits.index'))
            ->assertOk()
            ->assertSee('Send USDT using the specified network and address.');

        $this->actingAs($admin)
            ->post(route('admin.deposits.review', $depositId), ['decision' => 'approved'])
            ->assertRedirect(route('admin.deposits.index'));

        $this->assertDatabaseHas('wallet_accounts', [
            'user_id' => $investor->id,
            'currency' => 'USDT',
            'balance' => '1.23456789',
        ]);
        $this->assertDatabaseHas('ledger_transactions', [
            'idempotency_key' => 'deposit-request:'.$depositId,
            'exchange_rate_id' => $rateId,
        ]);
    }

    public function test_investor_cannot_access_deposit_review_actions(): void
    {
        $investor = $this->investor();
        $depositId = $this->createPendingDeposit($investor, 'BDT', '25.00', 'ACCESS-CHECK-TXN');

        $this->actingAs($investor)
            ->get(route('admin.deposits.index'))
            ->assertForbidden();

        $this->post(route('admin.deposits.review', $depositId), ['decision' => 'approved'])
            ->assertForbidden();

        $this->assertDatabaseHas('deposit_requests', ['id' => $depositId, 'status' => 'pending']);
        $this->assertDatabaseCount('ledger_transactions', 0);
    }

    private function investor(): User
    {
        $investor = User::factory()->create();
        $investor->forceFill(['role' => 'investor', 'status' => 'active'])->save();

        return $investor;
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'super_admin', 'status' => 'active'])->save();

        return $admin;
    }

    private function setInstructions(string $currency, string $instructions): void
    {
        $key = 'platform.payment_instructions.'.strtolower($currency);
        DB::table('platform_settings')->insert([
            'key' => $key,
            'version' => 1,
            'value' => $instructions,
            'currency' => $currency,
            'effective_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createPendingDeposit(User $investor, string $currency, string $amount, string $reference): int
    {
        return DB::table('deposit_requests')->insertGetId([
            'user_id' => $investor->id,
            'currency' => $currency,
            'amount' => $amount,
            'bdt_amount' => $currency === 'BDT' ? $amount : null,
            'payment_reference' => $reference,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
