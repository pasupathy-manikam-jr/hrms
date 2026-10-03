<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Statutory payroll (EPF, SOCSO, EIS, PCB) is now calculated from the official tables and LHDN's formula,
     * which need these employee details; each payslip keeps the employee and employer shares.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('citizenship', 20)->nullable()->after('id_number');
            $table->string('marital_status', 20)->nullable()->after('citizenship');
            $table->boolean('spouse_working')->default(true)->after('marital_status');
            // Child relief units as LHDN counts them (a child studying for a diploma or degree counts as 4, etc.).
            $table->unsignedTinyInteger('tax_children')->default(0)->after('spouse_working');
            $table->boolean('tax_resident')->default(true)->after('tax_children');
            $table->string('epf_number', 20)->nullable()->after('tax_payer_id');
            $table->boolean('lindung24_opt_out')->default(false)->after('epf_number');
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->json('statutory')->nullable()->after('deductions');
        });

        // The old flat-percentage statutory components are replaced by the calculated deductions.
        DB::table('salary_components')
            ->whereIn('name', ['EPF (KWSP)', 'SOCSO (PERKESO)', 'EIS (SIP)', 'PCB (Monthly Tax Deduction)'])
            ->delete();
    }

    public function down(): void
    {
        Schema::table('payslips', fn (Blueprint $table) => $table->dropColumn('statutory'));
        Schema::table('employees', fn (Blueprint $table) => $table->dropColumn([
            'citizenship', 'marital_status', 'spouse_working', 'tax_children', 'tax_resident', 'epf_number', 'lindung24_opt_out',
        ]));
    }
};
