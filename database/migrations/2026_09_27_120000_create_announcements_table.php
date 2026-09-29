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
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category', 50)->index();
            $table->text('description')->nullable();
            $table->longText('content');
            $table->date('start_date')->index();
            $table->date('end_date')->nullable()->index();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_high_priority')->default(false);
            $table->boolean('is_company_wide')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('announcement_department', function (Blueprint $table) {
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->primary(['announcement_id', 'department_id']);
        });

        Schema::create('announcement_branch', function (Blueprint $table) {
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->primary(['announcement_id', 'branch_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcement_branch');
        Schema::dropIfExists('announcement_department');
        Schema::dropIfExists('announcements');
    }
};
