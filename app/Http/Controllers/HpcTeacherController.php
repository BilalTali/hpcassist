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
            ['1', 'Student Metadata', 'Locked String', 'APAAR ID, HPC Login ID, Student Name, and Status. HPC Login ID is the unique identifier.', 'Pre-filled from school portal roster.'],
            ['2', 'Status Rule', 'System Rule', 'Students with status "HPC Completed" will be left untouched and skipped during update runs.', 'HPC Completed (skipped), HPC Pending / In Progress (updated)'],
            ['3', 'Part-A(1) Profile', 'Dragdown (Select)', 'Select valid Class / Grade from dropdown.', 'Grade 6, Grade 7, Grade 8'],
            ['4', 'Part-A(1) Profile', 'Dragdown (Select)', 'Select Medium of Instruction / Mother Tongue.', 'Kashmiri, Urdu, Hindi, English, Dogri, Punjabi, Other'],
            ['5', 'Part-A(1) Profile', 'Dragdown (Select)', 'Select relationship of guardian.', 'Father, Mother, Guardian (Other than Parents)'],
            ['6', 'Part-A(1) Attendance', 'Integer (Number)', 'Working and Present days must be numeric integers. Present days must be <= Working days.', 'Working: 150-260, Present: 0-260'],
            ['7', 'Part-A(1) Interests', 'Dragdown (Select)', 'Select student primary hobby / interest.', 'Reading, Drawing, Sports, Music, Craft, Science, Mathematics...'],
            ['8', 'Part-A(2) Habits', 'Dragdown (Select)', 'Student responsibility level in classroom.', 'All the time, Most times, Sometimes'],
            ['9', 'Part-A(3) Ambition', 'String & Dragdown', 'Career ambition string and primary 21st Century skill.', 'Critical Thinking, Communication, Collaboration, Problem Solving...'],
            ['10', 'Part-A(4) Home Feedback', 'Dragdown (Radio)', 'Parent observation ratings for motivation and progress.', 'Yes, Sometimes, No, Not Sure'],
            ['11', 'Part-B Activities', 'Dragdown (Select)', 'Pedagogical approach for holistic activities.', 'Art-Integrated, Sports-Integrated, Toy-Based, Technology-Integrated...'],
            ['12', 'Part-C Curricular', 'Dragdown (Radio)', 'Year-end formative and summative performance levels across domains.', 'Beginner, Proficient, Advanced'],
            ['13', 'HPC Credit (Marks)', 'Integer (0-100)', 'Summative Assessment marks for each of the 8 subjects.', 'Max Marks (default 50), Marks Obtained (0 to Max Marks)'],
            ['14', 'HPC Credit (Declaration)', 'Dragdown (Select)', 'Teacher declaration certifying entered evaluation.', 'Yes, No'],
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
        $instructionSheet->getColumnDimension('B')->setWidth(24);
        $instructionSheet->getColumnDimension('C')->setWidth(22);
        $instructionSheet->getColumnDimension('D')->setWidth(50);
        $instructionSheet->getColumnDimension('E')->setWidth(45);

        // ─── Sheet 2: Student Evaluations ─────────────────────────────────────
        $evalSheet = $spreadsheet->createSheet();
        $evalSheet->setTitle('Student Evaluations');

        $columns = [
            ['key' => 'apaar_id',     'label' => 'APAAR ID (Locked)',        'width' => 20, 'type' => 'locked',  'note' => 'Official APAAR ID if assigned'],
            ['key' => 'login_id',     'label' => 'HPC Login ID (Locked) *',  'width' => 22, 'type' => 'locked',  'note' => 'Unique student ID on portal'],
            ['key' => 'name',         'label' => 'Student Name (Locked) *',  'width' => 26, 'type' => 'locked',  'note' => 'Locked name from portal'],
            ['key' => 'status',       'label' => 'Status (Locked)',          'width' => 18, 'type' => 'locked',  'note' => 'HPC Completed (skipped) / Pending'],
            ['key' => 'grade',        'label' => 'Class / Grade *',          'width' => 16, 'type' => 'list',    'options' => '"Grade 6,Grade 7,Grade 8"', 'note' => 'Select grade'],
            ['key' => 'section',      'label' => 'Section',                  'width' => 12, 'type' => 'list',    'options' => '"A,B,C,D,E"', 'note' => 'Select section'],
            ['key' => 'location',     'label' => 'Area / Location',          'width' => 14, 'type' => 'list',    'options' => '"Rural,Urban"', 'note' => 'Rural or Urban'],
            ['key' => 'mother_tongue','label' => 'Mother Tongue *',          'width' => 18, 'type' => 'list',    'options' => '"Kashmiri,Urdu,Hindi,English,Dogri,Punjabi,Other"', 'note' => 'Medium / Mother tongue'],
            ['key' => 'relation',     'label' => 'Parent Relation *',        'width' => 22, 'type' => 'list',    'options' => '"Father,Mother,Guardian (Other than Parents)"', 'note' => 'Select relation'],
            ['key' => 'parent_name',  'label' => 'Parent / Guardian Name *', 'width' => 24, 'type' => 'string',  'note' => 'Full name'],
            ['key' => 'working_days', 'label' => 'School Working Days *',    'width' => 20, 'type' => 'integer', 'note' => 'Annual working days (e.g. 220)'],
            ['key' => 'present_days', 'label' => 'Student Present Days *',   'width' => 20, 'type' => 'integer', 'note' => 'Annual present days (e.g. 204)'],
            ['key' => 'interest',     'label' => 'Primary Interest *',       'width' => 20, 'type' => 'list',    'options' => '"Reading,Drawing,Sports,Music,Craft,Science,Mathematics,Drama,Dance,Social Service"', 'note' => 'Hobby / Interest'],
            ['key' => 'responsible',  'label' => 'Responsibility (A2)',      'width' => 20, 'type' => 'list',    'options' => '"All the time,Most times,Sometimes"', 'note' => 'Self-discipline level'],
            ['key' => 'academic_goal','label' => 'Academic Goal (A2)',       'width' => 26, 'type' => 'string',  'note' => 'e.g. Excel in Science and Mathematics'],
            ['key' => 'ambition',     'label' => 'Student Ambition (A3)',    'width' => 20, 'type' => 'string',  'note' => 'e.g. Teacher, Engineer, Doctor'],
            ['key' => 'skill',        'label' => 'Primary 21stC Skill (A3) *','width' => 22, 'type' => 'list',   'options' => '"Critical Thinking,Communication,Collaboration,Problem Solving,Creativity"', 'note' => 'Core skill'],
            ['key' => 'mentor',       'label' => 'Teacher Mentor Name',      'width' => 22, 'type' => 'string',  'note' => 'Mentor full name'],
            ['key' => 'p_motivation', 'label' => 'Parent Motivation (A4) *', 'width' => 22, 'type' => 'list',    'options' => '"Yes,Sometimes,No,Not Sure"', 'note' => 'Home engagement'],
            ['key' => 'p_progress',   'label' => 'Parent Progress (A4) *',   'width' => 22, 'type' => 'list',    'options' => '"Yes,Sometimes,No,Not Sure"', 'note' => 'Grade progress'],
            ['key' => 'act_approach', 'label' => 'Activity Approach (B) *',  'width' => 22, 'type' => 'list',    'options' => '"Art-Integrated,Sports-Integrated,Toy-Based,Technology-Integrated,Any Other"', 'note' => 'Pedagogical approach'],
            ['key' => 'c_formative',  'label' => 'Formative Level (C) *',    'width' => 20, 'type' => 'list',    'options' => '"Beginner,Proficient,Advanced"', 'note' => 'Curricular progress'],
            ['key' => 'c_summative',  'label' => 'Summative Level (C) *',    'width' => 20, 'type' => 'list',    'options' => '"Beginner,Proficient,Advanced"', 'note' => 'Curricular progress'],
            ['key' => 'l1_max',       'label' => 'Lang 1 Max Marks *',       'width' => 18, 'type' => 'integer', 'note' => 'Default 50'],
            ['key' => 'l1_obt',       'label' => 'Lang 1 Marks Obtained *',  'width' => 20, 'type' => 'integer', 'note' => '0 to Max Marks'],
            ['key' => 'l2_max',       'label' => 'Lang 2 Max Marks *',       'width' => 18, 'type' => 'integer', 'note' => 'Default 50'],
            ['key' => 'l2_obt',       'label' => 'Lang 2 Marks Obtained *',  'width' => 20, 'type' => 'integer', 'note' => '0 to Max Marks'],
            ['key' => 'l3_max',       'label' => 'Lang 3 Max Marks *',       'width' => 18, 'type' => 'integer', 'note' => 'Default 50'],
            ['key' => 'l3_obt',       'label' => 'Lang 3 Marks Obtained *',  'width' => 20, 'type' => 'integer', 'note' => '0 to Max Marks'],
            ['key' => 'm_max',        'label' => 'Maths Max Marks *',        'width' => 18, 'type' => 'integer', 'note' => 'Default 50'],
            ['key' => 'm_obt',        'label' => 'Maths Marks Obtained *',   'width' => 20, 'type' => 'integer', 'note' => '0 to Max Marks'],
            ['key' => 'sci_max',      'label' => 'Science Max Marks *',      'width' => 18, 'type' => 'integer', 'note' => 'Default 50'],
            ['key' => 'sci_obt',      'label' => 'Science Marks Obtained *', 'width' => 20, 'type' => 'integer', 'note' => '0 to Max Marks'],
            ['key' => 'soc_max',      'label' => 'Soc Sci Max Marks *',      'width' => 18, 'type' => 'integer', 'note' => 'Default 50'],
            ['key' => 'soc_obt',      'label' => 'Soc Sci Marks Obtained *', 'width' => 20, 'type' => 'integer', 'note' => '0 to Max Marks'],
            ['key' => 'art_max',      'label' => 'Art Ed Max Marks *',       'width' => 18, 'type' => 'integer', 'note' => 'Default 50'],
            ['key' => 'art_obt',      'label' => 'Art Ed Marks Obtained *',  'width' => 20, 'type' => 'integer', 'note' => '0 to Max Marks'],
            ['key' => 'pe_max',       'label' => 'Physical Ed Max Marks *',  'width' => 20, 'type' => 'integer', 'note' => 'Default 50'],
            ['key' => 'pe_obt',       'label' => 'Physical Ed Marks Obt *',  'width' => 20, 'type' => 'integer', 'note' => '0 to Max Marks'],
            ['key' => 'declaration',  'label' => 'Declaration Certified *',  'width' => 22, 'type' => 'list',    'options' => '"Yes,No"', 'note' => 'Confirm accurate evaluation'],
            ['key' => 'remarks',      'label' => 'Teacher Remarks',          'width' => 32, 'type' => 'string',  'note' => 'Holistic summary observation'],
        ];

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

        // Pre-fill student rows
        foreach ($students as $sIdx => $st) {
            $rowNum = $sIdx + 3;

            $defaults = [
                $st['apaar_id'] ?? '',
                $st['login_id'] ?? '',
                $st['name'] ?? '',
                $st['status'] ?? 'HPC Pending',
                'Grade 6',
                'A',
                'Rural',
                'Kashmiri',
                'Father',
                'Mohammad Ahmad',
                220,
                204,
                'Reading',
                'Most times',
                'Excel in Science and Mathematics',
                'Teacher',
                'Critical Thinking',
                'Bilal Ahmad',
                'Yes',
                'Yes',
                'Art-Integrated',
                'Proficient',
                'Proficient',
                50, 42,
                50, 40,
                50, 41,
                50, 44,
                50, 43,
                50, 42,
                50, 46,
                50, 47,
                'Yes',
                'Consistent performance and active classroom participation',
            ];

            foreach ($columns as $cIdx => $col) {
                $colLetter = $this->indexToColumnLetter($cIdx);
                $val = $defaults[$cIdx] ?? '';
                $evalSheet->setCellValue("{$colLetter}{$rowNum}", $val);

                if ($col['type'] === 'list') {
                    $validation = $evalSheet->getCell("{$colLetter}{$rowNum}")->getDataValidation();
                    $validation->setType(DataValidation::TYPE_LIST);
                    $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                    $validation->setAllowBlank(false);
                    $validation->setShowDropDown(true);
                    $validation->setShowInputMessage(true);
                    $validation->setPromptTitle('Select Option');
                    $validation->setPrompt('Click the dropdown arrow to pick a valid option.');
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

        $evalSheet->freezePane('E3');

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

            for ($r = 3; $r <= $max; $r++) {
                $apaarId     = trim((string) $ws->getCell("A{$r}")->getValue());
                $loginId     = trim((string) $ws->getCell("B{$r}")->getValue());
                $name        = trim((string) $ws->getCell("C{$r}")->getValue());
                $status      = trim((string) $ws->getCell("D{$r}")->getValue());
                $grade       = trim((string) $ws->getCell("E{$r}")->getValue());
                $section     = trim((string) $ws->getCell("F{$r}")->getValue());
                $location    = trim((string) $ws->getCell("G{$r}")->getValue());
                $motherTongue= trim((string) $ws->getCell("H{$r}")->getValue());
                $relation    = trim((string) $ws->getCell("I{$r}")->getValue());
                $parentName  = trim((string) $ws->getCell("J{$r}")->getValue());
                $workingDays = trim((string) $ws->getCell("K{$r}")->getValue());
                $presentDays = trim((string) $ws->getCell("L{$r}")->getValue());
                $interest    = trim((string) $ws->getCell("M{$r}")->getValue());
                $responsible = trim((string) $ws->getCell("N{$r}")->getValue());
                $goal        = trim((string) $ws->getCell("O{$r}")->getValue());
                $ambition    = trim((string) $ws->getCell("P{$r}")->getValue());
                $skill       = trim((string) $ws->getCell("Q{$r}")->getValue());
                $mentor      = trim((string) $ws->getCell("R{$r}")->getValue());
                $pMotivation = trim((string) $ws->getCell("S{$r}")->getValue());
                $pProgress   = trim((string) $ws->getCell("T{$r}")->getValue());
                $actApproach = trim((string) $ws->getCell("U{$r}")->getValue());
                $cFormative  = trim((string) $ws->getCell("V{$r}")->getValue());
                $cSummative  = trim((string) $ws->getCell("W{$r}")->getValue());

                // Marks
                $l1Max = trim((string) $ws->getCell("X{$r}")->getValue());
                $l1Obt = trim((string) $ws->getCell("Y{$r}")->getValue());
                $l2Max = trim((string) $ws->getCell("Z{$r}")->getValue());
                $l2Obt = trim((string) $ws->getCell("AA{$r}")->getValue());
                $l3Max = trim((string) $ws->getCell("AB{$r}")->getValue());
                $l3Obt = trim((string) $ws->getCell("AC{$r}")->getValue());
                $mMax  = trim((string) $ws->getCell("AD{$r}")->getValue());
                $mObt  = trim((string) $ws->getCell("AE{$r}")->getValue());
                $sciMax= trim((string) $ws->getCell("AF{$r}")->getValue());
                $sciObt= trim((string) $ws->getCell("AG{$r}")->getValue());
                $socMax= trim((string) $ws->getCell("AH{$r}")->getValue());
                $socObt= trim((string) $ws->getCell("AI{$r}")->getValue());
                $artMax= trim((string) $ws->getCell("AJ{$r}")->getValue());
                $artObt= trim((string) $ws->getCell("AK{$r}")->getValue());
                $peMax = trim((string) $ws->getCell("AL{$r}")->getValue());
                $peObt = trim((string) $ws->getCell("AM{$r}")->getValue());

                $declaration = trim((string) $ws->getCell("AN{$r}")->getValue());
                $remarks     = trim((string) $ws->getCell("AO{$r}")->getValue());

                if ($name === '' && $loginId === '') continue;

                $isCompleted = strcasecmp($status, 'HPC Completed') === 0;

                $rowErrors = [];
                if (! $isCompleted) {
                    if ($name === '') $rowErrors[] = 'Student Name missing';
                    if ($loginId === '') $rowErrors[] = 'HPC Login ID missing';
                    if ($motherTongue === '') $rowErrors[] = 'Mother Tongue required';
                    if ($parentName === '') $rowErrors[] = 'Parent Name required';
                }

                $preview[] = [
                    'row'          => $r,
                    'apaar_id'     => $apaarId,
                    'login_id'     => $loginId,
                    'name'         => $name,
                    'status'       => $status ?: 'HPC Pending',
                    'is_completed' => $isCompleted,
                    'grade'        => $grade ?: 'Grade 6',
                    'section'      => $section ?: 'A',
                    'location'     => $location ?: 'Rural',
                    'mother_tongue'=> $motherTongue ?: 'Kashmiri',
                    'relation'     => $relation ?: 'Father',
                    'parent_name'  => $parentName ?: 'Parent',
                    'working_days' => $workingDays ?: '220',
                    'present_days' => $presentDays ?: '204',
                    'interest'     => $interest ?: 'Reading',
                    'responsible'  => $responsible ?: 'Most times',
                    'goal'         => $goal ?: 'Academic improvement',
                    'ambition'     => $ambition ?: 'Teacher',
                    'skill'        => $skill ?: 'Critical Thinking',
                    'mentor'       => $mentor ?: 'Teacher Mentor',
                    'p_motivation' => $pMotivation ?: 'Yes',
                    'p_progress'   => $pProgress ?: 'Yes',
                    'act_approach' => $actApproach ?: 'Art-Integrated',
                    'c_formative'  => $cFormative ?: 'Proficient',
                    'c_summative'  => $cSummative ?: 'Proficient',
                    'marks' => [
                        'l1' => ['max' => $l1Max ?: '50', 'obt' => $l1Obt ?: '42'],
                        'l2' => ['max' => $l2Max ?: '50', 'obt' => $l2Obt ?: '40'],
                        'l3' => ['max' => $l3Max ?: '50', 'obt' => $l3Obt ?: '41'],
                        'm'  => ['max' => $mMax  ?: '50', 'obt' => $mObt  ?: '44'],
                        'sci'=> ['max' => $sciMax ?: '50', 'obt' => $sciObt ?: '43'],
                        'soc'=> ['max' => $socMax ?: '50', 'obt' => $socObt ?: '42'],
                        'art'=> ['max' => $artMax ?: '50', 'obt' => $artObt ?: '46'],
                        'pe' => ['max' => $peMax ?: '50', 'obt' => $peObt ?: '47'],
                    ],
                    'declaration'  => $declaration ?: 'Yes',
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
