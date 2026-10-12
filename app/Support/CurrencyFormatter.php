<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class CurrencyFormatter
{
    private ?string $configuredDisplayCurrency = null;

    private bool $displayCurrencyLoaded = false;

    private ?float $rate = null;

    private bool $rateLoaded = false;

    public function displayCurrency(): string
    {
        return $this->configuredDisplayCurrency() ?? 'BDT';
    }

    public function format(int|float|string|null $amount, string $sourceCurrency = 'BDT'): string
    {
        if (! in_array($sourceCurrency, ['BDT', 'USDT'], true)) {
            throw new InvalidArgumentException("Unsupported currency: {$sourceCurrency}");
        }

        if ($amount === null || ! is_numeric($amount)) {
            throw new InvalidArgumentException('A numeric amount is required.');
        }

        $displayCurrency = $this->displayCurrency();
        $value = (float) $amount;

        if ($sourceCurrency !== $displayCurrency) {
            if (! $this->hasConfiguredDisplayCurrency()) {
                return $this->formatAmount($value, $sourceCurrency);
            }

            $rate = $this->exchangeRate();
            $value = $sourceCurrency === 'BDT'
                ? $value / $rate
                : $value * $rate;
        }

        return $this->formatAmount($value, $displayCurrency);
    }

    private function formatAmount(float $amount, string $currency): string
    {
        return number_format($amount, $currency === 'BDT' ? 2 : 4).' '.$currency;
    }

    private function configuredDisplayCurrency(): ?string
    {
        if (! $this->displayCurrencyLoaded) {
            $value = DB::table('platform_settings')
                ->where('key', 'platform.display_currency')
                ->orderByDesc('version')
                ->value('value');
            $this->configuredDisplayCurrency = $value === null ? null : (string) $value;
            $this->displayCurrencyLoaded = true;
        }

        return $this->configuredDisplayCurrency;
    }

    private function hasConfiguredDisplayCurrency(): bool
    {
        return $this->configuredDisplayCurrency() !== null;
    }

    private function exchangeRate(): float
    {
        if (! $this->rateLoaded) {
            $value = DB::table('exchange_rates')
                ->where('base_currency', 'USDT')
                ->where('quote_currency', 'BDT')
                ->orderByDesc('version')
                ->value('quote_per_base');

            if ($value !== null) {
                $this->rate = (float) $value;
            }

            $this->rateLoaded = true;
        }

        if ($this->rate === null || $this->rate <= 0) {
            throw new RuntimeException('A valid USDT-to-BDT exchange rate is required for site-wide currency conversion.');
        }

        return $this->rate;
    }
}
