<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The demo's timesheet has three statuses (pending/approved/rejected): fold draft and submitted into pending.
     */
    public function up(): void
    {
        DB::table('time_entries')->whereIn('status', ['draft', 'submitted'])->update(['status' => 'pending']);

        Schema::table('time_entries', function ($table) {
            $table->string('status', 20)->default('pending')->change();
        });
    }

    /**
     * Pending entries become submitted again (the draft/submitted split can't be recovered).
     */
    public function down(): void
    {
        DB::table('time_entries')->where('status', 'pending')->update(['status' => 'submitted']);

        Schema::table('time_entries', function ($table) {
            $table->string('status', 20)->default('draft')->change();
        });
    }
};
