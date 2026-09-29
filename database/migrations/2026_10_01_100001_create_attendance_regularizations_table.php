<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Requests to correct a day's clock in / out, applied to attendance_records on approval.
     */
    public function up(): void
    {
        Schema::create('attendance_regularizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_record_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->time('requested_clock_in');
            $table->time('requested_clock_out')->nullable();
            $table->time('original_clock_in')->nullable();
            $table->time('original_clock_out')->nullable();
            $table->string('reason', 1000);
            $table->string('status', 20)->default('pending')->index();
            $table->text('manager_comments')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_regularizations');
    }
};
