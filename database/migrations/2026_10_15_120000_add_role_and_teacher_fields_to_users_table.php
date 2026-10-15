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
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role', 30)->default('school')->after('email');
            }
            if (! Schema::hasColumn('users', 'school_id')) {
                $table->foreignId('school_id')->nullable()->after('role')->constrained('schools')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'teacher_id')) {
                $table->foreignId('teacher_id')->nullable()->after('school_id')->constrained('teachers')->nullOnDelete();
            }
        });

        Schema::table('teachers', function (Blueprint $table) {
            if (! Schema::hasColumn('teachers', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('school_id')->constrained('users')->nullOnDelete();
            }
        });

        // Set existing users to 'school' role and link to their school
        $schools = \Illuminate\Support\Facades\DB::table('schools')->get();
        foreach ($schools as $school) {
            if ($school->user_id) {
                \Illuminate\Support\Facades\DB::table('users')
                    ->where('id', $school->user_id)
                    ->update([
                        'role'      => 'school',
                        'school_id' => $school->id,
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            if (Schema::hasColumn('teachers', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'teacher_id')) {
                $table->dropForeign(['teacher_id']);
                $table->dropColumn('teacher_id');
            }
            if (Schema::hasColumn('users', 'school_id')) {
                $table->dropForeign(['school_id']);
                $table->dropColumn('school_id');
            }
            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }
        });
    }
};
