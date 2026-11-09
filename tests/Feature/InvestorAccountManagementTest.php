<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InvestorAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_search_and_filter_investor_accounts(): void
    {
        $admin = $this->superAdmin();
        $investor = User::factory()->create([
            'name' => 'Amina Investor',
            'email' => 'amina@example.test',
            'phone' => '01700000000',
            'referral_code' => 'AMINA1',
        ]);
        User::factory()->create(['name' => 'Suspended Investor', 'status' => 'suspended']);
        $otherAdmin = User::factory()->create(['role' => 'super_admin']);
        DB::table('wallet_accounts')->insert([
            'user_id' => $investor->id,
            'currency' => 'BDT',
            'balance' => '500.00000000',
            'reserved_balance' => '75.00000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.investors.index', ['search' => 'amina', 'status' => 'active']))
            ->assertOk()
            ->assertSee('Amina Investor')
            ->assertSee('amina@example.test')
            ->assertDontSee('Suspended Investor')
            ->assertDontSee($otherAdmin->email)
            ->assertSee('Investor accounts');

        $this->get(route('admin.investors.index', ['status' => 'suspended']))
            ->assertOk()
            ->assertSee('Suspended Investor')
            ->assertDontSee('Amina Investor');
    }

    public function test_super_admin_can_review_account_finances_and_update_profile(): void
    {
        $admin = $this->superAdmin();
        $investor = User::factory()->create(['email' => 'before@example.test']);
        DB::table('wallet_accounts')->insert([
            'user_id' => $investor->id,
            'currency' => 'USDT',
            'balance' => '10.00000000',
            'reserved_balance' => '2.00000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.investors.show', $investor->id))
            ->assertOk()
            ->assertSee('Wallet balances')
            ->assertSee('10.00 USDT')
            ->assertSee('8.00 USDT')
            ->assertSee('Suspend account')
            ->assertSee('Anonymize account')
            ->assertSee('Your current Super Admin password');

        $this->put(route('admin.investors.update', $investor->id), [
            'name' => 'Updated Investor',
            'email' => 'updated@example.test',
            'phone' => '01711111111',
        ])->assertRedirect(route('admin.investors.show', $investor->id));

        $this->assertDatabaseHas('users', [
            'id' => $investor->id,
            'name' => 'Updated Investor',
            'email' => 'updated@example.test',
            'phone' => '01711111111',
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_user_id' => $admin->id,
            'action' => 'investor_account.updated',
            'subject_type' => 'investor_account',
            'subject_id' => $investor->id,
        ]);
    }

    public function test_super_admin_can_suspend_and_reactivate_investor_with_audit_history(): void
    {
        $admin = $this->superAdmin();
        $investor = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.investors.status', $investor->id), ['status' => 'suspended'])
            ->assertRedirect(route('admin.investors.show', $investor->id));
        $this->assertDatabaseHas('users', ['id' => $investor->id, 'status' => 'suspended']);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'investor_account.status_changed',
            'subject_id' => $investor->id,
        ]);

        $this->actingAs($investor)->get(route('account.dashboard'))->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('admin.investors.status', $investor->id), ['status' => 'active'])
            ->assertRedirect(route('admin.investors.show', $investor->id));
        $this->assertDatabaseHas('users', ['id' => $investor->id, 'status' => 'active']);
        $this->assertDatabaseCount('admin_audit_logs', 2);
    }

    public function test_super_admin_can_anonymize_investor_and_preserve_financial_history(): void
    {
        $admin = $this->superAdmin();
        $admin->forceFill(['password' => 'Current-Admin-2026!'])->save();
        $investor = User::factory()->create([
            'name' => 'Private Investor',
            'email' => 'private-investor@example.test',
            'phone' => '01700000000',
            'referral_code' => 'PRIVATE1',
        ]);
        $walletId = DB::table('wallet_accounts')->insertGetId([
            'user_id' => $investor->id,
            'currency' => 'BDT',
            'balance' => '250.00000000',
            'reserved_balance' => '25.00000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $ledgerTransactionId = DB::table('ledger_transactions')->insertGetId([
            'idempotency_key' => 'INVESTOR-ANONYMIZE-LEDGER',
            'type' => 'deposit',
            'status' => 'posted',
            'description' => 'Investor deposit',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ledger_entries')->insert([
            'ledger_transaction_id' => $ledgerTransactionId,
            'wallet_account_id' => $walletId,
            'entry_type' => 'credit',
            'amount' => '250.00000000',
            'balance_after' => '250.00000000',
            'reserved_after' => '25.00000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('deposit_requests')->insert([
            'user_id' => $investor->id,
            'currency' => 'BDT',
            'amount' => '250.00000000',
            'payment_reference' => 'PRIVATE-INVESTOR-DEPOSIT',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('admin_audit_logs')->insert([
            'admin_user_id' => $admin->id,
            'action' => 'investor_account.updated',
            'subject_type' => 'investor_account',
            'subject_id' => $investor->id,
            'before_values' => json_encode(['email' => $investor->email], JSON_THROW_ON_ERROR),
            'after_values' => json_encode(['email' => 'new-private-email@example.test'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('password_reset_tokens')->insert([
            'email' => $investor->email,
            'token' => 'investor-reset-token',
            'created_at' => now(),
        ]);
        config(['session.driver' => 'database']);
        DB::table('sessions')->insert([
            'id' => 'anonymized-investor-session',
            'user_id' => $investor->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.investors.destroy', $investor->id), [
                'current_password' => 'Current-Admin-2026!',
                'confirm_anonymization' => '1',
            ])
            ->assertRedirect(route('admin.investors.show', $investor->id))
            ->assertSessionHas('status', __('site.investor_accounts.account_anonymized'));

        $this->assertDatabaseHas('users', [
            'id' => $investor->id,
            'name' => "Anonymized investor #{$investor->id}",
            'email' => "anonymized-investor-{$investor->id}@example.invalid",
            'phone' => null,
            'referral_code' => null,
            'status' => 'anonymized',
            'email_verified_at' => null,
        ]);
        $this->assertDatabaseHas('wallet_accounts', [
            'id' => $walletId,
            'balance' => '250.00000000',
            'reserved_balance' => '25.00000000',
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'ledger_transaction_id' => $ledgerTransactionId,
            'wallet_account_id' => $walletId,
            'amount' => '250.00000000',
        ]);
        $this->assertDatabaseHas('deposit_requests', [
            'user_id' => $investor->id,
            'payment_reference' => 'PRIVATE-INVESTOR-DEPOSIT',
        ]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $investor->email]);
        $this->assertDatabaseMissing('sessions', ['id' => 'anonymized-investor-session']);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'investor_account.updated',
            'subject_id' => $investor->id,
            'before_values' => null,
            'after_values' => null,
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_user_id' => $admin->id,
            'action' => 'investor_account.anonymized',
            'subject_id' => $investor->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.investors.index', ['status' => 'anonymized']))
            ->assertOk()
            ->assertSee(__('site.investor_accounts.anonymized_name', ['id' => $investor->id]))
            ->assertDontSee('private-investor@example.test');
        $this->get(route('admin.investors.show', $investor->id))
            ->assertOk()
            ->assertDontSee('Anonymize account')
            ->assertSee(__('site.investor_accounts.account_anonymized_read_only'));

        $this->patch(route('admin.investors.status', $investor->id), ['status' => 'active'])
            ->assertForbidden();
        $this->put(route('admin.investors.update', $investor->id), [
            'name' => 'Changed name',
            'email' => 'changed@example.test',
            'phone' => '01711111111',
        ])->assertForbidden();
        $this->actingAs($investor->fresh())
            ->get(route('account.dashboard'))
            ->assertForbidden();
    }

    public function test_super_admin_cannot_anonymize_investor_without_current_password_or_confirmation(): void
    {
        $admin = $this->superAdmin();
        $admin->forceFill(['password' => 'Current-Admin-2026!'])->save();
        $investor = User::factory()->create(['email' => 'unchanged@example.test']);

        $this->actingAs($admin)
            ->delete(route('admin.investors.destroy', $investor->id), [
                'current_password' => 'Wrong-Admin-Password!',
                'confirm_anonymization' => '1',
            ])
            ->assertSessionHasErrors('current_password');

        $this->delete(route('admin.investors.destroy', $investor->id), [
            'current_password' => 'Current-Admin-2026!',
        ])->assertSessionHasErrors('confirm_anonymization');

        $this->assertDatabaseHas('users', [
            'id' => $investor->id,
            'email' => 'unchanged@example.test',
            'status' => 'active',
        ]);
        $this->assertDatabaseMissing('admin_audit_logs', [
            'action' => 'investor_account.anonymized',
            'subject_id' => $investor->id,
        ]);
    }

    public function test_investor_cannot_anonymize_an_account(): void
    {
        $investor = User::factory()->create();
        $target = User::factory()->create(['email' => 'target@example.test']);

        $this->actingAs($investor)
            ->delete(route('admin.investors.destroy', $target->id), [
                'current_password' => 'password',
                'confirm_anonymization' => '1',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'email' => 'target@example.test',
            'status' => 'active',
        ]);
    }

    public function test_account_management_does_not_expose_super_admin_profiles(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->get(route('admin.investors.show', $admin->id))
            ->assertNotFound();

        $this->actingAs($admin)
            ->patch(route('admin.investors.status', $admin->id), ['status' => 'suspended'])
            ->assertNotFound();
        $this->actingAs($admin)
            ->delete(route('admin.investors.destroy', $admin->id), [
                'current_password' => 'password',
                'confirm_anonymization' => '1',
            ])
            ->assertNotFound();
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'status' => 'active']);
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'super_admin', 'status' => 'active'])->save();

        return $admin;
    }
}
