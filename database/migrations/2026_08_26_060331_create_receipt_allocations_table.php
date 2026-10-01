<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('receipts', 'unallocated_amount')) {
            Schema::table('receipts', function (Blueprint $table) {
                $table->decimal('unallocated_amount', 15, 2)->default(0.00)->after('amount');
            });
        }

        Schema::create('receipt_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained('receipts')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->foreignId('allocated_currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->decimal('exchange_rate', 12, 6)->default(1.0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipt_allocations');

        if (Schema::hasColumn('receipts', 'unallocated_amount')) {
            Schema::table('receipts', function (Blueprint $table) {
                $table->dropColumn('unallocated_amount');
            });
        }
    }
};
