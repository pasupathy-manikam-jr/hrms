<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Interview types and rounds, interviews (+ interviewers), feedback, offer templates and offers.
     */
    public function up(): void
    {
        Schema::create('interview_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('interview_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('job_postings')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sequence_number')->default(1);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['job_id', 'sequence_number']);
        });

        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('job_postings')->cascadeOnDelete();
            $table->foreignId('round_id')->nullable()->constrained('interview_rounds')->nullOnDelete();
            $table->foreignId('interview_type_id')->nullable()->constrained()->nullOnDelete();
            $table->date('scheduled_date')->index();
            $table->time('scheduled_time');
            $table->unsignedSmallInteger('duration')->default(60);
            $table->string('location')->nullable();
            $table->string('meeting_link')->nullable();
            $table->string('status', 20)->default('Scheduled')->index();
            $table->boolean('feedback_submitted')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('interview_interviewer', function (Blueprint $table) {
            $table->foreignId('interview_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['interview_id', 'user_id']);
        });

        Schema::create('interview_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_id')->constrained()->cascadeOnDelete();
            $table->foreignId('interviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('technical_rating')->nullable();
            $table->unsignedTinyInteger('communication_rating')->nullable();
            $table->unsignedTinyInteger('cultural_fit_rating')->nullable();
            $table->unsignedTinyInteger('overall_rating');
            $table->string('recommendation', 20)->index();
            $table->text('strengths')->nullable();
            $table->text('weaknesses')->nullable();
            $table->text('comments')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('offer_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->longText('template_content');
            $table->json('variables')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('job_postings')->cascadeOnDelete();
            $table->foreignId('offer_template_id')->nullable()->constrained()->nullOnDelete();
            $table->date('offer_date');
            $table->decimal('salary', 15, 2);
            $table->decimal('bonus', 15, 2)->nullable();
            $table->text('benefits')->nullable();
            $table->date('start_date');
            $table->date('expiration_date');
            $table->string('status', 20)->default('Draft')->index();
            $table->date('response_date')->nullable();
            $table->text('decline_reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
        Schema::dropIfExists('offer_templates');
        Schema::dropIfExists('interview_feedback');
        Schema::dropIfExists('interview_interviewer');
        Schema::dropIfExists('interviews');
        Schema::dropIfExists('interview_rounds');
        Schema::dropIfExists('interview_types');
    }
};
