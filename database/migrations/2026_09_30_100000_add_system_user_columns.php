<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('module')->nullable()->after('guard_name');
            $table->string('label')->nullable()->after('module');
            $table->string('description')->nullable()->after('label');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->string('label')->nullable()->after('guard_name');
            $table->string('description')->nullable()->after('label');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('permissions', fn (Blueprint $table) => $table->dropColumn(['module', 'label', 'description']));
        Schema::table('roles', fn (Blueprint $table) => $table->dropColumn(['label', 'description']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('status'));
    }
};
