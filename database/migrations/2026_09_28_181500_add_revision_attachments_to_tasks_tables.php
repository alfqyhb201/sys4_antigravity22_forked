<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تشغيل التهجير.
     */
    public function up(): void
    {
        Schema::table('client_tag_distributions', function (Blueprint $table) {
            if (! Schema::hasColumn('client_tag_distributions', 'reviewer_attachments')) {
                $table->json('reviewer_attachments')->nullable()->after('reviewer_feedback');
            }
        });

        Schema::table('design_tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('design_tasks', 'revision_files')) {
                $table->json('revision_files')->nullable()->after('revision_notes');
            }
        });
    }

    /**
     * التراجع عن التهجير.
     */
    public function down(): void
    {
        Schema::table('client_tag_distributions', function (Blueprint $table) {
            if (Schema::hasColumn('client_tag_distributions', 'reviewer_attachments')) {
                $table->dropColumn('reviewer_attachments');
            }
        });

        Schema::table('design_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('design_tasks', 'revision_files')) {
                $table->dropColumn('revision_files');
            }
        });
    }
};
