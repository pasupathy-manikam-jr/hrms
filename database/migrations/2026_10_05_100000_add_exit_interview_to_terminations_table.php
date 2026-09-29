<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The demo records the exit interview when a termination's status changes.
     */
    public function up(): void
    {
        Schema::table('terminations', function (Blueprint $table) {
            $table->boolean('exit_interview_conducted')->default(false)->after('description');
            $table->date('exit_interview_date')->nullable()->after('exit_interview_conducted');
            $table->text('exit_feedback')->nullable()->after('exit_interview_date');
        });
    }

    public function down(): void
    {
        Schema::table('terminations', function (Blueprint $table) {
            $table->dropColumn(['exit_interview_conducted', 'exit_interview_date', 'exit_feedback']);
        });
    }
};
