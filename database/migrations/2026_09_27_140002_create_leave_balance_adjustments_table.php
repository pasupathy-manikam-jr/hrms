<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Balances are computed from leave types and applications; only the manual inputs live here.
     */
    public function up(): void
    {
        Schema::create('leave_balance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('carried_forward')->default(0);
            $table->smallInteger('manual_adjustment')->default(0);
            $table->string('adjustment_reason')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_balance_adjustments');
    }
};
