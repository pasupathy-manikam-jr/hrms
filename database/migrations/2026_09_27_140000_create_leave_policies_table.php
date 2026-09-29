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
        Schema::create('leave_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->string('accrual_type', 20)->default('yearly');
            $table->decimal('accrual_rate', 5, 2)->default(0);
            $table->unsignedSmallInteger('carry_forward_limit')->default(0);
            $table->unsignedSmallInteger('min_days_per_application')->default(1);
            $table->unsignedSmallInteger('max_days_per_application')->default(1);
            $table->boolean('requires_approval')->default(true);
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_policies');
    }
};
