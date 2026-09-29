<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Resignations, terminations, trips and complaints (Employee Lifecycle).
     */
    public function up(): void
    {
        Schema::create('resignations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('resignation_date');
            $table->date('last_working_day');
            $table->string('notice_period', 50)->nullable();
            $table->string('reason');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('terminations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('termination_type', 50);
            $table->date('notice_date');
            $table->date('termination_date');
            $table->string('notice_period', 50)->nullable();
            $table->string('reason');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('planned')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('purpose');
            $table->string('destination');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('description')->nullable();
            $table->text('expected_outcomes')->nullable();
            $table->string('status', 20)->default('planned')->index();
            $table->decimal('advance_amount', 15, 2)->nullable();
            $table->decimal('total_expenses', 15, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('against_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('complaint_type', 50);
            $table->string('subject');
            $table->date('complaint_date');
            $table->text('description');
            $table->string('status', 30)->default('submitted')->index();
            $table->text('investigation_notes')->nullable();
            $table->text('resolution_action')->nullable();
            $table->date('resolution_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('trips');
        Schema::dropIfExists('terminations');
        Schema::dropIfExists('resignations');
    }
};
