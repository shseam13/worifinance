<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_pages_link_to_a_non_enumerating_password_reset_form(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('password.request'));

        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee(route('password.request'));

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Enter your account email');

        $this->post(route('password.email'), ['email' => 'missing@example.test'])
            ->assertRedirect()
            ->assertSessionHas('status', __('site.password_reset.request_submitted'));
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_existing_user_receives_password_reset_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'investor@example.test']);

        $this->post(route('password.email'), ['email' => 'INVESTOR@example.test'])
            ->assertRedirect()
            ->assertSessionHas('status', __('site.password_reset.request_submitted'));

        Notification::assertSentTo($user, ResetPassword::class);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_password_reset_token_changes_password_and_cannot_be_reused(): void
    {
        $user = User::factory()->create([
            'email' => 'investor@example.test',
            'password' => 'Old-password-2026',
        ]);
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'INVESTOR@example.test',
            'password' => 'New-password-2026',
            'password_confirmation' => 'New-password-2026',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('status', __('site.password_reset.password_changed'));

        $this->assertTrue(Hash::check('New-password-2026', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);

        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'Old-password-2026',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Another-password-2026',
            'password_confirmation' => 'Another-password-2026',
        ])->assertSessionHasErrors('email');

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'New-password-2026',
        ])->assertRedirect(route('account.dashboard'));
    }

    public function test_super_admin_can_change_password_after_current_password_verification(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.test',
            'password' => 'Current-Admin-2026!',
        ]);
        $admin->forceFill(['role' => 'super_admin', 'status' => 'active'])->save();
        config(['session.driver' => 'database']);
        DB::table('sessions')->insert([
            'id' => 'other-admin-session',
            'user_id' => $admin->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.password.edit'))
            ->assertOk()
            ->assertSee('Current password')
            ->assertSee('Update password');

        $this->put(route('admin.password.update'), [
            'current_password' => 'Wrong-Current-2026!',
            'password' => 'New-Admin-Password-2026!',
            'password_confirmation' => 'New-Admin-Password-2026!',
        ])->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('Current-Admin-2026!', $admin->fresh()->password));

        $this->put(route('admin.password.update'), [
            'current_password' => 'Current-Admin-2026!',
            'password' => 'New-Admin-Password-2026!',
            'password_confirmation' => 'New-Admin-Password-2026!',
        ])->assertRedirect()
            ->assertSessionHas('status', __('site.admin_password.password_changed'));

        $this->assertTrue(Hash::check('New-Admin-Password-2026!', $admin->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'other-admin-session']);
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_user_id' => $admin->id,
            'action' => 'super_admin.password_changed',
            'subject_type' => 'user',
            'subject_id' => $admin->id,
        ]);
        $this->assertDatabaseMissing('admin_audit_logs', ['after_values' => 'New-Admin-Password-2026!']);
    }

    public function test_investor_cannot_access_super_admin_password_page(): void
    {
        $investor = User::factory()->create(['role' => 'investor']);

        $this->actingAs($investor)
            ->get(route('admin.password.edit'))
            ->assertForbidden();
    }
}
