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

    public function test_middle_stage_schema_has_exact_223_columns_and_13_sections(): void
    {
        $columns = HpcMiddleStageSchema::getColumns();
        $this->assertCount(223, $columns);
        $this->assertEquals('Student_Name', $columns[0]['key']);
        $this->assertEquals('APAAR_ID', $columns[1]['key']);
        $this->assertEquals('School_Admission_No', $columns[2]['key']);
        $this->assertEquals('Working_Days_Apr', $columns[15]['key']);
        $this->assertEquals('Present_Days_Apr', $columns[16]['key']);
        $this->assertEquals('Working_Days_May', $columns[17]['key']);
        $this->assertEquals('Present_Days_May', $columns[18]['key']);

        $sections = HpcMiddleStageSchema::getSections();
        $this->assertCount(13, $sections);

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
        $this->assertEquals('998877665544', $starterRow[1]);

        $normalized = HpcMiddleStageSchema::normalizeStudentRow([
            'Student_Name'        => 'Ayaan Ahmad',
            'APAAR_ID'            => '998877665544',
            'School_Admission_No' => 'ADM-101',
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

    public function test_preparatory_stage_schema_has_exact_177_columns_and_12_sections(): void
    {
        $columns = HpcPreparatoryStageSchema::getColumns();
        $this->assertCount(177, $columns);
        $this->assertEquals('Student_Name', $columns[0]['key']);
        $this->assertEquals('APAAR_ID', $columns[1]['key']);
        $this->assertEquals('School_Admission_No', $columns[2]['key']);
        $this->assertEquals('Working_Days_Apr', $columns[15]['key']);
        $this->assertEquals('Present_Days_Apr', $columns[16]['key']);
        $this->assertEquals('Working_Days_May', $columns[17]['key']);
        $this->assertEquals('Present_Days_May', $columns[18]['key']);

        $sections = HpcPreparatoryStageSchema::getSections();
        $this->assertCount(12, $sections);

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
        $this->assertEquals('998877665545', $starterRow[1]);

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

    public function test_upload_preparatory_and_middle_stage_excel_does_not_throw_undefined_array_key(): void
    {
        $user = User::create([
            'name' => 'Teacher User',
            'email' => 'teacher_test@example.com',
            'password' => bcrypt('password'),
        ]);

        $school = School::create([
            'user_id' => $user->id,
            'name' => 'Govt Middle School Test',
            'udise_code' => '01041800999',
            'principal_name' => 'Principal Test',
            'phone' => '9419000111',
            'district' => 'Pulwama',
            'state' => 'Jammu & Kashmir',
            'is_active' => true,
        ]);

        $service = app(StageTemplateService::class);
        $students = [
            ['name' => 'Maryam Jan', 'login_id' => 'PREP001', 'admission_no' => 'ADM-201', 'apaar_id' => '998877665545'],
        ];

        // Save prep spreadsheet
        $prepSs = $service->buildSpreadsheet('preparatory-stage', $students);
        $tmpPrep = tempnam(sys_get_temp_dir(), 'prep_') . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($prepSs);
        $writer->save($tmpPrep);

        $uploadedFile = new \Illuminate\Http\UploadedFile($tmpPrep, 'prep.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $response = $this->actingAs($user)->postJson(route('hpc.teacher.upload'), [
            'file' => $uploadedFile,
            'stage' => 'preparatory-stage',
        ]);

        @unlink($tmpPrep);

        $response->assertOk();
        $data = $response->json();
        $this->assertTrue($data['ok']);
        $this->assertGreaterThanOrEqual(1, $data['total']);
        $this->assertEquals('Maryam Jan', $data['preview'][0]['name']);
        $this->assertEquals('PREP001', $data['preview'][0]['login_id']);
    }
}
