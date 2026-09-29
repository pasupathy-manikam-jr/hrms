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
        Schema::create('attendance_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('late_arrival_grace')->default(0);
            $table->unsignedSmallInteger('early_departure_grace')->default(0);
            $table->decimal('half_day_threshold', 5, 2)->default(0);
            $table->decimal('overtime_rate_per_hour', 10, 2)->default(0);
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_policies');
    }
};
