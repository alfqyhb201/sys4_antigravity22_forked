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
        Schema::table('tags_groups', function (Blueprint $table) {
            $table->boolean('assign_all_categories')->default(false)->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tags_groups', function (Blueprint $table) {
            $table->dropColumn('assign_all_categories');
        });
    }
};
