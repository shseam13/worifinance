<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function edit(): View
    {
        $usdtQrPath = $this->currentSetting('platform.payment_qr.usdt', '');

        return view('admin.settings.edit', [
            'pendingDeposits' => DB::table('deposit_requests')->where('status', 'pending')->count(),
            'pendingWithdrawals' => DB::table('withdrawal_requests')->where('status', 'pending')->count(),
            'annualFee' => $this->currentSetting('platform.annual_fee', '0'),
            'minimumWithdrawal' => $this->currentSetting('platform.minimum_withdrawal', '0'),
            'referralRate' => $this->currentSetting('platform.referral_commission_percent', '0'),
            'displayCurrency' => $this->currentSetting('platform.display_currency', 'BDT'),
            'usdtToBdtRate' => $this->currentExchangeRate(),
            'bdtPaymentInstructions' => $this->currentSetting('platform.payment_instructions.bdt', ''),
            'usdtPaymentInstructions' => $this->currentSetting('platform.payment_instructions.usdt', ''),
            'usdtQrUrl' => $usdtQrPath !== '' ? Storage::disk('public')->url($usdtQrPath) : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'annual_fee' => ['required', 'numeric', 'min:0', 'max:9999999999999999.99', 'regex:/^\d{1,16}(?:\.\d{1,2})?$/'],
            'minimum_withdrawal' => ['required', 'numeric', 'min:0', 'max:9999999999999999.99', 'regex:/^\d{1,16}(?:\.\d{1,2})?$/'],
            'referral_commission_percent' => ['required', 'numeric', 'between:0,100', 'regex:/^\d{1,3}(?:\.\d{1,4})?$/'],
            'display_currency' => ['required', 'in:BDT,USDT'],
            'usdt_to_bdt_rate' => ['required', 'numeric', 'gt:0', 'max:999999999999.99999999', 'regex:/^\d{1,12}(?:\.\d{1,8})?$/'],
            'bdt_payment_instructions' => ['nullable', 'string', 'max:5000'],
            'usdt_payment_instructions' => ['nullable', 'string', 'max:5000'],
            'usdt_qr_code' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
        ]);

        $uploadedQrPath = $request->file('usdt_qr_code')?->store('payment-qr', 'public');
        if ($uploadedQrPath === false) {
            throw new \RuntimeException('Unable to store the uploaded Binance payment QR image.');
        }

        try {
            DB::transaction(function () use ($request, $validated, $uploadedQrPath): void {
                foreach ([
                    'platform.annual_fee' => ['value' => $this->fixedDecimal($validated['annual_fee'], 2), 'currency' => 'BDT'],
                    'platform.minimum_withdrawal' => ['value' => $this->fixedDecimal($validated['minimum_withdrawal'], 2), 'currency' => 'BDT'],
                    'platform.referral_commission_percent' => ['value' => $this->fixedDecimal($validated['referral_commission_percent'], 4), 'currency' => null],
                    'platform.display_currency' => ['value' => $validated['display_currency'], 'currency' => $validated['display_currency']],
                    'platform.payment_instructions.bdt' => ['value' => $validated['bdt_payment_instructions'] ?? '', 'currency' => 'BDT'],
                    'platform.payment_instructions.usdt' => ['value' => $validated['usdt_payment_instructions'] ?? '', 'currency' => 'USDT'],
                ] as $key => $setting) {
                    $version = (int) DB::table('platform_settings')
                        ->where('key', $key)
                        ->lockForUpdate()
                        ->max('version');
                    $nextVersion = $version + 1;
                    $previousValue = $version === 0
                        ? null
                        : DB::table('platform_settings')->where('key', $key)->where('version', $version)->value('value');

                    DB::table('platform_settings')->insert([
                        'key' => $key,
                        'version' => $nextVersion,
                        'value' => $setting['value'],
                        'currency' => $setting['currency'],
                        'effective_at' => now(),
                        'created_by_user_id' => $request->user()->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('admin_audit_logs')->insert([
                        'admin_user_id' => $request->user()->id,
                        'action' => 'platform_setting.updated',
                        'subject_type' => 'platform_setting',
                        'subject_id' => null,
                        'before_values' => json_encode([
                            'key' => $key,
                            'value' => $previousValue,
                        ], JSON_THROW_ON_ERROR),
                        'after_values' => json_encode(['key' => $key, 'value' => $setting['value'], 'version' => $nextVersion], JSON_THROW_ON_ERROR),
                        'ip_address' => $request->ip(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                if ($uploadedQrPath !== null) {
                    $this->createSettingVersion($request, 'platform.payment_qr.usdt', $uploadedQrPath, 'USDT');
                }

                $rateVersion = (int) DB::table('exchange_rates')
                    ->where('base_currency', 'USDT')
                    ->where('quote_currency', 'BDT')
                    ->lockForUpdate()
                    ->max('version');
                $nextRateVersion = $rateVersion + 1;
                $rate = $this->fixedDecimal($validated['usdt_to_bdt_rate'], 8);
                $previousRate = $rateVersion === 0
                    ? null
                    : DB::table('exchange_rates')
                        ->where('base_currency', 'USDT')
                        ->where('quote_currency', 'BDT')
                        ->where('version', $rateVersion)
                        ->value('quote_per_base');

                DB::table('exchange_rates')->insert([
                    'base_currency' => 'USDT',
                    'quote_currency' => 'BDT',
                    'quote_per_base' => $rate,
                    'version' => $nextRateVersion,
                    'effective_at' => now(),
                    'created_by_user_id' => $request->user()->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('admin_audit_logs')->insert([
                    'admin_user_id' => $request->user()->id,
                    'action' => 'platform_setting.updated',
                    'subject_type' => 'exchange_rate',
                    'subject_id' => null,
                    'before_values' => json_encode([
                        'pair' => 'USDT/BDT',
                        'quote_per_base' => $previousRate,
                    ], JSON_THROW_ON_ERROR),
                    'after_values' => json_encode([
                        'pair' => 'USDT/BDT',
                        'quote_per_base' => $rate,
                        'version' => $nextRateVersion,
                    ], JSON_THROW_ON_ERROR),
                    'ip_address' => $request->ip(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } catch (\Throwable $exception) {
            if ($uploadedQrPath !== null && ! Storage::disk('public')->delete($uploadedQrPath)) {
                report(new \RuntimeException("Unable to remove uncommitted payment QR image {$uploadedQrPath}.", previous: $exception));
            }

            throw $exception;
        }

        return redirect()->route('admin.settings.edit')->with('status', __('site.admin_settings.saved'));
    }

    private function currentSetting(string $key, string $default): string
    {
        return (string) (DB::table('platform_settings')
            ->where('key', $key)
            ->orderByDesc('version')
            ->value('value') ?? $default);
    }

    private function fixedDecimal(string|int|float $value, int $scale): string
    {
        [$integer, $fraction] = array_pad(explode('.', (string) $value, 2), 2, '');

        return (ltrim($integer, '0') ?: '0')
            .'.'.str_pad(substr($fraction, 0, $scale), $scale, '0');
    }

    private function currentExchangeRate(): string
    {
        return (string) (DB::table('exchange_rates')
            ->where('base_currency', 'USDT')
            ->where('quote_currency', 'BDT')
            ->orderByDesc('version')
            ->value('quote_per_base') ?? '');
    }

    private function createSettingVersion(Request $request, string $key, string $value, ?string $currency): void
    {
        $version = (int) DB::table('platform_settings')->where('key', $key)->lockForUpdate()->max('version');
        $nextVersion = $version + 1;
        $previousValue = $version === 0
            ? null
            : DB::table('platform_settings')->where('key', $key)->where('version', $version)->value('value');

        DB::table('platform_settings')->insert([
            'key' => $key,
            'version' => $nextVersion,
            'value' => $value,
            'currency' => $currency,
            'effective_at' => now(),
            'created_by_user_id' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admin_audit_logs')->insert([
            'admin_user_id' => $request->user()->id,
            'action' => 'platform_setting.updated',
            'subject_type' => 'platform_setting',
            'subject_id' => null,
            'before_values' => json_encode(['key' => $key, 'value' => $previousValue], JSON_THROW_ON_ERROR),
            'after_values' => json_encode(['key' => $key, 'value' => $value, 'version' => $nextVersion], JSON_THROW_ON_ERROR),
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
