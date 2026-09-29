<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Promotions, transfers and warnings (Employee Lifecycle).
     */
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('previous_designation_id')->nullable()->constrained('designations')->nullOnDelete();
            $table->foreignId('designation_id')->constrained()->cascadeOnDelete();
            $table->date('promotion_date');
            $table->date('effective_date');
            $table->decimal('salary_adjustment', 15, 2)->nullable();
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('to_branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('from_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('to_department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('from_designation_id')->nullable()->constrained('designations')->nullOnDelete();
            $table->foreignId('to_designation_id')->constrained('designations')->cascadeOnDelete();
            $table->date('transfer_date');
            $table->date('effective_date');
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warning_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('warning_type', 30);
            $table->string('subject');
            $table->string('severity', 20);
            $table->date('warning_date');
            $table->date('expiry_date')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->date('acknowledgment_date')->nullable();
            $table->text('employee_response')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warnings');
        Schema::dropIfExists('transfers');
        Schema::dropIfExists('promotions');
    }
};
