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
        Schema::table('client_designer', function (Blueprint $table) {
            $table->dropUnique('sub_week_unique');
            $table->dropForeign(['subscription_id']);
            $table->dropColumn('subscription_id');
        });

        Schema::dropIfExists('subscription_tags');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('subscriptions');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
