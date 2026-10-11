<?php

namespace Database\Seeders;

use App\Models\SchoolWorkingDay;
use Illuminate\Database\Seeder;

class SchoolWorkingDaysSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SchoolWorkingDay::updateOrCreate(
            ['academic_year' => '2025-2026'],
            [
                'apr'        => 20,
                'may'        => 22,
                'jun'        => 10,
                'jul'        => 20,
                'aug'        => 22,
                'sep'        => 21,
                'oct'        => 19,
                'nov'        => 20,
                'dec'        => 15,
                'jan'        => 15,
                'feb'        => 20,
                'mar'        => 18,
                'total_days' => 222,
                'is_default' => true,
                'remarks'    => 'Standard Academic Session (Middle & Preparatory Stages)',
            ]
        );
    }
}
