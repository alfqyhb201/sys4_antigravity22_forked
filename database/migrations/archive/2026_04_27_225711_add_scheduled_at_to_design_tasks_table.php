<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إضافة حقل الجدولة لمهام التصميم.
     */
    public function up(): void
    {
        Schema::table('design_tasks', function (Blueprint $table) {
            $table->timestamp('scheduled_at')->nullable()->after('status');
        });
    }

    /**
     * التراجع عن الـ Migration.
     */
    public function down(): void
    {
        Schema::table('design_tasks', function (Blueprint $table) {
            $table->dropColumn('scheduled_at');
        });
    }
};
