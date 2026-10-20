<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Submissions can be corrected online and are then re-checked from the saved data, so the consent answer and each
 * salary row's line in the workbook are kept, and the last online edit is recorded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('survey_participants', function (Blueprint $table) {
            $table->boolean('consented')->nullable()->after('unionised');
            $table->timestamp('edited_at')->nullable()->after('warnings');
            $table->foreignId('edited_by')->nullable()->after('edited_at')->constrained('users')->nullOnDelete();
        });
        Schema::table('survey_salary_rows', function (Blueprint $table) {
            $table->unsignedSmallInteger('line')->nullable()->after('survey_participant_id');
        });

        // Existing submissions consented unless their errors say otherwise; rows keep their saved order as lines.
        DB::table('survey_participants')->orderBy('id')->each(function (object $participant) {
            $errors = (string) $participant->errors;
            DB::table('survey_participants')->where('id', $participant->id)->update([
                'consented' => ! str_contains($errors, 'not consented') && ! str_contains($errors, 'Consent & Submission.'),
            ]);
            DB::table('survey_salary_rows')->where('survey_participant_id', $participant->id)->orderBy('id')->pluck('id')
                ->each(fn (int $id, int $index) => DB::table('survey_salary_rows')->where('id', $id)->update(['line' => $index + 1]));
        });
    }

    public function down(): void
    {
        Schema::table('survey_salary_rows', function (Blueprint $table) {
            $table->dropColumn('line');
        });
        Schema::table('survey_participants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('edited_by');
            $table->dropColumn(['consented', 'edited_at']);
        });
    }
};
