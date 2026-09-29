<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The demo's checklist items can be switched off without deleting them.
     */
    public function up(): void
    {
        Schema::table('checklist_items', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->index()->after('is_required');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_items', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
