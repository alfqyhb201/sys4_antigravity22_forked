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
        Schema::table('client_designer', function (Blueprint $table) {
            $table->foreignId('subscription_id')->nullable()->after('client_id')->constrained()->cascadeOnDelete();
        });

        // Backfill existing records with main subscription
        $records = DB::table('client_designer')->get();
        foreach ($records as $record) {
            $mainSub = DB::table('subscriptions')
                ->where('client_id', $record->client_id)
                ->where('is_main', true)
                ->first();

            if ($mainSub) {
                DB::table('client_designer')
                    ->where('id', $record->id)
                    ->update(['subscription_id' => $mainSub->id]);
            }
        }

        Schema::table('client_designer', function (Blueprint $table) {
            // Drop client_id foreign key temporarily because it relies on the unique index we are about to drop
            $table->dropForeign(['client_id']);

            // Drop old unique constraint
            $table->dropUnique('client_week_unique');

            // Add new unique constraint
            $table->unique(['subscription_id', 'week_start_date'], 'sub_week_unique');

            // Restore client_id foreign key
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_designer', function (Blueprint $table) {
            $table->dropUnique('sub_week_unique');
            $table->dropForeign(['subscription_id']);
            $table->dropColumn('subscription_id');

            // Restore old unique constraint (might fail if duplicates exist now)
            $table->unique(['client_id', 'week_start_date'], 'client_week_unique');
        });
    }
};
