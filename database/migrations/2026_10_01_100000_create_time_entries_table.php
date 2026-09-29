<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Timesheet: hours an employee logged against a project on a day.
     */
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('project')->nullable();
            $table->text('description');
            $table->decimal('hours', 5, 2);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_billable')->default(false);
            $table->string('status', 20)->default('draft')->index();
            $table->text('manager_comments')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
