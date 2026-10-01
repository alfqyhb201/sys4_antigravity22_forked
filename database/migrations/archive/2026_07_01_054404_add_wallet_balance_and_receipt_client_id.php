<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('clients', 'wallet_balance')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->decimal('wallet_balance', 15, 2)->default(0.00)->after('is_pay_per_design');
            });
        }

        if (! Schema::hasColumn('receipts', 'client_id')) {
            Schema::table('receipts', function (Blueprint $table) {
                $table->foreignId('client_id')->nullable()->after('invoice_id')->constrained('clients')->cascadeOnDelete();
                $table->foreignId('invoice_id')->nullable()->change();
            });
        }

        $receipts = DB::table('receipts')->get();
        foreach ($receipts as $receipt) {
            if ($receipt->invoice_id) {
                $invoice = DB::table('invoices')->where('id', $receipt->invoice_id)->first();
                if ($invoice) {
                    DB::table('receipts')->where('id', $receipt->id)->update([
                        'client_id' => $invoice->client_id,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('wallet_balance');
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropColumn('client_id');
            $table->foreignId('invoice_id')->nullable(false)->change();
        });
    }
};
