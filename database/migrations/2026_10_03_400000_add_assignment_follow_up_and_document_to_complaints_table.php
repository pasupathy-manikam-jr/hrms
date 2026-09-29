<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The demo's complaints can be anonymous, are assigned to an investigator with a deadline,
     * record a follow-up and feedback, and carry one supporting document.
     */
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->boolean('is_anonymous')->default(false)->after('description');
            $table->foreignId('assigned_to')->nullable()->after('is_anonymous')->constrained('users')->nullOnDelete();
            $table->date('resolution_deadline')->nullable()->after('assigned_to');
            $table->text('follow_up_action')->nullable()->after('resolution_date');
            $table->date('follow_up_date')->nullable()->after('follow_up_action');
            $table->text('feedback')->nullable()->after('follow_up_date');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn([
                'is_anonymous', 'resolution_deadline', 'follow_up_action', 'follow_up_date', 'feedback',
                'file_path', 'file_name', 'file_type', 'file_size',
            ]);
        });
    }
};
