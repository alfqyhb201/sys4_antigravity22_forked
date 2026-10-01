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
            $table->text('designer_notes')->nullable()->after('status');
            $table->string('attachment_path')->nullable()->after('designer_notes');
            $table->text('reviewer_feedback')->nullable()->after('attachment_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_tag_distributions', function (Blueprint $table) {
            $table->dropColumn(['designer_notes', 'attachment_path', 'reviewer_feedback']);
        });
    }
};
