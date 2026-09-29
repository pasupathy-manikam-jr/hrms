<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Malaysian identity: MyKad (NRIC) number for citizens and PRs, passport number for foreign staff. */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('id_type', 10)->nullable()->after('gender');
            $table->string('id_number', 30)->nullable()->unique()->after('id_type');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['id_number']);
            $table->dropColumn(['id_type', 'id_number']);
        });
    }
};
