<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The demo's warnings carry an optional improvement plan and one supporting document.
     */
    public function up(): void
    {
        Schema::table('warnings', function (Blueprint $table) {
            $table->boolean('has_improvement_plan')->default(false)->after('employee_response');
            $table->text('improvement_plan_goals')->nullable()->after('has_improvement_plan');
            $table->date('improvement_plan_start_date')->nullable()->after('improvement_plan_goals');
            $table->date('improvement_plan_end_date')->nullable()->after('improvement_plan_start_date');
            $table->text('improvement_plan_progress')->nullable()->after('improvement_plan_end_date');
            $table->string('file_path')->nullable()->after('improvement_plan_progress');
            $table->string('file_name')->nullable()->after('file_path');
            $table->string('file_type', 100)->nullable()->after('file_name');
            $table->unsignedBigInteger('file_size')->nullable()->after('file_type');
        });
    }

    public function down(): void
    {
        Schema::table('warnings', function (Blueprint $table) {
            $table->dropColumn([
                'has_improvement_plan', 'improvement_plan_goals', 'improvement_plan_start_date', 'improvement_plan_end_date',
                'improvement_plan_progress', 'file_path', 'file_name', 'file_type', 'file_size',
            ]);
        });
    }
};
