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
        Schema::create('school_max_marks', function (Blueprint $table) {
            $table->id();
            $table->string('academic_year')->default('2025-2026');
            $table->unsignedSmallInteger('l1_max')->default(50);
            $table->unsignedSmallInteger('l2_max')->default(50);
            $table->unsignedSmallInteger('l3_max')->default(50);
            $table->unsignedSmallInteger('m_max')->default(50);
            $table->unsignedSmallInteger('sci_max')->default(50);
            $table->unsignedSmallInteger('soc_max')->default(50);
            $table->unsignedSmallInteger('art_max')->default(50);
            $table->unsignedSmallInteger('pe_max')->default(50);
            $table->boolean('is_default')->default(true);
            $table->string('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_max_marks');
    }
};
