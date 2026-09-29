<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rebuild the meeting_attendees pivot with an id and the demo's RSVP/attendance columns
     * (rebuilt rather than altered: SQLite cannot add a primary key column in place), and add meeting minutes.
     */
    public function up(): void
    {
        $rows = DB::table('meeting_attendees')->get(['meeting_id', 'user_id']);
        Schema::drop('meeting_attendees');

        Schema::create('meeting_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('Required');
            $table->string('rsvp_status', 20)->default('Pending')->index();
            $table->string('attendance_status', 20)->default('Not Attended')->index();
            $table->date('rsvp_date')->nullable();
            $table->string('decline_reason')->nullable();
            $table->timestamps();
            $table->unique(['meeting_id', 'user_id']);
        });

        DB::table('meeting_attendees')->insert($rows->map(fn ($row) => (array) $row)->all());

        Schema::create('meeting_minutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->string('topic');
            $table->text('content');
            $table->string('type', 20)->default('Note')->index();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_minutes');

        $rows = DB::table('meeting_attendees')->get(['meeting_id', 'user_id']);
        Schema::drop('meeting_attendees');

        Schema::create('meeting_attendees', function (Blueprint $table) {
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['meeting_id', 'user_id']);
        });

        DB::table('meeting_attendees')->insert($rows->map(fn ($row) => (array) $row)->all());
    }
};
