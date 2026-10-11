<?php

namespace App\Http\Controllers;

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

        return view('hpc.teacher-dashboard', compact(
            'schoolsData',
            'activeSchool',
            'activeTeacher',
            'hasSession',
            'stages',
            'students',
            'preview',
            'lastRun'
        ));
    }

    // ─── Step 1: Download Filled Roster Template ──────────────────────────────

    public function downloadTemplate(Request $request)
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

        $sheet = new Spreadsheet();
        $ws = $sheet->getActiveSheet();
        $ws->setTitle('HPC Assessment');

        $columns = [
            ['key' => 'name',         'label' => 'Student Name (Locked) *',  'width' => 24, 'note' => 'Pre-filled from portal records'],
            ['key' => 'login_id',     'label' => 'HPC Login ID (Locked) *',  'width' => 22, 'note' => 'Portal Login ID (do not edit)'],
            ['key' => 'mother_tongue','label' => 'Mother Tongue *',          'width' => 18, 'note' => 'e.g. Kashmiri, Urdu, Hindi'],
            ['key' => 'relation',     'label' => 'Parent Relation *',        'width' => 18, 'note' => 'Select Father, Mother, Guardian'],
            ['key' => 'parent_name',  'label' => 'Parent / Guardian Name *', 'width' => 24, 'note' => 'Father / Mother / Guardian full name'],
            ['key' => 'working_days', 'label' => 'Avg Working Days/Mo *',    'width' => 20, 'note' => 'Monthly school working days (e.g. 22)'],
            ['key' => 'present_days', 'label' => 'Avg Present Days/Mo *',    'width' => 20, 'note' => 'Monthly days student present (e.g. 20)'],
            ['key' => 'interests',    'label' => 'Student Interests *',      'width' => 26, 'note' => 'Reading, Art, Craft, Sports, Music...'],
            ['key' => 'academic_goal','label' => 'Academic Goal',            'width' => 26, 'note' => 'e.g. Excel in Science and Mathematics'],
            ['key' => 'ambition',     'label' => 'Student Ambition',         'width' => 20, 'note' => 'e.g. Teacher, Engineer, Doctor'],
            ['key' => 'self_rating',  'label' => 'Self Assessment Rating *', 'width' => 22, 'note' => 'Yes, To an extent, Sometimes, No'],
            ['key' => 'peer_rating',  'label' => 'Peer Assessment Rating *', 'width' => 22, 'note' => 'Yes, Sometimes, No, Not Sure'],
            ['key' => 'activity_name','label' => 'Activity Name (Part-B) *', 'width' => 26, 'note' => 'e.g. Science Fair Project, Art Model'],
            ['key' => 'progress_lvl', 'label' => 'Progress Level (Part-C) *','width' => 22, 'note' => 'Proficient, Advanced, Developing'],
            ['key' => 'remarks',      'label' => 'Teacher Remarks',          'width' => 30, 'note' => 'Overall holistic summary note'],
        ];

        // ── Header row (Row 1) ────────────────────────────────────────────────
        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 11],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCBD5E1']]],
        ];

        foreach ($columns as $i => $col) {
            $letter = chr(65 + $i);
            $ws->setCellValue("{$letter}1", $col['label']);
            $ws->getColumnDimension($letter)->setWidth($col['width']);
            $ws->getStyle("{$letter}1")->applyFromArray($headerStyle);
        }
        $ws->getRowDimension(1)->setRowHeight(28);

        // ── Notes row (Row 2) ─────────────────────────────────────────────────
        $noteStyle = [
            'font'      => ['italic' => true, 'color' => ['argb' => 'FF475569'], 'size' => 9],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF1F5F9']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
        ];
        foreach ($columns as $i => $col) {
            $letter = chr(65 + $i);
            $ws->setCellValue("{$letter}2", $col['note']);
            $ws->getStyle("{$letter}2")->applyFromArray($noteStyle);
        }
        $ws->getRowDimension(2)->setRowHeight(20);

        // ── Pre-fill Student Rows (Starting Row 3) ────────────────────────────
        $rowStyleLocked = [
            'font'      => ['bold' => true, 'color' => ['argb' => 'FF0F172A'], 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF8FAFC']],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
        ];
        $rowStyleEditable = [
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFFFFF']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ];

        $relationFormula = '"Father,Mother,Guardian"';
        $selfFormula     = '"Yes,To an extent,Sometimes,No"';
        $peerFormula     = '"Yes,Sometimes,No,Not Sure"';
        $progressFormula = '"Proficient,Advanced,Developing"';

        foreach ($students as $idx => $st) {
            $r = $idx + 3;
            $ws->setCellValueExplicit("A{$r}", $st['name'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $ws->setCellValueExplicit("B{$r}", $st['login_id'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $ws->getStyle("A{$r}:B{$r}")->applyFromArray($rowStyleLocked);

            // Default suggested values
            $ws->setCellValueExplicit("C{$r}", 'Kashmiri', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $ws->setCellValueExplicit("D{$r}", 'Father', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $ws->setCellValueExplicit("E{$r}", 'Mohammad Ahmad', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $ws->setCellValueExplicit("F{$r}", '22', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $ws->setCellValueExplicit("G{$r}", '20', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $ws->setCellValueExplicit("H{$r}", 'Reading, Art, Sports', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $ws->setCellValueExplicit("I{$r}", 'Excel in Science and Mathematics', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $ws->setCellValueExplicit("J{$r}", 'Teacher', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $ws->setCellValueExplicit("K{$r}", 'Yes', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $ws->setCellValueExplicit("L{$r}", 'Yes', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $ws->setCellValueExplicit("M{$r}", 'Science Model Activity', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $ws->setCellValueExplicit("N{$r}", 'Proficient', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $ws->setCellValueExplicit("O{$r}", 'Consistent performance and active classroom participation', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

            $lastLetter = chr(64 + count($columns));
            $ws->getStyle("C{$r}:{$lastLetter}{$r}")->applyFromArray($rowStyleEditable);

            // Dropdown validations
            $this->setValidation($ws, "D{$r}", $relationFormula);
            $this->setValidation($ws, "K{$r}", $selfFormula);
            $this->setValidation($ws, "L{$r}", $peerFormula);
            $this->setValidation($ws, "N{$r}", $progressFormula);

            $ws->getRowDimension($r)->setRowHeight(22);
        }

        $ws->freezePane('C3');

        $filename = 'hpc_teacher_assessment_template.xlsx';
        $writer   = new Xlsx($sheet);

        return response()->stream(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
        ]);
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
            $ws  = $ss->getActiveSheet();
            $max = $ws->getHighestDataRow();

            for ($r = 3; $r <= $max; $r++) {
                $name        = trim((string) $ws->getCell("A{$r}")->getValue());
                $loginId     = trim((string) $ws->getCell("B{$r}")->getValue());
                $motherTongue= trim((string) $ws->getCell("C{$r}")->getValue());
                $relation    = trim((string) $ws->getCell("D{$r}")->getValue());
                $parentName  = trim((string) $ws->getCell("E{$r}")->getValue());
                $workingDays = trim((string) $ws->getCell("F{$r}")->getValue());
                $presentDays = trim((string) $ws->getCell("G{$r}")->getValue());
                $interests   = trim((string) $ws->getCell("H{$r}")->getValue());
                $goal        = trim((string) $ws->getCell("I{$r}")->getValue());
                $ambition    = trim((string) $ws->getCell("J{$r}")->getValue());
                $selfRating  = trim((string) $ws->getCell("K{$r}")->getValue());
                $peerRating  = trim((string) $ws->getCell("L{$r}")->getValue());
                $activity    = trim((string) $ws->getCell("M{$r}")->getValue());
                $progressLvl = trim((string) $ws->getCell("N{$r}")->getValue());
                $remarks     = trim((string) $ws->getCell("O{$r}")->getValue());

                if ($name === '' && $loginId === '') continue;

                $rowErrors = [];
                if ($name === '') $rowErrors[] = 'Student Name missing';
                if ($loginId === '') $rowErrors[] = 'HPC Login ID missing';
                if ($motherTongue === '') $rowErrors[] = 'Mother Tongue required';
                if ($parentName === '') $rowErrors[] = 'Parent Name required';

                $preview[] = [
                    'row'          => $r,
                    'name'         => $name,
                    'login_id'     => $loginId,
                    'mother_tongue'=> $motherTongue ?: 'Kashmiri',
                    'relation'     => $relation ?: 'Father',
                    'parent_name'  => $parentName ?: 'Parent',
                    'working_days' => $workingDays ?: '22',
                    'present_days' => $presentDays ?: '20',
                    'interests'    => $interests ?: 'Reading, Art',
                    'goal'         => $goal ?: 'Academic improvement',
                    'ambition'     => $ambition ?: 'Teacher',
                    'self_rating'  => $selfRating ?: 'Yes',
                    'peer_rating'  => $peerRating ?: 'Yes',
                    'activity'     => $activity ?: 'Science Project',
                    'progress_lvl' => $progressLvl ?: 'Proficient',
                    'remarks'      => $remarks ?: 'Good progress',
                    'errors'       => $rowErrors,
                ];

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
}
