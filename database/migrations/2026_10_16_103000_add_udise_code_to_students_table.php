<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'udise_code')) {
                $table->string('udise_code', 20)->nullable()->after('school_id')->index();
                $table->index(['udise_code', 'status']);
                $table->index(['school_id', 'udise_code']);
            }
        });

        // Backfill existing records from the parent school's udise_code
        DB::statement("
            UPDATE students 
            JOIN schools ON students.school_id = schools.id 
            SET students.udise_code = schools.udise_code 
            WHERE students.udise_code IS NULL OR students.udise_code = ''
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'udise_code')) {
                $table->dropIndex(['udise_code', 'status']);
                $table->dropIndex(['school_id', 'udise_code']);
                $table->dropColumn('udise_code');
            }
        });
    }
};
