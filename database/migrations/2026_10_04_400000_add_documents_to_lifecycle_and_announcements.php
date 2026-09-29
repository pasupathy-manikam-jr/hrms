<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The demo attaches one supporting document to each of these records. */
    private const TABLES = ['promotions', 'transfers', 'terminations', 'announcements'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('file_path')->nullable();
                $table->string('file_name')->nullable();
                $table->string('file_type', 100)->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn(['file_path', 'file_name', 'file_type', 'file_size']);
            });
        }
    }
};
