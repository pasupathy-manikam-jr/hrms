<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->string('job_code', 30)->nullable()->unique();
            $table->string('title');
            $table->foreignId('job_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('job_locations')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('positions')->default(1);
            $table->decimal('min_experience', 4, 1)->default(0);
            $table->decimal('max_experience', 4, 1)->nullable();
            $table->decimal('min_salary', 12, 2)->nullable();
            $table->decimal('max_salary', 12, 2)->nullable();
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->text('benefits')->nullable();
            $table->json('skills')->nullable();
            $table->date('start_date')->nullable();
            $table->date('application_deadline')->nullable();
            $table->string('priority', 20)->default('Medium');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_published')->default(false);
            $table->date('publish_date')->nullable();
            $table->string('status', 20)->default('Draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_postings');
    }
};
