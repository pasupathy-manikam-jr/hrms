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
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('break_duration')->default(0);
            $table->time('break_start_time')->nullable();
            $table->time('break_end_time')->nullable();
            $table->unsignedSmallInteger('grace_period')->default(0);
            $table->boolean('is_night_shift')->default(false)->index();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
