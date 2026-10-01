<?php

use App\Models\Currency;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $baseCurrencyId = DB::table('currencies')->where('is_base', true)->value('id');

        if (! $baseCurrencyId) {
            $firstCurrencyId = DB::table('currencies')->value('id');
            if ($firstCurrencyId) {
                DB::table('currencies')->where('id', $firstCurrencyId)->update(['is_base' => true]);
                $baseCurrencyId = $firstCurrencyId;
            }
        }

        if ($baseCurrencyId) {
            // Update invoices with null currency_id
            DB::statement("
                UPDATE invoices 
                LEFT JOIN contracts ON invoices.contract_id = contracts.id 
                SET invoices.currency_id = COALESCE(contracts.currency_id, {$baseCurrencyId}),
                    invoices.exchange_rate = COALESCE(invoices.exchange_rate, 1.000000),
                    invoices.base_currency_amount = COALESCE(invoices.base_currency_amount, invoices.total_amount)
                WHERE invoices.currency_id IS NULL
            ");

            // Update receipts with null paid_currency_id
            DB::statement("
                UPDATE receipts 
                LEFT JOIN invoices ON receipts.invoice_id = invoices.id 
                SET receipts.paid_currency_id = COALESCE(invoices.currency_id, {$baseCurrencyId}),
                    receipts.original_amount = COALESCE(receipts.original_amount, receipts.amount),
                    receipts.exchange_rate = COALESCE(receipts.exchange_rate, 1.000000),
                    receipts.base_currency_amount = COALESCE(receipts.base_currency_amount, receipts.amount)
                WHERE receipts.paid_currency_id IS NULL
            ");
        }
    }

    public function down(): void
    {
        // No down migration needed for data backfill
    }
};
