<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WithdrawalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_investor_withdrawal_reserves_funds_and_admin_can_reject_or_mark_paid(): void
    {
        $investor = $this->investor();
        $admin = $this->admin();
        $this->setMinimum('100.00');
        $this->wallet($investor, 'BDT', '1000.00');

        $this->actingAs($investor)
            ->get(route('account.withdrawals.create'))
            ->assertOk()
            ->assertSee('Request a withdrawal')
            ->assertSee('1,000.00');

        $this->post(route('account.withdrawals.store'), [
            'currency' => 'BDT',
            'amount' => '99.99',
            'destination' => 'Bank account ending 1234',
        ])->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('withdrawal_requests', 0);

        $this->post(route('account.withdrawals.store'), [
            'currency' => 'BDT',
            'amount' => '200.00',
            'destination' => 'Bank account ending 1234',
        ])->assertRedirect(route('account.dashboard'));

        $withdrawalId = (int) DB::table('withdrawal_requests')->value('id');
        $this->assertDatabaseHas('withdrawal_requests', [
            'id' => $withdrawalId,
            'user_id' => $investor->id,
            'currency' => 'BDT',
            'amount' => '200.00000000',
            'minimum_amount_snapshot' => '100.00000000',
            'destination' => 'Bank account ending 1234',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('wallet_accounts', [
            'user_id' => $investor->id,
            'currency' => 'BDT',
            'balance' => '1000.00000000',
            'reserved_balance' => '200.00000000',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.withdrawals.index'))
            ->assertOk()
            ->assertSee($investor->email)
            ->assertSee('Bank account ending 1234');
        $this->post(route('admin.withdrawals.review', $withdrawalId), [
            'decision' => 'paid',
        ])->assertSessionHasErrors('payout_reference');

        $this->post(route('admin.withdrawals.review', $withdrawalId), [
            'decision' => 'rejected',
        ])->assertSessionHasErrors('review_note');

        $this->post(route('admin.withdrawals.review', $withdrawalId), [
            'decision' => 'rejected',
            'review_note' => 'Please update the receiving account details.',
        ])->assertRedirect(route('admin.withdrawals.index'));
        $this->assertDatabaseHas('wallet_accounts', [
            'user_id' => $investor->id,
            'currency' => 'BDT',
            'balance' => '1000.00000000',
            'reserved_balance' => '0.00000000',
        ]);
        $this->post(route('admin.withdrawals.review', $withdrawalId), [
            'decision' => 'rejected',
            'review_note' => 'Duplicate review',
        ])->assertStatus(409);

        $this->actingAs($investor)->post(route('account.withdrawals.store'), [
            'currency' => 'BDT',
            'amount' => '300.00',
            'destination' => 'Bank account ending 1234',
        ])->assertRedirect(route('account.dashboard'));
        $paidWithdrawalId = (int) DB::table('withdrawal_requests')->where('status', 'pending')->value('id');

        $this->actingAs($admin)->post(route('admin.withdrawals.review', $paidWithdrawalId), [
            'decision' => 'paid',
            'payout_reference' => 'BANK-PAYOUT-001',
        ])->assertRedirect(route('admin.withdrawals.index'));

        $this->assertDatabaseHas('withdrawal_requests', [
            'id' => $paidWithdrawalId,
            'status' => 'paid',
            'payout_reference' => 'BANK-PAYOUT-001',
            'reviewed_by_user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('wallet_accounts', [
            'user_id' => $investor->id,
            'currency' => 'BDT',
            'balance' => '700.00000000',
            'reserved_balance' => '0.00000000',
        ]);
        $this->assertDatabaseHas('ledger_transactions', [
            'idempotency_key' => 'withdrawal-review:'.$paidWithdrawalId,
            'type' => 'withdrawal',
            'created_by_user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'entry_type' => 'withdrawal_debit',
            'amount' => '-300.00000000',
            'balance_after' => '700.00000000',
        ]);
    }

    public function test_usdt_withdrawals_use_saved_rate_and_round_minimum_up(): void
    {
        $investor = $this->investor();
        $this->setMinimum('1.00');
        $rateId = DB::table('exchange_rates')->insertGetId([
            'base_currency' => 'USDT',
            'quote_currency' => 'BDT',
            'quote_per_base' => '3.00000000',
            'version' => 1,
            'effective_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->wallet($investor, 'USDT', '5.00000000');

        $this->actingAs($investor)->post(route('account.withdrawals.store'), [
            'currency' => 'USDT',
            'amount' => '0.33333333',
            'destination' => 'USDT wallet address',
            'network' => 'TRC20',
        ])->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('withdrawal_requests', 0);

        $this->post(route('account.withdrawals.store'), [
            'currency' => 'USDT',
            'amount' => '0.33333334',
            'destination' => 'USDT wallet address',
            'network' => 'TRC20',
        ])->assertRedirect(route('account.dashboard'));

        $this->assertDatabaseHas('withdrawal_requests', [
            'user_id' => $investor->id,
            'currency' => 'USDT',
            'amount' => '0.33333334',
            'minimum_amount_snapshot' => '0.33333334',
            'exchange_rate_id' => $rateId,
            'bdt_per_usdt_snapshot' => '3.00000000',
            'network' => 'TRC20',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('wallet_accounts', [
            'user_id' => $investor->id,
            'currency' => 'USDT',
            'balance' => '5.00000000',
            'reserved_balance' => '0.33333334',
        ]);
    }

    public function test_withdrawal_routes_are_role_protected(): void
    {
        $investor = $this->investor();

        $this->actingAs($investor)
            ->get(route('admin.withdrawals.index'))
            ->assertForbidden();
        $this->actingAs($investor)
            ->post(route('admin.withdrawals.review', 1), ['decision' => 'paid', 'payout_reference' => 'TX-1'])
            ->assertForbidden();
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

    private function setMinimum(string $amount): void
    {
        DB::table('platform_settings')->insert([
            'key' => 'platform.minimum_withdrawal',
            'version' => 1,
            'value' => $amount,
            'currency' => 'BDT',
            'effective_at' => now(),
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
}
