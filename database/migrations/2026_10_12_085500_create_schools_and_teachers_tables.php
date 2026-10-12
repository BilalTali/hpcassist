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
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('udise_code', 15)->index();
            $table->string('category')->default('Middle School')->nullable();
            $table->string('principal_name')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('district')->nullable();
            $table->string('state')->default('Jammu & Kashmir')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name');
            $table->string('designation')->default('Teacher');
            $table->string('pen_code', 30)->nullable(); // National Teacher Code
            $table->string('mobile', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('classes')->default('Grade 6, Grade 7, Grade 8')->nullable();
            $table->string('hpc_username')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Add school_id to school_working_days and school_max_marks if needed
        if (Schema::hasTable('school_working_days') && ! Schema::hasColumn('school_working_days', 'school_id')) {
            Schema::table('school_working_days', function (Blueprint $table) {
                $table->foreignId('school_id')->nullable()->after('id')->constrained('schools')->nullOnDelete();
            });
        }

        if (Schema::hasTable('school_max_marks') && ! Schema::hasColumn('school_max_marks', 'school_id')) {
            Schema::table('school_max_marks', function (Blueprint $table) {
                $table->foreignId('school_id')->nullable()->after('id')->constrained('schools')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('school_max_marks', 'school_id')) {
            Schema::table('school_max_marks', function (Blueprint $table) {
                $table->dropForeign(['school_id']);
                $table->dropColumn('school_id');
            });
        }

        if (Schema::hasColumn('school_working_days', 'school_id')) {
            Schema::table('school_working_days', function (Blueprint $table) {
                $table->dropForeign(['school_id']);
                $table->dropColumn('school_id');
            });
        }

        Schema::dropIfExists('teachers');
        Schema::dropIfExists('schools');
    }
};
