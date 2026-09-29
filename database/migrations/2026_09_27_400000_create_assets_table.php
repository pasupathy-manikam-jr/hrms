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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('asset_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('serial_number', 100)->nullable();
            $table->string('asset_code', 50)->nullable()->unique();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 15, 2)->default(0);
            $table->decimal('salvage_value', 15, 2)->default(0);
            $table->unsignedTinyInteger('useful_life_years')->default(5);
            // Base state only (available, under_maintenance, disposed); "assigned" comes from asset_assignments.
            $table->string('status', 20)->default('available')->index();
            $table->string('condition', 20)->default('good');
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('assigned_at');
            $table->date('returned_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['asset_id', 'returned_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_assignments');
        Schema::dropIfExists('assets');
    }
};
