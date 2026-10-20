<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workbooks with errors are kept (and left out of the analytics) so they can be followed up, so the
 * answers an invalid entry leaves empty become nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('survey_participants', function (Blueprint $table) {
            $table->json('errors')->nullable()->after('file_name');
            $table->string('industry')->nullable()->change();
            $table->string('state')->nullable()->change();
            $table->string('employee_band')->nullable()->change();
        });
        Schema::table('survey_salary_rows', function (Blueprint $table) {
            $table->decimal('median_salary', 12, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('survey_participants', function (Blueprint $table) {
            $table->dropColumn('errors');
        });
    }
};
