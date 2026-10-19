<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('hpc:reparse-preview {schoolId=2}', function ($schoolId) {
    $dir = storage_path("app/hpc/schools/{$schoolId}");
    $uploadedPath = "{$dir}/teacher_assessment_uploaded.xlsx";
    if (!file_exists($uploadedPath)) {
        $this->error("No uploaded file at {$uploadedPath}");
        return 1;
    }

    $stageService = app(\App\Services\StageTemplateService::class);
    $middleSchema = app(\App\Services\HpcMiddleStageSchema::class);
    $columns = $stageService->getStageColumns(\App\Services\StageTemplateService::STAGE_MIDDLE);

    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($uploadedPath);
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($uploadedPath);
    $ws = $spreadsheet->getSheetByName('Student_Assessment_Data') ?? $spreadsheet->getActiveSheet();
    $max = $ws->getHighestRow();

    $students = [];
    for ($r = 4; $r <= $max; $r++) {
        $rowValues = [];
        foreach ($columns as $cIdx => $col) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx + 1);
            $val = $ws->getCell("{$colLetter}{$r}")->getValue();
            $rowValues[$col['key']] = is_string($val) ? trim($val) : $val;
        }

        $name = trim((string)($rowValues['Student_Name'] ?? $rowValues['name'] ?? ''));
        $loginId = trim((string)($rowValues['HPC_Login_ID'] ?? $rowValues['login_id'] ?? ''));
        $apaarId = trim((string)($rowValues['APAAR_ID'] ?? $rowValues['apaar_id'] ?? ''));

        if ($name === '' && $loginId === '' && $apaarId === '') {
            continue;
        }

        $normalized = $middleSchema->normalizeStudentRow($rowValues);
        $rowItem = array_merge($rowValues, $normalized, [
            'row' => $r,
            'stage' => 'middle-stage',
            'student_id' => $apaarId ?: ($loginId ?: ('STU_' . $r)),
            'name' => $name,
            'login_id' => $loginId,
            'apaar_id' => $apaarId,
            'grade' => $normalized['grade'] ?? $rowValues['Grade'] ?? 'Grade 6',
            'section' => $normalized['section'] ?? $rowValues['Section'] ?? 'A',
            'ambition' => $rowValues['Ambition_Goal'] ?? $normalized['ambition'] ?? 'Software Engineer',
            'achieve_how' => $rowValues['Ambition_Achieve_Strategy'] ?? $normalized['achieve_how'] ?? '',
            'skills_needed' => $rowValues['Ambition_5_Skills_Needed'] ?? $normalized['skills_needed'] ?? '',
            'focus_subjects' => $rowValues['Ambition_Subjects_To_Focus'] ?? $normalized['focus_subjects'] ?? '',
            'habits_i_will' => $rowValues['Ambition_Habits_Required'] ?? $normalized['habits_i_will'] ?? '',
            'mentor' => $rowValues['Ambition_Guidance_From_Person'] ?? $normalized['mentor'] ?? '',
            'mentor_help' => $rowValues['Ambition_How_Person_Will_Help'] ?? $normalized['mentor_help'] ?? '',
            'learn_new' => $rowValues['Ambition_Learn_New'] ?? $normalized['learn_new'] ?? '',
            'feel_when_achieve' => $rowValues['Ambition_How_Student_Will_Feel'] ?? $normalized['feel_when_achieve'] ?? '',
            'parents_feel' => $rowValues['Ambition_How_Parents_Will_Feel'] ?? $normalized['parents_feel'] ?? '',
            'live_with' => $rowValues['AboutMe_Live_With'] ?? $normalized['live_with'] ?? '',
            'stay_at' => $rowValues['AboutMe_Stay_At'] ?? $normalized['stay_at'] ?? '',
            'free_time' => $rowValues['AboutMe_Free_Time_Doing'] ?? $normalized['free_time'] ?? '',
            'do_well' => $rowValues['AboutMe_Do_Very_Well'] ?? $normalized['do_well'] ?? '',
            'responsible' => $rowValues['AboutMe_Responsible_Frequency'] ?? $normalized['responsible'] ?? 'all the time',
            'do_better' => $rowValues['AboutMe_Could_Do_Better_Specially'] ?? $normalized['do_better'] ?? '',
            'want_to_improve' => $rowValues['AboutMe_Could_Do_Better_Specially'] ?? $normalized['want_to_improve'] ?? '',
            'care_others' => $rowValues['AboutMe_Care_About_Others_By'] ?? $normalized['care_others'] ?? '',
            'feel_proud' => $rowValues['AboutMe_Proud_Of_Myself_When'] ?? $normalized['feel_proud'] ?? '',
            'acad_goal' => $rowValues['Academic_Goal'] ?? $normalized['acad_goal'] ?? '',
            'acad_goal_importance' => $rowValues['Academic_Goal_Importance'] ?? $normalized['acad_goal_importance'] ?? '',
            'acad_step1' => $rowValues['Academic_Goal_Step_1'] ?? $normalized['acad_step1'] ?? '',
            'acad_step2' => $rowValues['Academic_Goal_Step_2'] ?? $normalized['acad_step2'] ?? '',
            'pers_goal' => $rowValues['Personal_Goal'] ?? $normalized['pers_goal'] ?? '',
            'pers_goal_importance' => $rowValues['Personal_Goal_Importance'] ?? $normalized['pers_goal_importance'] ?? '',
            'pers_step1' => $rowValues['Personal_Goal_Step_1'] ?? $normalized['pers_step1'] ?? '',
            'pers_step2' => $rowValues['Personal_Goal_Step_2'] ?? $normalized['pers_step2'] ?? '',
        ]);
        $students[] = $rowItem;
    }

    $previewData = [
        'stage' => 'middle-stage',
        'uploaded_at' => now()->toIso8601String(),
        'filename' => 'teacher_assessment_uploaded.xlsx',
        'total_students' => count($students),
        'students' => $students,
    ];

    file_put_contents("{$dir}/teacher_preview.json", json_encode($previewData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    file_put_contents("{$dir}/teacher_preview_middle-stage.json", json_encode($previewData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $this->info("Reparsed " . count($students) . " students for School {$schoolId}.");
    if (!empty($students)) {
        $this->info("Sample student: {$students[0]['name']} | Grade: {$students[0]['grade']} | Ambition: {$students[0]['ambition']}");
    }
    return 0;
})->purpose('Reparse uploaded teacher assessment excel to refresh preview JSON');

