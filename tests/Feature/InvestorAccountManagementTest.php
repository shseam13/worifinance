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
            ->assertSee('10.0000 USDT')
            ->assertSee('8.0000 USDT')
            ->assertSee('Suspend account');

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

    public function test_account_management_does_not_expose_super_admin_profiles(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->get(route('admin.investors.show', $admin->id))
            ->assertNotFound();

        $this->actingAs($admin)
            ->patch(route('admin.investors.status', $admin->id), ['status' => 'suspended'])
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
