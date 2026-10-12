<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DepositRequestController extends Controller
{
    public function create(): View
    {
        $instructions = $this->paymentInstructions();
        $qrSetting = $this->currentPaymentQrSetting();
        $enabledCurrencies = array_keys(array_filter(
            $instructions,
            static fn (array $value): bool => trim($value['text']) !== '',
        ));

        return view('account.deposits.create', [
            'pendingDeposits' => DB::table('deposit_requests')->where('status', 'pending')->count(),
            'pendingWithdrawals' => DB::table('withdrawal_requests')->where('status', 'pending')->count(),
            'instructions' => $instructions,
            'enabledCurrencies' => $enabledCurrencies,
            'usdtQrUrl' => $qrSetting?->value ? Storage::disk('public')->url($qrSetting->value) : null,
            'usdtQrVersion' => (int) ($qrSetting->version ?? 0),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $instructions = $this->paymentInstructions();
        $enabledCurrencies = array_keys(array_filter(
            $instructions,
            static fn (array $value): bool => trim($value['text']) !== '',
        ));

        $validated = $request->validate([
            'currency' => ['required', Rule::in($enabledCurrencies)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999.99'],
            'payment_reference' => ['required', 'string', 'max:150', 'unique:deposit_requests,payment_reference'],
            'bdt_payment_instructions_version' => ['nullable', 'integer', 'min:0'],
            'usdt_payment_instructions_version' => ['nullable', 'integer', 'min:0'],
            'usdt_payment_qr_version' => ['nullable', 'integer', 'min:0'],
        ]);

        $amountPattern = $validated['currency'] === 'BDT'
            ? '/^\d{1,16}(?:\.\d{1,2})?$/'
            : '/^\d{1,16}(?:\.\d{1,8})?$/';

        if (! preg_match($amountPattern, $validated['amount'])) {
            throw ValidationException::withMessages([
                'amount' => __('site.deposits.amount_precision'),
            ]);
        }

        DB::transaction(function () use ($request, $validated): void {
            $instructionKey = 'platform.payment_instructions.'.strtolower($validated['currency']);
            $postedInstructionVersion = $validated[strtolower($validated['currency']).'_payment_instructions_version'] ?? null;
            $instructionSetting = DB::table('platform_settings')
                ->where('key', $instructionKey)
                ->orderByDesc('version')
                ->lockForUpdate()
                ->first();

            if (
                $instructionSetting === null
                || trim((string) $instructionSetting->value) === ''
                || (int) $instructionSetting->version !== (int) $postedInstructionVersion
            ) {
                throw ValidationException::withMessages([
                    'currency' => __('site.deposits.instructions_changed'),
                ]);
            }

            $qrSetting = null;
            if ($validated['currency'] === 'USDT') {
                $qrSetting = $this->currentPaymentQrSetting(lockForUpdate: true);
                $postedQrVersion = $validated['usdt_payment_qr_version'] ?? 0;
                if ((int) ($qrSetting->version ?? 0) !== (int) $postedQrVersion) {
                    throw ValidationException::withMessages([
                        'currency' => __('site.deposits.qr_changed'),
                    ]);
                }
            }

            $exchangeRate = null;

            if ($validated['currency'] === 'USDT') {
                $exchangeRate = DB::table('exchange_rates')
                    ->where('base_currency', 'USDT')
                    ->where('quote_currency', 'BDT')
                    ->orderByDesc('version')
                    ->lockForUpdate()
                    ->first();

                if ($exchangeRate === null || (float) $exchangeRate->quote_per_base <= 0) {
                    throw ValidationException::withMessages([
                        'currency' => __('site.deposits.rate_unavailable'),
                    ]);
                }
            }

            $depositId = DB::table('deposit_requests')->insertGetId([
                'user_id' => $request->user()->id,
                'currency' => $validated['currency'],
                'amount' => $validated['amount'],
                'bdt_amount' => $validated['currency'] === 'BDT' ? $validated['amount'] : null,
                'exchange_rate_id' => $exchangeRate?->id,
                'bdt_per_usdt_snapshot' => $exchangeRate?->quote_per_base,
                'payment_reference' => $validated['payment_reference'],
                'payment_instructions_version' => $instructionSetting->version,
                'payment_instructions_snapshot' => $instructionSetting->value,
                'payment_qr_path_snapshot' => $qrSetting?->value,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('admin_audit_logs')->insert([
                'admin_user_id' => null,
                'action' => 'deposit_request.submitted',
                'subject_type' => 'deposit_request',
                'subject_id' => $depositId,
                'before_values' => null,
                'after_values' => json_encode([
                    'user_id' => $request->user()->id,
                    'currency' => $validated['currency'],
                    'amount' => $validated['amount'],
                    'payment_reference' => $validated['payment_reference'],
                    'payment_instructions_version' => $instructionSetting->version,
                    'payment_qr_path_snapshot' => $qrSetting?->value,
                    'status' => 'pending',
                ], JSON_THROW_ON_ERROR),
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('account.dashboard')->with('status', __('site.deposits.submitted'));
    }

    /**
     * @return array{BDT: array{text: string, version: int}, USDT: array{text: string, version: int}}
     */
    private function paymentInstructions(): array
    {
        $settings = DB::table('platform_settings')
            ->whereIn('key', ['platform.payment_instructions.bdt', 'platform.payment_instructions.usdt'])
            ->orderByDesc('version')
            ->get()
            ->unique('key')
            ->keyBy('key');

        $result = [];

        foreach (['BDT' => 'bdt', 'USDT' => 'usdt'] as $currency => $suffix) {
            $setting = $settings->get('platform.payment_instructions.'.$suffix);
            $result[$currency] = [
                'text' => (string) ($setting->value ?? ''),
                'version' => (int) ($setting->version ?? 0),
            ];
        }

        return $result;
    }

    private function currentPaymentQrSetting(bool $lockForUpdate = false): ?object
    {
        $query = DB::table('platform_settings')
            ->where('key', 'platform.payment_qr.usdt')
            ->orderByDesc('version');
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }
}
