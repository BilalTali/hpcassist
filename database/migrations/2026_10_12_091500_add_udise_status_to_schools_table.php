<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            if (! Schema::hasColumn('schools', 'udise_status')) {
                $table->string('udise_status', 30)->default('not_attempted')->after('address');
            }
            if (! Schema::hasColumn('schools', 'udise_synced_at')) {
                $table->timestamp('udise_synced_at')->nullable()->after('udise_status');
            }
            if (! Schema::hasColumn('schools', 'udise_students_count')) {
                $table->unsignedInteger('udise_students_count')->default(0)->after('udise_synced_at');
            }
            if (! Schema::hasColumn('schools', 'udise_apaar_count')) {
                $table->unsignedInteger('udise_apaar_count')->default(0)->after('udise_students_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['udise_status', 'udise_synced_at', 'udise_students_count', 'udise_apaar_count']);
        });
    }
};
