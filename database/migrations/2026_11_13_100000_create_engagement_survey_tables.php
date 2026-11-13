<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Employee Engagement Survey: HR runs campaigns over a bank of agree/disagree statements grouped into
     * elements; employees answer online; results are only ever shown as aggregates (see EngagementReport).
     */
    public function up(): void
    {
        Schema::create('engagement_elements', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('engagement_statements', function (Blueprint $table) {
            $table->id();
            // Null = an overall engagement statement that belongs to no element.
            $table->foreignId('engagement_element_id')->nullable()->constrained()->nullOnDelete();
            $table->string('text', 500);
            // Negatively worded: agreeing is the unfavourable answer.
            $table->boolean('reverse_scored')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Score bands, highest first: a score at or above min_score falls in the band.
        Schema::create('engagement_tiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('level')->unique();
            $table->string('name');
            $table->decimal('min_score', 4, 2);
            $table->string('need')->nullable();
            $table->string('profile')->nullable();
            $table->string('action')->nullable();
            $table->timestamps();
        });

        Schema::create('engagement_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->date('closes_on')->nullable();
            // A breakdown group with fewer respondents than this is hidden, so answers can't be traced to a person.
            $table->unsignedSmallInteger('min_group_size')->default(5);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // The statements a campaign asks, fixed when it is created so later bank edits don't change it.
        Schema::create('engagement_campaign_statement', function (Blueprint $table) {
            $table->foreignId('engagement_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('engagement_statement_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->primary(['engagement_campaign_id', 'engagement_statement_id']);
        });

        // One per employee per campaign. The org and demographic fields are a snapshot taken on submission.
        Schema::create('engagement_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('engagement_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gender', 20)->nullable();
            $table->string('age_band', 10)->nullable();
            $table->string('tenure_band', 10)->nullable();
            $table->timestamps();
            $table->unique(['engagement_campaign_id', 'employee_id']);
        });

        Schema::create('engagement_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('engagement_response_id')->constrained()->cascadeOnDelete();
            $table->foreignId('engagement_statement_id')->constrained()->restrictOnDelete();
            // 1 = strongly disagree … 4 = strongly agree, as answered (reverse scoring is applied in the report).
            $table->unsignedTinyInteger('rating');
            // Named explicitly: the generated name exceeds MySQL's 64-character limit.
            $table->unique(['engagement_response_id', 'engagement_statement_id'], 'engagement_answers_response_statement_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engagement_answers');
        Schema::dropIfExists('engagement_responses');
        Schema::dropIfExists('engagement_campaign_statement');
        Schema::dropIfExists('engagement_campaigns');
        Schema::dropIfExists('engagement_tiers');
        Schema::dropIfExists('engagement_statements');
        Schema::dropIfExists('engagement_elements');
    }
};
