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
        Schema::table('client_tag_distributions', function (Blueprint $table) {
            $table->foreignId('idea_id')->nullable()->constrained('ideas')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_tag_distributions', function (Blueprint $table) {
            $table->dropForeign(['idea_id']);
            $table->dropColumn('idea_id');
        });
    }
};
