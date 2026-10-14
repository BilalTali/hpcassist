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
        if (Schema::hasTable('students') && ! Schema::hasColumn('students', 'admission_no')) {
            Schema::table('students', function (Blueprint $table) {
                $table->string('admission_no', 50)->nullable()->after('pen_code')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('students') && Schema::hasColumn('students', 'admission_no')) {
            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('admission_no');
            });
        }
    }
};
