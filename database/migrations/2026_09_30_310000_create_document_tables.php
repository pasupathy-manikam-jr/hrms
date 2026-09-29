<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Documents & Contracts: categories, HR documents (+ acknowledgments), templates, contract types/templates, employee contracts.
     */
    public function up(): void
    {
        Schema::create('document_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 7)->default('#3B82F6');
            $table->boolean('is_mandatory')->default(false);
            $table->string('status')->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('hr_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('document_categories')->nullOnDelete();
            $table->string('version', 20)->default('1.0');
            $table->date('effective_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('requires_acknowledgment')->default(false);
            $table->string('status')->default('draft');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_type')->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('document_acknowledgments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('hr_documents')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->date('due_date')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->text('acknowledgment_note')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['document_id', 'user_id']);
        });

        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('document_categories')->nullOnDelete();
            $table->longText('template_content');
            $table->boolean('is_default')->default(false);
            $table->string('status')->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('contract_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('default_duration_months')->nullable();
            $table->unsignedSmallInteger('probation_period_months')->default(0);
            $table->unsignedSmallInteger('notice_period_days')->default(0);
            $table->boolean('is_renewable')->default(false);
            $table->string('status')->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('contract_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('contract_type_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('template_content');
            $table->boolean('is_default')->default(false);
            $table->string('status')->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_number')->unique();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contract_template_id')->nullable()->constrained()->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->text('terms_conditions')->nullable();
            // draft | active | terminated; "expired" is derived from end_date (see EmployeeContract).
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_contracts');
        Schema::dropIfExists('contract_templates');
        Schema::dropIfExists('contract_types');
        Schema::dropIfExists('document_templates');
        Schema::dropIfExists('document_acknowledgments');
        Schema::dropIfExists('hr_documents');
        Schema::dropIfExists('document_categories');
    }
};
