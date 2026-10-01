<?php

namespace App\Filament\Traits;

use App\Models\Client;
use App\Models\Currency;
use App\Services\CurrencyService;

trait PaymentFormHelpers
{
    protected static function computeExchangeRate(?int $paymentCurrencyId, ?int $targetCurrencyId): float
    {
        if (! $paymentCurrencyId || ! $targetCurrencyId || $paymentCurrencyId === $targetCurrencyId) {
            return 1.0;
        }

        return app(CurrencyService::class)->getLatestRate($paymentCurrencyId, $targetCurrencyId);
    }

    protected static function convertAmount(?int $paymentCurrencyId, ?int $targetCurrencyId, float $originalAmount, float $exchangeRate): float
    {
        if (! $originalAmount || ! $paymentCurrencyId || ! $targetCurrencyId || $paymentCurrencyId === $targetCurrencyId) {
            return round($originalAmount, 2);
        }

        return app(CurrencyService::class)->convert($originalAmount, $paymentCurrencyId, $targetCurrencyId, $exchangeRate);
    }

    protected static function conversionHint(?int $paymentCurrencyId, ?int $targetCurrencyId): string
    {
        if (! $paymentCurrencyId || ! $targetCurrencyId) {
            return 'سيتم قيد المبلغ بعد التحويل حسب سعر الصرف.';
        }
        if ($paymentCurrencyId === $targetCurrencyId) {
            return 'نفس العملة، لا يوجد تحويل.';
        }
        $pay = Currency::find($paymentCurrencyId);
        $tar = Currency::find($targetCurrencyId);
        if (! $pay || ! $tar) {
            return '';
        }

        return "سيتم تحويل المبلغ من {$pay->currency} إلى {$tar->currency} باستخدام سعر الصرف.";
    }

    protected static function resolveTargetCurrencyId(?int $clientId): ?int
    {
        if ($clientId) {
            $client = Client::find($clientId);

            return $client?->currentContract?->currency_id ?? $client?->currency_id;
        }

        return null;
    }
}
