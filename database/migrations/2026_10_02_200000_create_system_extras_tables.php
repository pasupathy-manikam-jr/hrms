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
        Schema::create('login_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('browser', 40)->nullable();
            $table->string('os', 40)->nullable();
            $table->string('device', 20)->nullable();
            $table->timestamp('logged_in_at')->index();
        });

        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('from')->nullable();
            $table->timestamps();
        });

        Schema::create('email_template_langs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('email_templates')->cascadeOnDelete();
            $table->string('lang', 10);
            $table->string('subject');
            $table->text('content');
            $table->timestamps();
            $table->unique(['parent_id', 'lang']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_template_langs');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('login_histories');
    }
};
