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
        Schema::create('school_working_days', function (Blueprint $table) {
            $table->id();
            $table->string('academic_year')->default('2025-2026');
            $table->unsignedSmallInteger('apr')->default(20);
            $table->unsignedSmallInteger('may')->default(22);
            $table->unsignedSmallInteger('jun')->default(10);
            $table->unsignedSmallInteger('jul')->default(20);
            $table->unsignedSmallInteger('aug')->default(22);
            $table->unsignedSmallInteger('sep')->default(21);
            $table->unsignedSmallInteger('oct')->default(19);
            $table->unsignedSmallInteger('nov')->default(20);
            $table->unsignedSmallInteger('dec')->default(15);
            $table->unsignedSmallInteger('jan')->default(15);
            $table->unsignedSmallInteger('feb')->default(20);
            $table->unsignedSmallInteger('mar')->default(18);
            $table->unsignedSmallInteger('total_days')->default(222);
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
        Schema::dropIfExists('school_working_days');
    }
};
