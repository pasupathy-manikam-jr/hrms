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
        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type', 20)->index();
            $table->string('calculation_type', 20);
            $table->decimal('default_amount', 15, 2)->default(0);
            $table->decimal('percentage_of_basic', 5, 2)->nullable();
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_mandatory')->default(false);
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_components');
    }
};
