<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('schools', 'udise_internal_id')) {
            Schema::table('schools', function (Blueprint $table) {
                $table->string('udise_internal_id', 50)->nullable()->after('udise_code')->index();
            });
        }

        // Pre-populate known UDISE internal IDs
        DB::table('schools')->where('id', 1)->update(['udise_internal_id' => '1007872']);
        DB::table('schools')->where('id', 3)->update(['udise_internal_id' => '1007846']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('schools', 'udise_internal_id')) {
            Schema::table('schools', function (Blueprint $table) {
                $table->dropColumn('udise_internal_id');
            });
        }
    }
};
