<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Zakat paid through salary (it reduces PCB) and LHDN's CP38 instruction to deduct a fixed amount each month
     * until a given month.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('zakat_monthly', 15, 2)->default(0)->after('lindung24_opt_out');
            $table->decimal('cp38_monthly', 15, 2)->default(0)->after('zakat_monthly');
            $table->date('cp38_until')->nullable()->after('cp38_monthly');
        });
    }

    public function down(): void
    {
        Schema::table('employees', fn (Blueprint $table) => $table->dropColumn(['zakat_monthly', 'cp38_monthly', 'cp38_until']));
    }
};
