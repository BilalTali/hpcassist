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

    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($uploadedPath);
    $sheet = $spreadsheet->getSheetByName('Student_Assessment_Data') ?? $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, false);
    $headers = array_map('trim', array_map('strval', $rows[0] ?? []));

    $schema = app(\App\Services\HpcMiddleStageSchema::class);
    $students = [];
    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        if (empty(array_filter($row))) continue;
        $rowValues = [];
        foreach ($headers as $idx => $hdr) {
            if ($hdr !== '') {
                $rowValues[$hdr] = isset($row[$idx]) ? trim(strval($row[$idx])) : '';
            }
        }
        $normalized = $schema->normalizeStudentRow($rowValues);
        $rowItem = array_merge($rowValues, $normalized, [
            'student_id' => $rowValues['Aadhaar_Number'] ?? $rowValues['Student_ID'] ?? $normalized['student_id'] ?? ('STU_' . $i),
            'name' => $rowValues['Student_Name'] ?? $normalized['name'] ?? ('Student ' . $i),
            'grade' => $normalized['grade'] ?? $rowValues['Grade'] ?? $rowValues['Class'] ?? '6',
            'ambition' => $normalized['ambition'] ?? $rowValues['Ambition_Goal'] ?? '',
            'achieve_how' => $normalized['achieve_how'] ?? $rowValues['Ambition_How'] ?? '',
            'skills_needed' => $normalized['skills_needed'] ?? $rowValues['Ambition_Skills'] ?? '',
            'focus_subjects' => $normalized['focus_subjects'] ?? $rowValues['Ambition_Focus_Subjects'] ?? '',
            'habits_i_will' => $normalized['habits_i_will'] ?? $rowValues['Ambition_Habits'] ?? '',
            'mentor' => $normalized['mentor'] ?? $rowValues['Ambition_Mentor'] ?? '',
            'mentor_help' => $normalized['mentor_help'] ?? $rowValues['Ambition_Mentor_Support'] ?? '',
            'learn_new' => $normalized['learn_new'] ?? $rowValues['Ambition_Learn_New'] ?? '',
            'feel_when_achieve' => $normalized['feel_when_achieve'] ?? $rowValues['Ambition_Student_Feeling'] ?? '',
            'parents_feel' => $normalized['parents_feel'] ?? $rowValues['Ambition_Parents_Feeling'] ?? '',
            'live_with' => $normalized['live_with'] ?? $rowValues['AboutMe_Live_With'] ?? '',
            'stay_at' => $normalized['stay_at'] ?? $rowValues['AboutMe_Location'] ?? '',
            'free_time' => $normalized['free_time'] ?? $rowValues['AboutMe_Free_Time'] ?? '',
            'do_well' => $normalized['do_well'] ?? $rowValues['AboutMe_Do_Well'] ?? '',
            'responsible' => $normalized['responsible'] ?? $rowValues['AboutMe_Responsible'] ?? '',
            'do_better' => $normalized['do_better'] ?? $rowValues['AboutMe_Improve'] ?? '',
            'care_others' => $normalized['care_others'] ?? $rowValues['AboutMe_Care_Others'] ?? '',
            'feel_proud' => $normalized['feel_proud'] ?? $rowValues['AboutMe_Proud'] ?? '',
            'acad_goal' => $normalized['acad_goal'] ?? $rowValues['AboutMe_Acad_Goal'] ?? '',
            'acad_goal_importance' => $normalized['acad_goal_importance'] ?? $rowValues['AboutMe_Acad_Goal_Why'] ?? '',
            'acad_step1' => $normalized['acad_step1'] ?? $rowValues['AboutMe_Acad_Step_1'] ?? '',
            'acad_step2' => $normalized['acad_step2'] ?? $rowValues['AboutMe_Acad_Step_2'] ?? '',
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

