<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The demo's trips track the advance and the expense reimbursement separately, who approved the trip,
     * a post-trip report and one supporting document.
     */
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->string('advance_status', 20)->nullable()->after('advance_amount');
            $table->string('reimbursement_status', 20)->nullable()->after('total_expenses');
            $table->text('trip_report')->nullable()->after('reimbursement_status');
            $table->foreignId('approved_by')->nullable()->after('trip_report')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'advance_status', 'reimbursement_status', 'trip_report', 'approved_at',
                'file_path', 'file_name', 'file_type', 'file_size',
            ]);
        });
    }
};
