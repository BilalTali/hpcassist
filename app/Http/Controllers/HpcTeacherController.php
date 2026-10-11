<?php

namespace App\Http\Controllers;

use App\Models\SchoolWorkingDay;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class HpcTeacherController extends Controller
{
    // ─── Dashboard View ───────────────────────────────────────────────────────

    public function dashboard()
    {
        $storagePath = config('hpc.storage_path');
        $schoolsData = $this->getSchoolsData();
        $activeSchool = $this->getActiveSchool();
        $activeTeacher = $this->getActiveTeacher();
        $teacherProfileDir = $this->getActiveTeacherProfileDir();

        $hasSession = is_dir($teacherProfileDir) && count(scandir($teacherProfileDir)) > 2;

        $stagesFile = $storagePath . '/stages.json';
        $stages = file_exists($stagesFile) ? (json_decode(file_get_contents($stagesFile), true) ?? []) : [
            ['title' => 'Middle Stage', 'slug' => 'middle-stage', 'url' => 'https://hpc.parakh.ncert.gov.in/en/hpc/middle-stage'],
            ['title' => 'Preparatory Stage', 'slug' => 'preparatory-stage', 'url' => 'https://hpc.parakh.ncert.gov.in/en/hpc/preparatory-stage']
        ];

        $studentsFile = $storagePath . '/teacher_students.json';
        $students = file_exists($studentsFile) ? (json_decode(file_get_contents($studentsFile), true) ?? []) : [];

        $previewFile = $storagePath . '/teacher_preview.json';
        $preview = file_exists($previewFile) ? (json_decode(file_get_contents($previewFile), true) ?? []) : [];

        $resultsFile = $storagePath . '/teacher_run_results.json';
        $lastRun = file_exists($resultsFile) ? json_decode(file_get_contents($resultsFile), true) : null;

        $workingDays = SchoolWorkingDay::getDefault();

        return view('hpc.teacher-dashboard', compact(
            'schoolsData',
            'activeSchool',
            'activeTeacher',
            'hasSession',
            'stages',
            'students',
            'preview',
            'lastRun',
            'workingDays'
        ));
    }

    // ─── Step 1: Download Filled Roster Template ──────────────────────────────

    public function downloadTemplate(?Request $request = null)
    {
        $storagePath = config('hpc.storage_path');
        $studentsFile = $storagePath . '/teacher_students.json';
        $students = file_exists($studentsFile) ? (json_decode(file_get_contents($studentsFile), true) ?? []) : [];

        if (empty($students)) {
            $students = [
                ['name' => 'Faizan Ahmad',     'login_id' => 'S1CA3C8009', 'status' => 'HPC Pending'],
                ['name' => 'Zehra Batool',     'login_id' => 'S49C309C52', 'status' => 'HPC Pending'],
                ['name' => 'Mohammad Yousuf',  'login_id' => 'S2C7236921', 'status' => 'HPC Pending'],
                ['name' => 'Insha Jan',        'login_id' => 'S9CD420A51', 'status' => 'HPC Pending'],
                ['name' => 'Sahil Bashir',     'login_id' => 'SD3675F62B', 'status' => 'HPC Pending'],
            ];
        }

        $spreadsheet = new Spreadsheet();

        // ─── Sheet 1: Instructions & Rules ────────────────────────────────────
        $instructionSheet = $spreadsheet->getActiveSheet();
        $instructionSheet->setTitle('Instructions & Rules');

        $instructionSheet->setCellValue('A1', 'HPCAssist - Evaluation Template Instructions & Validation Rules');
        $instructionSheet->mergeCells('A1:E1');
        $instructionSheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $instructionSheet->getRowDimension(1)->setRowHeight(32);

        $rules = [
            ['Rule #', 'Section / Page', 'Field Type', 'Rule Description', 'Permitted Values / Dragdown Options'],
            ['1', 'Student Metadata', 'Locked String', 'APAAR ID, HPC Login ID, Student Name, and Status. HPC Login ID is the unique identifier on the PARAKH portal.', 'Pre-filled from school portal roster.'],
            ['2', 'Status Protection', 'System Rule', 'Students with status "HPC Completed" will be protected and left untouched during update runs.', 'HPC Completed (skipped), HPC Pending / In Progress (updated)'],
            ['3', 'Row 3 Guide Row', 'Guide Reference', 'Row 3 (DEMO_SAMPLE) provides a complete, realistic model of teacher evaluation for guidance. Do not delete or alter.', 'All 129 fields pre-filled for visual demonstration.'],
            ['4', 'Part-A(1) Monthly Attendance', 'Integer & Formulas', 'Fill monthly Working Days (Apr-Mar) & Present Days (Apr-Mar). Total Working, Total Present, and Attendance % calculate automatically via Excel formulas.', 'Monthly Working (0-31), Monthly Present (0-Working). Present <= Working.'],
            ['5', 'Part-A(2) All About Me', 'Text & Dragdown', 'Living arrangement, leisure activities, self-responsibility, improvement areas, friend info, academic & personal goals with rationale & steps, learnings in/outside school.', 'Responsibility: all the time, most times, sometimes.'],
            ['6', 'Part-A(3) Ambition Card', 'Text & Dragdown', 'Student career ambition, path to achieve, 5 key skills, focus subjects, habits to cultivate, teacher mentor name & guidance, emotional reflections.', 'Mentor guidance and structured skill acquisition.'],
            ['7', 'Part-A(4) Parent Partnership', 'Dragdown & Text', 'Home resources, motivation level, routine/screen time schedule, curriculum difficulty, academic progress, support needs, parent home action plan, peer review.', 'Motivation/Schedule/Difficulty/Progress: Yes, Sometimes, No, Not Sure.'],
            ['8', 'Part-B Holistic Activities', 'Dragdown & Text', 'Pedagogical approach, activity title, description, rubric ratings (Awareness, Sensitivity, Creativity), student self-reflection, peer review, teacher strength/barrier areas & support plan.', 'Approach: Art-Integrated, Sports-Integrated...; Rubrics: Beginner, Proficient, Advanced.'],
            ['9', 'Part-C Curricular Assessment', 'Dragdown (Level)', 'Year-end formative and summative performance levels across curricular domains.', 'Beginner, Proficient, Advanced'],
            ['10', 'HPC Credit (Marks Grid)', 'Integer (0-50)', 'Summative Assessment marks for each of the 8 curricular subjects.', 'Max Marks (default 50), Marks Obtained (0 to Max Marks).'],
            ['11', 'Declaration & Remarks', 'Dragdown & Text', 'Teacher declaration certifying entered evaluation and holistic summary observation.', 'Declaration: Yes, No; Holistic remarks.'],
        ];

        foreach ($rules as $rIdx => $row) {
            $rowNum = $rIdx + 3;
            foreach ($row as $cIdx => $val) {
                $colLetter = chr(65 + $cIdx);
                $instructionSheet->setCellValue("{$colLetter}{$rowNum}", $val);
            }
            if ($rIdx === 0) {
                $instructionSheet->getStyle("A{$rowNum}:E{$rowNum}")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF334155']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $instructionSheet->getRowDimension($rowNum)->setRowHeight(24);
            } else {
                $instructionSheet->getStyle("A{$rowNum}:E{$rowNum}")->applyFromArray([
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $instructionSheet->getRowDimension($rowNum)->setRowHeight(22);
            }
        }

        $instructionSheet->getColumnDimension('A')->setWidth(10);
        $instructionSheet->getColumnDimension('B')->setWidth(26);
        $instructionSheet->getColumnDimension('C')->setWidth(22);
        $instructionSheet->getColumnDimension('D')->setWidth(55);
        $instructionSheet->getColumnDimension('E')->setWidth(45);

        // ─── Sheet 2: Student Evaluations ─────────────────────────────────────
        $evalSheet = $spreadsheet->createSheet();
        $evalSheet->setTitle('Student Evaluations');

        $columns = $this->getEvaluationColumns();

        // Write Header (Row 1) & Notes (Row 2)
        foreach ($columns as $idx => $col) {
            $colLetter = $this->indexToColumnLetter($idx);
            $evalSheet->setCellValue("{$colLetter}1", $col['label']);
            $evalSheet->setCellValue("{$colLetter}2", $col['note']);
            $evalSheet->getColumnDimension($colLetter)->setWidth($col['width']);

            $evalSheet->getStyle("{$colLetter}1")->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 10],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E293B']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCBD5E1']]],
            ]);

            $evalSheet->getStyle("{$colLetter}2")->applyFromArray([
                'font'      => ['italic' => true, 'color' => ['argb' => 'FF64748B'], 'size' => 9],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF8FAFC']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
            ]);
        }
        $evalSheet->getRowDimension(1)->setRowHeight(28);
        $evalSheet->getRowDimension(2)->setRowHeight(20);

        // ─── Row 3: COMPLETE DEMO / DUMMY STUDENT (GUIDE ONLY) ────────────────
        $demoRow = $this->getDemoStudentRow();

        foreach ($columns as $cIdx => $col) {
            $colLetter = $this->indexToColumnLetter($cIdx);
            $val = $demoRow[$cIdx] ?? '';
            $evalSheet->setCellValue("{$colLetter}3", $val);

            if ($col['type'] === 'list') {
                $validation = $evalSheet->getCell("{$colLetter}3")->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                $validation->setAllowBlank(false);
                $validation->setShowDropDown(true);
                $validation->setFormula1($col['options']);
            }

            $evalSheet->getStyle("{$colLetter}3")->applyFromArray([
                'font'      => ['bold' => true, 'italic' => true, 'color' => ['argb' => 'FF854D0E']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFEF9C3']],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFFDE047']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
        }
        $evalSheet->getRowDimension(3)->setRowHeight(24);


        // ─── Rows 4 onwards: REAL ENROLLED STUDENTS ───────────────────────────
        $defaultWd = SchoolWorkingDay::getDefault();
        $defaultWorkMap = $defaultWd->toMonthMap();

        foreach ($students as $sIdx => $st) {
            $rowNum = $sIdx + 4; // Starts at Row 4 below Demo Student

            foreach ($columns as $cIdx => $col) {
                $colLetter = $this->indexToColumnLetter($cIdx);
                $val = '';

                // Pre-fill metadata fetched from portal and default school working days
                if ($cIdx === 0) $val = $st['apaar_id'] ?? '';
                elseif ($cIdx === 1) $val = $st['login_id'] ?? '';
                elseif ($cIdx === 2) $val = $st['name'] ?? '';
                elseif ($cIdx === 3) $val = $st['status'] ?? 'HPC Pending';
                elseif (str_starts_with($col['key'], 'work_')) {
                    $mKey = substr($col['key'], 5);
                    $val = $defaultWorkMap[$mKey] ?? 20;
                }
                elseif ($col['key'] === 'total_work') $val = "=SUM(M{$rowNum}:X{$rowNum})";
                elseif ($col['key'] === 'total_pres') $val = "=SUM(Z{$rowNum}:AK{$rowNum})";
                elseif ($col['key'] === 'attendance_pct') $val = "=IF(Y{$rowNum}>0, ROUND((AL{$rowNum}/Y{$rowNum})*100, 1) & \"%\", \"\")";

                $evalSheet->setCellValue("{$colLetter}{$rowNum}", $val);

                if ($col['type'] === 'list') {
                    $validation = $evalSheet->getCell("{$colLetter}{$rowNum}")->getDataValidation();
                    $validation->setType(DataValidation::TYPE_LIST);
                    $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                    $validation->setAllowBlank(true);
                    $validation->setShowDropDown(true);
                    $validation->setShowInputMessage(true);
                    $validation->setPromptTitle('Select Option');
                    $validation->setPrompt('Click the dropdown arrow to pick an option.');
                    $validation->setFormula1($col['options']);
                }

                $isLocked = ($col['type'] === 'locked');
                $evalSheet->getStyle("{$colLetter}{$rowNum}")->applyFromArray([
                    'font'      => ['bold' => $isLocked, 'color' => ['argb' => $isLocked ? 'FF0F172A' : 'FF334155']],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $isLocked ? 'FFF1F5F9' : 'FFFFFFFF']],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
            }

            $evalSheet->getRowDimension($rowNum)->setRowHeight(22);
        }

        $evalSheet->freezePane('E4');

        $filename = 'hpc_teacher_assessment_template.xlsx';
        $writer   = new Xlsx($spreadsheet);

        return response()->stream(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
        ]);
    }

    private function indexToColumnLetter(int $index): string
    {
        $letter = '';
        while ($index >= 0) {
            $letter = chr($index % 26 + 65) . $letter;
            $index = intval($index / 26) - 1;
        }
        return $letter;
    }

    private function setValidation($ws, string $cell, string $formula): void
    {
        $v = $ws->getCell($cell)->getDataValidation();
        $v->setType(DataValidation::TYPE_LIST);
        $v->setErrorStyle(DataValidation::STYLE_INFORMATION);
        $v->setAllowBlank(false);
        $v->setShowDropDown(true);
        $v->setFormula1($formula);
    }

    // ─── Step 2: Upload Filled Template ───────────────────────────────────────

    public function upload(Request $request)
    {
        $request->validate(['file' => 'required|file|max:10240']);

        $uploadedFile = $request->file('file');
        $ext = strtolower($uploadedFile->getClientOriginalExtension());
        if (! in_array($ext, ['xlsx', 'xls', 'csv'])) {
            return response()->json(['ok' => false, 'error' => 'Please upload a valid Excel (.xlsx, .xls) file.']);
        }

        $dir = storage_path('app/hpc');
        if (! is_dir($dir)) @mkdir($dir, 0777, true);

        $fullPath = $dir . '/teacher_assessment_uploaded.xlsx';
        $uploadedFile->move($dir, 'teacher_assessment_uploaded.xlsx');

        $errors  = [];
        $preview = [];

        try {
            $ss  = \PhpOffice\PhpSpreadsheet\IOFactory::load($fullPath);
            $ws  = $ss->getSheetByName('Student Evaluations') ?? $ss->getActiveSheet();
            $max = $ws->getHighestDataRow();

            $columns = $this->getEvaluationColumns();

            for ($r = 3; $r <= $max; $r++) {
                $rowValues = [];
                foreach ($columns as $cIdx => $col) {
                    $colLetter = $this->indexToColumnLetter($cIdx);
                    $rowValues[$col['key']] = trim((string) $ws->getCell("{$colLetter}{$r}")->getValue());
                }

                $name    = $rowValues['name'] ?? '';
                $loginId = $rowValues['login_id'] ?? '';
                $status  = $rowValues['status'] ?? '';

                if ($name === '' && $loginId === '') continue;

                $isDemo      = ($loginId === 'DEMO_SAMPLE' || stripos($name, 'SAMPLE') !== false || stripos($name, 'DEMO') !== false);
                $isCompleted = strcasecmp($status, 'HPC Completed') === 0;

                $rowErrors = [];
                if (! $isDemo && ! $isCompleted) {
                    if ($name === '') $rowErrors[] = 'Student Name missing';
                    if ($loginId === '') $rowErrors[] = 'HPC Login ID missing';
                }

                $rowItem = array_merge($rowValues, [
                    'row'          => $r,
                    'is_demo'      => $isDemo,
                    'is_completed' => $isCompleted,
                    'errors'       => $rowErrors,
                    'marks' => [
                        'l1' => ['max' => $rowValues['l1_max'] ?: '50', 'obt' => $rowValues['l1_obt'] ?: '42'],
                        'l2' => ['max' => $rowValues['l2_max'] ?: '50', 'obt' => $rowValues['l2_obt'] ?: '40'],
                        'l3' => ['max' => $rowValues['l3_max'] ?: '50', 'obt' => $rowValues['l3_obt'] ?: '41'],
                        'm'  => ['max' => $rowValues['m_max']  ?: '50', 'obt' => $rowValues['m_obt']  ?: '44'],
                        'sci'=> ['max' => $rowValues['sci_max'] ?: '50', 'obt' => $rowValues['sci_obt'] ?: '43'],
                        'soc'=> ['max' => $rowValues['soc_max'] ?: '50', 'obt' => $rowValues['soc_obt'] ?: '42'],
                        'art'=> ['max' => $rowValues['art_max'] ?: '50', 'obt' => $rowValues['art_obt'] ?: '46'],
                        'pe' => ['max' => $rowValues['pe_max'] ?: '50', 'obt' => $rowValues['pe_obt'] ?: '47'],
                    ],
                ]);

                $preview[] = $rowItem;

                if (! empty($rowErrors)) {
                    $errors[] = "Row {$r} ({$name}): " . implode(', ', $rowErrors);
                }
            }
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'Could not read Excel file: ' . $e->getMessage()]);
        }

        file_put_contents(storage_path('app/hpc/teacher_preview.json'), json_encode($preview, JSON_PRETTY_PRINT));

        return response()->json([
            'ok'      => true,
            'total'   => count($preview),
            'valid'   => count(array_filter($preview, fn($r) => empty($r['errors']))),
            'invalid' => count(array_filter($preview, fn($r) => ! empty($r['errors']))),
            'errors'  => $errors,
            'preview' => $preview,
        ]);
    }

    // ─── Step 3: Start Bulk HPC Assessment Runner ─────────────────────────────

    public function runStart(Request $request)
    {
        $previewFile = storage_path('app/hpc/teacher_preview.json');
        if (! file_exists($previewFile)) {
            return response()->json(['ok' => false, 'error' => 'No student evaluation data found. Please upload the filled Excel file first.']);
        }

        $allStudents   = json_decode(file_get_contents($previewFile), true) ?? [];
        $validStudents = array_values(array_filter($allStudents, fn($s) => empty($s['errors'])));

        if (empty($validStudents)) {
            return response()->json(['ok' => false, 'error' => 'All rows have validation errors. Please fix and re-upload.']);
        }

        $total = count($validStudents);
        $resultsFile = storage_path('app/hpc/teacher_run_results.json');
        $init = [
            'started'    => true,
            'done'       => false,
            'login_wait' => true,
            'total'      => $total,
            'processed'  => 0,
            'results'    => [],
            'started_at' => now()->toIso8601String(),
        ];
        file_put_contents($resultsFile, json_encode($init, JSON_PRETTY_PRINT));

        $artisan = base_path('artisan');
        $logFile = storage_path('logs/hpc_teacher_run.log');
        $php     = PHP_BINARY;

        exec("nohup {$php} {$artisan} hpc:teacher-run > {$logFile} 2>&1 &");

        return response()->json(['ok' => true, 'total' => $total]);
    }

    public function runStatus()
    {
        $file = storage_path('app/hpc/teacher_run_results.json');
        if (! file_exists($file)) {
            return response()->json(['started' => false, 'results' => []]);
        }
        $data = json_decode(file_get_contents($file), true) ?? [];
        return response()->json($data);
    }

    public function runReset()
    {
        $file = storage_path('app/hpc/teacher_run_results.json');
        if (file_exists($file)) @unlink($file);
        return response()->json(['ok' => true]);
    }

    // ─── Discovery Trigger ────────────────────────────────────────────────────

    public function discover(Request $request)
    {
        $stage = trim((string) $request->input('stage'));
        $artisan = base_path('artisan');
        $php = PHP_BINARY;
        $cmd = escapeshellcmd("{$php} {$artisan} hpc:teacher-discover --skip-login " . ($stage ? "--stage=" . escapeshellarg($stage) : ""));

        exec($cmd . ' 2>&1', $output, $exitCode);

        $studentsFile = storage_path('app/hpc/teacher_students.json');
        $students = file_exists($studentsFile) ? json_decode(file_get_contents($studentsFile), true) : [];

        return response()->json([
            'ok'       => true,
            'total'    => count($students),
            'students' => $students,
            'output'   => implode("\n", $output)
        ]);
    }

    // ─── School Working Days Defaults Management ──────────────────────────────

    public function getWorkingDays(Request $request)
    {
        $wd = SchoolWorkingDay::getDefault();
        return response()->json([
            'ok'   => true,
            'data' => $wd,
        ]);
    }

    public function updateWorkingDays(Request $request)
    {
        $data = $request->validate([
            'academic_year' => 'nullable|string|max:20',
            'apr'           => 'required|integer|min:0|max:31',
            'may'           => 'required|integer|min:0|max:31',
            'jun'           => 'required|integer|min:0|max:31',
            'jul'           => 'required|integer|min:0|max:31',
            'aug'           => 'required|integer|min:0|max:31',
            'sep'           => 'required|integer|min:0|max:31',
            'oct'           => 'required|integer|min:0|max:31',
            'nov'           => 'required|integer|min:0|max:31',
            'dec'           => 'required|integer|min:0|max:31',
            'jan'           => 'required|integer|min:0|max:31',
            'feb'           => 'required|integer|min:0|max:31',
            'mar'           => 'required|integer|min:0|max:31',
            'remarks'       => 'nullable|string|max:255',
        ]);

        $wd = SchoolWorkingDay::getDefault();
        $data['academic_year'] = $data['academic_year'] ?? $wd->academic_year ?? '2025-2026';
        $data['total_days'] = array_sum([
            $data['apr'], $data['may'], $data['jun'], $data['jul'], $data['aug'], $data['sep'],
            $data['oct'], $data['nov'], $data['dec'], $data['jan'], $data['feb'], $data['mar']
        ]);
        $data['is_default'] = true;

        $wd->update($data);

        return response()->json([
            'ok'      => true,
            'message' => 'Default school working days updated successfully!',
            'data'    => $wd->fresh(),
        ]);
    }

    // ─── Multi-Teacher Session Management ─────────────────────────────────────

    public function getSchoolsData(): array
    {
        $file = storage_path('app/hpc/schools.json');
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if (is_array($data) && isset($data['schools'])) return $data;
        }

        $default = [
            'active' => 'default',
            'schools' => [
                [
                    'id'   => 'default',
                    'name' => 'BMS PWC Sugin',
                    'dir'  => 'chrome-profile',
                    'teachers' => [
                        ['id' => 'teacher_default', 'name' => 'Bilal Ahmad Tali', 'dir' => 'teacher-profile']
                    ],
                    'active_teacher' => 'teacher_default'
                ]
            ]
        ];
        file_put_contents($file, json_encode($default, JSON_PRETTY_PRINT));
        return $default;
    }

    public function getActiveSchool(): array
    {
        $data = $this->getSchoolsData();
        $activeId = $data['active'] ?? 'default';
        foreach ($data['schools'] as $school) {
            if ($school['id'] === $activeId) return $school;
        }
        return $data['schools'][0];
    }

    public function getActiveTeacher(): array
    {
        $school = $this->getActiveSchool();
        $activeTeacherId = $school['active_teacher'] ?? 'teacher_default';
        foreach ($school['teachers'] ?? [] as $t) {
            if ($t['id'] === $activeTeacherId) return $t;
        }
        return $school['teachers'][0] ?? ['id' => 'teacher_default', 'name' => 'Teacher', 'dir' => 'teacher-profile'];
    }

    public function getActiveTeacherProfileDir(): string
    {
        $teacher = $this->getActiveTeacher();
        $dir = $teacher['dir'] ?? 'teacher-profile';
        return storage_path('app/hpc/' . $dir);
    }

    public function switchTeacher(Request $request)
    {
        $teacherId = trim((string) $request->input('id'));
        $data = $this->getSchoolsData();
        $activeSchoolId = $data['active'] ?? 'default';

        foreach ($data['schools'] as &$sch) {
            if ($sch['id'] === $activeSchoolId) {
                $sch['active_teacher'] = $teacherId;
                break;
            }
        }
        file_put_contents(storage_path('app/hpc/schools.json'), json_encode($data, JSON_PRETTY_PRINT));
        return response()->json(['ok' => true]);
    }

    public function addTeacher(Request $request)
    {
        $name = trim((string) $request->input('name'));
        if ($name === '') return response()->json(['ok' => false, 'error' => 'Please provide a valid teacher name.']);

        $data = $this->getSchoolsData();
        $activeSchoolId = $data['active'] ?? 'default';
        $id = 'teacher_' . time();
        $dir = 'teacher-profiles/' . $id;

        foreach ($data['schools'] as &$sch) {
            if ($sch['id'] === $activeSchoolId) {
                if (! isset($sch['teachers'])) $sch['teachers'] = [];
                $sch['teachers'][] = ['id' => $id, 'name' => $name, 'dir' => $dir];
                $sch['active_teacher'] = $id;
                break;
            }
        }

        @mkdir(storage_path('app/hpc/' . $dir), 0777, true);
        file_put_contents(storage_path('app/hpc/schools.json'), json_encode($data, JSON_PRETTY_PRINT));

        return response()->json(['ok' => true, 'id' => $id, 'name' => $name]);
    }

    public function deleteTeacher(Request $request)
    {
        $id = trim((string) $request->input('id'));
        if ($id === 'teacher_default') {
            return response()->json(['ok' => false, 'error' => 'Default teacher profile cannot be deleted.']);
        }

        $data = $this->getSchoolsData();
        $activeSchoolId = $data['active'] ?? 'default';

        foreach ($data['schools'] as &$sch) {
            if ($sch['id'] === $activeSchoolId) {
                $sch['teachers'] = array_values(array_filter($sch['teachers'] ?? [], fn($t) => $t['id'] !== $id));
                if (($sch['active_teacher'] ?? '') === $id) {
                    $sch['active_teacher'] = $sch['teachers'][0]['id'] ?? 'teacher_default';
                }
                break;
            }
        }
        file_put_contents(storage_path('app/hpc/schools.json'), json_encode($data, JSON_PRETTY_PRINT));
        return response()->json(['ok' => true]);
    }

    public function startSession()
    {
        $loginUrl   = config('hpc.login_url');
        $profileDir = $this->getActiveTeacherProfileDir();
        @mkdir($profileDir, 0777, true);

        exec("nohup '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' --user-data-dir=" . escapeshellarg($profileDir) . " " . escapeshellarg($loginUrl) . " > /dev/null 2>&1 &");

        $teacher = $this->getActiveTeacher();
        return response()->json(['ok' => true, 'message' => "Chrome opened for Teacher {$teacher['name']}."]);
    }

    public function endSession()
    {
        $profileDir = $this->getActiveTeacherProfileDir();
        exec("pkill -f " . escapeshellarg($profileDir) . " 2>/dev/null");
        if (is_dir($profileDir)) exec("rm -rf " . escapeshellarg($profileDir));
        return response()->json(['ok' => true, 'message' => "Session ended for active teacher profile."]);
    }

    public function getEvaluationColumns(): array
    {
        return [
            // ── Student Metadata (Locked) ─────────────────────────────────────────────
            ['key' => 'apaar_id',               'label' => 'APAAR ID (Locked)',                      'width' => 20, 'type' => 'locked',  'note' => 'Official APAAR ID if assigned'],
            ['key' => 'login_id',               'label' => 'HPC Login ID (Locked) *',                'width' => 22, 'type' => 'locked',  'note' => 'Unique student ID on portal'],
            ['key' => 'name',                   'label' => 'Student Name (Locked) *',                'width' => 26, 'type' => 'locked',  'note' => 'Locked name from portal'],
            ['key' => 'status',                 'label' => 'Status (Locked)',                        'width' => 18, 'type' => 'locked',  'note' => 'HPC Completed (skipped) / Pending'],

            // ── Part-A(1) Student Profile & Attendance ─────────────────────────────────
            ['key' => 'admission_no',           'label' => 'School Admission Number *',              'width' => 22, 'type' => 'string',  'note' => 'Official school admission / roll no.'],
            ['key' => 'grade',                  'label' => 'Class / Grade *',                        'width' => 16, 'type' => 'list',    'options' => '"Grade 6,Grade 7,Grade 8"', 'note' => 'Select grade'],
            ['key' => 'section',                'label' => 'Section',                                'width' => 12, 'type' => 'list',    'options' => '"A,B,C,D,E"', 'note' => 'Select section'],
            ['key' => 'location',               'label' => 'Area / Location',                        'width' => 14, 'type' => 'list',    'options' => '"Rural,Urban"', 'note' => 'Rural or Urban'],
            ['key' => 'medium_of_instruction',  'label' => 'Medium of Instruction *',                'width' => 22, 'type' => 'list',    'options' => '"English,Urdu,Hindi,Kashmiri,Dogri,Other"', 'note' => 'Instructional medium'],
            ['key' => 'mother_tongue',          'label' => 'Mother Tongue *',                        'width' => 18, 'type' => 'list',    'options' => '"Kashmiri,Urdu,Hindi,English,Dogri,Punjabi,Other"', 'note' => 'Student mother tongue'],
            ['key' => 'relation',               'label' => 'Parent Relation *',                      'width' => 22, 'type' => 'list',    'options' => '"Father,Mother,Guardian (Other than Parents)"', 'note' => 'Select relation'],
            ['key' => 'parent_name',            'label' => 'Parent / Guardian Name *',               'width' => 24, 'type' => 'string',  'note' => 'Full parent / guardian name'],
            // Monthly Working Days (12 Months: Apr - Mar)
            ['key' => 'work_apr',               'label' => 'Working Days - Apr *',                   'width' => 16, 'type' => 'integer', 'note' => 'April working days (e.g. 20)'],
            ['key' => 'work_may',               'label' => 'Working Days - May *',                   'width' => 16, 'type' => 'integer', 'note' => 'May working days (e.g. 22)'],
            ['key' => 'work_jun',               'label' => 'Working Days - Jun *',                   'width' => 16, 'type' => 'integer', 'note' => 'June working days (e.g. 10)'],
            ['key' => 'work_jul',               'label' => 'Working Days - Jul *',                   'width' => 16, 'type' => 'integer', 'note' => 'July working days (e.g. 20)'],
            ['key' => 'work_aug',               'label' => 'Working Days - Aug *',                   'width' => 16, 'type' => 'integer', 'note' => 'August working days (e.g. 22)'],
            ['key' => 'work_sep',               'label' => 'Working Days - Sep *',                   'width' => 16, 'type' => 'integer', 'note' => 'September working days (e.g. 21)'],
            ['key' => 'work_oct',               'label' => 'Working Days - Oct *',                   'width' => 16, 'type' => 'integer', 'note' => 'October working days (e.g. 19)'],
            ['key' => 'work_nov',               'label' => 'Working Days - Nov *',                   'width' => 16, 'type' => 'integer', 'note' => 'November working days (e.g. 20)'],
            ['key' => 'work_dec',               'label' => 'Working Days - Dec *',                   'width' => 16, 'type' => 'integer', 'note' => 'December working days (e.g. 15)'],
            ['key' => 'work_jan',               'label' => 'Working Days - Jan *',                   'width' => 16, 'type' => 'integer', 'note' => 'January working days (e.g. 15)'],
            ['key' => 'work_feb',               'label' => 'Working Days - Feb *',                   'width' => 16, 'type' => 'integer', 'note' => 'February working days (e.g. 20)'],
            ['key' => 'work_mar',               'label' => 'Working Days - Mar *',                   'width' => 16, 'type' => 'integer', 'note' => 'March working days (e.g. 18)'],
            ['key' => 'total_work',             'label' => 'Total Working Days (Auto)',              'width' => 20, 'type' => 'calc',    'note' => 'Auto-calculated sum: =SUM(M:X)'],

            // Monthly Present Days (12 Months: Apr - Mar)
            ['key' => 'pres_apr',               'label' => 'Present Days - Apr *',                   'width' => 16, 'type' => 'integer', 'note' => 'April present days (e.g. 19)'],
            ['key' => 'pres_may',               'label' => 'Present Days - May *',                   'width' => 16, 'type' => 'integer', 'note' => 'May present days (e.g. 21)'],
            ['key' => 'pres_jun',               'label' => 'Present Days - Jun *',                   'width' => 16, 'type' => 'integer', 'note' => 'June present days (e.g. 10)'],
            ['key' => 'pres_jul',               'label' => 'Present Days - Jul *',                   'width' => 16, 'type' => 'integer', 'note' => 'July present days (e.g. 18)'],
            ['key' => 'pres_aug',               'label' => 'Present Days - Aug *',                   'width' => 16, 'type' => 'integer', 'note' => 'August present days (e.g. 20)'],
            ['key' => 'pres_sep',               'label' => 'Present Days - Sep *',                   'width' => 16, 'type' => 'integer', 'note' => 'September present days (e.g. 20)'],
            ['key' => 'pres_oct',               'label' => 'Present Days - Oct *',                   'width' => 16, 'type' => 'integer', 'note' => 'October present days (e.g. 18)'],
            ['key' => 'pres_nov',               'label' => 'Present Days - Nov *',                   'width' => 16, 'type' => 'integer', 'note' => 'November present days (e.g. 17)'],
            ['key' => 'pres_dec',               'label' => 'Present Days - Dec *',                   'width' => 16, 'type' => 'integer', 'note' => 'December present days (e.g. 14)'],
            ['key' => 'pres_jan',               'label' => 'Present Days - Jan *',                   'width' => 16, 'type' => 'integer', 'note' => 'January present days (e.g. 13)'],
            ['key' => 'pres_feb',               'label' => 'Present Days - Feb *',                   'width' => 16, 'type' => 'integer', 'note' => 'February present days (e.g. 19)'],
            ['key' => 'pres_mar',               'label' => 'Present Days - Mar *',                   'width' => 16, 'type' => 'integer', 'note' => 'March present days (e.g. 17)'],
            ['key' => 'total_pres',             'label' => 'Total Present Days (Auto)',              'width' => 20, 'type' => 'calc',    'note' => 'Auto-calculated sum: =SUM(Z:AK)'],

            // Attendance % & Absence
            ['key' => 'attendance_pct',         'label' => 'Attendance % (Auto)',                    'width' => 18, 'type' => 'calc',    'note' => 'Auto %: (Present/Working)*100'],
            ['key' => 'absent_count',           'label' => 'Absent Times Count',                     'width' => 18, 'type' => 'integer', 'note' => 'Total times remained absent (e.g. 16)'],
            ['key' => 'absence_reason',         'label' => 'Reason for Absence (A1)',                'width' => 26, 'type' => 'string',  'note' => 'e.g. Brief seasonal viral illness'],
            ['key' => 'interest',               'label' => 'Primary Interest / Hobby (A1) *',         'width' => 32, 'type' => 'list',    'options' => '"Reading,Dancing or Singing or Playing a musical instrument,Creative Writing,Gardening,Yoga,Art,Craft,Sports or Games,Cooking,Participation at home"', 'note' => 'Student hobby from Doc 29'],

            // ── Part-A(2) All About Me & Goal Setting ──────────────────────────────────
            ['key' => 'live_with',              'label' => 'I Live With (A2)',                       'width' => 24, 'type' => 'string',  'note' => 'e.g. Parents and Grandparents'],
            ['key' => 'stay_at',                'label' => 'We Stay At (A2)',                        'width' => 22, 'type' => 'string',  'note' => 'e.g. Sugin, Budgam'],
            ['key' => 'free_time',              'label' => 'Free Time Activity (A2)',                'width' => 28, 'type' => 'string',  'note' => 'e.g. Reading storybooks, science experiments & cricket'],
            ['key' => 'do_well',                'label' => 'Things I Do Very Well (A2)',             'width' => 28, 'type' => 'string',  'note' => 'e.g. Mental arithmetic, sketching & recitation'],
            ['key' => 'responsible',            'label' => 'I Am Responsible (A2) *',               'width' => 22, 'type' => 'list',    'options' => '"all the time,most times,sometimes"', 'note' => 'Exact radio from Doc 29'],
            ['key' => 'do_better',              'label' => 'Could Do Better At (A2)',                'width' => 28, 'type' => 'string',  'note' => 'e.g. Public speaking & cursive handwriting speed'],
            ['key' => 'care_others',            'label' => 'I Care About Others (A2)',               'width' => 30, 'type' => 'string',  'note' => 'e.g. Sharing study notes & helping classmates'],
            ['key' => 'feel_proud',             'label' => 'Feel Proud When (A2)',                   'width' => 30, 'type' => 'string',  'note' => 'e.g. Solving difficult math problems independently'],
            ['key' => 'friend_name',            'label' => 'Best Friend Name (A2)',                  'width' => 22, 'type' => 'string',  'note' => 'e.g. Ayaan Ahmad'],
            ['key' => 'friend_age',             'label' => 'Best Friend Age (A2)',                   'width' => 18, 'type' => 'integer', 'note' => 'e.g. 12'],
            ['key' => 'acad_goal',              'label' => 'Academic Goal (A2)',                     'width' => 28, 'type' => 'string',  'note' => 'e.g. Score above 90% in Maths & Science'],
            ['key' => 'acad_goal_why',          'label' => 'Why Academic Goal Matters (A2) *',       'width' => 32, 'type' => 'string',  'note' => 'e.g. Foundation for engineering studies'],
            ['key' => 'acad_step1',             'label' => 'Academic Goal Step 1 (A2)',              'width' => 28, 'type' => 'string',  'note' => 'e.g. Daily formula revision and practice'],
            ['key' => 'acad_step2',             'label' => 'Academic Goal Step 2 (A2)',              'width' => 28, 'type' => 'string',  'note' => 'e.g. Weekly problem solving tests'],
            ['key' => 'pers_goal',              'label' => 'Personal Goal (A2)',                     'width' => 28, 'type' => 'string',  'note' => 'e.g. Morning exercise & reading routine'],
            ['key' => 'pers_goal_why',          'label' => 'Why Personal Goal Matters (A2) *',       'width' => 32, 'type' => 'string',  'note' => 'e.g. Boosts stamina and daily concentration'],
            ['key' => 'pers_step1',             'label' => 'Personal Goal Step 1 (A2)',              'width' => 28, 'type' => 'string',  'note' => 'e.g. Wake up at 6 AM and 30 min yoga'],
            ['key' => 'pers_step2',             'label' => 'Personal Goal Step 2 (A2)',              'width' => 28, 'type' => 'string',  'note' => 'e.g. Read 15 pages of books before bed'],
            ['key' => 'sch_learn1',             'label' => 'School Learning 1 (A2)',                 'width' => 28, 'type' => 'string',  'note' => 'e.g. Laws of reflection and light refraction'],
            ['key' => 'sch_learn2',             'label' => 'School Learning 2 (A2)',                 'width' => 28, 'type' => 'string',  'note' => 'e.g. Solving linear equations with one variable'],
            ['key' => 'sch_learn3',             'label' => 'School Learning 3 (A2)',                 'width' => 28, 'type' => 'string',  'note' => 'e.g. Preamble and fundamental civic rights'],
            ['key' => 'out_learn1',             'label' => 'Outside Learning 1 (A2)',                'width' => 28, 'type' => 'string',  'note' => 'e.g. Kitchen gardening and composting at home'],
            ['key' => 'out_learn2',             'label' => 'Outside Learning 2 (A2)',                'width' => 28, 'type' => 'string',  'note' => 'e.g. Bicycle maintenance and chain lubrication'],
            ['key' => 'out_learn3',             'label' => 'Outside Learning 3 (A2)',                'width' => 28, 'type' => 'string',  'note' => 'e.g. Basic family budgeting and grocery calculation'],
            ['key' => 'help_with',              'label' => 'How Teacher Can Help (A2)',              'width' => 30, 'type' => 'string',  'note' => 'e.g. Science laboratory experiments & advanced math'],
            ['key' => 'teacher_know',           'label' => 'Teacher Should Know (A2)',               'width' => 30, 'type' => 'string',  'note' => 'e.g. Thrives in collaborative practical projects'],

            // ── Part-A(3) My Ambition Card ─────────────────────────────────────────────
            ['key' => 'ambition',               'label' => 'My Ambition Is (A3) *',                  'width' => 24, 'type' => 'string',  'note' => 'e.g. Astrophysicist / Software Engineer / Doctor'],
            ['key' => 'achieve_how',            'label' => 'Achieve Ambition By (A3) *',             'width' => 32, 'type' => 'string',  'note' => 'e.g. Deep conceptual study in Maths & Science'],
            ['key' => 'skills_needed',          'label' => '5 Skills Needed (A3) *',                 'width' => 30, 'type' => 'string',  'note' => 'e.g. Critical Thinking, Coding, Persistence'],
            ['key' => 'focus_subjects',         'label' => 'Subjects To Focus On (A3) *',            'width' => 28, 'type' => 'string',  'note' => 'e.g. Mathematics, Science, English, Computing'],
            ['key' => 'habits_build',           'label' => 'Habits To Build (A3)',                   'width' => 28, 'type' => 'string',  'note' => 'e.g. Daily disciplined study & curious questioning'],
            ['key' => 'i_will',                 'label' => 'I Will... (A3) *',                       'width' => 30, 'type' => 'string',  'note' => 'e.g. Maintain daily science journal and problem solving'],
            ['key' => 'mentor',                 'label' => 'Guidance From Mentor (A3) *',            'width' => 24, 'type' => 'string',  'note' => 'Teacher Mentor name (e.g. Bilal Ahmad Tali)'],
            ['key' => 'mentor_help',            'label' => 'Mentor Will Help By (A3) *',             'width' => 32, 'type' => 'string',  'note' => 'e.g. Recommending advanced reference books & Olympiads'],
            ['key' => 'learn_new',              'label' => 'I Will Learn New... (A3) *',             'width' => 30, 'type' => 'string',  'note' => 'e.g. Scientific analysis and computer algorithms'],
            ['key' => 'feel_when_achieve',      'label' => 'I Will Feel When Achieved (A3) *',       'width' => 30, 'type' => 'string',  'note' => 'e.g. Deeply fulfilled, proud, and inspired'],
            ['key' => 'parents_feel',           'label' => 'Parents Will Feel (A3) *',               'width' => 28, 'type' => 'string',  'note' => 'e.g. Immensely joyful, proud, and grateful'],

            // ── Part-A(4) Parent-Teacher Partnership Card ──────────────────────────────
            ['key' => 'home_resources',         'label' => 'Home Resources Available (A4) *',        'width' => 32, 'type' => 'string',  'note' => 'e.g. Books, Newspaper, Computer, Internet, TV'],
            ['key' => 'p_motivated',            'label' => 'Child Motivated To Learn (A4) *',        'width' => 24, 'type' => 'list',    'options' => '"Yes,Sometimes,No,Not Sure"', 'note' => 'Doc 29 Q1: motivated to learn'],
            ['key' => 'p_schedule',             'label' => 'Follows Home Schedule (A4) *',           'width' => 22, 'type' => 'list',    'options' => '"Yes,Sometimes,No,Not Sure"', 'note' => 'Doc 29 Q2: home schedule & screen time'],
            ['key' => 'p_curriculum_diff',      'label' => 'Curriculum Difficult (A4) *',            'width' => 22, 'type' => 'list',    'options' => '"No,Sometimes,Yes,Not Sure"', 'note' => 'Doc 29 Q3: curriculum difficulty'],
            ['key' => 'p_progress',             'label' => 'Good Grade Progress (A4) *',             'width' => 22, 'type' => 'list',    'options' => '"Yes,Sometimes,No,Not Sure"', 'note' => 'Doc 29 Q4: grade progress'],
            ['key' => 'child_needs_support',    'label' => 'Child Needs Support With (A4) *',        'width' => 32, 'type' => 'string',  'note' => 'e.g. Mathematics, Science, Time management'],
            ['key' => 'parent_feedback',        'label' => 'Parent Home Support Plan (A4) *',        'width' => 34, 'type' => 'string',  'note' => 'e.g. Evening study supervision & screen time limits'],
            ['key' => 'peer_rating',            'label' => 'Peer Review Collaboration (A4) *',       'width' => 24, 'type' => 'list',    'options' => '"All the time,Most times,Sometimes"', 'note' => 'Peer collaboration rating'],

            // ── Part-B Holistic Activities ─────────────────────────────────────────────
            ['key' => 'act_approach',           'label' => 'Activity Approach (B) *',                'width' => 24, 'type' => 'list',    'options' => '"Art-Integrated,Sports-Integrated,Toy-Based,Technology-Integrated,Experiential Learning,Any Other"', 'note' => 'Pedagogical approach from Doc 29'],
            ['key' => 'act_title',              'label' => 'Activity Title (B)',                     'width' => 28, 'type' => 'string',  'note' => 'e.g. Solar System 3D Model & Daily Life Geometry'],
            ['key' => 'act_desc',               'label' => 'Activity Description (B)',                'width' => 34, 'type' => 'string',  'note' => 'e.g. Created scale planetary orbits and verified ratios'],
            ['key' => 'act_rubric_awareness',   'label' => 'Rubric: Awareness (B) *',                'width' => 22, 'type' => 'list',    'options' => '"Beginner,Proficient,Advanced"', 'note' => 'Doc 29 rubric level'],
            ['key' => 'act_rubric_sensitivity', 'label' => 'Rubric: Sensitivity (B) *',              'width' => 22, 'type' => 'list',    'options' => '"Beginner,Proficient,Advanced"', 'note' => 'Doc 29 rubric level'],
            ['key' => 'act_rubric_creativity',  'label' => 'Rubric: Creativity (B) *',               'width' => 22, 'type' => 'list',    'options' => '"Beginner,Proficient,Advanced"', 'note' => 'Doc 29 rubric level'],
            ['key' => 'act_self_effort',        'label' => 'Self: Proud of Effort (B) *',            'width' => 22, 'type' => 'list',    'options' => '"Yes,To an extent,No,Not Sure"', 'note' => 'Doc 29 student reflection Q1'],
            ['key' => 'act_self_apply',         'label' => 'Self: Apply To Real Life (B) *',         'width' => 22, 'type' => 'list',    'options' => '"Yes,To an extent,No,Not Sure"', 'note' => 'Doc 29 student reflection Q2'],
            ['key' => 'act_self_motivated',     'label' => 'Self: Motivated To Learn (B) *',         'width' => 22, 'type' => 'list',    'options' => '"Yes,To an extent,No,Not Sure"', 'note' => 'Doc 29 student reflection Q3'],
            ['key' => 'act_learnt',             'label' => 'Student Learnt (B) *',                   'width' => 34, 'type' => 'string',  'note' => 'By doing this activity, I learnt (Doc 29)'],
            ['key' => 'act_interesting',        'label' => 'Most Interesting Aspect (B) *',          'width' => 32, 'type' => 'string',  'note' => 'The most interesting thing was (Doc 29)'],
            ['key' => 'act_practice',           'label' => 'Need Practice On (B) *',                 'width' => 30, 'type' => 'string',  'note' => 'I need practice on (Doc 29)'],
            ['key' => 'act_help',               'label' => 'Need Help With (B) *',                   'width' => 30, 'type' => 'string',  'note' => 'I need help with (Doc 29)'],
            ['key' => 'act_peer_name',          'label' => 'Peer Reviewer Name (B) *',               'width' => 22, 'type' => 'string',  'note' => 'My Peer\'s Name is (Doc 29)'],
            ['key' => 'act_peer_engaged',       'label' => 'Peer: Engaged & Motivated (B) *',        'width' => 24, 'type' => 'list',    'options' => '"Yes,Sometimes,No,Not Sure"', 'note' => 'Doc 29 Peer Q1'],
            ['key' => 'act_peer_shared',        'label' => 'Peer: Shared Thoughts (B) *',            'width' => 24, 'type' => 'list',    'options' => '"Yes,Sometimes,No,Not Sure"', 'note' => 'Doc 29 Peer Q2'],
            ['key' => 'act_peer_practice',      'label' => 'Peer: Needs Practice On (B)',            'width' => 30, 'type' => 'string',  'note' => 'My peer needs to practice (Doc 29)'],
            ['key' => 'act_peer_help',          'label' => 'Peer: Needs Help With (B)',              'width' => 30, 'type' => 'string',  'note' => 'My peer needs help with (Doc 29)'],
            ['key' => 'act_peer_feedback',      'label' => 'Peer General Remarks (B)',               'width' => 32, 'type' => 'string',  'note' => 'Peer holistic comment'],
            ['key' => 'act_strength',           'label' => 'Teacher Strength Area (B) *',            'width' => 26, 'type' => 'list',    'options' => '"Follow Instructions,Collaboration,Independent Work,Responsible,Communication,Creative,Solution-Focused Thinking,Concentration,Empathy,Organization & Prioritization"', 'note' => 'Exact from Doc 29'],
            ['key' => 'act_barriers',           'label' => 'Teacher Barrier(s) Area (B) *',          'width' => 26, 'type' => 'list',    'options' => '"None,Lack of Attention,Peer Pressure,Lack of Motivation,Undefined Goals,Lack of Preparation,Domestic Issues,Inappropriate behavior in classroom,Severe illness or injury"', 'note' => 'Exact from Doc 29'],
            ['key' => 'act_support_plan',       'label' => 'Teacher Support Plan (B) *',             'width' => 34, 'type' => 'string',  'note' => 'How I can help student progress further (Doc 29)'],

            // ── Part-C Year-End Curricular Assessment ───────────────────────────────────
            ['key' => 'c_formative',            'label' => 'Formative Overall Level (C) *',          'width' => 22, 'type' => 'list',    'options' => '"Beginner,Proficient,Advanced"', 'note' => 'Curricular formative progress'],
            ['key' => 'c_summative',            'label' => 'Summative Overall Level (C) *',          'width' => 22, 'type' => 'list',    'options' => '"Beginner,Proficient,Advanced"', 'note' => 'Curricular summative progress'],

            // ── HPC Credit (Summative Assessment Marks Grid) ───────────────────────────
            ['key' => 'l1_max',                 'label' => 'Lang 1 Max Marks *',                     'width' => 18, 'type' => 'integer', 'note' => 'Default 50'],
            ['key' => 'l1_obt',                 'label' => 'Lang 1 Marks Obtained *',                'width' => 20, 'type' => 'integer', 'note' => '0 to Max Marks'],
            ['key' => 'l2_max',                 'label' => 'Lang 2 Max Marks *',                     'width' => 18, 'type' => 'integer', 'note' => 'Default 50'],
            ['key' => 'l2_obt',                 'label' => 'Lang 2 Marks Obtained *',                'width' => 20, 'type' => 'integer', 'note' => '0 to Max Marks'],
            ['key' => 'l3_max',                 'label' => 'Lang 3 Max Marks *',                     'width' => 18, 'type' => 'integer', 'note' => 'Default 50'],
            ['key' => 'l3_obt',                 'label' => 'Lang 3 Marks Obtained *',                'width' => 20, 'type' => 'integer', 'note' => '0 to Max Marks'],
            ['key' => 'm_max',                  'label' => 'Maths Max Marks *',                      'width' => 18, 'type' => 'integer', 'note' => 'Default 50'],
            ['key' => 'm_obt',                  'label' => 'Maths Marks Obtained *',                 'width' => 20, 'type' => 'integer', 'note' => '0 to Max Marks'],
            ['key' => 'sci_max',                'label' => 'Science Max Marks *',                    'width' => 18, 'type' => 'integer', 'note' => 'Default 50'],
            ['key' => 'sci_obt',                'label' => 'Science Marks Obtained *',               'width' => 20, 'type' => 'integer', 'note' => '0 to Max Marks'],
            ['key' => 'soc_max',                'label' => 'Soc Sci Max Marks *',                    'width' => 18, 'type' => 'integer', 'note' => 'Default 50'],
            ['key' => 'soc_obt',                'label' => 'Soc Sci Marks Obtained *',               'width' => 20, 'type' => 'integer', 'note' => '0 to Max Marks'],
            ['key' => 'art_max',                'label' => 'Art Ed Max Marks *',                     'width' => 18, 'type' => 'integer', 'note' => 'Default 50'],
            ['key' => 'art_obt',                'label' => 'Art Ed Marks Obtained *',                'width' => 20, 'type' => 'integer', 'note' => '0 to Max Marks'],
            ['key' => 'pe_max',                 'label' => 'Physical Ed Max Marks *',                'width' => 20, 'type' => 'integer', 'note' => 'Default 50'],
            ['key' => 'pe_obt',                 'label' => 'Physical Ed Marks Obt *',                'width' => 20, 'type' => 'integer', 'note' => '0 to Max Marks'],

            // ── Declaration & Final Remarks ────────────────────────────────────────────
            ['key' => 'declaration',            'label' => 'Declaration Certified *',                'width' => 22, 'type' => 'list',    'options' => '"Yes,No"', 'note' => 'Confirm certified evaluation'],
            ['key' => 'remarks',                'label' => 'Teacher Holistic Summary Remarks',        'width' => 36, 'type' => 'string',  'note' => 'Professional teacher overall appraisal'],
        ];
    }

    public function getDemoStudentRow(): array
    {
        $wd = SchoolWorkingDay::getDefault();
        $m = $wd->toMonthMap();

        return [
            // Metadata
            'SAMPLE999',
            'DEMO_SAMPLE',
            '[SAMPLE DEMO STUDENT - GUIDE ONLY]',
            'Sample Demo',
            // Part-A(1)
            'ADM-2026-089',
            'Grade 6',
            'A',
            'Rural',
            'English',
            'Kashmiri',
            'Father',
            'Mohammad Ahmad Wani',
            // Monthly Working Days (12 Months: Apr - Mar)
            $m['apr'], $m['may'], $m['jun'], $m['jul'], $m['aug'], $m['sep'],
            $m['oct'], $m['nov'], $m['dec'], $m['jan'], $m['feb'], $m['mar'],
            '=SUM(M3:X3)',
            // Monthly Present Days (12 Months: Apr - Mar)
            min(19, $m['apr']), min(21, $m['may']), min(10, $m['jun']), min(18, $m['jul']),
            min(20, $m['aug']), min(20, $m['sep']), min(18, $m['oct']), min(17, $m['nov']),
            min(14, $m['dec']), min(13, $m['jan']), min(19, $m['feb']), min(17, $m['mar']),
            '=SUM(Z3:AK3)',
            // Attendance % & Absence
            '=IF(Y3>0, ROUND((AL3/Y3)*100, 1) & "%", "")',
            16,
            'Viral fever during seasonal change in November',
            'Reading',
            // Part-A(2)
            'Parents and Grandparents',
            'Sugin, Budgam',
            'Reading storybooks, science experiments & playing cricket',
            'Mental arithmetic, pencil sketching and recitation',
            'most times',
            'Public speaking and cursive handwriting speed',
            'Sharing study notes and helping classmates with difficulties',
            'Solving challenging geometry problems and helping peers',
            'Ayaan Ahmad',
            12,
            'Score above 90% in Mathematics and Science',
            'Builds solid foundation for pursuing higher studies in Engineering',
            'Dedicate 45 minutes daily to numerical problem solving and formula revision',
            'Clarify doubts with subject teachers immediately and practice weekly mock tests',
            'Develop regular physical exercise and disciplined reading habits',
            'Keeps mind alert, improves stamina, and enhances daily concentration',
            'Wake up at 6:00 AM every morning and do 30 minutes of yoga and jogging',
            'Read at least 15 pages of non-academic literature before bedtime',
            'Laws of reflection and refraction in science laboratory',
            'Formulating and solving linear equations in single variable',
            'Constitutional preamble and fundamental rights in Social Science',
            'Organic kitchen gardening, composting, and soil preparation',
            'Bicycle maintenance, tire inflation, and chain lubrication',
            'Basic household budgeting and grocery calculation',
            'Hands-on science experiments and advanced mathematical puzzles',
            'I am enthusiastic about collaborative group projects and active discussions',
            // Part-A(3)
            'Astrophysicist & Space Research Scientist',
            'Deep conceptual focus in Mathematics, regular physics experiments, and continuous reading',
            'Critical Thinking, Problem Solving, Data Analysis, Scientific Curiosity, Persistence',
            'Mathematics, Physics, Chemistry, English, Computer Science',
            'Consistent daily study routine, punctual time management, and inquisitive questioning',
            'Maintain daily science journal and read monthly astronomy publications',
            'Bilal Ahmad Tali',
            'Mentoring in Olympiad preparations and recommending advanced reference materials',
            'Scientific problem-solving strategies and computational simulations',
            'Immensely fulfilled, proud, and inspired to contribute to the nation space mission',
            'Overjoyed, deeply proud, and grateful',
            // Part-A(4)
            'Books and Magazines, Newspaper, Phone and Computer, Internet, T.V., Toys Games Sports',
            'Yes',
            'Yes',
            'No',
            'Yes',
            'Mathematics, Science, Developing effective study skills like time management',
            'Provide quiet study space, supervise 1.5 hours of evening revision, monitor screen time, and discuss daily learnings',
            'All the time',
            // Part-B
            'Art-Integrated',
            'Solar System 3D Model & Daily Life Geometry',
            'Constructed scale models of planetary orbits, computed relative ratios, and demonstrated to class',
            'Advanced',
            'Proficient',
            'Advanced',
            'Yes',
            'Yes',
            'Yes',
            'Learned celestial orbital distances, 3D scale geometry, and public presentation skills',
            'Fabricating rotating planetary models using recycled materials and LED lighting',
            'Time management during hands-on model assembly and fine scale calibrations',
            'Calculating complex elliptical orbit equations and angular measurements',
            'Ayaan Ahmad',
            'Yes',
            'Yes',
            'Color shading and finer model labeling',
            'Mathematical formula application during group work',
            'Very supportive and collaborative teammate; explained difficult astronomical concepts clearly',
            'Critical Thinking',
            'None',
            'Encourage participation in inter-school science competitions, provide advanced telescope observation sessions, and recommend for student mentorship roles',
            // Part-C
            'Advanced',
            'Proficient',
            // HPC Credit Marks
            50, 46,
            50, 45,
            50, 44,
            50, 48,
            50, 47,
            50, 44,
            50, 48,
            50, 49,
            // Declaration & Remarks
            'Yes',
            'Faizan is an exceptionally motivated, intellectually curious, and responsible student. Demonstrates superior grasp in Mathematics and Sciences, with remarkable peer collaboration and positive leadership in class activities.'
        ];
    }
}

