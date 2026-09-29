<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Training & Development: types, programs, sessions, employee trainings, assessments and results.
     */
    public function up(): void
    {
        Schema::create('training_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('department_training_type', function (Blueprint $table) {
            $table->foreignId('training_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->primary(['training_type_id', 'department_id']);
        });

        Schema::create('training_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_type_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration')->nullable();
            $table->decimal('cost', 15, 2)->default(0);
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->string('prerequisites')->nullable();
            $table->boolean('is_mandatory')->default(false);
            $table->boolean('is_self_enrollment')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_program_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->string('location_type', 20)->default('physical');
            $table->string('location')->nullable();
            $table->string('meeting_link')->nullable();
            $table->string('status', 20)->default('scheduled')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('training_session_trainers', function (Blueprint $table) {
            $table->foreignId('training_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->primary(['training_session_id', 'employee_id']);
        });

        Schema::create('employee_trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('assigned')->index();
            $table->date('assigned_date');
            $table->date('completion_date')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->boolean('certification')->default(false);
            $table->text('feedback')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'training_program_id']);
        });

        Schema::create('training_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_program_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type', 20)->index();
            $table->decimal('passing_score', 5, 2);
            $table->text('criteria')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('training_assessment_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_training_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_assessment_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 5, 2);
            $table->boolean('is_passed');
            $table->text('feedback')->nullable();
            $table->date('assessment_date');
            $table->foreignId('assessed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_training_id', 'training_assessment_id'], 'training_results_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_assessment_results');
        Schema::dropIfExists('training_assessments');
        Schema::dropIfExists('employee_trainings');
        Schema::dropIfExists('training_session_trainers');
        Schema::dropIfExists('training_sessions');
        Schema::dropIfExists('training_programs');
        Schema::dropIfExists('department_training_type');
        Schema::dropIfExists('training_types');
    }
};
