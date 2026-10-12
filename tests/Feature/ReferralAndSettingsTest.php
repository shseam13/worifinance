<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReferralAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_signup_from_a_valid_referral_link_is_attributed_and_reported(): void
    {
        $referrer = User::factory()->create();
        $referrer->forceFill(['referral_code' => 'WORIREF123'])->save();

        $this->get(route('register', ['ref' => $referrer->referral_code]))->assertOk();
        $this->post(route('register.store'), [
            'name' => 'Referred Investor',
            'email' => 'referred@example.test',
            'password' => 'referred-password-2026',
            'password_confirmation' => 'referred-password-2026',
        ])->assertRedirect(route('account.dashboard'));

        $referred = User::query()->where('email', 'referred@example.test')->firstOrFail();
        $this->assertSame($referrer->id, $referred->referred_by_user_id);
        $this->assertNotEmpty($referred->referral_code);
        $this->get(route('account.dashboard'))
            ->assertOk()
            ->assertSee(route('register', ['ref' => $referred->referral_code]));

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'super_admin', 'status' => 'active'])->save();
        $this->actingAs($admin)
            ->get(route('admin.referrals.index'))
            ->assertOk()
            ->assertSee('WORIREF123')
            ->assertSee($referrer->email);
    }

    public function test_unknown_or_inactive_referrals_are_not_attributed(): void
    {
        $inactive = User::factory()->create(['status' => 'suspended']);
        $inactive->forceFill(['referral_code' => 'INACTIVE123'])->save();

        $this->get(route('register', ['ref' => 'INVALID123']))->assertOk();
        $this->post(route('register.store'), [
            'name' => 'Direct Investor',
            'email' => 'direct@example.test',
            'password' => 'direct-password-2026',
            'password_confirmation' => 'direct-password-2026',
        ])->assertRedirect(route('account.dashboard'));

        $this->assertDatabaseHas('users', [
            'email' => 'direct@example.test',
            'referred_by_user_id' => null,
        ]);
    }

    public function test_super_admin_settings_are_versioned_and_audited(): void
    {
        $this->seed();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'super_admin', 'status' => 'active'])->save();

        $this->actingAs($admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Site-wide currency display')
            ->assertSee('Enter how many BDT equal 1 USDT.');

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'annual_fee' => '250.50',
                'minimum_withdrawal' => '500.00',
                'referral_commission_percent' => '4.2500',
                'display_currency' => 'USDT',
                'usdt_to_bdt_rate' => '123.45678901',
                'bdt_payment_instructions' => 'Send to the configured BDT account.',
                'usdt_payment_instructions' => 'USDT network: TRC20; enter the verified address.',
            ])
            ->assertRedirect(route('admin.settings.edit'));

        $this->assertDatabaseHas('platform_settings', [
            'key' => 'platform.annual_fee',
            'version' => 2,
            'value' => '250.50',
            'created_by_user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('platform_settings', [
            'key' => 'platform.minimum_withdrawal',
            'version' => 2,
            'value' => '500.00',
        ]);
        $this->assertDatabaseHas('platform_settings', [
            'key' => 'platform.referral_commission_percent',
            'version' => 2,
            'value' => '4.2500',
        ]);
        $this->assertDatabaseHas('platform_settings', [
            'key' => 'platform.display_currency',
            'version' => 1,
            'value' => 'USDT',
            'currency' => 'USDT',
        ]);
        $this->assertDatabaseHas('exchange_rates', [
            'base_currency' => 'USDT',
            'quote_currency' => 'BDT',
            'quote_per_base' => '123.45678901',
            'version' => 1,
            'created_by_user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('platform_settings', [
            'key' => 'platform.payment_instructions.bdt',
            'version' => 1,
            'value' => 'Send to the configured BDT account.',
        ]);
        $this->assertDatabaseHas('platform_settings', [
            'key' => 'platform.payment_instructions.usdt',
            'version' => 1,
            'value' => 'USDT network: TRC20; enter the verified address.',
        ]);
        $this->assertDatabaseCount('admin_audit_logs', 7);
    }

    public function test_invalid_site_currency_or_exchange_rate_is_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'super_admin', 'status' => 'active'])->save();

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'annual_fee' => '250.50',
                'minimum_withdrawal' => '500.00',
                'referral_commission_percent' => '4.2500',
                'display_currency' => 'USD',
                'usdt_to_bdt_rate' => '0',
            ])
            ->assertSessionHasErrors(['display_currency', 'usdt_to_bdt_rate']);

        $this->assertDatabaseCount('exchange_rates', 0);
        $this->assertDatabaseCount('platform_settings', 0);
    }

    public function test_investor_can_open_referral_dashboard_without_any_activity(): void
    {
        $investor = User::factory()->create();
        $investor->forceFill(['role' => 'investor', 'status' => 'active'])->save();

        $this->actingAs($investor)
            ->get(route('account.dashboard'))
            ->assertOk()
            ->assertSee('Referral program')
            ->assertSee('No accounts have registered through your referral link yet.');

        $this->assertSame(1, DB::table('users')->whereNotNull('referral_code')->count());
    }
}
