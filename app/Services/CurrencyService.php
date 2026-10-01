<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\CurrencySetting;
use App\Models\ExchangeRate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class CurrencyService
{
    /**
     * جلب العملة الأساسية للنظام.
     */
    public function getBaseCurrency(): ?Currency
    {
        return Currency::getBase() ?? Currency::first();
    }

    /**
     * جلب سعر الصرف الحديث بين عملتين.
     */
    public function getLatestRate(int $fromId, int $toId): float
    {
        if ($fromId === $toId) {
            return 1.0;
        }

        // 1. Direct/inverse rate from exchange_rates table
        $rate = ExchangeRate::getLatestRate($fromId, $toId);
        if ($rate !== null && $rate > 0) {
            return $rate;
        }

        // 2. Triangulation via base currency (from -> base -> to)
        $baseCurrency = Currency::getBase();
        if ($baseCurrency && $baseCurrency->id !== $fromId && $baseCurrency->id !== $toId) {
            $fromToBase = ExchangeRate::getLatestRate($fromId, $baseCurrency->id);
            $baseToTarget = ExchangeRate::getLatestRate($baseCurrency->id, $toId);
            if ($fromToBase !== null && $fromToBase > 0 && $baseToTarget !== null && $baseToTarget > 0) {
                return round($fromToBase * $baseToTarget, 6);
            }
        }

        // 3. Fallback to value property in Currency model (legacy USD-based rates)
        $fromCurrency = Currency::find($fromId);
        $toCurrency = Currency::find($toId);

        if ($fromCurrency && $toCurrency && $fromCurrency->value > 0 && $toCurrency->value > 0) {
            return round($toCurrency->value / $fromCurrency->value, 6);
        }

        // 4. Last resort: 1.0 with a warning
        Log::warning('CurrencyService: No exchange rate found, falling back to 1.0', [
            'from_currency_id' => $fromId,
            'to_currency_id' => $toId,
        ]);

        return 1.0;
    }

    /**
     * جلب سعر الصرف التاريخي لتاريخ محدد.
     */
    public function getRateForDate(int $fromId, int $toId, Carbon|string $date): float
    {
        if ($fromId === $toId) {
            return 1.0;
        }

        $rate = ExchangeRate::getRateForDate($fromId, $toId, $date);
        if ($rate !== null && $rate > 0) {
            return $rate;
        }

        return $this->getLatestRate($fromId, $toId);
    }

    /**
     * تحويل مبلغ من عملة لأخرى.
     */
    public function convert(float $amount, int $fromId, int $toId, ?float $rate = null, ?int $decimals = null): float
    {
        if ($fromId === $toId || $amount == 0) {
            $decimals = $decimals ?? (Currency::find($toId)?->decimal_places ?? 2);

            return round($amount, $decimals);
        }

        $actualRate = $rate ?? $this->getLatestRate($fromId, $toId);
        $converted = $amount * $actualRate;

        $targetCurrency = Currency::find($toId);
        $decimals = $decimals ?? ($targetCurrency?->decimal_places ?? 2);

        $roundingMethod = CurrencySetting::get('rounding_method', 'round');

        return match ($roundingMethod) {
            'floor' => floor($converted * pow(10, $decimals)) / pow(10, $decimals),
            'ceil' => ceil($converted * pow(10, $decimals)) / pow(10, $decimals),
            default => round($converted, $decimals),
        };
    }

    /**
     * تحويل مبلغ للعملة الأساسية.
     */
    public function convertToBase(float $amount, int $fromCurrencyId, ?float $rate = null): float
    {
        $baseCurrency = $this->getBaseCurrency();
        if (! $baseCurrency) {
            return round($amount, 2);
        }

        return $this->convert($amount, $fromCurrencyId, $baseCurrency->id, $rate);
    }

    /**
     * تنسيق المبلغ مع رمزه أو اسمه.
     */
    public function formatAmount(float $amount, ?Currency $currency = null): string
    {
        $currency = $currency ?? $this->getBaseCurrency();
        $decimals = $currency?->decimal_places ?? (int) CurrencySetting::get('default_decimal_places', 2);
        $symbol = $currency?->symbol ?? $currency?->currency ?? '';

        $formattedNumber = number_format($amount, $decimals);

        return trim("{$formattedNumber} {$symbol}");
    }
}
