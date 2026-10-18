<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Resets any student record originally imported via excel_upload, udise_sync,
     * or manual entry that was mistakenly marked 'success' without having been
     * submitted to or confirmed on the PARAKH HPC portal back to 'pending'.
     */
    public function up(): void
    {
        if (Schema::hasTable('students')) {
            DB::table('students')
                ->whereIn('source', ['excel_upload', 'udise_sync', 'manual'])
                ->where('status', 'success')
                ->whereNull('hpc_submitted_at')
                ->whereNull('hpc_fetched_at')
                ->update(['status' => 'pending']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
