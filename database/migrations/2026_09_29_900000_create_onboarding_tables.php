<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recruitment extras: custom questions, candidate assessments, onboarding checklists and candidate onboarding.
     */
    public function up(): void
    {
        Schema::create('custom_questions', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->string('type', 20)->default('text');
            $table->json('options')->nullable();
            $table->boolean('required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('candidate_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->string('assessment_name');
            $table->date('assessment_date');
            $table->decimal('score', 8, 2)->nullable();
            $table->decimal('max_score', 8, 2)->default(100);
            $table->string('pass_fail_status', 20)->default('Pending')->index();
            $table->text('comments')->nullable();
            $table->foreignId('conducted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('onboarding_checklists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->constrained('onboarding_checklists')->cascadeOnDelete();
            $table->string('task_name');
            $table->text('description')->nullable();
            $table->string('category', 50)->index();
            $table->string('assigned_to_role')->nullable();
            $table->unsignedSmallInteger('due_day')->default(0);
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('candidate_onboardings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('checklist_id')->nullable()->constrained('onboarding_checklists')->nullOnDelete();
            $table->date('start_date');
            $table->foreignId('buddy_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Per-onboarding copies of the checklist items; the onboarding's status and progress derive from these.
        Schema::create('candidate_onboarding_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_onboarding_id')->constrained()->cascadeOnDelete();
            $table->string('task_name');
            $table->text('description')->nullable();
            $table->string('category', 50);
            $table->string('assigned_to_role')->nullable();
            $table->unsignedSmallInteger('due_day')->default(0);
            $table->date('due_date');
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidate_onboarding_tasks');
        Schema::dropIfExists('candidate_onboardings');
        Schema::dropIfExists('checklist_items');
        Schema::dropIfExists('onboarding_checklists');
        Schema::dropIfExists('candidate_assessments');
        Schema::dropIfExists('custom_questions');
    }
};
