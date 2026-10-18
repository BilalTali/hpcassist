<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Store the PARAKH portal's own internal record ID (from the /en/students list)
            if (! Schema::hasColumn('students', 'hpc_student_id')) {
                $table->string('hpc_student_id', 50)->nullable()->index()->after('udise_student_id');
            }
            // Track when this record was last fetched/confirmed from the HPC portal
            if (! Schema::hasColumn('students', 'hpc_fetched_at')) {
                $table->timestamp('hpc_fetched_at')->nullable()->after('hpc_submitted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'hpc_student_id')) {
                $table->dropIndex(['hpc_student_id']);
                $table->dropColumn('hpc_student_id');
            }
            if (Schema::hasColumn('students', 'hpc_fetched_at')) {
                $table->dropColumn('hpc_fetched_at');
            }
        });
    }
};
