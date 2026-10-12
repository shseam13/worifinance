<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_registration_and_admin_login_pages_are_available(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Welcome back.');
        $this->get(route('admin.login'))->assertOk()->assertSee('Super Admin login');
        $this->get(route('register'))->assertOk()->assertSee('Create investor account');
    }

    public function test_registration_creates_only_an_investor_account_and_logs_them_in(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test Investor',
            'email' => 'INVESTOR@example.test',
            'phone' => '01234567890',
            'password' => 'investor-password-2026',
            'password_confirmation' => 'investor-password-2026',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
        $response->assertRedirect(route('account.dashboard'))
            ->assertSessionHas(Auth::guard()->getName());

        $user = User::query()->where('email', 'investor@example.test')->firstOrFail();

        $this->assertSame('investor', $user->role);
        $this->assertSame('01234567890', $user->phone);
        $this->assertAuthenticatedAs($user);
        $this->get(route('account.dashboard'))->assertOk()->assertSee('Your investor workspace');
        $this->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_investors_cannot_use_the_super_admin_login_portal(): void
    {
        $user = User::factory()->create([
            'email' => 'investor@example.test',
            'password' => 'investor-password',
        ]);

        $this->from(route('admin.login'))
            ->post(route('admin.login.store'), [
                'email' => $user->email,
                'password' => 'investor-password',
            ])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_investor_can_sign_in_and_sign_out(): void
    {
        $user = User::factory()->create([
            'email' => 'investor@example.test',
            'password' => 'investor-password',
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'investor-password',
        ])->assertRedirect(route('account.dashboard'));

        $this->get(route('account.dashboard'))->assertOk();

        $this->post(route('logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->get(route('account.dashboard'))->assertRedirect(route('login'));
    }

    public function test_suspended_accounts_cannot_sign_in(): void
    {
        $user = User::factory()->create([
            'email' => 'suspended@example.test',
            'password' => 'investor-password',
            'status' => 'suspended',
        ]);

        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'investor-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_suspended_accounts_lose_access_to_an_existing_session(): void
    {
        $user = User::factory()->create([
            'role' => 'investor',
            'status' => 'suspended',
        ]);

        $this->actingAs($user)
            ->get(route('account.dashboard'))
            ->assertForbidden();
    }

    public function test_super_admin_can_login_and_view_the_admin_summary(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.test',
            'password' => 'admin-password',
        ]);
        $admin->forceFill(['role' => 'super_admin'])->save();

        User::factory()->create();
        User::factory()->create(['role' => 'investor', 'status' => 'suspended']);

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'admin-password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('admin-workspace')
            ->assertSee('admin-sidebar')
            ->assertSee('Investor management')
            ->assertSee('Platform configuration')
            ->assertDontSee('<footer class="site-footer">')
            ->assertSee('Super Admin dashboard')
            ->assertSee('Investor accounts')
            ->assertSee('Confirmed investment volume');

        $this->get(route('account.dashboard'))->assertForbidden();
    }

    public function test_super_admin_creation_command_provisions_an_admin_securely(): void
    {
        $this->artisan('app:create-super-admin')
            ->expectsQuestion('Super Admin name', 'Site Owner')
            ->expectsQuestion('Super Admin email', 'owner@example.test')
            ->expectsQuestion('Password (at least 12 characters, mixed case, number, and symbol)', 'WoriAdmin!2026')
            ->expectsQuestion('Confirm password', 'WoriAdmin!2026')
            ->expectsOutput('Super Admin account created successfully.')
            ->assertExitCode(0);

        $admin = User::query()->where('email', 'owner@example.test')->firstOrFail();
        $this->assertSame('super_admin', $admin->role);
        $this->assertTrue(Hash::check('WoriAdmin!2026', $admin->password));
    }
}
