<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Performance management: indicators, goals, review cycles and reviews.
     */
    public function up(): void
    {
        Schema::create('performance_indicator_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('performance_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('performance_indicator_categories')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('measurement_unit', 50)->nullable();
            $table->string('target_value', 50)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('goal_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('employee_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goal_type_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('target')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('status', 20)->default('not_started')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('review_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('frequency', 20);
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('employee_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('review_cycle_id')->constrained()->cascadeOnDelete();
            $table->date('review_date');
            $table->date('completion_date')->nullable();
            $table->decimal('overall_rating', 3, 2)->nullable();
            $table->text('comments')->nullable();
            $table->string('status', 20)->default('scheduled')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('employee_review_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('performance_indicator_id')->constrained()->cascadeOnDelete();
            $table->decimal('rating', 2, 1);
            $table->text('comments')->nullable();
            $table->timestamps();
            $table->unique(['employee_review_id', 'performance_indicator_id'], 'review_ratings_review_indicator_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_review_ratings');
        Schema::dropIfExists('employee_reviews');
        Schema::dropIfExists('review_cycles');
        Schema::dropIfExists('employee_goals');
        Schema::dropIfExists('goal_types');
        Schema::dropIfExists('performance_indicators');
        Schema::dropIfExists('performance_indicator_categories');
    }
};
