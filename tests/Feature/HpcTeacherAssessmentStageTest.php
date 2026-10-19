<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\School;
use App\Services\StageTemplateService;
use App\Services\HpcMiddleStageSchema;
use App\Services\HpcPreparatoryStageSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HpcTeacherAssessmentStageTest extends TestCase
{
    use RefreshDatabase;

    public function test_middle_stage_schema_has_exact_222_columns_and_14_sections(): void
    {
        $columns = HpcMiddleStageSchema::getColumns();
        $this->assertCount(222, $columns);

        $sections = HpcMiddleStageSchema::getSections();
        $this->assertCount(14, $sections);

        $starterRow = HpcMiddleStageSchema::buildStarterRow(
            0,
            ['name' => 'Ayaan Ahmad', 'login_id' => 'MID001', 'admission_no' => 'ADM-101'],
            [],
            [],
            4,
            true
        );
        $this->assertCount(222, $starterRow);

        $normalized = HpcMiddleStageSchema::normalizeStudentRow([
            'School_Admission_No' => 'ADM-101',
            'HPC_Login_ID'        => 'MID001',
            'name'                => 'Ayaan Ahmad',
            'L1_Max_Marks_SA'     => 50,
            'L1_Marks_Obtained_SA'=> 45,
            'Math_Max_Marks_SA'   => 50,
            'Math_Marks_Obtained_SA' => 48,
        ]);
        $this->assertEquals('ADM-101', $normalized['admission_no']);
        $this->assertEquals('MID001', $normalized['login_id']);
        $this->assertEquals(45, $normalized['marks']['l1']['obt']);
        $this->assertEquals(48, $normalized['marks']['math']['obt']);
    }

    public function test_preparatory_stage_schema_has_exact_176_columns_and_13_sections(): void
    {
        $columns = HpcPreparatoryStageSchema::getColumns();
        $this->assertCount(176, $columns);

        $sections = HpcPreparatoryStageSchema::getSections();
        $this->assertCount(13, $sections);

        $starterRow = HpcPreparatoryStageSchema::buildStarterRow(
            0,
            ['name' => 'Maryam Jan', 'login_id' => 'PREP001', 'admission_no' => 'ADM-201'],
            [],
            [],
            4,
            true
        );
        $this->assertCount(176, $starterRow);

        $normalized = HpcPreparatoryStageSchema::normalizeStudentRow([
            'School_Admission_No' => 'ADM-201',
            'HPC_Login_ID'        => 'PREP001',
            'name'                => 'Maryam Jan',
            'L1_Max_Marks_SA'     => 50,
            'L1_Marks_Obtained_SA'=> 46,
            'Math_Max_Marks_SA'   => 50,
            'Math_Marks_Obtained_SA' => 49,
        ]);
        $this->assertEquals('ADM-201', $normalized['admission_no']);
        $this->assertEquals('PREP001', $normalized['login_id']);
        $this->assertEquals(46, $normalized['marks']['lang1']['obt']);
        $this->assertEquals(49, $normalized['marks']['math']['obt']);
    }

    public function test_spreadsheet_generation_for_both_stages_builds_three_sheets_with_correct_dimensions(): void
    {
        $service = app(StageTemplateService::class);

        $students = [
            ['name' => 'Student 1', 'login_id' => 'S001', 'admission_no' => 'A1'],
            ['name' => 'Student 2', 'login_id' => 'S002', 'admission_no' => 'A2'],
        ];

        // Middle Stage: 222 columns (Col HN)
        $midSs = $service->buildSpreadsheet('middle-stage', $students);
        $this->assertEquals(3, $midSs->getSheetCount());
        $this->assertEquals('HN', $midSs->getSheetByName('Manual Entry (Blank)')->getHighestColumn());
        $this->assertEquals('HN', $midSs->getSheetByName('Pre-Filled (Auto-Fill)')->getHighestColumn());

        // Preparatory Stage: 176 columns (Col FT)
        $prepSs = $service->buildSpreadsheet('preparatory-stage', $students);
        $this->assertEquals(3, $prepSs->getSheetCount());
        $this->assertEquals('FT', $prepSs->getSheetByName('Manual Entry (Blank)')->getHighestColumn());
        $this->assertEquals('FT', $prepSs->getSheetByName('Pre-Filled (Auto-Fill)')->getHighestColumn());
    }
}
