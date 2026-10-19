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

    public function test_middle_stage_schema_has_exact_223_columns_and_14_sections(): void
    {
        $columns = HpcMiddleStageSchema::getColumns();
        $this->assertCount(223, $columns);
        $this->assertEquals('Student_Name', $columns[0]['key']);
        $this->assertEquals('APAAR_ID', $columns[4]['key']);

        $sections = HpcMiddleStageSchema::getSections();
        $this->assertCount(14, $sections);

        $starterRow = HpcMiddleStageSchema::buildStarterRow(
            0,
            ['name' => 'Ayaan Ahmad', 'login_id' => 'MID001', 'admission_no' => 'ADM-101', 'apaar_id' => '998877665544'],
            [],
            [],
            4,
            true
        );
        $this->assertCount(223, $starterRow);
        $this->assertEquals('Ayaan Ahmad', $starterRow[0]);

        $normalized = HpcMiddleStageSchema::normalizeStudentRow([
            'Student_Name'        => 'Ayaan Ahmad',
            'School_Admission_No' => 'ADM-101',
            'APAAR_ID'            => '998877665544',
            'HPC_Login_ID'        => 'MID001',
            'L1_Max_Marks_SA'     => 50,
            'L1_Marks_Obtained_SA'=> 45,
            'Math_Max_Marks_SA'   => 50,
            'Math_Marks_Obtained_SA' => 48,
        ]);
        $this->assertEquals('Ayaan Ahmad', $normalized['name']);
        $this->assertEquals('998877665544', $normalized['apaar_id']);
        $this->assertEquals('ADM-101', $normalized['admission_no']);
        $this->assertEquals('MID001', $normalized['login_id']);
        $this->assertEquals(45, $normalized['marks']['l1']['obt']);
        $this->assertEquals(48, $normalized['marks']['math']['obt']);
    }

    public function test_preparatory_stage_schema_has_exact_177_columns_and_13_sections(): void
    {
        $columns = HpcPreparatoryStageSchema::getColumns();
        $this->assertCount(177, $columns);
        $this->assertEquals('Student_Name', $columns[0]['key']);
        $this->assertEquals('APAAR_ID', $columns[4]['key']);

        $sections = HpcPreparatoryStageSchema::getSections();
        $this->assertCount(13, $sections);

        $starterRow = HpcPreparatoryStageSchema::buildStarterRow(
            0,
            ['name' => 'Maryam Jan', 'login_id' => 'PREP001', 'admission_no' => 'ADM-201', 'apaar_id' => '998877665545'],
            [],
            [],
            4,
            true
        );
        $this->assertCount(177, $starterRow);
        $this->assertEquals('Maryam Jan', $starterRow[0]);

        $normalized = HpcPreparatoryStageSchema::normalizeStudentRow([
            'Student_Name'        => 'Maryam Jan',
            'School_Admission_No' => 'ADM-201',
            'APAAR_ID'            => '998877665545',
            'HPC_Login_ID'        => 'PREP001',
            'L1_Max_Marks_SA'     => 50,
            'L1_Marks_Obtained_SA'=> 46,
            'Math_Max_Marks_SA'   => 50,
            'Math_Marks_Obtained_SA' => 49,
        ]);
        $this->assertEquals('Maryam Jan', $normalized['name']);
        $this->assertEquals('998877665545', $normalized['apaar_id']);
        $this->assertEquals('ADM-201', $normalized['admission_no']);
        $this->assertEquals('PREP001', $normalized['login_id']);
        $this->assertEquals(46, $normalized['marks']['lang1']['obt']);
        $this->assertEquals(49, $normalized['marks']['math']['obt']);
    }

    public function test_spreadsheet_generation_for_both_stages_builds_three_sheets_with_correct_dimensions(): void
    {
        $service = app(StageTemplateService::class);

        $students = [
            ['name' => 'Student 1', 'login_id' => 'S001', 'admission_no' => 'A1', 'apaar_id' => '998877665540'],
            ['name' => 'Student 2', 'login_id' => 'S002', 'admission_no' => 'A2', 'apaar_id' => '998877665541'],
        ];

        // Middle Stage: 223 columns (Col HO)
        $midSs = $service->buildSpreadsheet('middle-stage', $students);
        $this->assertEquals(3, $midSs->getSheetCount());
        $this->assertEquals('HO', $midSs->getSheetByName('Manual Entry (Blank)')->getHighestColumn());
        $this->assertEquals('HO', $midSs->getSheetByName('Pre-Filled (Auto-Fill)')->getHighestColumn());

        // Preparatory Stage: 177 columns (Col FU)
        $prepSs = $service->buildSpreadsheet('preparatory-stage', $students);
        $this->assertEquals(3, $prepSs->getSheetCount());
        $this->assertEquals('FU', $prepSs->getSheetByName('Manual Entry (Blank)')->getHighestColumn());
        $this->assertEquals('FU', $prepSs->getSheetByName('Pre-Filled (Auto-Fill)')->getHighestColumn());
    }
}
