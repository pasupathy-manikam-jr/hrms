<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The icon shown on the category's tile (one of DocumentCategory::ICONS). */
    public function up(): void
    {
        Schema::table('document_categories', function (Blueprint $table) {
            $table->string('icon', 30)->default('Folder')->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('document_categories', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
