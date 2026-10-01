<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('subscriptions')->where('subscription_type', 'weekly')
            ->whereNull('end_date')
            ->chunkById(100, function ($subscriptions) {
                foreach ($subscriptions as $subscription) {
                    if ($subscription->start_date) {
                        $startDate = Carbon::parse($subscription->start_date);
                        DB::table('subscriptions')
                            ->where('id', $subscription->id)
                            ->update([
                                'end_date' => $startDate->copy()->addWeek()->toDateString(),
                            ]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot safely reverse as we don't know which were updated
    }
};
