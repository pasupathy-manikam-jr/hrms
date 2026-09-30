<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Salary & Benefits Benchmark Survey: the admin uploads each participating company's completed workbook;
     * every company's answers are kept per cycle and pooled into analytics.
     */
    public function up(): void
    {
        Schema::create('survey_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status', 20)->default('open');
            // A statistic is only shown when at least this many companies contribute to it.
            $table->unsignedSmallInteger('min_companies')->default(3);
            // The blank template this cycle's workbooks follow; its Lookups sheet supplies the dropdown lists.
            $table->string('template_path')->nullable();
            $table->string('template_name')->nullable();
            $table->json('lookups')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('benchmark_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_cycle_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('industry');
            $table->string('job_family');
            $table->string('title');
            $table->string('typical_level')->nullable();
            $table->text('summary')->nullable();
            $table->text('responsibilities')->nullable();
            $table->text('requirements')->nullable();
            $table->string('masco_group')->nullable();
            $table->string('masco_reference')->nullable();
            $table->unique(['survey_cycle_id', 'code']);
            $table->index(['survey_cycle_id', 'title']);
        });

        Schema::create('survey_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_cycle_id')->constrained()->cascadeOnDelete();
            $table->string('company_name');
            // Lower-cased name without "Sdn Bhd" etc., so a re-upload replaces the same company's data.
            $table->string('company_key');
            $table->string('industry');
            $table->string('state');
            $table->string('employee_band');
            $table->string('revenue_band')->nullable();
            $table->string('ownership_type')->nullable();
            $table->unsignedSmallInteger('locations')->nullable();
            $table->string('listed_status')->nullable();
            $table->string('unionised', 10)->nullable();
            $table->string('authorised_name')->nullable();
            $table->string('authorised_designation')->nullable();
            $table->date('consent_date')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->json('warnings')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['survey_cycle_id', 'company_key']);
        });

        Schema::create('survey_salary_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_participant_id')->constrained()->cascadeOnDelete();
            $table->string('job_code', 20)->nullable();
            $table->string('job_family');
            $table->string('job_title');
            $table->string('own_title')->nullable();
            $table->string('job_level');
            $table->unsignedInteger('headcount');
            $table->unsignedInteger('male')->default(0);
            $table->unsignedInteger('female')->default(0);
            $table->unsignedInteger('tenure_under_1')->default(0);
            $table->unsignedInteger('tenure_1_2')->default(0);
            $table->unsignedInteger('tenure_3_4')->default(0);
            $table->unsignedInteger('tenure_5_plus')->default(0);
            $table->string('experience_required', 20)->nullable();
            $table->boolean('shift_based')->nullable();
            $table->decimal('min_salary', 12, 2)->nullable();
            $table->decimal('max_salary', 12, 2)->nullable();
            $table->decimal('median_salary', 12, 2);
            $table->decimal('avg_male', 12, 2)->nullable();
            $table->decimal('avg_female', 12, 2)->nullable();
            $table->decimal('avg_overall', 12, 2)->nullable();
            $table->decimal('guaranteed_bonus_months', 5, 2)->nullable();
            foreach (['transport', 'meal', 'housing', 'shift', 'phone', 'overtime', 'outstation', 'other'] as $type) {
                $table->decimal("allowance_{$type}", 10, 2)->nullable();
            }
            $table->string('other_allowance_note')->nullable();
            $table->decimal('total_allowances', 10, 2)->default(0);
            $table->index(['job_title', 'job_level']);
        });

        Schema::create('survey_benefits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_participant_id')->constrained()->cascadeOnDelete();
            $table->string('item', 60);
            $table->boolean('same_for_all')->nullable();
            // Answers are Yes/No or amounts depending on the item, so they are kept as entered.
            $table->string('company_value')->nullable();
            $table->string('exec_value')->nullable();
            $table->string('manager_value')->nullable();
            $table->text('remarks')->nullable();
            $table->unique(['survey_participant_id', 'item']);
        });

        Schema::create('survey_attrition', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_participant_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('attrition_rate', 6, 2)->nullable();
            $table->decimal('new_hire_attrition_rate', 6, 2)->nullable();
            $table->unsignedInteger('retirements')->nullable();
            $table->unsignedInteger('involuntary_terminations')->nullable();
            $table->unsignedInteger('contract_non_renewals')->nullable();
            $table->string('hardest_to_hire_1')->nullable();
            $table->string('hardest_to_hire_2')->nullable();
            $table->string('hardest_to_hire_3')->nullable();
            $table->unsignedInteger('time_to_fill_days')->nullable();
            $table->string('headcount_plan', 20)->nullable();
            $table->string('hardest_to_retain_1')->nullable();
            $table->string('hardest_to_retain_2')->nullable();
            $table->string('hardest_to_retain_3')->nullable();
            $table->text('retention_initiatives')->nullable();
            $table->string('pay_reason_for_leaving', 20)->nullable();
            $table->text('other_leaving_reasons')->nullable();
            $table->boolean('retrenched')->nullable();
            $table->string('retrenchment_driver')->nullable();
            $table->string('retrenchment_above_statutory', 60)->nullable();
            $table->text('retrenchment_above_note')->nullable();
            $table->text('comments')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_attrition');
        Schema::dropIfExists('survey_benefits');
        Schema::dropIfExists('survey_salary_rows');
        Schema::dropIfExists('survey_participants');
        Schema::dropIfExists('benchmark_jobs');
        Schema::dropIfExists('survey_cycles');
    }
};
