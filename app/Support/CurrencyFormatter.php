<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class CurrencyFormatter
{
    private ?string $configuredDisplayCurrency = null;

    private bool $displayCurrencyLoaded = false;

    /**
     * @var array<string, float>
     */
    private array $rates = [];

    public function displayCurrency(): string
    {
        return $this->configuredDisplayCurrency() ?? 'BDT';
    }

    public function format(int|float|string|null $amount, string $sourceCurrency = 'BDT'): string
    {
        if (! in_array($sourceCurrency, ['BDT', 'USDT', 'USD'], true)) {
            throw new InvalidArgumentException("Unsupported currency: {$sourceCurrency}");
        }

        if ($amount === null || ! is_numeric($amount)) {
            throw new InvalidArgumentException('A numeric amount is required.');
        }

        $displayCurrency = $this->displayCurrency();
        $value = (float) $amount;

        if ($sourceCurrency === $displayCurrency) {
            return $this->formatAmount($value, $displayCurrency);
        }

        if (! $this->hasConfiguredDisplayCurrency()) {
            return $this->formatAmount($value, $sourceCurrency);
        }

        $amountBdt = match ($sourceCurrency) {
            'BDT' => $value,
            'USDT' => $value * $this->exchangeRate('USDT'),
            'USD' => $value * $this->exchangeRate('USD'),
        };
        $convertedAmount = match ($displayCurrency) {
            'BDT' => $amountBdt,
            'USDT' => $amountBdt / $this->exchangeRate('USDT'),
            'USD' => $amountBdt / $this->exchangeRate('USD'),
            default => throw new InvalidArgumentException("Unsupported display currency: {$displayCurrency}"),
        };

        return $this->formatAmount($convertedAmount, $displayCurrency);
    }

    private function formatAmount(float $amount, string $currency): string
    {
        return number_format($amount, 2).' '.$currency;
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

    private function exchangeRate(string $baseCurrency): float
    {
        if (! array_key_exists($baseCurrency, $this->rates)) {
            $value = DB::table('exchange_rates')
                ->where('base_currency', $baseCurrency)
                ->where('quote_currency', 'BDT')
                ->orderByDesc('version')
                ->value('quote_per_base');

            if ($value !== null) {
                $this->rates[$baseCurrency] = (float) $value;
            }
        }

        $rate = $this->rates[$baseCurrency] ?? null;

        if ($rate === null || $rate <= 0) {
            throw new RuntimeException("A valid {$baseCurrency}-to-BDT exchange rate is required for site-wide currency conversion.");
        }

        return $rate;
    }
}
