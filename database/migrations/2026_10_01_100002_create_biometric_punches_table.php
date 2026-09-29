<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The employee's ID on the biometric device, and the punches imported from it.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('biometric_emp_id', 50)->nullable()->unique();
        });

        Schema::create('biometric_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('time');
            $table->timestamps();
            $table->unique(['employee_id', 'date', 'time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_punches');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['biometric_emp_id']);
            $table->dropColumn('biometric_emp_id');
        });
    }
};
