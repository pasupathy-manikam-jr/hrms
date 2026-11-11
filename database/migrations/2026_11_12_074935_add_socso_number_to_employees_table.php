<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PERKESO identifies foreign workers by their SSFW / SSFDW number instead of an IC number.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('socso_number', 12)->nullable()->after('epf_number');
        });
    }

    public function down(): void
    {
        Schema::table('employees', fn (Blueprint $table) => $table->dropColumn('socso_number'));
    }
};
