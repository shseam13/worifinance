<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BinanceQrPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_upload_public_binance_qr_and_usdt_deposit_saves_its_snapshot(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                ...$this->settings(),
                'usdt_qr_code' => UploadedFile::fake()->createWithContent(
                    'binance-qr.png',
                    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII='),
                ),
            ])
            ->assertRedirect(route('admin.settings.edit'));

        $qrSetting = DB::table('platform_settings')
            ->where('key', 'platform.payment_qr.usdt')
            ->first();
        $this->assertNotNull($qrSetting);
        Storage::disk('public')->assertExists($qrSetting->value);

        $this->actingAs($admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Binance / USDT wallet QR code')
            ->assertSee(Storage::disk('public')->url($qrSetting->value));

        $investor = $this->investor();
        $this->actingAs($investor)
            ->get(route('account.deposits.create'))
            ->assertOk()
            ->assertSee('Scan this QR code in Binance or your wallet app.')
            ->assertSee(Storage::disk('public')->url($qrSetting->value));

        $this->post(route('account.deposits.store'), [
            'currency' => 'USDT',
            'amount' => '12.50000000',
            'payment_reference' => 'BINANCE-TXN-QR-001',
            'usdt_payment_instructions_version' => 1,
            'usdt_payment_qr_version' => $qrSetting->version,
        ])->assertRedirect(route('account.dashboard'));

        $this->assertDatabaseHas('deposit_requests', [
            'user_id' => $investor->id,
            'payment_reference' => 'BINANCE-TXN-QR-001',
            'payment_qr_path_snapshot' => $qrSetting->value,
            'status' => 'pending',
        ]);
    }

    public function test_invalid_qr_upload_is_rejected_without_adding_a_payment_qr_setting(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from(route('admin.settings.edit'))
            ->put(route('admin.settings.update'), [
                ...$this->settings(),
                'usdt_qr_code' => UploadedFile::fake()->create('qr.pdf', 25, 'application/pdf'),
            ])
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHasErrors('usdt_qr_code');

        $this->assertDatabaseMissing('platform_settings', ['key' => 'platform.payment_qr.usdt']);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'super_admin', 'status' => 'active'])->save();

        return $user;
    }

    private function investor(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'investor', 'status' => 'active'])->save();

        return $user;
    }

    /**
     * @return array<string, string>
     */
    private function settings(): array
    {
        return [
            'annual_fee' => '100.00',
            'minimum_withdrawal' => '100.00',
            'referral_commission_percent' => '2.0000',
            'display_currency' => 'BDT',
            'usdt_to_bdt_rate' => '120.00000000',
            'bdt_payment_instructions' => 'Send BDT to the configured account.',
            'usdt_payment_instructions' => 'Send USDT using the configured Binance wallet and network.',
        ];
    }
}
