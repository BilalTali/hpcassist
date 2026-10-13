<?php

namespace App\Http\Controllers;

use App\Models\SchoolWorkingDay;
use App\Models\SchoolMaxMark;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class HpcTeacherController extends Controller
{
    // ─── Dashboard View ───────────────────────────────────────────────────────

    public function getSchoolStorageDir($school = null): string
    {
        if (! $school && \Illuminate\Support\Facades\Auth::check()) {
            $school = \Illuminate\Support\Facades\Auth::user()->getEffectiveSchool();
        }

        if ($school) {
            $dir = storage_path('app/hpc/schools/' . $school->id);
            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            return $dir;
        }

        return storage_path('app/hpc');
    }

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

        $school = \Illuminate\Support\Facades\Auth::user()?->getEffectiveSchool();
        $schoolDir = $this->getSchoolStorageDir($school);

        $studentsFile = $schoolDir . '/teacher_students.json';
        $students = file_exists($studentsFile) ? (json_decode(file_get_contents($studentsFile), true) ?? []) : [];

        $previewFile = $schoolDir . '/teacher_preview.json';
        $preview = file_exists($previewFile) ? (json_decode(file_get_contents($previewFile), true) ?? []) : [];

        $resultsFile = $schoolDir . '/teacher_run_results.json';
        $lastRun = file_exists($resultsFile) ? json_decode(file_get_contents($resultsFile), true) : null;

        $workingDays = SchoolWorkingDay::getDefault();
        $maxMarks = SchoolMaxMark::getDefault();

        return view('hpc.teacher-dashboard', compact(
            'schoolsData',
            'activeSchool',
            'activeTeacher',
            'hasSession',
            'stages',
            'students',
            'preview',
            'lastRun',
            'workingDays',
            'maxMarks'
        ));
    }

    /**
     * Download Filled Roster Template.
     *
     * @param Request|null $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadTemplate(?Request $request = null): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $students = $this->getTeacherTemplateStudents();

        return $this->generateSpreadsheetResponse($students);
    }

    private function generateSpreadsheetResponse(array $students): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        /** @var Spreadsheet $spreadsheet */
        $spreadsheet = new Spreadsheet();

        $this->buildInstructionsSheet($spreadsheet);
        $this->buildEvaluationsSheet($spreadsheet, $students);

        $filename = 'hpc_teacher_assessment_template.xlsx';
        /** @var Xlsx $writer */
        $writer   = new Xlsx($spreadsheet);

        return response()->stream(function () use ($writer): void {
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
        ]);
    }

    private function getTeacherTemplateStudents(): array
    {
        $school       = \Illuminate\Support\Facades\Auth::user()?->getEffectiveSchool();
        $schoolDir    = $this->getSchoolStorageDir($school);
        $studentsFile = $schoolDir . '/teacher_students.json';
        $students     = file_exists($studentsFile) ? (json_decode(file_get_contents($studentsFile), true) ?? []) : [];

        if (empty($students) && $school && $school->students()->exists()) {
            $students = $school->students()->orderBy('id')->get()->map(function($st) {
                return [
                    'name'     => $st->name,
                    'apaar_id' => $st->apaar_id ?: '',
                    'login_id' => $st->pen_code ?: ($st->apaar_id ?: 'S' . strtoupper(substr(md5($st->id . $st->name), 0, 9))),
                    'status'   => 'HPC Pending',
                ];
            })->all();
        }

        if (empty($students)) {
            $students = [
                ['name' => 'Faizan Ahmad',     'login_id' => 'S1CA3C8009', 'status' => 'HPC Pending'],
                ['name' => 'Zehra Batool',     'login_id' => 'S49C309C52', 'status' => 'HPC Pending'],
                ['name' => 'Mohammad Yousuf',  'login_id' => 'S2C7236921', 'status' => 'HPC Pending'],
                ['name' => 'Insha Jan',        'login_id' => 'S9CD420A51', 'status' => 'HPC Pending'],
                ['name' => 'Sahil Bashir',     'login_id' => 'SD3675F62B', 'status' => 'HPC Pending'],
            ];
        }

        return $students;
    }

    private function buildInstructionsSheet(Spreadsheet $spreadsheet): void
    {
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
            ['3', 'Row 4 Guide Row', 'Guide Reference', 'Row 4 (DEMO_SAMPLE) provides a complete, realistic model of teacher evaluation for guidance. Do not delete or alter.', 'All 147 fields pre-filled for visual demonstration.'],
            ['4', 'Part-A(1) Monthly Attendance', 'Integer & Formulas', 'Working Days and Present Days are arranged neck-to-neck for each month (Apr to Mar). Total Working, Total Present, and Attendance % calculate automatically via Excel formulas.', 'Monthly Working (0-31), Monthly Present (0-Working). Present <= Working.'],
            ['5', 'Part-A(2) All About Me', 'Text & Dragdown', 'Living arrangement, leisure activities, self-responsibility, improvement areas, friend info, academic & personal goals with rationale & steps, learnings in/outside school.', 'Responsibility: all the time, most times, sometimes.'],
            ['6', 'Part-A(3) Ambition Card', 'Text & Dragdown', 'Student career ambition, path to achieve, 5 key skills, focus subjects, habits to cultivate, teacher mentor name & guidance, emotional reflections.', 'Mentor guidance and structured skill acquisition.'],
            ['7', 'Part-A(4) Parent Partnership', 'Checkbox (Yes/No) & Radio', 'Home resources (individual Yes/No columns), Understanding of child Q1-Q4 (Radio smileys: Yes, Sometimes, No, Not Sure), Support needs (individual Yes/No columns), and Home support plan text.', 'Checkboxes: Yes / No; Radio: Yes, Sometimes, No, Not Sure; Specify text.'],
            ['8', 'Part-B Holistic Activities', 'Dragdown & Text', 'Choose activity subject/domain (Science, Maths, Languages, Social Sci, Art, PE, Skill, etc.), pedagogical approach, activity title, description, rubric ratings (Awareness, Sensitivity, Creativity), student reflection, peer review, teacher strength/barrier areas & support plan.', 'Subject: Science, Maths, Languages...; Approach: Art-Integrated...; Rubrics: Beginner, Proficient, Advanced.'],
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
    }

    private function buildEvaluationsSheet(Spreadsheet $spreadsheet, array $students): void
    {
        $evalSheet = $spreadsheet->createSheet();
        $evalSheet->setTitle('Student Evaluations');

        $columns  = $this->getEvaluationColumns();
        $sections = $this->getEvaluationSections();

        // Row 1: Category Super-Headers (Color Banners)
        foreach ($sections as $sec) {
            $startCol = $this->indexToColumnLetter($sec['start']);
            $endCol   = $this->indexToColumnLetter($sec['end']);
            $range    = "{$startCol}1:{$endCol}1";

            $evalSheet->mergeCells($range);
            $evalSheet->setCellValue("{$startCol}1", $sec['title']);
            $evalSheet->getStyle($range)->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 10],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $sec['bg']]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFFFFFFF']]],
            ]);
        }

        $collapsibleRanges = [
            [0, 1],
            [3, 3],
            [5, 10],
            [12, 35],
            [39, 40],
            [42, 66],
            [69, 76],
            [78, 86],
            [88, 90],
            [92, 101],
            [104, 125],
            [129, 144],
        ];

        foreach ($collapsibleRanges as [$startIdx, $endIdx]) {
            for ($cIdx = $startIdx; $cIdx <= $endIdx; $cIdx++) {
                $cLetter = $this->indexToColumnLetter($cIdx);
                $evalSheet->getColumnDimension($cLetter)->setOutlineLevel(1);
                $evalSheet->getColumnDimension($cLetter)->setVisible(false);
                $evalSheet->getColumnDimension($cLetter)->setCollapsed(true);
            }
        }
        $evalSheet->setShowSummaryRight(true);
        $evalSheet->getRowDimension(1)->setRowHeight(30);

        // Row 2: Field Names & Row 3: Guidance Notes
        foreach ($columns as $idx => $col) {
            $colLetter = $this->indexToColumnLetter($idx);
            $isObt    = str_ends_with($col['key'], '_obt');
            $isLocked = in_array($col['type'], ['locked', 'calc']);

            $label = $isObt ? '✏️ ' . $col['label'] : $col['label'];
            $evalSheet->setCellValue("{$colLetter}2", $label);
            $evalSheet->setCellValue("{$colLetter}3", $col['note']);
            $evalSheet->getColumnDimension($colLetter)->setWidth($col['width']);

            $headerBg    = $isObt ? 'FFFEF08A' : ($isLocked ? 'FFE2E8F0' : 'FF1E293B');
            $headerColor = $isObt ? 'FF854D0E' : ($isLocked ? 'FF334155' : 'FFFFFFFF');

            $evalSheet->getStyle("{$colLetter}2")->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['argb' => $headerColor], 'size' => 9.5],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $headerBg]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCBD5E1']]],
            ]);

            $evalSheet->getStyle("{$colLetter}3")->applyFromArray([
                'font'      => ['italic' => true, 'color' => ['argb' => 'FF64748B'], 'size' => 8.5],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF8FAFC']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
            ]);
        }
        $evalSheet->getRowDimension(2)->setRowHeight(26);
        $evalSheet->getRowDimension(3)->setRowHeight(20);

        // Row 4: Complete Demo Student
        $demoRow = $this->getDemoStudentRow();
        foreach ($columns as $cIdx => $col) {
            $colLetter = $this->indexToColumnLetter($cIdx);
            $val = $demoRow[$cIdx] ?? '';
            $evalSheet->setCellValue("{$colLetter}4", $val);

            if ($col['type'] === 'list') {
                $validation = $evalSheet->getCell("{$colLetter}4")->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                $validation->setAllowBlank(false);
                $validation->setShowDropDown(true);
                $validation->setFormula1($col['options']);
            }

            $evalSheet->getStyle("{$colLetter}4")->applyFromArray([
                'font'      => ['bold' => true, 'italic' => true, 'color' => ['argb' => 'FF854D0E']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFEF9C3']],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFFDE047']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
        }
        $evalSheet->getRowDimension(4)->setRowHeight(24);

        // Rows 5 onwards: Enrolled Students
        $defaultWd = SchoolWorkingDay::getDefault();
        $defaultWorkMap = $defaultWd->toMonthMap();
        $defaultMax = SchoolMaxMark::getDefault()->toSubjectMap();

        foreach ($students as $sIdx => $st) {
            $rowNum = $sIdx + 5;
            $starterRow = $this->getStudentStarterProfile($sIdx, $st, $defaultWorkMap, $defaultMax, $rowNum);

            foreach ($columns as $cIdx => $col) {
                $colLetter = $this->indexToColumnLetter($cIdx);
                $val = $starterRow[$cIdx] ?? '';

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

                $isObt    = str_ends_with($col['key'], '_obt');
                $isLocked = in_array($col['type'], ['locked', 'calc']);

                $cellBg    = $isObt ? 'FFFEFCE8' : ($isLocked ? 'FFF1F5F9' : 'FFFFFFFF');
                $cellColor = $isObt ? 'FF0F172A' : ($isLocked ? 'FF475569' : 'FF1E293B');

                $evalSheet->getStyle("{$colLetter}{$rowNum}")->applyFromArray([
                    'font'      => ['bold' => ($isObt || $isLocked), 'color' => ['argb' => $cellColor]],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $cellBg]],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $isObt ? 'FFFDE047' : 'FFE2E8F0']]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
            }

            $evalSheet->getRowDimension($rowNum)->setRowHeight(22);
        }

        $lastColLetter = $this->indexToColumnLetter(count($columns) - 1);
        $maxRow = max(5, count($students) + 4);
        $evalDataRange = "A5:{$lastColLetter}{$maxRow}";

        $blankRule = new Conditional();
        $blankRule->setConditionType(Conditional::CONDITION_CONTAINSBLANKS);
        $blankRule->getStyle()->getFill()->setFillType(Fill::FILL_SOLID);
        $blankRule->getStyle()->getFill()->getStartColor()->setARGB('FFFEE2E2');
        $blankRule->getStyle()->getFill()->getEndColor()->setARGB('FFFEE2E2');
        $blankRule->getStyle()->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFFCA5A5');
        $blankRule->getStyle()->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFFCA5A5');
        $blankRule->getStyle()->getBorders()->getLeft()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFFCA5A5');
        $blankRule->getStyle()->getBorders()->getRight()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFFCA5A5');

        $evalSheet->getStyle($evalDataRange)->setConditionalStyles([$blankRule]);
        $evalSheet->freezePane('D5');
    }

    public function indexToColumnLetter(int $index): string
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

            $portalStudents = [];
            $tStudentsFile = storage_path('app/hpc/teacher_students.json');
            if (file_exists($tStudentsFile)) {
                $tsData = json_decode(file_get_contents($tStudentsFile), true) ?: [];
                foreach ($tsData as $tSt) {
                    if (!empty($tSt['login_id'])) {
                        $portalStudents[$tSt['login_id']] = $tSt['status'] ?? '';
                    }
                }
            }

            for ($r = 4; $r <= $max; $r++) {
                $rowValues = [];
                foreach ($columns as $cIdx => $col) {
                    $colLetter = $this->indexToColumnLetter($cIdx);
                    $rowValues[$col['key']] = trim((string) $ws->getCell("{$colLetter}{$r}")->getValue());
                }

                $name    = $rowValues['name'] ?? '';
                $loginId = $rowValues['login_id'] ?? '';
                $status  = $rowValues['status'] ?? '';

                if ($name === '' && $loginId === '') continue;
                if (str_contains($loginId, 'Login ID') || str_contains($name, 'Student Name')) continue;

                if (!empty($loginId) && isset($portalStudents[$loginId]) && !empty($portalStudents[$loginId])) {
                    $status = $portalStudents[$loginId];
                    $rowValues['status'] = $status;
                }

                $isDemo      = ($loginId === 'DEMO_SAMPLE' || stripos($name, 'SAMPLE') !== false || stripos($name, 'DEMO') !== false);
                $isCompleted = (strcasecmp($status, 'HPC Completed') === 0 || stripos($status, 'completed') !== false);

                $rowErrors = [];
                if (! $isDemo && ! $isCompleted) {
                    if ($name === '') $rowErrors[] = 'Student Name missing';
                    if ($loginId === '') $rowErrors[] = 'HPC Login ID missing';
                }

                $defaultMax = SchoolMaxMark::getDefault()->toSubjectMap();

                $rowItem = array_merge($rowValues, [
                    'row'          => $r,
                    'is_demo'      => $isDemo,
                    'is_completed' => $isCompleted,
                    'errors'       => $rowErrors,
                    'marks' => [
                        'l1' => ['max' => $rowValues['l1_max'] ?: (string) ($defaultMax['l1'] ?? 50), 'obt' => $rowValues['l1_obt'] ?: '42'],
                        'l2' => ['max' => $rowValues['l2_max'] ?: (string) ($defaultMax['l2'] ?? 50), 'obt' => $rowValues['l2_obt'] ?: '40'],
                        'l3' => ['max' => $rowValues['l3_max'] ?: (string) ($defaultMax['l3'] ?? 50), 'obt' => $rowValues['l3_obt'] ?: '41'],
                        'm'  => ['max' => $rowValues['m_max']  ?: (string) ($defaultMax['m']  ?? 50), 'obt' => $rowValues['m_obt']  ?: '44'],
                        'sci'=> ['max' => $rowValues['sci_max'] ?: (string) ($defaultMax['sci'] ?? 50), 'obt' => $rowValues['sci_obt'] ?: '43'],
                        'soc'=> ['max' => $rowValues['soc_max'] ?: (string) ($defaultMax['soc'] ?? 50), 'obt' => $rowValues['soc_obt'] ?: '42'],
                        'art'=> ['max' => $rowValues['art_max'] ?: (string) ($defaultMax['art'] ?? 50), 'obt' => $rowValues['art_obt'] ?: '46'],
                        'pe' => ['max' => $rowValues['pe_max'] ?: (string) ($defaultMax['pe'] ?? 50), 'obt' => $rowValues['pe_obt'] ?: '47'],
                    ],
                ]);

                $preview[] = $rowItem;

                if (! empty($rowErrors)) {
                    $errors[] = "Row {$r} ({$name}): " . implode(', ', $rowErrors);
                }
            }
        } catch (\Throwable $e) {
            $msg = 'Could not read Excel file: ' . $e->getMessage();
            return ($request->expectsJson() || $request->ajax())
                ? response()->json(['ok' => false, 'error' => $msg])
                : redirect()->to(route('dashboard') . '?step=4')->with('error', $msg);
        }

        $schoolDir = $this->getSchoolStorageDir();
        file_put_contents($schoolDir . '/teacher_preview.json', json_encode($preview, JSON_PRETTY_PRINT));

        if (! $request->expectsJson() && ! $request->ajax()) {
            return redirect()->to(route('dashboard') . '?step=4')
                ->with('success', 'Successfully uploaded ' . count($preview) . ' assessment records!');
        }

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
        $schoolDir   = $this->getSchoolStorageDir();
        $previewFile = $schoolDir . '/teacher_preview.json';
        if (! file_exists($previewFile)) {
            return response()->json(['ok' => false, 'error' => 'No student evaluation data found. Please upload the filled Excel file first.']);
        }

        $allStudents   = json_decode(file_get_contents($previewFile), true) ?? [];
        $validStudents = array_values(array_filter($allStudents, fn($s) => empty($s['errors'])));

        if (empty($validStudents)) {
            return response()->json(['ok' => false, 'error' => 'All rows have validation errors. Please fix and re-upload.']);
        }

        $total = count($validStudents);
        $resultsFile = $schoolDir . '/teacher_run_results.json';
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

        $school    = \Illuminate\Support\Facades\Auth::user()?->getEffectiveSchool();
        $schoolArg = $school ? "--school={$school->id}" : "";
        $teacher   = $this->getActiveTeacher();
        $tArg      = !empty($teacher['id']) ? "--teacher={$teacher['id']}" : "";

        $artisan = base_path('artisan');
        $logFile = storage_path('logs/hpc_teacher_run.log');
        $php     = PHP_BINARY;

        exec("nohup {$php} {$artisan} hpc:teacher-run {$schoolArg} {$tArg} > {$logFile} 2>&1 &");

        return response()->json(['ok' => true, 'total' => $total]);
    }

    public function runStatus()
    {
        $file = $this->getSchoolStorageDir() . '/teacher_run_results.json';
        if (! file_exists($file)) {
            return response()->json(['started' => false, 'results' => []]);
        }
        $data = json_decode(file_get_contents($file), true) ?? [];
        return response()->json($data);
    }

    public function runReset()
    {
        $file = $this->getSchoolStorageDir() . '/teacher_run_results.json';
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

    // ─── School Working Days & Curricular Max Marks Settings ──────────────────

    public function academicSettings(Request $request)
    {
        $school   = \Illuminate\Support\Facades\Auth::user()->getEffectiveSchool();
        $middleWd = SchoolWorkingDay::getDefault('middle');
        $prepWd   = SchoolWorkingDay::getDefault('preparatory');
        $middleMm = SchoolMaxMark::getDefault('middle');
        $prepMm   = SchoolMaxMark::getDefault('preparatory');

        return view('school.academic-settings', compact(
            'school', 'middleWd', 'prepWd', 'middleMm', 'prepMm'
        ));
    }

    public function getWorkingDays(Request $request)
    {
        $stage = $request->query('stage', 'middle');
        $wd = SchoolWorkingDay::getDefault($stage);
        return response()->json([
            'ok'   => true,
            'data' => $wd,
        ]);
    }

    public function updateWorkingDays(Request $request)
    {
        $data = $request->validate([
            'stage'         => 'nullable|string|in:middle,preparatory',
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

        $stage = $data['stage'] ?? 'middle';
        $wd = SchoolWorkingDay::getDefault($stage);
        $data['stage'] = $stage;
        $data['academic_year'] = $data['academic_year'] ?? $wd->academic_year ?? '2025-2026';
        $data['total_days'] = array_sum([
            $data['apr'], $data['may'], $data['jun'], $data['jul'], $data['aug'], $data['sep'],
            $data['oct'], $data['nov'], $data['dec'], $data['jan'], $data['feb'], $data['mar']
        ]);
        $data['is_default'] = true;

        $wd->update($data);

        return response()->json([
            'ok'      => true,
            'message' => ucfirst($stage) . ' Stage working days (' . $data['total_days'] . ' days) saved successfully!',
            'data'    => $wd->fresh(),
        ]);
    }

    // ─── Curricular Max Marks Defaults Management ─────────────────────────────

    public function getMaxMarks(Request $request)
    {
        $stage = $request->query('stage', 'middle');
        $mm = SchoolMaxMark::getDefault($stage);
        return response()->json([
            'ok'     => true,
            'data'   => $mm,
            'map'    => $mm->toSubjectMap(),
            'labels' => SchoolMaxMark::subjectLabels($stage),
        ]);
    }

    public function updateMaxMarks(Request $request)
    {
        $data = $request->validate([
            'stage'         => 'nullable|string|in:middle,preparatory',
            'academic_year' => 'nullable|string|max:20',
            'l1_max'        => 'required|integer|min:1|max:100',
            'l2_max'        => 'required|integer|min:1|max:100',
            'l3_max'        => 'required|integer|min:1|max:100',
            'm_max'         => 'required|integer|min:1|max:100',
            'sci_max'       => 'required|integer|min:1|max:100',
            'soc_max'       => 'required|integer|min:1|max:100',
            'art_max'       => 'required|integer|min:1|max:100',
            'pe_max'        => 'required|integer|min:1|max:100',
            'remarks'       => 'nullable|string|max:255',
        ]);

        $stage = $data['stage'] ?? 'middle';
        $mm = SchoolMaxMark::getDefault($stage);
        $data['stage'] = $stage;
        $data['academic_year'] = $data['academic_year'] ?? $mm->academic_year ?? '2025-2026';
        $data['is_default'] = true;

        $mm->update($data);

        return response()->json([
            'ok'      => true,
            'message' => ucfirst($stage) . ' Stage curricular max marks updated successfully!',
            'data'    => $mm->fresh(),
            'map'     => $mm->fresh()->toSubjectMap(),
        ]);
    }

    // ─── Multi-Teacher Session Management ─────────────────────────────────────

    public function getSchoolsData(): array
    {
        if (\Illuminate\Support\Facades\Auth::check()) {
            $user = \Illuminate\Support\Facades\Auth::user();
            $dbSchool = $user->getEffectiveSchool();
            if ($dbSchool) {
                $dbTeachers = $dbSchool->teachers()->where('is_active', true)->get()->map(function($t) use ($dbSchool) {
                    return [
                        'id'    => 'teacher_' . $t->id,
                        'name'  => $t->name . ($t->designation ? ' (' . $t->designation . ')' : ''),
                        'dir'   => 'schools/' . $dbSchool->id . '/teacher-profiles/teacher_' . $t->id,
                        'db_id' => $t->id,
                    ];
                })->toArray();

                if (empty($dbTeachers)) {
                    $dbTeachers = [
                        ['id' => 'teacher_default', 'name' => $dbSchool->principal_name ?: 'Teacher', 'dir' => 'schools/' . $dbSchool->id . '/teacher-profiles/teacher_default']
                    ];
                }

                $activeTeacherId = session('active_teacher_id', $dbTeachers[0]['id']);

                return [
                    'active' => 'school_' . $dbSchool->id,
                    'schools' => [
                        [
                            'id'             => 'school_' . $dbSchool->id,
                            'name'           => $dbSchool->name,
                            'udise'          => $dbSchool->udise_code,
                            'dir'            => 'schools/' . $dbSchool->id . '/chrome-profile',
                            'teachers'       => $dbTeachers,
                            'active_teacher' => $activeTeacherId,
                        ]
                    ]
                ];
            }
        }

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

    public function getActiveTeacherProfileDir(?\App\Models\School $school = null): string
    {
        if (! $school && \Illuminate\Support\Facades\Auth::check()) {
            $school = \Illuminate\Support\Facades\Auth::user()->getEffectiveSchool();
        }

        $teacher = $this->getActiveTeacher();
        $dir = $teacher['dir'] ?? 'teacher-profile';

        if ($school && ! str_starts_with($dir, 'schools/')) {
            $dir = 'schools/' . $school->id . '/' . $dir;
        }

        // If school is #1 and legacy teacher-profile directory exists, fallback for compatibility
        if ($school && $school->id === 1 && ! is_dir(storage_path('app/hpc/' . $dir)) && is_dir(storage_path('app/hpc/teacher-profile'))) {
            return storage_path('app/hpc/teacher-profile');
        }

        return storage_path('app/hpc/' . $dir);
    }

    public function switchTeacher(Request $request)
    {
        $teacherId = trim((string) $request->input('id'));
        session(['active_teacher_id' => $teacherId]);

        $file = storage_path('app/hpc/schools.json');
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true) ?: [];
            $activeSchoolId = $data['active'] ?? 'default';

            foreach ($data['schools'] ?? [] as &$sch) {
                if ($sch['id'] === $activeSchoolId) {
                    $sch['active_teacher'] = $teacherId;
                    break;
                }
            }
            file_put_contents(storage_path('app/hpc/schools.json'), json_encode($data, JSON_PRETTY_PRINT));
        }

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
        $loginUrl   = config('hpc.login_url', 'https://hpc.parakh.ncert.gov.in/en/login');
        $profileDir = $this->getActiveTeacherProfileDir();
        @mkdir($profileDir, 0777, true);
        @file_put_contents($profileDir . '/session_active.txt', now()->toIso8601String());

        if (function_exists('exec')) {
            $macChrome = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
            if (file_exists($macChrome)) {
                @exec("nohup '{$macChrome}' --user-data-dir=" . escapeshellarg($profileDir) . " " . escapeshellarg($loginUrl) . " > /dev/null 2>&1 &");
            }
        }

        $teacher = $this->getActiveTeacher();
        return response()->json([
            'ok'      => true,
            'url'     => $loginUrl,
            'message' => "Teacher session connected for {$teacher['name']}. Please sign in to the PARAKH portal."
        ]);
    }

    public function endSession()
    {
        $profileDir = $this->getActiveTeacherProfileDir();
        if (function_exists('exec')) {
            @exec("pkill -f " . escapeshellarg($profileDir) . " 2>/dev/null");
        }
        if (is_dir($profileDir)) {
            $files = glob($profileDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) @unlink($file);
            }
            @rmdir($profileDir);
        }
        return response()->json(['ok' => true, 'message' => "Session ended for active teacher profile."]);
    }

    public function getEvaluationSections(): array
    {
        return [
            ['title' => '🔒 Part 1: Student Identity (Locked)',          'start' => 0,   'end' => 3,   'bg' => 'FF0F172A'],
            ['title' => '👤 Part 2: Part-A1 Basic Profile',             'start' => 4,   'end' => 11,  'bg' => 'FF1E40AF'],
            ['title' => '📅 Part 3: Monthly Attendance (Apr–Mar)',      'start' => 12,  'end' => 35,  'bg' => 'FF0369A1'],
            ['title' => '📈 Part 4: Attendance Summary (Auto)',          'start' => 36,  'end' => 38,  'bg' => 'FF0D9488'],
            ['title' => '🌟 Part 5: Absence & Hobby',                    'start' => 39,  'end' => 41,  'bg' => 'FF4338CA'],
            ['title' => '🌱 Part 6: Part-A2 All About Me & Goals',      'start' => 42,  'end' => 67,  'bg' => 'FF6D28D9'],
            ['title' => '🚀 Part 7: Part-A3 My Ambition Card',          'start' => 68,  'end' => 77,  'bg' => 'FF7C3AED'],
            ['title' => '🏡 Part 8: Part-A4 Home Resources',            'start' => 78,  'end' => 87,  'bg' => 'FFBE185D'],
            ['title' => '😊 Part 9: Part-A4 Motivation & Progress',     'start' => 88,  'end' => 91,  'bg' => 'FF9D174D'],
            ['title' => '🤝 Part 10: Part-A4 School Support Needed',    'start' => 92,  'end' => 102, 'bg' => 'FFB91C1C'],
            ['title' => '📝 Part 11: Parent Home Support Plan',         'start' => 103, 'end' => 103, 'bg' => 'FFC2410C'],
            ['title' => '🎨 Part 12: Part-B Holistic Activity Profile', 'start' => 104, 'end' => 126, 'bg' => 'FFD97706'],
            ['title' => '📊 Part 13: Part-C Curricular Progress Levels','start' => 127, 'end' => 128, 'bg' => 'FF059669'],
            ['title' => '🎯 Part 14: HPC Credit Subject Marks (8 Subj)','start' => 129, 'end' => 144, 'bg' => 'FF047857'],
            ['title' => '✍️ Part 15: Final Declaration & Remarks',       'start' => 145, 'end' => 146, 'bg' => 'FF334155'],
        ];
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
            // Monthly Attendance Neck-to-Neck (Working Days / Present Days per Month: Apr to Mar)
            ['key' => 'work_apr',               'label' => 'Working Days - Apr *',                   'width' => 16, 'type' => 'integer', 'note' => 'April working days (e.g. 20)'],
            ['key' => 'pres_apr',               'label' => 'Present Days - Apr *',                   'width' => 16, 'type' => 'integer', 'note' => 'April present days (e.g. 19)'],
            ['key' => 'work_may',               'label' => 'Working Days - May *',                   'width' => 16, 'type' => 'integer', 'note' => 'May working days (e.g. 22)'],
            ['key' => 'pres_may',               'label' => 'Present Days - May *',                   'width' => 16, 'type' => 'integer', 'note' => 'May present days (e.g. 21)'],
            ['key' => 'work_jun',               'label' => 'Working Days - Jun *',                   'width' => 16, 'type' => 'integer', 'note' => 'June working days (e.g. 10)'],
            ['key' => 'pres_jun',               'label' => 'Present Days - Jun *',                   'width' => 16, 'type' => 'integer', 'note' => 'June present days (e.g. 10)'],
            ['key' => 'work_jul',               'label' => 'Working Days - Jul *',                   'width' => 16, 'type' => 'integer', 'note' => 'July working days (e.g. 20)'],
            ['key' => 'pres_jul',               'label' => 'Present Days - Jul *',                   'width' => 16, 'type' => 'integer', 'note' => 'July present days (e.g. 18)'],
            ['key' => 'work_aug',               'label' => 'Working Days - Aug *',                   'width' => 16, 'type' => 'integer', 'note' => 'August working days (e.g. 22)'],
            ['key' => 'pres_aug',               'label' => 'Present Days - Aug *',                   'width' => 16, 'type' => 'integer', 'note' => 'August present days (e.g. 20)'],
            ['key' => 'work_sep',               'label' => 'Working Days - Sep *',                   'width' => 16, 'type' => 'integer', 'note' => 'September working days (e.g. 21)'],
            ['key' => 'pres_sep',               'label' => 'Present Days - Sep *',                   'width' => 16, 'type' => 'integer', 'note' => 'September present days (e.g. 20)'],
            ['key' => 'work_oct',               'label' => 'Working Days - Oct *',                   'width' => 16, 'type' => 'integer', 'note' => 'October working days (e.g. 19)'],
            ['key' => 'pres_oct',               'label' => 'Present Days - Oct *',                   'width' => 16, 'type' => 'integer', 'note' => 'October present days (e.g. 18)'],
            ['key' => 'work_nov',               'label' => 'Working Days - Nov *',                   'width' => 16, 'type' => 'integer', 'note' => 'November working days (e.g. 20)'],
            ['key' => 'pres_nov',               'label' => 'Present Days - Nov *',                   'width' => 16, 'type' => 'integer', 'note' => 'November present days (e.g. 17)'],
            ['key' => 'work_dec',               'label' => 'Working Days - Dec *',                   'width' => 16, 'type' => 'integer', 'note' => 'December working days (e.g. 15)'],
            ['key' => 'pres_dec',               'label' => 'Present Days - Dec *',                   'width' => 16, 'type' => 'integer', 'note' => 'December present days (e.g. 14)'],
            ['key' => 'work_jan',               'label' => 'Working Days - Jan *',                   'width' => 16, 'type' => 'integer', 'note' => 'January working days (e.g. 15)'],
            ['key' => 'pres_jan',               'label' => 'Present Days - Jan *',                   'width' => 16, 'type' => 'integer', 'note' => 'January present days (e.g. 13)'],
            ['key' => 'work_feb',               'label' => 'Working Days - Feb *',                   'width' => 16, 'type' => 'integer', 'note' => 'February working days (e.g. 20)'],
            ['key' => 'pres_feb',               'label' => 'Present Days - Feb *',                   'width' => 16, 'type' => 'integer', 'note' => 'February present days (e.g. 19)'],
            ['key' => 'work_mar',               'label' => 'Working Days - Mar *',                   'width' => 16, 'type' => 'integer', 'note' => 'March working days (e.g. 18)'],
            ['key' => 'pres_mar',               'label' => 'Present Days - Mar *',                   'width' => 16, 'type' => 'integer', 'note' => 'March present days (e.g. 17)'],
            ['key' => 'total_work',             'label' => 'Total Working Days (Auto)',              'width' => 20, 'type' => 'calc',    'note' => 'Auto-calculated sum: =SUM(M,O,Q,S,U,W,Y,AA,AC,AE,AG,AI)'],
            ['key' => 'total_pres',             'label' => 'Total Present Days (Auto)',              'width' => 20, 'type' => 'calc',    'note' => 'Auto-calculated sum: =SUM(N,P,R,T,V,X,Z,AB,AD,AF,AH,AJ)'],

            // Attendance % & Absence
            ['key' => 'attendance_pct',         'label' => 'Attendance % (Auto)',                    'width' => 18, 'type' => 'calc',    'note' => 'Auto %: =IF(AK>0, ROUND((AL/AK)*100, 1) & "%", "")'],
            ['key' => 'absent_count',           'label' => 'Absent Times Count',                     'width' => 18, 'type' => 'integer', 'note' => 'Total times remained absent (e.g. 16)'],
            ['key' => 'absence_reason',         'label' => 'Reason for Absence (A1)',                'width' => 26, 'type' => 'string',  'note' => 'e.g. Brief seasonal viral illness'],
            ['key' => 'interest',               'label' => 'Primary Interest / Hobby (A1) *',         'width' => 32, 'type' => 'list',    'options' => '"Reading,Dancing or Singing or Playing a musical instrument,Creative Writing,Gardening,Yoga,Art,Craft,Sports or Games,Cooking,Participation at home"', 'note' => 'Student hobby from Doc 29'],

            // ── Part-A(2) All About Me & Goal Setting ──────────────────────────────────
            ['key' => 'live_with',              'label' => 'I live with my (A2)',                    'width' => 24, 'type' => 'string',  'note' => 'e.g. Family / Parents and Grandparents'],
            ['key' => 'stay_at',                'label' => 'We stay at (A2)',                        'width' => 22, 'type' => 'string',  'note' => 'e.g. Sugin, Budgam'],
            ['key' => 'free_time',              'label' => 'I spend my free time doing (A2)',        'width' => 30, 'type' => 'string',  'note' => 'e.g. Reading books and drawing'],
            ['key' => 'do_well',                'label' => 'I ... very well (A2)',                   'width' => 30, 'type' => 'string',  'note' => 'I [things I do] very well, e.g. Learning new skills and actively participating'],
            ['key' => 'responsible',            'label' => 'I am responsible (A2) *',                'width' => 24, 'type' => 'list',    'options' => '"all the time,most times,sometimes"', 'note' => 'Select: all the time, most times, sometimes'],
            ['key' => 'do_better',              'label' => 'I could do better specially when it comes to (A2)', 'width' => 38, 'type' => 'string',  'note' => 'e.g. Time management in exams'],
            ['key' => 'care_others',            'label' => 'I care about others. I show it by (A2)', 'width' => 34, 'type' => 'string',  'note' => 'e.g. Helping peers with notes'],
            ['key' => 'feel_proud',             'label' => 'I feel proud of myself when (A2)',       'width' => 32, 'type' => 'string',  'note' => 'e.g. Solving challenging problems independently'],
            ['key' => 'friend_name',            'label' => 'My Friend: Name (A2)',                   'width' => 22, 'type' => 'string',  'note' => 'e.g. Ayaan Ahmad'],
            ['key' => 'friend_age',             'label' => 'My Friend: Age (A2)',                    'width' => 18, 'type' => 'integer', 'note' => 'e.g. 12'],
            ['key' => 'acad_goal',              'label' => 'My Academic Goal (A2)',                  'width' => 28, 'type' => 'string',  'note' => 'e.g. Score above 90% in Mathematics and Science'],
            ['key' => 'acad_goal_why',          'label' => 'Academic: This goal is important to me because (A2) *', 'width' => 38, 'type' => 'string',  'note' => 'e.g. Builds solid foundation for pursuing higher studies in Engineering'],
            ['key' => 'acad_step1',             'label' => 'Academic: Step 1 (A2)',                  'width' => 28, 'type' => 'string',  'note' => 'e.g. Dedicate 45 minutes daily to numerical problem solving'],
            ['key' => 'acad_step2',             'label' => 'Academic: Step 2 (A2)',                  'width' => 28, 'type' => 'string',  'note' => 'e.g. Clarify doubts with subject teachers immediately'],
            ['key' => 'pers_goal',              'label' => 'My Personal Goal (A2)',                  'width' => 28, 'type' => 'string',  'note' => 'e.g. Develop regular physical exercise and disciplined reading routine'],
            ['key' => 'pers_goal_why',          'label' => 'Personal: This goal is important to me because (A2) *', 'width' => 38, 'type' => 'string',  'note' => 'e.g. Keeps mind alert, improves stamina, and enhances daily concentration'],
            ['key' => 'pers_step1',             'label' => 'Personal: Step 1 (A2)',                  'width' => 28, 'type' => 'string',  'note' => 'e.g. Wake up at 6:00 AM every morning and do yoga'],
            ['key' => 'pers_step2',             'label' => 'Personal: Step 2 (A2)',                  'width' => 28, 'type' => 'string',  'note' => 'e.g. Read at least 15 pages of books before bedtime'],
            ['key' => 'sch_learn1',             'label' => 'Learnt at school: 1 (A2)',               'width' => 28, 'type' => 'string',  'note' => 'e.g. Laws of reflection and refraction in science laboratory'],
            ['key' => 'sch_learn2',             'label' => 'Learnt at school: 2 (A2)',               'width' => 28, 'type' => 'string',  'note' => 'e.g. Formulating and solving linear equations in single variable'],
            ['key' => 'sch_learn3',             'label' => 'Learnt at school: 3 (A2)',               'width' => 28, 'type' => 'string',  'note' => 'e.g. Constitutional preamble and fundamental rights in Social Science'],
            ['key' => 'out_learn1',             'label' => 'Learnt outside school: 1 (A2)',          'width' => 28, 'type' => 'string',  'note' => 'e.g. Organic kitchen gardening, composting, and soil preparation'],
            ['key' => 'out_learn2',             'label' => 'Learnt outside school: 2 (A2)',          'width' => 28, 'type' => 'string',  'note' => 'e.g. Bicycle maintenance, tire inflation, and chain lubrication'],
            ['key' => 'out_learn3',             'label' => 'Learnt outside school: 3 (A2)',          'width' => 28, 'type' => 'string',  'note' => 'e.g. Basic household budgeting and grocery calculation'],
            ['key' => 'help_with',              'label' => 'I would like my teacher to help me with (A2)', 'width' => 36, 'type' => 'string',  'note' => 'e.g. Hands-on science experiments and advanced mathematical puzzles'],
            ['key' => 'teacher_know',           'label' => 'I would like my teacher to know (A2)',    'width' => 36, 'type' => 'string',  'note' => 'e.g. I am enthusiastic about collaborative group projects and active discussions'],

            // ── Part-A(3) My Ambition Card ─────────────────────────────────────────────
            ['key' => 'ambition',               'label' => 'My ambition is (A3) *',                                   'width' => 26, 'type' => 'string',  'note' => 'e.g. Doctor / Astrophysicist / Software Engineer'],
            ['key' => 'achieve_how',            'label' => 'I will achieve my ambition by (A3) *',                    'width' => 36, 'type' => 'string',  'note' => 'e.g. Conceptual understanding and daily practice in Maths & Science'],
            ['key' => 'skills_needed',          'label' => '5 skills I need to achieve my ambition (A3) *',           'width' => 36, 'type' => 'string',  'note' => 'e.g. Critical Thinking, Problem Solving, Communication, Coding, Curiosity'],
            ['key' => 'focus_subjects',         'label' => 'Subjects I need to focus on (A3) *',                      'width' => 30, 'type' => 'string',  'note' => 'e.g. Mathematics, Science, English, Computing'],
            ['key' => 'habits_i_will',          'label' => 'To achieve my ambition, I need my habits to be... I will... (A3) *', 'width' => 44, 'type' => 'string',  'note' => 'e.g. Disciplined daily study, 30 min morning exercise, and curious questioning'],
            ['key' => 'mentor',                 'label' => 'I will take guidance from ... to achieve my ambition (A3) *', 'width' => 36, 'type' => 'string',  'note' => 'e.g. Bilal Ahmad Tali (Teacher Mentor)'],
            ['key' => 'mentor_help',            'label' => 'I think this person will help me by (A3) *',              'width' => 36, 'type' => 'string',  'note' => 'e.g. Clarifying complex topics, recommending Olympiad books & guiding projects'],
            ['key' => 'learn_new',              'label' => 'I will learn new (A3) *',                                 'width' => 32, 'type' => 'string',  'note' => 'e.g. Scientific techniques, coding algorithms & laboratory experiments'],
            ['key' => 'feel_when_achieve',      'label' => 'I will feel ... when I achieve my ambition (A3) *',        'width' => 34, 'type' => 'string',  'note' => 'e.g. Proud, happy, fulfilled, and motivated to contribute to society'],
            ['key' => 'parents_feel',           'label' => 'My parents will feel ... when I achieve my ambition (A3) *', 'width' => 36, 'type' => 'string',  'note' => 'e.g. Immensely joyful, honored, and proud'],

            // ── Part-A(4) Parent-Teacher Partnership Card (Cols CA onwards) ───────────
            // 1. Tick the resources available to your child at home (Checkboxes - Yes/No)
            ['key' => 'res_books',              'label' => 'Home: Books and Magazines (A4)',          'width' => 26, 'type' => 'list',    'options' => '"Yes,No"', 'note' => 'Checkbox: Yes or No'],
            ['key' => 'res_news',               'label' => 'Home: Newspaper (A4)',                    'width' => 22, 'type' => 'list',    'options' => '"Yes,No"', 'note' => 'Checkbox: Yes or No'],
            ['key' => 'res_toys',               'label' => 'Home: Toys, Games and Sports (A4)',       'width' => 26, 'type' => 'list',    'options' => '"Yes,No"', 'note' => 'Checkbox: Yes or No'],
            ['key' => 'res_phone',              'label' => 'Home: Phone and Computer (A4)',           'width' => 26, 'type' => 'list',    'options' => '"Yes,No"', 'note' => 'Checkbox: Yes or No'],
            ['key' => 'res_internet',           'label' => 'Home: Internet (A4)',                     'width' => 22, 'type' => 'list',    'options' => '"Yes,No"', 'note' => 'Checkbox: Yes or No'],
            ['key' => 'res_radio',              'label' => 'Home: Radio (A4)',                        'width' => 20, 'type' => 'list',    'options' => '"Yes,No"', 'note' => 'Checkbox: Yes or No'],
            ['key' => 'res_broadcast',          'label' => 'Home: Broadcast (A4)',                    'width' => 22, 'type' => 'list',    'options' => '"Yes,No"', 'note' => 'Checkbox: Yes or No'],
            ['key' => 'res_tv',                 'label' => 'Home: T.V. (A4)',                         'width' => 20, 'type' => 'list',    'options' => '"Yes,No"', 'note' => 'Checkbox: Yes or No'],
            ['key' => 'res_cwd',                'label' => 'Home: Resources for CWD (A4)',            'width' => 26, 'type' => 'list',    'options' => '"Yes,No"', 'note' => 'Checkbox: Yes or No'],
            ['key' => 'res_other',              'label' => 'Home: Any other (please specify) (A4)',   'width' => 32, 'type' => 'string',  'note' => 'Specify other home resources if any'],

            // 2. Understanding of my Child (Radio buttons - Single select)
            ['key' => 'p_motivated',            'label' => '1. My child seems motivated to learn (A4) *', 'width' => 38, 'type' => 'list', 'options' => '"Yes,Sometimes,No,Not Sure"', 'note' => 'Radio smiley: Yes, Sometimes, No, Not Sure'],
            ['key' => 'p_schedule',             'label' => '2. Follows a schedule at home for curriculum & screen time (A4) *', 'width' => 42, 'type' => 'list', 'options' => '"Yes,Sometimes,No,Not Sure"', 'note' => 'Radio smiley: Yes, Sometimes, No, Not Sure'],
            ['key' => 'p_curriculum_diff',      'label' => '3. Finds the grade-level curriculum difficult (A4) *', 'width' => 38, 'type' => 'list', 'options' => '"Yes,Sometimes,No,Not Sure"', 'note' => 'Radio smiley: Yes, Sometimes, No, Not Sure'],
            ['key' => 'p_progress',             'label' => '4. Making good progress as per the grade (A4) *', 'width' => 34, 'type' => 'list', 'options' => '"Yes,Sometimes,No,Not Sure"', 'note' => 'Radio smiley: Yes, Sometimes, No, Not Sure'],

            // 3. At school, my child needs support with (Checkboxes - Yes/No)
            ['key' => 'supp_lang',              'label' => 'Support: Languages (R1, R2, R3) (A4)',    'width' => 28, 'type' => 'list',    'options' => '"Yes,No"', 'note' => 'Checkbox: Yes or No'],
            ['key' => 'supp_math',              'label' => 'Support: Mathematics (A4)',               'width' => 24, 'type' => 'list',    'options' => '"Yes,No"', 'note' => 'Checkbox: Yes or No'],
            ['key' => 'supp_sci',               'label' => 'Support: Science (A4)',                   'width' => 22, 'type' => 'list',    'options' => '"Yes,No"', 'note' => 'Checkbox: Yes or No'],
            ['key' => 'supp_soc',               'label' => 'Support: Social Science (A4)',            'width' => 24, 'type' => 'list',    'options' => '"Yes,No"', 'note' => 'Checkbox: Yes or No'],
            ['key' => 'supp_self_belief',       'label' => 'Support: Building self-belief (A4)',      'width' => 28, 'type' => 'list',    'options' => '"Yes,No"', 'note' => 'Checkbox: Yes or No'],
            ['key' => 'supp_social_skills',     'label' => 'Support: Developing social skills (A4)',  'width' => 28, 'type' => 'list',    'options' => '"Yes,No"', 'note' => 'Checkbox: Yes or No'],
            ['key' => 'supp_emotions',          'label' => 'Support: Managing difficult emotions (A4)', 'width' => 28, 'type' => 'list',  'options' => '"Yes,No"', 'note' => 'Checkbox: Yes or No'],
            ['key' => 'supp_study_skills',      'label' => 'Support: Developing effective study skills (A4)', 'width' => 32, 'type' => 'list', 'options' => '"Yes,No"', 'note' => 'Checkbox: Yes or No'],
            ['key' => 'supp_skill_guidance',    'label' => 'Support: Skill Guidance / Digital Literacy (A4)', 'width' => 32, 'type' => 'list', 'options' => '"Yes,No"', 'note' => 'Checkbox: Yes or No'],
            ['key' => 'supp_other',             'label' => 'Support: Any Other (A4)',                 'width' => 22, 'type' => 'list',    'options' => '"Yes,No"', 'note' => 'Checkbox: Yes or No'],
            ['key' => 'supp_other_specify',     'label' => 'Support: Any Other Specify (A4)',         'width' => 28, 'type' => 'string',  'note' => 'Specify details if Any Other is Yes'],

            // 4. Support Plan at Home (Textarea)
            ['key' => 'parent_feedback',        'label' => 'Based on my discussion with teacher, I will support my child at home by (A4) *', 'width' => 48, 'type' => 'string', 'note' => 'Parent home support plan (max 700 chars)'],

            // ── Part-B Holistic Activities ─────────────────────────────────────────────
            ['key' => 'act_subject',            'label' => 'Activity Subject / Domain (B) *',        'width' => 32, 'type' => 'list',    'options' => '"Science,Mathematics,Language 1 (R1),Language 2 (R2),Language 3 (R3),Social Science,Art Education,Physical Education & Well Being,Skill Education,Other Learning Activities,Bagless days for vocational Experience"', 'note' => 'Select Curricular Subject / Domain for this activity'],
            ['key' => 'act_approach',           'label' => 'Activity Approach (B) *',                'width' => 24, 'type' => 'list',    'options' => '"Art-Integrated,Sports-Integrated,Toy-Based,Technology-Integrated,Experiential Learning,Any Other"', 'note' => 'Pedagogical approach from Doc 29'],
            ['key' => 'act_title',              'label' => 'Activity Title (B) *',                   'width' => 30, 'type' => 'string',  'note' => 'e.g. Solar System 3D Model & Daily Life Geometry'],
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
        $defaultMax = SchoolMaxMark::getDefault()->toSubjectMap();

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
            // Monthly Attendance Neck-to-Neck (12 Months: Apr to Mar - Cols M to AJ)
            $m['apr'], min(19, $m['apr']),
            $m['may'], min(21, $m['may']),
            $m['jun'], min(10, $m['jun']),
            $m['jul'], min(18, $m['jul']),
            $m['aug'], min(20, $m['aug']),
            $m['sep'], min(20, $m['sep']),
            $m['oct'], min(18, $m['oct']),
            $m['nov'], min(17, $m['nov']),
            $m['dec'], min(14, $m['dec']),
            $m['jan'], min(13, $m['jan']),
            $m['feb'], min(19, $m['feb']),
            $m['mar'], min(17, $m['mar']),
            // Total Working Days (Col AK4) & Total Present Days (Col AL4)
            '=SUM(M4,O4,Q4,S4,U4,W4,Y4,AA4,AC4,AE4,AG4,AI4)',
            '=SUM(N4,P4,R4,T4,V4,X4,Z4,AB4,AD4,AF4,AH4,AJ4)',
            // Attendance % (Col AM4) & Absence Info
            '=IF(AK4>0, ROUND((AL4/AK4)*100, 1) & "%", "")',
            16,
            'Viral fever during seasonal change in November',
            'Reading',
            // Part-A(2)
            'Family',
            'Sugin, Budgam',
            'Reading books and drawing',
            'Learning new skills and actively participating',
            'most times',
            'Time management in exams',
            'Helping peers with notes',
            'Solving challenging problems independently',
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
            'Consistent daily study routine, punctual time management, curious questioning, and regular science reading',
            'Bilal Ahmad Tali',
            'Mentoring in Olympiad preparations and recommending advanced reference materials',
            'Scientific problem-solving strategies and computational simulations',
            'Immensely fulfilled, proud, and inspired to contribute to the nation space mission',
            'Overjoyed, deeply proud, and grateful',
            // Part-A(4) Parent-Teacher Partnership Card (Cols CA onwards)
            // 1. Home Resources Available Checkboxes (Yes/No)
            'Yes', // Books and Magazines
            'Yes', // Newspaper
            'Yes', // Toys, Games and Sports
            'Yes', // Phone and Computer
            'Yes', // Internet
            'No',  // Radio
            'No',  // Broadcast
            'Yes', // T.V.
            'No',  // Resources for CWD
            '',    // Any other specify
            // 2. Understanding of my Child (Radio)
            'Yes', // Motivated to learn
            'Yes', // Follows schedule
            'No',  // Curriculum difficult
            'Yes', // Making good progress
            // 3. Child Needs Support With Checkboxes (Yes/No)
            'No',  // Languages
            'Yes', // Mathematics
            'Yes', // Science
            'No',  // Social Science
            'No',  // Building self-belief
            'No',  // Developing social skills
            'No',  // Managing emotions
            'Yes', // Developing study skills
            'No',  // Skill Guidance
            'No',  // Any Other
            '',    // Any Other Specify
            // 4. Support Plan at Home
            'Provide quiet study space, supervise 1.5 hours of evening revision, monitor screen time, and discuss daily learnings',
            // Part-B (Subject, Approach, Title, Rubrics, Reflection, Feedback)
            'Science',
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
            $defaultMax['l1'] ?? 50, 46,
            $defaultMax['l2'] ?? 50, 45,
            $defaultMax['l3'] ?? 50, 44,
            $defaultMax['m']  ?? 50, 48,
            $defaultMax['sci']?? 50, 47,
            $defaultMax['soc']?? 50, 44,
            $defaultMax['art']?? 50, 48,
            $defaultMax['pe'] ?? 50, 49,
            // Declaration & Remarks
            'Yes',
            'Faizan is an exceptionally motivated, intellectually curious, and responsible student. Demonstrates superior grasp in Mathematics and Sciences, with remarkable peer collaboration and positive leadership in class activities.'
        ];
    }

    public function getStudentStarterProfile(int $sIdx, array $st, array $defaultWorkMap, array $defaultMax, int $rowNum): array
    {
        $admNo  = 'ADM-2026-' . str_pad($sIdx + 1, 3, '0', STR_PAD_LEFT);
        $stName = $st['name'] ?? 'Student ' . ($sIdx + 1);

        $monthKeys = ['apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec', 'jan', 'feb', 'mar'];
        $monthlyPairs = [];

        foreach ($monthKeys as $mKey) {
            $w = $defaultWorkMap[$mKey] ?? 20;
            $monthlyPairs[] = $w;  // Pre-filled official working days from school calendar
            $monthlyPairs[] = '';  // Blank present days for teacher entry
        }

        // Assemble 147 evaluation values matching getEvaluationColumns schema
        $row = [
            // 0-3: Locked Metadata
            $st['apaar_id'] ?? '',
            $st['login_id'] ?? '',
            $st['name'] ?? $stName,
            $st['status'] ?? 'HPC Pending',

            // 4-11: Part-A1 Profile (Admission No, Grade, Section pre-filled, others blank)
            $admNo,
            'Grade 6',
            'A',
            '', // Location
            '', // Medium of Instruction
            '', // Mother Tongue
            '', // Parent Relation
            '', // Parent / Guardian Name
        ];

        // 12-35: 24 Monthly Attendance (Working days pre-filled, Present days blank)
        foreach ($monthlyPairs as $val) {
            $row[] = $val;
        }

        // 36-38: Attendance Totals & % Formulas
        $row[] = "=SUM(M{$rowNum},O{$rowNum},Q{$rowNum},S{$rowNum},U{$rowNum},W{$rowNum},Y{$rowNum},AA{$rowNum},AC{$rowNum},AE{$rowNum},AG{$rowNum},AI{$rowNum})";
        $row[] = "=SUM(N{$rowNum},P{$rowNum},R{$rowNum},T{$rowNum},V{$rowNum},X{$rowNum},Z{$rowNum},AB{$rowNum},AD{$rowNum},AF{$rowNum},AH{$rowNum},AJ{$rowNum})";
        $row[] = "=IF(AK{$rowNum}>0, ROUND((AL{$rowNum}/AK{$rowNum})*100, 1) & \"%\", \"\")";

        // 39-41: Absent count, reason, hobby (blank)
        $row[] = '';
        $row[] = '';
        $row[] = '';

        // 42-67: Part-A2 All About Me & Goal Setting (26 blank items)
        for ($i = 0; $i < 26; $i++) {
            $row[] = '';
        }

        // 68-77: Part-A3 My Ambition Card (10 blank items)
        for ($i = 0; $i < 10; $i++) {
            $row[] = '';
        }

        // 78-87: Part-A4 Home Resources (10 blank items)
        for ($i = 0; $i < 10; $i++) {
            $row[] = '';
        }

        // 88-91: Part-A4 Motivation (4 blank items)
        for ($i = 0; $i < 4; $i++) {
            $row[] = '';
        }

        // 92-102: Part-A4 Support Needed (11 blank items)
        for ($i = 0; $i < 11; $i++) {
            $row[] = '';
        }

        // 103: Part-A4 Parent Support Plan (1 blank item)
        $row[] = '';

        // 104-126: Part-B Holistic Activity (23 blank items)
        for ($i = 0; $i < 23; $i++) {
            $row[] = '';
        }

        // 127-128: Part-C Levels (2 blank items)
        $row[] = '';
        $row[] = '';

        // 129-144: HPC Credit Marks (16 items: Max marks pre-filled from settings, Obtained blank)
        $subjKeys = ['l1', 'l2', 'l3', 'm', 'sci', 'soc', 'art', 'pe'];
        foreach ($subjKeys as $sKey) {
            $maxM = $defaultMax[$sKey] ?? 50;
            $row[] = $maxM; // Pre-filled default max marks
            $row[] = '';    // Blank obtained marks ready for teacher entry
        }

        // 145-146: Declaration & Remarks (2 items)
        $row[] = 'Yes'; // Declaration certified
        $row[] = '';    // Remarks blank

        return $row;
    }
}
