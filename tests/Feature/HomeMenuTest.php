<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_home_menu_shows_home_contact_and_authentication_links(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('home').'#contact')
            ->assertSee(__('site.nav.contact_us'))
            ->assertSee(__('site.auth.login'))
            ->assertSee(__('site.auth.register'))
            ->assertDontSee(__('site.auth.logout'));
    }

    public function test_investor_home_menu_links_to_transactions_referrals_profile_and_logout(): void
    {
        $investor = User::factory()->create(['role' => 'investor']);
        $investor->forceFill(['role' => 'investor', 'status' => 'active'])->save();

        $this->actingAs($investor)
            ->get(route('home'))
            ->assertOk()
            ->assertSee(route('account.transactions.index'))
            ->assertSee(route('account.dashboard').'#referrals')
            ->assertSee(route('account.dashboard').'#profile')
            ->assertSee(route('home').'#contact')
            ->assertSee(__('site.nav.transactions'))
            ->assertSee(__('site.nav.referral_reward'))
            ->assertSee(__('site.nav.my_profile'))
            ->assertSee(__('site.auth.logout'));
    }

    public function test_admin_home_menu_uses_admin_transaction_and_referral_routes(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->forceFill(['role' => 'super_admin', 'status' => 'active'])->save();

        $this->actingAs($admin)
            ->get(route('home'))
            ->assertOk()
            ->assertSee(route('admin.transactions.index'))
            ->assertSee(route('admin.referrals.index'))
            ->assertSee(route('admin.dashboard').'#profile')
            ->assertDontSee(route('account.transactions.index'));
    }
}
