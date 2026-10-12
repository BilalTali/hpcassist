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
        if (Schema::hasTable('school_working_days') && ! Schema::hasColumn('school_working_days', 'stage')) {
            Schema::table('school_working_days', function (Blueprint $table) {
                $table->string('stage', 32)->default('middle')->after('school_id');
            });
        }

        if (Schema::hasTable('school_max_marks') && ! Schema::hasColumn('school_max_marks', 'stage')) {
            Schema::table('school_max_marks', function (Blueprint $table) {
                $table->string('stage', 32)->default('middle')->after('school_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('school_working_days') && Schema::hasColumn('school_working_days', 'stage')) {
            Schema::table('school_working_days', function (Blueprint $table) {
                $table->dropColumn('stage');
            });
        }

        if (Schema::hasTable('school_max_marks') && Schema::hasColumn('school_max_marks', 'stage')) {
            Schema::table('school_max_marks', function (Blueprint $table) {
                $table->dropColumn('stage');
            });
        }
    }
};
