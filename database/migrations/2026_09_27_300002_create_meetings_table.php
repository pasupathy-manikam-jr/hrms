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
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('type_id')->nullable()->constrained('meeting_types')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('meeting_rooms')->nullOnDelete();
            $table->date('meeting_date')->index();
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('duration')->default(60);
            $table->text('agenda')->nullable();
            $table->string('status', 20)->default('Scheduled')->index();
            $table->string('recurrence', 20)->default('None');
            $table->date('recurrence_end_date')->nullable();
            $table->foreignId('organizer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('meeting_attendees', function (Blueprint $table) {
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['meeting_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_attendees');
        Schema::dropIfExists('meetings');
    }
};
