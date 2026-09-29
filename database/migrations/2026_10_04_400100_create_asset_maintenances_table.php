<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The demo's "Schedule Maintenance" records, plus the condition noted when an asset is checked back in.
     */
    public function up(): void
    {
        Schema::create('asset_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('maintenance_type', 30);
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('cost', 15, 2)->default(0);
            $table->text('details');
            $table->string('supplier')->nullable();
            $table->timestamps();
        });

        Schema::table('asset_assignments', function (Blueprint $table) {
            $table->string('checkin_condition', 20)->nullable()->after('returned_at');
        });
    }

    public function down(): void
    {
        Schema::table('asset_assignments', function (Blueprint $table) {
            $table->dropColumn('checkin_condition');
        });
        Schema::dropIfExists('asset_maintenances');
    }
};
