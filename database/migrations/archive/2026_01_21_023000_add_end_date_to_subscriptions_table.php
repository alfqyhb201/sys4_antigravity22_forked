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
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->date('end_date')->nullable()->after('start_date');
        });

        // Populate existing data
        // Using raw SQL or model? Raw SQL is safer in migration usually.
        // Logic: monthly = +1 month, yearly = +1 year.
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement("UPDATE subscriptions SET end_date = date(start_date, '+1 month') WHERE subscription_type = 'monthly'");
            DB::statement("UPDATE subscriptions SET end_date = date(start_date, '+1 year') WHERE subscription_type = 'yearly'");
        } else {
            DB::statement("UPDATE subscriptions SET end_date = DATE_ADD(start_date, INTERVAL 1 MONTH) WHERE subscription_type = 'monthly'");
            DB::statement("UPDATE subscriptions SET end_date = DATE_ADD(start_date, INTERVAL 1 YEAR) WHERE subscription_type = 'yearly'");
        }
        // Default for others?
        // DB::statement("UPDATE subscriptions SET end_date = DATE_ADD(start_date, INTERVAL 1 MONTH) WHERE end_date IS NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('end_date');
        });
    }
};
