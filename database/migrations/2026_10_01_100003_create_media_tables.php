<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Media library: flat folders (as in the demo) and privately stored files.
     */
    public function up(): void
    {
        Schema::create('media_directories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('directory_id')->nullable()->constrained('media_directories')->nullOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
        Schema::dropIfExists('media_directories');
    }
};
