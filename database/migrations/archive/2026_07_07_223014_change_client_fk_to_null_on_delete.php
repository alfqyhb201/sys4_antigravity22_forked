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
        // invoices: change client_id FK from cascadeOnDelete to nullOnDelete
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->foreignId('client_id')->nullable()->change()->constrained()->nullOnDelete();
        });

        // receipts: change invoice_id FK from cascadeOnDelete to nullOnDelete
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->foreignId('invoice_id')->nullable()->change()->constrained()->nullOnDelete();
        });

        // receipts: change client_id FK from cascadeOnDelete to nullOnDelete
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->foreignId('client_id')->nullable()->change()->constrained('clients')->nullOnDelete();
        });

        // contracts: change client_id FK from cascadeOnDelete to nullOnDelete
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->foreignId('client_id')->nullable()->change()->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // invoices: revert client_id FK to cascadeOnDelete
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->foreignId('client_id')->nullable(false)->change()->constrained()->cascadeOnDelete();
        });

        // receipts: revert invoice_id FK to cascadeOnDelete
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->foreignId('invoice_id')->nullable(false)->change()->constrained()->cascadeOnDelete();
        });

        // receipts: revert client_id FK to cascadeOnDelete
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->foreignId('client_id')->nullable(false)->change()->constrained('clients')->cascadeOnDelete();
        });

        // contracts: revert client_id FK to cascadeOnDelete
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->foreignId('client_id')->nullable(false)->change()->constrained()->cascadeOnDelete();
        });
    }
};
