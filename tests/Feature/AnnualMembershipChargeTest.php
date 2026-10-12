<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnnualMembershipChargeTest extends TestCase
{
    use RefreshDatabase;

    public function test_anniversary_charge_is_debited_once_from_available_bdt_balance(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 13)->startOfDay()->addMinutes(20));
        $investor = $this->investorWhoseAnniversaryIsToday();
        $this->setAnnualFee('250.50', now()->subDays(2));
        $this->wallet($investor, '1000.00', '100.00');

        $this->artisan('membership-charges:process')->assertSuccessful();
        $this->artisan('membership-charges:process')->assertSuccessful();

        $this->assertDatabaseCount('membership_charges', 1);
        $this->assertDatabaseHas('membership_charges', [
            'user_id' => $investor->id,
            'currency' => 'BDT',
            'amount' => '250.50000000',
            'setting_version' => 1,
            'period_start' => '2026-10-13',
            'period_end' => '2027-10-12',
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('wallet_accounts', [
            'user_id' => $investor->id,
            'currency' => 'BDT',
            'balance' => '749.50000000',
            'reserved_balance' => '100.00000000',
        ]);
        $this->assertDatabaseHas('ledger_transactions', [
            'idempotency_key' => 'membership-charge:1',
            'type' => 'membership_charge',
            'status' => 'posted',
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'entry_type' => 'membership_charge',
            'amount' => '-250.50000000',
            'balance_after' => '749.50000000',
            'reserved_after' => '100.00000000',
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'membership_charge.paid',
            'subject_type' => 'membership_charge',
        ]);
    }

    public function test_insufficient_or_reserved_funds_leave_charge_due_until_a_later_retry(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 13)->startOfDay()->addMinutes(20));
        $investor = $this->investorWhoseAnniversaryIsToday();
        $this->setAnnualFee('200.00', now()->subDays(2));

        $this->artisan('membership-charges:process')->assertSuccessful();
        $chargeId = (int) DB::table('membership_charges')->value('id');
        $this->assertDatabaseHas('membership_charges', ['id' => $chargeId, 'status' => 'due']);
        $this->assertDatabaseCount('ledger_transactions', 0);

        $this->wallet($investor, '200.00', '50.00');
        $this->artisan('membership-charges:process')->assertSuccessful();
        $this->assertDatabaseHas('membership_charges', ['id' => $chargeId, 'status' => 'due']);
        $this->assertDatabaseCount('ledger_transactions', 0);

        DB::table('wallet_accounts')->where('user_id', $investor->id)->update([
            'reserved_balance' => '0.00000000',
            'updated_at' => now(),
        ]);
        $this->artisan('membership-charges:process')->assertSuccessful();
        $this->artisan('membership-charges:process')->assertSuccessful();

        $this->assertDatabaseHas('membership_charges', ['id' => $chargeId, 'status' => 'paid']);
        $this->assertDatabaseHas('wallet_accounts', [
            'user_id' => $investor->id,
            'balance' => '0.00000000',
            'reserved_balance' => '0.00000000',
        ]);
        $this->assertDatabaseCount('ledger_transactions', 1);
        $this->assertSame('active', $investor->fresh()->status);
    }

    public function test_anniversary_charge_is_caught_up_when_scheduler_runs_more_than_thirty_days_late(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 13)->startOfDay()->addMinutes(20));
        $investor = $this->investorWhoseAnniversaryIsToday(45);
        $this->setAnnualFee('200.00', now()->subDays(46));

        $this->artisan('membership-charges:process')->assertSuccessful();

        $this->assertDatabaseHas('membership_charges', [
            'user_id' => $investor->id,
            'currency' => 'BDT',
            'amount' => '200.00000000',
            'period_start' => now()->subDays(45)->toDateString(),
            'status' => 'due',
        ]);
    }

    public function test_super_admin_can_review_annual_charge_history(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'super_admin', 'status' => 'active'])->save();
        $investor = $this->investorWhoseAnniversaryIsToday();
        DB::table('membership_charges')->insert([
            'user_id' => $investor->id,
            'currency' => 'BDT',
            'amount' => '200.00000000',
            'setting_version' => 1,
            'period_start' => '2026-10-13',
            'period_end' => '2027-10-12',
            'status' => 'due',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.membership-charges.index'))
            ->assertOk()
            ->assertSee($investor->email)
            ->assertSee('200.00')
            ->assertSee('Automatic collection and retry');
    }

    private function investorWhoseAnniversaryIsToday(int $daysPastDue = 0): User
    {
        $investor = User::factory()->create();
        $investor->forceFill(['role' => 'investor', 'status' => 'active'])->save();
        DB::table('users')->where('id', $investor->id)->update([
            'created_at' => now()->subYear()->subDays($daysPastDue)->startOfDay(),
            'updated_at' => now()->subYear()->subDays($daysPastDue)->startOfDay(),
        ]);

        return $investor;
    }

    private function setAnnualFee(string $amount, mixed $effectiveAt): void
    {
        DB::table('platform_settings')->insert([
            'key' => 'platform.annual_fee',
            'version' => 1,
            'value' => $amount,
            'currency' => 'BDT',
            'effective_at' => $effectiveAt,
            'created_at' => $effectiveAt,
            'updated_at' => $effectiveAt,
        ]);
    }

    private function wallet(User $user, string $balance, string $reserved): void
    {
        DB::table('wallet_accounts')->insert([
            'user_id' => $user->id,
            'currency' => 'BDT',
            'balance' => $balance,
            'reserved_balance' => $reserved,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
