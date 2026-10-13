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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('name');
            $table->string('dob', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('grade', 50)->default('Grade 6');
            $table->string('section', 20)->default('A');
            $table->string('apaar_id', 20)->nullable()->index();
            $table->string('pen_code', 50)->nullable()->index();
            $table->string('udise_student_id', 50)->nullable()->index();
            $table->string('gender', 20)->nullable();
            $table->string('source', 30)->default('udise_sync'); // udise_sync, excel_upload, manual
            $table->string('status', 30)->default('pending');    // pending, success, failed
            $table->text('remark')->nullable();                  // Success remark / sync notes
            $table->text('failure_reason')->nullable();          // Reason if invalid / submission failed
            $table->timestamp('hpc_submitted_at')->nullable();
            $table->timestamps();

            // Compound indexes to speed up lookups and avoid duplicates
            $table->index(['school_id', 'apaar_id']);
            $table->index(['school_id', 'pen_code']);
            $table->index(['school_id', 'grade', 'section']);
            $table->index(['school_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
