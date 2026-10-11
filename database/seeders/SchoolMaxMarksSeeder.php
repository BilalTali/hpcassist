<?php

namespace Database\Seeders;

use App\Models\SchoolMaxMark;
use Illuminate\Database\Seeder;

class SchoolMaxMarksSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SchoolMaxMark::updateOrCreate(
            ['academic_year' => '2025-2026'],
            [
                'l1_max'     => 50,
                'l2_max'     => 50,
                'l3_max'     => 50,
                'm_max'      => 50,
                'sci_max'    => 50,
                'soc_max'    => 50,
                'art_max'    => 50,
                'pe_max'     => 50,
                'is_default' => true,
                'remarks'    => 'Standard Summative Assessment Max Marks (HPC Credit)',
            ]
        );
    }
}
