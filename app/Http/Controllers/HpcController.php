<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use App\Models\School;
use App\Models\Student;

/**
 * Class HpcController
 *
 * @package App\Http\Controllers
 */
class HpcController extends Controller
{
    // ─── Dashboard View ───────────────────────────────────────────────────────

    public function dashboard()
    {
        $school       = \Illuminate\Support\Facades\Auth::user()?->getEffectiveSchool();
        $fieldsFile   = storage_path('app/hpc/fields.json');
        $previewFile  = $this->getPreviewFile($school);
        $resultsFile  = $this->getSchoolStorageDir($school) . '/run_results.json';

        $schoolsData  = $this->getSchoolsData();
        $activeSchool = $this->getActiveSchool();
        $profileDir   = $this->getActiveProfileDir($school);

        $hasFields  = file_exists($fieldsFile);
        $hasProfile = is_dir($profileDir) && count(scandir($profileDir)) > 2;

        $preview = [];
        if ($school && $school->students()->exists()) {
            $preview = $school->students()->orderBy('id')->get()->map(fn($st, $idx) => $st->toPreviewArray($idx + 1))->values()->all();
        } elseif (file_exists($previewFile)) {
            $preview = json_decode(file_get_contents($previewFile), true) ?? [];
        }

        $lastRun = null;
        if (file_exists($resultsFile)) {
            $lastRun = json_decode(file_get_contents($resultsFile), true);
        }

        $currentFormUrl = file_exists(storage_path('app/hpc/current_form.txt'))
            ? trim(file_get_contents(storage_path('app/hpc/current_form.txt')))
            : config('hpc.add_student_url');


        $udiseCount      = count($preview);
        $udiseApaarCount = count(array_filter($preview, fn($s) => !empty($s['apaar_id']) && strlen((string)$s['apaar_id']) === 12));

        return view('hpc.dashboard', compact(
            'hasFields', 'hasProfile', 'preview', 'lastRun', 'currentFormUrl',
            'schoolsData', 'activeSchool', 'udiseCount', 'udiseApaarCount'
        ));
    }

    // ─── Step 1: Download the Excel template (Populated from UDISE if available) ────────

    public function downloadTemplate(Request $request)
    {
        $wantsUdise = $request->query('source') === 'udise'
                   || $request->query('udise') === '1'
                   || $request->query('with_udise') === '1'
                   || $request->is('*/udise*');

        $isForcedBlank = $request->query('blank') === '1';

        $school = \Illuminate\Support\Facades\Auth::user()?->getEffectiveSchool();
        $udiseStudents = [];

        if ($wantsUdise && ! $isForcedBlank && $school) {
            if ($school->students()->exists()) {
                $udiseStudents = $school->students()->orderBy('id')->get()->map(fn($st) => [
                    'first_name' => $st->first_name,
                    'last_name'  => $st->last_name,
                    'dob'          => $st->dob,
                    'gender'       => $st->getEffectiveGender(),
                    'email'        => $st->email,
                    'admission_no' => $st->admission_no ?? '',
                    'pen_code'     => $st->pen_code ?? '',
                    'grade'        => $st->grade,
                    'section'      => $st->section,
                    'apaar_id'     => $st->apaar_id,
                ])->all();
            } else {
                $udiseStudents = $this->getUdiseStudents($school);
            }
        }

        $sheet = $this->buildStudentSpreadsheet($udiseStudents);

        $udiseCode = $school?->udise_code ?? 'template';
        $filename = (! empty($udiseStudents))
            ? "hpc_students_{$udiseCode}_from_udise.xlsx"
            : 'hpc_students_template.xlsx';

        $writer = new Xlsx($sheet);

        return response()->stream(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function buildStudentSpreadsheet(array $udiseStudents = []): Spreadsheet
    {
        $sheet = new Spreadsheet();
        $ws    = $sheet->getActiveSheet();
        $ws->setTitle('Students');

        // Columns exposed in the template (matching PARAKH portal order 1-to-1)
        $columns = [
            ['key' => 'first_name',   'label' => 'First Name *',             'width' => 20, 'note' => 'First name from UDISE / records'],
            ['key' => 'last_name',    'label' => 'Last Name',                 'width' => 20, 'note' => 'Last name / surname (optional)'],
            ['key' => 'dob',          'label' => 'Date of Birth *',           'width' => 18, 'note' => 'DD/MM/YYYY e.g. 15/08/2012'],
            ['key' => 'gender',       'label' => 'Gender *',                  'width' => 14, 'note' => 'Male / Female / Other'],
            ['key' => 'email',        'label' => 'Email *',                   'width' => 32, 'note' => 'Valid email address'],
            ['key' => 'admission_no', 'label' => 'School Admission Number',  'width' => 24, 'note' => 'School admission / roll number (optional)'],
            ['key' => 'grade',        'label' => 'Grade *',                   'width' => 18, 'note' => 'Balvatika 1-3, Grade 1-8'],
            ['key' => 'section',      'label' => 'Section *',                 'width' => 14, 'note' => 'Default: A'],
            ['key' => 'apaar_id',     'label' => 'APAAR ID',                  'width' => 20, 'note' => '12-digit unmasked APAAR ID'],
        ];

        // ── Header row (Row 1) ────────────────────────────────────────────────
        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 11],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE0E7FF']]],
        ];

        foreach ($columns as $i => $col) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            $ws->setCellValue("{$letter}1", $col['label']);
            $ws->getColumnDimension($letter)->setWidth($col['width']);
            $ws->getStyle("{$letter}1")->applyFromArray($headerStyle);
        }
        $ws->getRowDimension(1)->setRowHeight(28);

        // ── Note row (Row 2 — light indigo, italic) ──────────────────────────
        $noteStyle = [
            'font'      => ['italic' => true, 'color' => ['argb' => 'FF4338CA'], 'size' => 9],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEEF2FF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE0E7FF']]],
        ];

        foreach ($columns as $i => $col) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            $ws->setCellValue("{$letter}2", $col['note']);
            $ws->getStyle("{$letter}2")->applyFromArray($noteStyle);
        }
        $ws->getRowDimension(2)->setRowHeight(20);

        $gradeMap  = $this->getGradeMap();
        $totalRows = max(count($udiseStudents) + 2, 200);

        if (! empty($udiseStudents)) {
            // Populate UDISE Students starting from Row 3
            foreach ($udiseStudents as $idx => $st) {
                $r = $idx + 3;

                if (isset($st['first_name'])) {
                    // Already-normalised row (from DB / previous sync)
                    $firstName   = trim($st['first_name']);
                    $lastName    = trim($st['last_name'] ?? '');
                    $dob         = trim($st['dob'] ?? '');
                    $gender      = trim($st['gender'] ?? '');
                    $email       = trim($st['email'] ?? '');
                    $admissionNo = trim($st['admission_no'] ?? '');
                    $grade       = trim($st['grade'] ?? 'Grade 6');
                    $section     = trim($st['section'] ?? 'A');
                    $apaarId     = trim((string)($st['apaar_id'] ?? ''));
                } else {
                    // Raw UDISE student row — normalise via shared helper
                    $norm        = $this->normalizeUdiseStudent($st, $idx, $gradeMap);
                    $firstName   = $norm['firstName'];
                    $lastName    = $norm['lastName'];
                    $dob         = $norm['dob'];
                    $gender      = $norm['gender'];
                    $email       = $norm['email'];
                    $admissionNo = $norm['admissionNo'];
                    $grade       = $norm['grade'];
                    $section     = $norm['section'];
                    $apaarId     = $norm['apaarId'];
                }

                $rowVals  = [$firstName, $lastName, $dob, $gender, $email, $admissionNo, $grade, $section, $apaarId];
                $isApaar  = ! empty($apaarId);
                $rowBg    = ($idx % 2 === 0) ? 'FFFFFFFF' : 'FFF8FAFC';

                foreach ($rowVals as $cIdx => $val) {
                    $colLetter = Coordinate::stringFromColumnIndex($cIdx + 1);
                    $ws->setCellValueExplicit("{$colLetter}{$r}", (string) $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                    $cellBg = ($cIdx === 8 && $isApaar) ? 'FFCCFBF1' : $rowBg;
                    $ws->getStyle("{$colLetter}{$r}")->applyFromArray([
                        'font'      => [
                            'size'  => 9.5,
                            'bold'  => ($cIdx === 8 && $isApaar),
                            'color' => ['argb' => ($cIdx === 8 && $isApaar) ? 'FF0F766E' : 'FF0F172A'],
                        ],
                        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $cellBg]],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
                    ]);
                }
                $ws->getRowDimension($r)->setRowHeight(22);
            }
        } else {
            // Sample row (Row 3)
            $sample = ['Aarav', 'Sharma', '15/08/2012', 'Male', 'aarav.sharma@gmail.com', '', 'Grade 6', 'A', '123456789012'];
            $sampleStyle = [
                'font'      => ['color' => ['argb' => 'FF334155'], 'size' => 10],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF8FAFC']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
            ];
            foreach ($sample as $i => $val) {
                $letter = chr(65 + $i);
                $ws->setCellValueExplicit("{$letter}3", $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $ws->getStyle("{$letter}3")->applyFromArray($sampleStyle);
            }
            $ws->getRowDimension(3)->setRowHeight(22);

            $dataStyle = [
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFFFFF']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ];
            $ws->getStyle("A4:I200")->applyFromArray($dataStyle);
        }

        // In-cell Dropdown Data Validation for Gender, Grade and Section
        $genderFormula  = '"Male,Female,Other"';
        $gradeFormula   = '"Balvatika 1,Balvatika 2,Balvatika 3,Grade 1,Grade 2,Grade 3,Grade 4,Grade 5,Grade 6,Grade 7,Grade 8"';
        $sectionFormula = '"A,B,C,D,E,F,G,H"';

        for ($r = 3; $r <= $totalRows; $r++) {
            $genV = $ws->getCell("D{$r}")->getDataValidation();
            $genV->setType(DataValidation::TYPE_LIST);
            $genV->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $genV->setAllowBlank(false);
            $genV->setShowDropDown(true);
            $genV->setPromptTitle('Gender Selection');
            $genV->setPrompt('Select Male, Female or Other');
            $genV->setFormula1($genderFormula);

            $gv = $ws->getCell("G{$r}")->getDataValidation();
            $gv->setType(DataValidation::TYPE_LIST);
            $gv->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $gv->setAllowBlank(false);
            $gv->setShowDropDown(true);
            $gv->setPromptTitle('Grade Selection');
            $gv->setPrompt('Select Grade 1-8 or Balvatika 1-3');
            $gv->setFormula1($gradeFormula);

            $sv = $ws->getCell("H{$r}")->getDataValidation();
            $sv->setType(DataValidation::TYPE_LIST);
            $sv->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $sv->setAllowBlank(false);
            $sv->setShowDropDown(true);
            $sv->setPromptTitle('Section Selection');
            $sv->setPrompt('Select Section');
            $sv->setFormula1($sectionFormula);
        }

        $ws->freezePane('A3');

        // Instructions Sheet
        $info = $sheet->createSheet();
        $info->setTitle('Instructions');
        $info->setCellValue('A1', '🎓 HPC Student Entry Tool — Excel Guide & UDISE Field Mapping');
        $info->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $info->setCellValue('A3', 'HPC Template Field');
        $info->setCellValue('B3', 'UDISE+ Source Column');
        $info->setCellValue('C3', 'Description & Mapping Rules');
        $info->getStyle('A3:C3')->getFont()->setBold(true);
        $info->getStyle('A3:C3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEDE9FE');

        $rows = [
            ['First Name *',             'studentName (Word 1)', 'Student first name as per official UDISE / school records.'],
            ['Last Name',                'studentName (Rest)',   'Student last name (optional or remaining parts of full name).'],
            ['Date of Birth *',          'dob',                  'Format: DD/MM/YYYY (e.g. 15/08/2012).'],
            ['Gender *',                 'genderDesc / gender',  'Student gender: Male, Female, or Other. Mandatory on PARAKH portal.'],
            ['Email *',                  'email / auto-gen',     'Official student email (auto-generated using PEN if blank).'],
            ['School Admission Number',  'studentCodeNat / roll','School admission number or student roll number (optional).'],
            ['Grade *',                  'classDesc',            'Mapped from UDISE class (PP-1 -> Balvatika 1, I-VIII -> Grade 1-8).'],
            ['Section *',                'sectionDesc',          'Defaulted to Section A.'],
            ['APAAR ID',                 'apaarId',              'Unmasked 12-digit APAAR ID from UDISE SDMS.'],
        ];

        foreach ($rows as $ri => $row) {
            $line = $ri + 4;
            $info->setCellValue("A{$line}", $row[0]);
            $info->setCellValue("B{$line}", $row[1]);
            $info->setCellValue("C{$line}", $row[2]);
        }

        $info->getColumnDimension('A')->setWidth(26);
        $info->getColumnDimension('B')->setWidth(24);
        $info->getColumnDimension('C')->setWidth(60);

        $sheet->setActiveSheetIndex(0);

        return $sheet;
    }

    public function preloadUdise(?Request $request = null, $school = null, ?array $customStudents = null)
    {
        if (! $school && \Illuminate\Support\Facades\Auth::check()) {
            $school = \Illuminate\Support\Facades\Auth::user()->getEffectiveSchool();
        }

        $udiseStudents = $customStudents ?? $this->getUdiseStudents($school);
        if (empty($udiseStudents)) {
            return response()->json([
                'ok'    => false,
                'error' => 'No UDISE student records found for this school.',
            ], 404);
        }

        $gradeMap = $this->getGradeMap();

        // 1. Generate and save students.xlsx in school storage
        $spreadsheet = $this->buildStudentSpreadsheet($udiseStudents);
        $dir = $this->getSchoolStorageDir($school);
        @mkdir($dir, 0755, true);
        $fullPath = $dir . '/students.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($fullPath);

        // 2. Generate preview summary from normalised UDISE rows (for response only — NOT written to Step 2)
        $preview = [];

        foreach ($udiseStudents as $idx => $st) {
            $norm = $this->normalizeUdiseStudent($st, $idx, $gradeMap);

            $preview[] = [
                'row'              => $idx + 3,
                'first_name'       => $norm['firstName'],
                'last_name'        => $norm['lastName'],
                'name'             => trim("{$norm['firstName']} {$norm['lastName']}"),
                'dob'              => $norm['dob'],
                'gender'           => $norm['gender'],
                'email'            => $norm['email'],
                'admission_no'     => $norm['admissionNo'],
                'pen_code'         => $norm['cleanPen'],
                'udise_student_id' => $st['studentId'] ?? null,
                'grade'            => $norm['grade'],
                'section'          => $norm['section'],
                'apaar_id'         => $norm['apaarId'],
                'errors'           => [],
            ];
        }

        // NOTE: We intentionally do NOT write to preview.json or upsert into the students
        // DB table here. UDISE data is stored as raw JSON and as a downloadable Excel
        // template only. The user must download the template, review/edit it, and upload
        // it via Step 2 for it to enter the HPC processing queue.

        return response()->json([
            'ok'      => true,
            'message' => 'Successfully synced ' . count($preview) . ' students from UDISE. Download the template to review and upload for HPC processing.',
            'total'   => count($preview),
            'valid'   => count($preview),
            'invalid' => 0,
            'errors'  => [],
            'preview' => $preview,
        ]);
    }

    public function getSchoolStorageDir($school = null): string
    {
        if (! $school && \Illuminate\Support\Facades\Auth::check()) {
            $school = \Illuminate\Support\Facades\Auth::user()->getEffectiveSchool();
        }

        if ($school) {
            $dir = storage_path('app/hpc/schools/' . $school->id);
            if (! is_dir($dir)) {
                if (! mkdir($dir, 0755, true) && ! is_dir($dir)) {
                    Log::warning("[HPC] Could not create school storage dir: {$dir}");
                }
            }
            return $dir;
        }

        return storage_path('app/hpc');
    }

    public function getPreviewFile($school = null): string
    {
        if (! $school && \Illuminate\Support\Facades\Auth::check()) {
            $school = \Illuminate\Support\Facades\Auth::user()->getEffectiveSchool();
        }

        if ($school) {
            $schoolDir = $this->getSchoolStorageDir($school);
            return $schoolDir . '/preview.json';
        }

        return storage_path('app/hpc/preview.json');
    }

    public function getUdiseStudents($school = null): array
    {
        if (! $school && \Illuminate\Support\Facades\Auth::check()) {
            $school = \Illuminate\Support\Facades\Auth::user()->getEffectiveSchool();
        }

        if ($school) {
            $schoolDir = $this->getSchoolStorageDir($school);
            $schoolRaw = $schoolDir . '/udise_students_raw.json';
            if (file_exists($schoolRaw)) {
                return json_decode(file_get_contents($schoolRaw), true) ?: [];
            }

            return [];
        }

        return [];
    }

    public function getGradeMap(): array
    {
        return [
            'PP-1'  => 'Balvatika 1',
            'PP-2'  => 'Balvatika 2',
            'PP-3'  => 'Balvatika 3',
            'I'     => 'Grade 1',
            'II'    => 'Grade 2',
            'III'   => 'Grade 3',
            'IV'    => 'Grade 4',
            'V'     => 'Grade 5',
            'VI'    => 'Grade 6',
            'VII'   => 'Grade 7',
            'VIII'  => 'Grade 8',
            '1'     => 'Grade 1',
            '2'     => 'Grade 2',
            '3'     => 'Grade 3',
            '4'     => 'Grade 4',
            '5'     => 'Grade 5',
            '6'     => 'Grade 6',
            '7'     => 'Grade 7',
            '8'     => 'Grade 8',
        ];
    }

    // ─── Step 2: Upload & validate the filled Excel ───────────────────────────

    public function upload(Request $request)
    {
        $request->validate([
            'file'        => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'replace_all' => 'nullable|boolean',
        ]);

        $uploadedFile = $request->file('file');
        $ext = strtolower($uploadedFile->getClientOriginalExtension());
        if (! in_array($ext, ['xlsx', 'xls', 'csv'])) {
            $msg = 'Please upload a valid Excel (.xlsx, .xls) file.';
            return ($request->expectsJson() || $request->ajax())
                ? response()->json(['ok' => false, 'error' => $msg])
                : redirect()->to(route('dashboard') . '?step=2')->with('error', $msg);
        }

        $dir = $this->getSchoolStorageDir();
        $fullPath = $dir . '/students.xlsx';
        $uploadedFile->move($dir, 'students.xlsx');

        $errors  = [];
        $preview = [];

        try {
            $ss  = \PhpOffice\PhpSpreadsheet\IOFactory::load($fullPath);
            $ws  = $ss->getActiveSheet();
            $max = $ws->getHighestDataRow();

            // Universal header detection for UDISE+, new 9-col template, and legacy templates
            $headerMap = [];
            $headerRow = 0;
            for ($testRow = 1; $testRow <= min(6, $max); $testRow++) {
                $cells = [];
                for ($col = 1; $col <= 40; $col++) {
                    $ltr = Coordinate::stringFromColumnIndex($col);
                    $val = strtolower(trim((string) $ws->getCell("{$ltr}{$testRow}")->getValue()));
                    if ($val !== '') $cells[$val] = $ltr;
                }
                foreach ($cells as $k => $ltr) {
                    if (str_contains($k, 'first') && str_contains($k, 'name')) $headerMap['first_name'] = $ltr;
                    elseif (str_contains($k, 'last') && str_contains($k, 'name')) $headerMap['last_name'] = $ltr;
                    elseif (str_contains($k, 'student') && str_contains($k, 'name')) $headerMap['name'] = $ltr;
                    elseif (str_contains($k, 'name') && empty($headerMap['name']) && empty($headerMap['first_name'])) $headerMap['name'] = $ltr;
                    elseif (str_contains($k, 'birth') || $k === 'dob') $headerMap['dob'] = $ltr;
                    elseif (str_contains($k, 'gender') || str_contains($k, 'sex')) $headerMap['gender'] = $ltr;
                    elseif (str_contains($k, 'email')) $headerMap['email'] = $ltr;
                    elseif (str_contains($k, 'admission') || str_contains($k, 'admn') || str_contains($k, 'adm no') || str_contains($k, 'adm.') || str_contains($k, 'roll')) $headerMap['admission'] = $ltr;
                    elseif (str_contains($k, 'grade') || str_contains($k, 'class')) $headerMap['grade'] = $ltr;
                    elseif (str_contains($k, 'section')) $headerMap['section'] = $ltr;
                    elseif (str_contains($k, 'apaar')) $headerMap['apaar'] = $ltr;
                    elseif (str_contains($k, 'pen') || str_contains($k, 'permanent education')) $headerMap['pen'] = $ltr;
                }
                if (! empty($headerMap['name']) || ! empty($headerMap['first_name'])) {
                    $headerRow = $testRow;
                    break;
                }
            }

            $startRow = $headerRow > 0 ? ($headerRow + 1) : 3;
            // If row after header is a note row (e.g. Row 2 with italic instructions), skip it
            $sampleTest = strtolower(trim((string)$ws->getCell("A{$startRow}")->getValue()));
            if (str_contains($sampleTest, 'first name from') || str_contains($sampleTest, 'dd/mm/yyyy')) {
                $startRow++;
            }

            $gradeMap = $this->getGradeMap();

            for ($r = $startRow; $r <= $max; $r++) {
                if ($headerRow > 0) {
                    // Extract via identified headers
                    if (! empty($headerMap['first_name'])) {
                        $firstName = trim((string) $ws->getCell($headerMap['first_name'] . $r)->getValue());
                        $lastName  = ! empty($headerMap['last_name']) ? trim((string) $ws->getCell($headerMap['last_name'] . $r)->getValue()) : '';
                    } else {
                        $fullName  = trim((string) $ws->getCell(($headerMap['name'] ?? 'A') . $r)->getValue());
                        if ($fullName === '') continue;
                        $parts     = preg_split('/\s+/', $fullName);
                        $firstName = $parts[0] ?? 'Student';
                        $lastName  = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';
                    }

                    if ($firstName === '' && empty($lastName)) continue;

                    // DOB
                    $dobCell = ! empty($headerMap['dob']) ? $ws->getCell($headerMap['dob'] . $r) : null;
                    if ($dobCell && ExcelDate::isDateTime($dobCell)) {
                        $dob = ExcelDate::excelToDateTimeObject($dobCell->getValue())->format('d/m/Y');
                    } else {
                        $dob = $dobCell ? trim((string) $dobCell->getFormattedValue()) : '';
                    }

                    // Gender
                    $gender = ! empty($headerMap['gender']) ? trim((string) $ws->getCell($headerMap['gender'] . $r)->getValue()) : '';

                    // Admission No / PEN
                    $pen = ! empty($headerMap['pen']) ? preg_replace('/[^0-9]/', '', (string)$ws->getCell($headerMap['pen'] . $r)->getValue()) : '';
                    $admissionNo = ! empty($headerMap['admission']) ? trim((string)$ws->getCell($headerMap['admission'] . $r)->getValue()) : '';

                    // Email
                    $rawEmail = ! empty($headerMap['email']) ? trim((string)$ws->getCell($headerMap['email'] . $r)->getValue()) : '';
                    if (empty($rawEmail) || str_contains(strtolower($rawEmail), 'gov.in') || ! filter_var($rawEmail, FILTER_VALIDATE_EMAIL)) {
                        $cleanFirst = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $firstName)) ?: 'student';
                        $cleanId    = $admissionNo ?: ($pen ?: (string)$r);
                        $email      = "{$cleanFirst}.{$cleanId}@gmail.com";
                    } else {
                        $email = $rawEmail;
                    }

                    // Grade & Section
                    $rawGrade = ! empty($headerMap['grade']) ? trim((string)$ws->getCell($headerMap['grade'] . $r)->getValue()) : 'Grade 6';
                    $grade    = $gradeMap[$rawGrade] ?? ($gradeMap[preg_replace('/[^0-9IVX]/i', '', $rawGrade)] ?? 'Grade 6');
                    $section  = ! empty($headerMap['section']) ? (trim((string)$ws->getCell($headerMap['section'] . $r)->getValue()) ?: 'A') : 'A';
                    $apaarId  = ! empty($headerMap['apaar']) ? preg_replace('/[^0-9]/', '', (string)$ws->getCell($headerMap['apaar'] . $r)->getValue()) : '';

                } else {
                    // Positional fallback (default 9-col or 7-col layout)
                    $firstName = trim((string) $ws->getCell("A{$r}")->getValue());
                    $lastName  = trim((string) $ws->getCell("B{$r}")->getValue());

                    $dobCell  = $ws->getCell("C{$r}");
                    if (ExcelDate::isDateTime($dobCell)) {
                        $dob = ExcelDate::excelToDateTimeObject($dobCell->getValue())->format('d/m/Y');
                    } else {
                        $dob = trim((string) $dobCell->getFormattedValue());
                    }

                    // Check if Col D is Gender or Email
                    $valD = trim((string) $ws->getCell("D{$r}")->getValue());
                    if (in_array(strtolower($valD), ['male', 'female', 'other', 'm', 'f', 'boy', 'girl'])) {
                        // 9-col layout: A=First, B=Last, C=DOB, D=Gender, E=Email, F=Admission, G=Grade, H=Section, I=APAAR
                        $gender      = $valD;
                        $email       = trim((string) $ws->getCell("E{$r}")->getValue());
                        $admissionNo = trim((string) $ws->getCell("F{$r}")->getValue());
                        $grade       = trim((string) $ws->getCell("G{$r}")->getValue());
                        $section     = trim((string) $ws->getCell("H{$r}")->getValue());
                        $apaarId     = trim((string) $ws->getCell("I{$r}")->getValue());
                    } else {
                        // Legacy 7-col layout: A=First, B=Last, C=DOB, D=Email, E=Grade, F=Section, G=APAAR
                        $gender      = '';
                        $email       = $valD;
                        $admissionNo = '';
                        $grade       = trim((string) $ws->getCell("E{$r}")->getValue());
                        $section     = trim((string) $ws->getCell("F{$r}")->getValue());
                        $apaarId     = trim((string) $ws->getCell("G{$r}")->getValue());
                    }

                    if ($firstName === '' && $email === '' && $grade === '') {
                        continue;
                    }
                }

                if (empty($gender)) {
                    $dummySt = new Student(['first_name' => $firstName, 'last_name' => $lastName]);
                    $gender  = $dummySt->getEffectiveGender();
                }

                $rowErrors = [];
                if ($firstName === '') {
                    $rowErrors[] = 'First Name required';
                }
                if ($dob === '') {
                    $rowErrors[] = 'Date of Birth required';
                }
                if ($email === '') {
                    $rowErrors[] = 'Email required';
                } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $rowErrors[] = 'Email invalid';
                }
                if ($grade === '') {
                    $rowErrors[] = 'Grade required';
                }
                if ($section === '') {
                    $rowErrors[] = 'Section required';
                }
                if ($apaarId !== '' && (! ctype_digit($apaarId) || strlen($apaarId) !== 12)) {
                    $rowErrors[] = 'APAAR ID must be exactly 12 digits';
                }

                $preview[] = [
                    'row'          => count($preview) + 1,
                    'first_name'   => $firstName,
                    'last_name'    => $lastName,
                    'name'         => trim("{$firstName} {$lastName}"),
                    'gender'       => $gender,
                    'dob'          => $dob,
                    'email'        => $email,
                    'admission_no' => $admissionNo,
                    'grade'        => $grade,
                    'section'      => $section,
                    'apaar_id'     => $apaarId,
                    'pen_code'     => $pen,
                    'errors'       => $rowErrors,
                ];

                if (! empty($rowErrors)) {
                    $errors[] = "Row {$r} ({$firstName}): " . implode(', ', $rowErrors);
                }
            }
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'Could not read Excel file: ' . $e->getMessage()]);
        }

        $schoolForFile = \Illuminate\Support\Facades\Auth::check() ? \Illuminate\Support\Facades\Auth::user()->getEffectiveSchool() : null;
        $this->writePreviewFile($this->getPreviewFile($schoolForFile), $preview);
        $this->syncSpreadsheetFromPreview($preview);

        if (\Illuminate\Support\Facades\Auth::check()) {
            $userSchool = \Illuminate\Support\Facades\Auth::user()->getEffectiveSchool();
            if ($userSchool) {
                // By default, fresh spreadsheet upload replaces the roster so the dashboard exactly matches the file
                // 'replace_all' checkbox: present+1 means replace, absent means merge
                $shouldReplace = $request->boolean('replace_all');
                if ($shouldReplace) {
                    Student::where('school_id', $userSchool->id)
                        ->where(function ($q) {
                            $q->whereNull('hpc_submitted_at')
                              ->where('source', '!=', Student::SOURCE_HPC);
                        })
                        ->delete();
                }

                foreach ($preview as $pRow) {
                    $this->upsertStudentToDatabase($userSchool, $pRow, 'excel_upload');
                }
                $apaarCount = count(array_filter($preview, fn($s) => !empty($s['apaar_id']) && strlen((string)$s['apaar_id']) === 12));
                $userSchool->update([
                    'udise_status'         => 'synced',
                    'udise_synced_at'      => now(),
                    'udise_students_count' => count($preview),
                    'udise_apaar_count'    => $apaarCount,
                ]);
            }
        }

        if (! $request->expectsJson() && ! $request->ajax()) {
            return redirect()->to(route('dashboard') . '?step=2')
                ->with('success', 'Successfully uploaded ' . count($preview) . ' students to your school roster!');
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

    // ─── Student CRUD & Column Corrections ───────────────────────────────────

    public function validateStudentRow(array $data, int $rowNum = 1): array
    {
        $firstName   = trim((string)($data['first_name'] ?? ''));
        $lastName    = trim((string)($data['last_name'] ?? ''));
        $gender      = trim((string)($data['gender'] ?? ''));
        $dob         = trim((string)($data['dob'] ?? ''));
        $email       = trim((string)($data['email'] ?? ''));
        $admissionNo = trim((string)($data['admission_no'] ?? ($data['admnNumber'] ?? '')));
        $penCode     = trim((string)($data['pen_code'] ?? ($data['studentCodeNat'] ?? '')));
        $grade       = trim((string)($data['grade'] ?? ''));
        $section     = trim((string)($data['section'] ?? ''));
        $apaarId     = trim((string)($data['apaar_id'] ?? ''));

        if (empty($gender)) {
            $dummy  = new Student(['first_name' => $firstName, 'last_name' => $lastName]);
            $gender = $dummy->getEffectiveGender();
        }

        $rowErrors = [];
        if ($firstName === '') {
            $rowErrors[] = 'First Name required';
        }
        if ($dob === '') {
            $rowErrors[] = 'Date of Birth required';
        } elseif (! preg_match('/^\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4}$/', $dob)) {
            $rowErrors[] = 'Date of Birth must be DD/MM/YYYY';
        }

        if ($email === '') {
            $rowErrors[] = 'Email required';
        } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $rowErrors[] = 'Email invalid';
        }

        if ($grade === '') {
            $rowErrors[] = 'Grade required';
        }
        if ($section === '') {
            $rowErrors[] = 'Section required';
        }
        if ($apaarId !== '' && (! ctype_digit($apaarId) || strlen($apaarId) !== 12)) {
            $rowErrors[] = 'APAAR ID must be exactly 12 digits';
        }

        return [
            'id'           => $data['id'] ?? null,
            'row'          => $rowNum,
            'first_name'   => $firstName,
            'last_name'    => $lastName,
            'name'         => trim("{$firstName} {$lastName}"),
            'gender'       => $gender,
            'dob'          => $dob,
            'email'        => $email,
            'admission_no' => $admissionNo,
            'pen_code'     => $penCode,
            'grade'        => $grade,
            'section'      => $section,
            'apaar_id'     => $apaarId,
            'errors'       => $rowErrors,
        ];
    }

    public function syncSpreadsheetFromPreview(array $preview): void
    {
        try {
            $sheet = $this->buildStudentSpreadsheet($preview);
            $writer = new Xlsx($sheet);
            $dir = $this->getSchoolStorageDir();
            @mkdir($dir, 0755, true);
            $writer->save($dir . '/students.xlsx');
        } catch (\Throwable $e) {
            \Log::warning('Could not sync students.xlsx: ' . $e->getMessage());
        }
    }

    public function saveStudent(Request $request)
    {
        $request->validate([
            'first_name'   => 'required|string|max:100',
            'last_name'    => 'nullable|string|max:100',
            'dob'          => 'required|string|max:20',
            'email'        => 'required|email|max:200',
            'gender'       => 'required|in:Male,Female,Other',
            'grade'        => 'required|string|max:30',
            'section'      => 'required|string|max:10',
            'apaar_id'     => 'nullable|digits:12',
            'admission_no' => 'nullable|string|max:50',
            'pen_code'     => 'nullable|string|max:20',
            'index'        => 'nullable|integer|min:0',
        ]);

        $previewFile = $this->getPreviewFile();
        $preview = file_exists($previewFile) ? (json_decode(file_get_contents($previewFile), true) ?? []) : [];

        $index = $request->input('index');
        $isEdit = ($index !== null && $index !== '' && (int)$index >= 0 && isset($preview[(int)$index]));
        $targetRow = $isEdit ? ((int)$index + 1) : (count($preview) + 1);

        $validated = $this->validateStudentRow($request->only([
            'first_name', 'last_name', 'dob', 'email', 'gender',
            'grade', 'section', 'apaar_id', 'admission_no', 'pen_code', 'id',
        ]), $targetRow);

        if (\Illuminate\Support\Facades\Auth::check()) {
            $userSchool = \Illuminate\Support\Facades\Auth::user()->getEffectiveSchool();
            if ($userSchool) {
                $dbStudent = $this->upsertStudentToDatabase($userSchool, $validated, 'manual');
                $validated['id'] = $dbStudent->id;
            }
        }

        if ($isEdit) {
            $preview[(int)$index] = $validated;
        } else {
            $preview[] = $validated;
        }

        // Re-index rows
        foreach ($preview as $i => &$st) {
            $st['row'] = $i + 1;
        }
        unset($st);

        $this->writePreviewFile($previewFile, $preview);
        $this->syncSpreadsheetFromPreview($preview);

        $valid = count(array_filter($preview, fn($r) => empty($r['errors'])));
        $invalid = count($preview) - $valid;

        return response()->json([
            'ok'      => true,
            'message' => $isEdit ? 'Student record updated successfully' : 'Student added successfully',
            'total'   => count($preview),
            'valid'   => $valid,
            'invalid' => $invalid,
            'preview' => $preview,
        ]);
    }

    public function deleteStudent(Request $request)
    {
        $userSchool = \Illuminate\Support\Facades\Auth::check()
            ? \Illuminate\Support\Facades\Auth::user()->getEffectiveSchool()
            : null;

        $previewFile = $this->getPreviewFile($userSchool);
        $preview = file_exists($previewFile) ? (json_decode(file_get_contents($previewFile), true) ?? []) : [];

        $index = $request->has('index') ? (int) $request->input('index') : null;
        $studentId = $request->input('id');

        $deletedStudent = null;

        if ($studentId && $userSchool) {
            $dbStudent = Student::where('school_id', $userSchool->id)->find($studentId);
            if ($dbStudent) {
                $deletedStudent = $dbStudent->toArray();
                $dbStudent->delete();
            }
            $preview = array_values(array_filter($preview, fn($s) => ($s['id'] ?? null) != $studentId));
        } elseif ($index !== null && isset($preview[$index])) {
            $deletedStudent = $preview[$index];
            array_splice($preview, $index, 1);

            if ($userSchool && $deletedStudent) {
                $this->deleteStudentFromDatabase($userSchool, $deletedStudent);
            }
        } elseif ($userSchool && $index !== null) {
            $dbStudent = Student::where('school_id', $userSchool->id)->orderBy('id')->skip($index)->first();
            if ($dbStudent) {
                $dbStudent->delete();
            }
        }

        foreach ($preview as $i => &$st) {
            $st['row'] = $i + 1;
        }
        unset($st);

        $this->writePreviewFile($previewFile, $preview);
        $this->syncSpreadsheetFromPreview($preview);

        $valid = count(array_filter($preview, fn($r) => empty($r['errors'])));
        $invalid = count($preview) - $valid;

        return response()->json([
            'ok'      => true,
            'message' => 'Student record removed',
            'total'   => count($preview),
            'valid'   => $valid,
            'invalid' => $invalid,
            'preview' => $preview,
        ]);
    }

    public function clearAllStudents(Request $request)
    {
        $userSchool = \Illuminate\Support\Facades\Auth::check()
            ? \Illuminate\Support\Facades\Auth::user()->getEffectiveSchool()
            : null;

        if ($userSchool) {
            Student::where('school_id', $userSchool->id)->delete();
            $userSchool->update([
                'udise_students_count' => 0,
                'udise_apaar_count'    => 0,
            ]);
            $previewFile = $this->getPreviewFile($userSchool);
            if (file_exists($previewFile) && ! unlink($previewFile)) {
                Log::warning("[HPC] Could not delete preview file: {$previewFile}");
            }
            $this->syncSpreadsheetFromPreview([]);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok'      => true,
                'message' => 'All students cleared from roster.',
                'total'   => 0,
                'preview' => [],
            ]);
        }

        return redirect()->to(route('dashboard') . '?step=2')
            ->with('success', 'Student roster cleared. You can now upload a fresh Excel file.');
    }

    public function updateAllStudents(Request $request)
    {
        $request->validate([
            'students'               => 'required|array|max:2000',
            'students.*.first_name'  => 'required|string|max:100',
            'students.*.email'       => 'required|email|max:200',
            'students.*.gender'      => 'required|in:Male,Female,Other',
            'students.*.grade'       => 'required|string|max:30',
            'students.*.section'     => 'required|string|max:10',
            'students.*.apaar_id'    => 'nullable|digits:12',
        ]);

        $items = $request->input('students', []);
        $preview = [];
        foreach ($items as $i => $item) {
            $preview[] = $this->validateStudentRow($item, $i + 1);
        }

        $previewFile = $this->getPreviewFile();
        $this->writePreviewFile($previewFile, $preview);
        $this->syncSpreadsheetFromPreview($preview);

        if (\Illuminate\Support\Facades\Auth::check()) {
            $userSchool = \Illuminate\Support\Facades\Auth::user()->getEffectiveSchool();
            if ($userSchool) {
                foreach ($preview as $pRow) {
                    $this->upsertStudentToDatabase($userSchool, $pRow, 'bulk_update');
                }
            }
        }

        $valid = count(array_filter($preview, fn($r) => empty($r['errors'])));
        $invalid = count($preview) - $valid;

        return response()->json([
            'ok'      => true,
            'message' => 'All student records updated successfully',
            'total'   => count($preview),
            'valid'   => $valid,
            'invalid' => $invalid,
            'preview' => $preview,
        ]);
    }

    /**
     * Upsert a student record into the MySQL database with deduplication.
     * Matches by 12-digit unmasked APAAR ID, PEN code, or Name + DOB + Grade.
     */
    public function upsertStudentToDatabase(School $school, array $data, string $source = 'udise_sync'): Student
    {
        $apaar = ! empty($data['apaar_id']) ? preg_replace('/[^0-9]/', '', (string) $data['apaar_id']) : null;
        
        // National PEN is 11 digits in UDISE+ (studentCodeNat)
        $pen = ! empty($data['studentCodeNat']) ? trim((string) $data['studentCodeNat']) : (! empty($data['pen_code']) ? trim((string) $data['pen_code']) : null);
        
        // School Admission Number is local school number (admnNumber)
        $admissionNo = ! empty($data['admission_no']) ? trim((string) $data['admission_no']) : (! empty($data['admnNumber']) ? trim((string) $data['admnNumber']) : null);
        
        // Clean short dummy values that were accidentally placed in pen_code
        if (! empty($pen) && strlen($pen) < 8 && empty($admissionNo)) {
            $admissionNo = $pen;
            $pen = null;
        }

        $firstName = trim($data['first_name'] ?? '');
        $lastName  = trim($data['last_name'] ?? '');
        $name      = trim($data['name'] ?? trim("{$firstName} {$lastName}"));
        $dob       = trim($data['dob'] ?? '');
        $grade     = trim($data['grade'] ?? 'Grade 6');
        $section   = trim($data['section'] ?? 'A');
        $email     = trim($data['email'] ?? '');
        $gender    = ! empty($data['gender']) ? trim((string)$data['gender']) : (! empty($data['genderDesc']) ? trim((string)$data['genderDesc']) : null);

        $student = null;

        // 1. Match by explicit database ID if provided (e.g., student edit / manual modal)
        if (! empty($data['id'])) {
            $student = Student::where('school_id', $school->id)->find($data['id']);
        }

        // 2. Match by 12-digit APAAR ID if present (Primary national identifier)
        if (! $student && $apaar && strlen($apaar) === 12) {
            $existing = Student::where('school_id', $school->id)->where('apaar_id', $apaar)->first();
            if ($existing) {
                similar_text(strtolower($existing->name), strtolower($name), $simPct);
                if (empty($name) || soundex($existing->first_name) === soundex($firstName) || $simPct >= 50) {
                    $student = $existing;
                }
            }
        }

        // 3. Match by National PEN code if APAAR did not match (Secondary national identifier)
        if (! $student && ! empty($pen)) {
            $existing = Student::where('school_id', $school->id)->where('pen_code', $pen)->first();
            if ($existing) {
                similar_text(strtolower($existing->name), strtolower($name), $simPct);
                if (empty($name) || soundex($existing->first_name) === soundex($firstName) || $simPct >= 50) {
                    $student = $existing;
                }
            }
        }

        // 4. Match by Admission Number if APAAR/PEN did not match (School-level identifier)
        if (! $student && ! empty($admissionNo)) {
            $existing = Student::where('school_id', $school->id)->where('admission_no', $admissionNo)->first();
            if ($existing) {
                similar_text(strtolower($existing->name), strtolower($name), $simPct);
                if (empty($name) || soundex($existing->first_name) === soundex($firstName) || $simPct >= 50) {
                    $student = $existing;
                }
            }
        }

        // 5. Match by UDISE internal student ID if present
        $udiseStId = $data['udise_student_id'] ?? ($data['studentId'] ?? null);
        if (! $student && ! empty($udiseStId)) {
            $student = Student::where('school_id', $school->id)->where('udise_student_id', $udiseStId)->first();
        }

        // 6. Fallback: Match by Name (+ DOB if present)
        if (! $student && ! empty($name)) {
            $query = Student::where('school_id', $school->id)->where('name', $name);
            if (! empty($dob)) {
                $dobMatch = (clone $query)->where('dob', $dob)->first();
                if ($dobMatch) {
                    $student = $dobMatch;
                }
            }
            if (! $student) {
                $candidates = $query->get();
                foreach ($candidates as $cand) {
                    // Must not conflict with APAAR ID
                    if ($apaar && ! empty($cand->apaar_id) && $cand->apaar_id !== $apaar) {
                        continue;
                    }
                    // Must not conflict with PEN
                    if (! empty($pen) && ! empty($cand->pen_code) && $cand->pen_code !== $pen) {
                        continue;
                    }
                    // Must not conflict with Admission Number
                    if (! empty($admissionNo) && ! empty($cand->admission_no) && $cand->admission_no !== $admissionNo) {
                        continue;
                    }
                    // Must not conflict with Gender if both are provided
                    if (! empty($gender) && ! empty($cand->gender) && strtolower($cand->gender) !== strtolower($gender)) {
                        continue;
                    }
                    // Must not conflict with Grade if both are provided
                    if (! empty($grade) && ! empty($cand->grade) && strtolower($cand->grade) !== strtolower($grade)) {
                        continue;
                    }

                    $student = $cand;
                    break;
                }
            }
        }

        $errors = $data['errors'] ?? [];

        // For HPC portal records: always mark as success (they're already submitted)
        if ($source === Student::SOURCE_HPC) {
            $status = Student::STATUS_SUCCESS;
            $remark = $data['remark'] ?? 'Confirmed on PARAKH HPC Portal';
            $failureReason = null;
        } else {
            $status = ! empty($errors) ? Student::STATUS_FAILED : Student::STATUS_PENDING;
            $defaultRemark = match ($source) {
                Student::SOURCE_UDISE  => 'Synced from UDISE+ SDMS',
                Student::SOURCE_EXCEL  => 'Imported from Excel spreadsheet',
                Student::SOURCE_MANUAL => 'Added manually',
                default                => 'Saved via School Portal',
            };
            $remark = ! empty($errors) ? null : ($data['remark'] ?? $defaultRemark);
            $failureReason = ! empty($errors) ? implode(', ', (array) $errors) : null;
        }

        $payload = [
            'school_id'        => $school->id,
            'first_name'       => $firstName ?: 'Student',
            'last_name'        => $lastName,
            'name'             => $name ?: 'Student',
            'dob'              => $dob,
            'email'            => $email,
            'grade'            => $grade,
            'section'          => $section ?: 'A',
            'apaar_id'         => $apaar,
            'pen_code'         => $pen,
            'admission_no'     => $admissionNo,
            'udise_student_id' => $data['udise_student_id'] ?? ($data['studentId'] ?? null),
            'hpc_student_id'   => $data['hpc_student_id'] ?? null,
            'gender'           => $gender,
            'source'           => $source,
            'status'           => $status,
            'remark'           => $remark,
            'failure_reason'   => $failureReason,
        ];

        // For HPC portal records, also stamp fetch time
        if ($source === Student::SOURCE_HPC) {
            $payload['hpc_fetched_at'] = now();
            $payload['hpc_submitted_at'] = $payload['hpc_submitted_at'] ?? now();
        }

        if ($student) {
            // Preserve existing unique IDs if incoming row left them empty
            if (empty($payload['apaar_id']) && ! empty($student->apaar_id)) {
                $payload['apaar_id'] = $student->apaar_id;
            }
            if (empty($payload['pen_code']) && ! empty($student->pen_code)) {
                $payload['pen_code'] = $student->pen_code;
            }
            if (empty($payload['admission_no']) && ! empty($student->admission_no)) {
                $payload['admission_no'] = $student->admission_no;
            }
            if (empty($payload['gender']) && ! empty($student->gender)) {
                $payload['gender'] = $student->gender;
            }

            // When we find an existing UDISE/Excel student and discover it's
            // already on the HPC portal, mark it as submitted without changing
            // its original source (so it still appears in Step 3 history).
            if ($source === Student::SOURCE_HPC) {
                $student->update(array_merge($payload, [
                    'source' => $student->source !== Student::SOURCE_HPC ? $student->source : Student::SOURCE_HPC,
                    'status' => Student::STATUS_SUCCESS,
                    'hpc_student_id' => $payload['hpc_student_id'] ?? $student->hpc_student_id,
                    'hpc_fetched_at' => now(),
                    'hpc_submitted_at' => $student->hpc_submitted_at ?? now(),
                ]));
            } else {
                $student->update($payload);
            }
            return $student;
        }

        return Student::create($payload);
    }

    /**
     * Remove student record from MySQL database.
     */
    public function deleteStudentFromDatabase(School $school, array $studentData): void
    {
        $query = Student::where('school_id', $school->id);

        if (! empty($studentData['id'])) {
            $query->where('id', $studentData['id'])->delete();
            return;
        }

        $apaar = ! empty($studentData['apaar_id']) ? preg_replace('/[^0-9]/', '', (string) $studentData['apaar_id']) : null;
        $pen   = ! empty($studentData['pen_code']) ? trim((string) $studentData['pen_code']) : null;

        if ($apaar && strlen($apaar) === 12) {
            $query->where('apaar_id', $apaar)->delete();
        } elseif ($pen) {
            $query->where('pen_code', $pen)->delete();
        } else {
            $name = trim($studentData['name'] ?? trim(($studentData['first_name'] ?? '') . ' ' . ($studentData['last_name'] ?? '')));
            $dob  = trim($studentData['dob'] ?? '');
            if (! empty($name) && ! empty($dob)) {
                $query->where('name', $name)->where('dob', $dob)->delete();
            }
        }
    }


    // ─── UDISE Student Normalisation Helper ─────────────────────────────────

    /**
     * Normalise a single raw UDISE+ student row into a flat array used by
     * both buildStudentSpreadsheet() and preloadUdise().
     * Eliminates the previous copy-paste duplication.
     */
    private function normalizeUdiseStudent(array $st, int $idx, array $gradeMap): array
    {
        $rawName   = trim($st['studentName'] ?? '');
        $parts     = preg_split('/\s+/', $rawName);
        $firstName = $parts[0] ?? 'Student';
        $lastName  = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';

        $dob = trim($st['dob'] ?? '');
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dob, $m)) {
            $dob = "{$m[3]}/{$m[2]}/{$m[1]}";
        }

        $rawGen = trim((string)($st['genderDesc'] ?? ($st['gender'] ?? '')));
        $gender = match (true) {
            in_array($rawGen, ['1', 1], true),
            strtolower($rawGen) === 'male',
            str_starts_with(strtolower($rawGen), 'm') => 'Male',

            in_array($rawGen, ['2', 2], true),
            strtolower($rawGen) === 'female',
            str_starts_with(strtolower($rawGen), 'f') => 'Female',

            in_array($rawGen, ['3', 3], true) => 'Other',

            default => '',
        };

        $cleanPen    = ! empty($st['studentCodeNat']) ? preg_replace('/[^0-9]/', '', (string) $st['studentCodeNat']) : '';
        $admissionNo = ! empty($st['admnNumber'])
            ? trim((string) $st['admnNumber'])
            : trim((string) ($st['admission_no'] ?? ''));

        $email = ! empty($st['email']) && filter_var($st['email'], FILTER_VALIDATE_EMAIL)
            ? trim($st['email'])
            : '';

        if (empty($email) || str_contains(strtolower($email), 'gov.in')) {
            $cleanFirst = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $firstName)) ?: 'student';
            $idPart     = $cleanPen ?: ($admissionNo ?: (string) ($idx + 1));
            $email      = "{$cleanFirst}.{$idPart}@gmail.com";
        }

        $rawClass = trim($st['classDesc'] ?? '');
        $grade    = $gradeMap[$rawClass] ?? ($gradeMap[preg_replace('/[^0-9IVX]/i', '', $rawClass)] ?? 'Grade 6');
        $section  = ! empty($st['sectionDesc']) ? trim($st['sectionDesc']) : 'A';
        $apaarId  = ! empty($st['apaarId']) ? (string) $st['apaarId'] : '';

        return compact('firstName', 'lastName', 'dob', 'gender', 'email', 'cleanPen', 'admissionNo', 'grade', 'section', 'apaarId');
    }

    // ─── Preview JSON Writer (with exclusive lock to prevent race conditions) ──

    /**
     * Write preview JSON atomically using LOCK_EX to prevent concurrent writes
     * from corrupting the file.
     */
    private function writePreviewFile(string $path, array $data): void
    {
        $dir = dirname($path);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            Log::warning("[HPC] writePreviewFile: could not create dir {$dir}");
        }
        if (file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
            Log::warning("[HPC] writePreviewFile: could not write to {$path}");
        }
    }

    // ─── Step 3: Start Automation ────────────────────────────────────────────

    public function runStart(Request $request)
    {
        $school      = \Illuminate\Support\Facades\Auth::user()?->getEffectiveSchool();
        $previewFile = $this->getPreviewFile($school);

        $allStudents = [];
        if (file_exists($previewFile)) {
            $allStudents = json_decode(file_get_contents($previewFile), true) ?? [];
        } elseif ($school && $school->students()->exists()) {
            $allStudents = $school->students()->orderBy('id')->get()->map(fn($st, $idx) => $st->toPreviewArray($idx + 1))->values()->all();
            @mkdir(dirname($previewFile), 0755, true);
            file_put_contents($previewFile, json_encode($allStudents, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        if (empty($allStudents)) {
            return response()->json(['ok' => false, 'error' => 'No student data found. Please upload an Excel file first (download the UDISE template if needed).']);
        }

        $validStudents = array_values(array_filter($allStudents, fn($s) => empty($s['errors'])));

        if (empty($validStudents)) {
            return response()->json(['ok' => false, 'error' => 'All rows have validation errors. Please fix and re-upload.']);
        }

        $total = count($validStudents);

        // Initialize run_results.json
        $resultsFile = $this->getSchoolStorageDir($school) . '/run_results.json';
        $init = [
            'started'    => true,
            'running'    => true,
            'done'       => false,
            'login_wait' => false,
            'total'      => $total,
            'current'    => 0,
            'processed'  => 0,
            'results'    => [],
            'started_at' => now()->toIso8601String(),
            'message'    => "Queue ready: {$total} students queued for submission.",
        ];
        file_put_contents($resultsFile, json_encode($init, JSON_PRETTY_PRINT));

        // Launch hpc:run command in the background if CLI execution is supported on this server
        $artisan     = escapeshellarg(base_path('artisan'));
        $schoolArg   = $school ? '--school=' . (int) $school->id : '';
        $logFile     = escapeshellarg(storage_path('logs/hpc_run.log'));
        $php         = escapeshellarg(PHP_BINARY);
        $launchedCli = false;

        if (function_exists('exec')) {
            exec("nohup {$php} {$artisan} hpc:run {$schoolArg} > {$logFile} 2>&1 &", $out, $code);
            if ($code !== 0) {
                Log::warning('[HPC] hpc:run CLI launch failed', ['output' => $out, 'code' => $code]);
            }
            $launchedCli = true;
        }

        return response()->json([
            'ok'           => true,
            'total'        => $total,
            'launched_cli' => $launchedCli,
            'students'     => $validStudents,
            'add_url'      => config('hpc.add_student_url', 'https://hpc.parakh.ncert.gov.in/en/students/add'),
            'message'      => $launchedCli ? 'Background server automation started.' : 'Student queue ready for browser-assisted submission.',
        ]);
    }

    public function runProgress(Request $request)
    {
        $corsOrigin = $this->getAllowedCorsOrigin();
        if ($request->isMethod('OPTIONS')) {
            return response()->json(['ok' => true], 200, [
                'Access-Control-Allow-Origin'  => $corsOrigin,
                'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Accept, X-HPC-Token',
            ]);
        }

        $schoolId = $request->input('school_id');
        $school   = $schoolId ? School::find($schoolId) : \Illuminate\Support\Facades\Auth::user()?->getEffectiveSchool();
        if (! $school && $request->filled('udise_code')) {
            $school = School::where('udise_code', $request->input('udise_code'))->first();
        }
        if (! $school) {
            return response()->json(['ok' => false, 'error' => 'School not found.'], 404, [
                'Access-Control-Allow-Origin'  => $corsOrigin,
                'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Accept, X-HPC-Token',
            ]);
        }

        // Authenticate via shared token or school token — sent by the Chrome extension
        $token = $request->header('X-HPC-Token') ?? $request->input('_hpc_token');
        $expected = config('hpc.progress_token', '');
        $schoolToken = substr(hash('sha256', $school->id . '_' . $school->udise_code . '_' . config('app.key')), 0, 32);
        if ($expected !== '' && $token !== $expected && $token !== $schoolToken) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 401, [
                'Access-Control-Allow-Origin'  => $corsOrigin,
                'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Accept, X-HPC-Token',
            ]);
        }

        $resultsFile = $this->getSchoolStorageDir($school) . '/run_results.json';

        $current = (int) $request->input('current', 0);
        $total   = (int) $request->input('total', 0);
        $name    = (string) $request->input('name', '');
        $status  = (string) $request->input('status', 'success');
        $message = (string) $request->input('message', '');
        $done    = (bool) $request->input('done', false);

        $data = file_exists($resultsFile) ? (json_decode(file_get_contents($resultsFile), true) ?? []) : [];
        $data['started']   = true;
        $data['running']   = ! $done;
        $data['done']      = $done;
        $data['current']   = $current;
        $data['processed'] = $current;
        if ($total > 0) {
            $data['total'] = $total;
        }
        $data['message']   = $message ?: ($done ? 'Automation Completed! All students submitted.' : "Submitting {$name} ({$current}/{$total})...");

        if ($name) {
            $data['results'] = $data['results'] ?? [];
            $data['results'][] = [
                'name'    => $name,
                'status'  => $status,
                'message' => $message,
                'time'    => now()->toIso8601String(),
            ];

            // Mark student as confirmed in DB upon successful submission by extension
            if ($status === 'success') {
                $studentId   = $request->input('student_id');
                $admissionNo = $request->input('admission_no');
                $apaarId     = $request->input('apaar_id');

                $st = null;
                // 1. Direct match by database ID if sent
                if ($studentId) {
                    $st = Student::where('school_id', $school->id)->find($studentId);
                }
                // 2. Match by 12-digit APAAR ID if sent
                if (! $st && $apaarId && strlen((string)$apaarId) === 12) {
                    $st = Student::where('school_id', $school->id)->where('apaar_id', $apaarId)->first();
                }
                // 3. Match by Admission Number if sent
                if (! $st && ! empty($admissionNo)) {
                    $st = Student::where('school_id', $school->id)->where('admission_no', $admissionNo)->first();
                }
                // 4. Match unsubmitted student by Name (prevents duplicate name collision)
                if (! $st && $name) {
                    $st = Student::where('school_id', $school->id)
                        ->where('name', $name)
                        ->whereNull('hpc_submitted_at')
                        ->first();
                    if (! $st) {
                        $parts = preg_split('/\s+/', trim($name));
                        $st = Student::where('school_id', $school->id)
                            ->where('first_name', $parts[0])
                            ->whereNull('hpc_submitted_at')
                            ->first();
                    }
                    if (! $st) {
                        $st = Student::where('school_id', $school->id)->where('name', $name)->first();
                    }
                }

                if ($st) {
                    $st->update([
                        'status'           => Student::STATUS_SUCCESS,
                        'hpc_submitted_at' => now(),
                        'remark'           => 'Submitted via HPC Assist Browser Extension',
                    ]);

                    // Immediately re-sync Step 2 preview file so the roster reflects removal
                    $step2 = $school->students()->step2()->orderBy('id')->get();
                    $preview = $step2->map(fn ($sRow, $idx) => $sRow->toPreviewArray($idx + 1))->values()->all();
                    $this->writePreviewFile($this->getPreviewFile($school), $preview);
                }
            }
        }

        file_put_contents($resultsFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

        return response()->json(['ok' => true], 200, [
            'Access-Control-Allow-Origin'  => $corsOrigin,
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Accept, X-HPC-Token',
        ]);
    }

    public function getStudentQueue(Request $request)
    {
        $corsOrigin = $this->getAllowedCorsOrigin();
        if ($request->isMethod('OPTIONS')) {
            return response()->json(['ok' => true], 200, [
                'Access-Control-Allow-Origin'  => $corsOrigin,
                'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Accept, X-HPC-Token',
            ]);
        }

        $schoolId = $request->input('school_id');
        $school   = $schoolId ? School::find($schoolId) : \Illuminate\Support\Facades\Auth::user()?->getEffectiveSchool();
        if (! $school && $request->filled('udise_code')) {
            $school = School::where('udise_code', $request->input('udise_code'))->first();
        }
        if (! $school) {
            return response()->json(['ok' => false, 'error' => 'School not found. Please log in.'], 404, [
                'Access-Control-Allow-Origin'  => $corsOrigin,
                'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Accept, X-HPC-Token',
            ]);
        }

        // Authenticate via shared token or school token — sent by the Chrome extension
        $token = $request->header('X-HPC-Token') ?? $request->input('_hpc_token');
        $expected = config('hpc.progress_token', '');
        $schoolToken = substr(hash('sha256', $school->id . '_' . $school->udise_code . '_' . config('app.key')), 0, 32);
        if ($expected !== '' && $token !== $expected && $token !== $schoolToken) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 401, [
                'Access-Control-Allow-Origin'  => $corsOrigin,
                'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Accept, X-HPC-Token',
            ]);
        }

        $previewFile = $this->getPreviewFile($school);
        $students    = [];
        if ($school->students()->exists()) {
            $students = $school->students()->step2()->orderBy('id')->get()->map(fn($st, $idx) => $st->toPreviewArray($idx + 1))->values()->all();
        } elseif (file_exists($previewFile)) {
            $students = json_decode(file_get_contents($previewFile), true) ?? [];
        }

        $valid = array_values(array_filter($students, fn($s) => empty($s['errors'])));

        return response()->json([
            'ok'          => true,
            'school_id'   => $school->id,
            'school_name' => $school->name,
            'total'       => count($valid),
            'students'    => $valid,
            'add_url'     => config('hpc.add_student_url', 'https://hpc.parakh.ncert.gov.in/en/students/add'),
        ], 200, [
            'Access-Control-Allow-Origin'  => $corsOrigin,
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Accept, X-HPC-Token',
        ]);
    }

    public function getSchoolsList(Request $request)
    {
        $corsOrigin = $this->getAllowedCorsOrigin();
        if ($request->isMethod('OPTIONS')) {
            return response()->json(['ok' => true], 200, [
                'Access-Control-Allow-Origin'  => $corsOrigin,
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Accept, X-HPC-Token',
            ]);
        }

        $schools = School::orderBy('name')->get()->map(function($sc) {
            return [
                'id'         => $sc->id,
                'name'       => $sc->name,
                'udise_code' => $sc->udise_code,
                'pending'    => $sc->students()->step2()->count(),
                'submitted'  => $sc->students()->step3()->count(),
                'total'      => $sc->students()->count(),
            ];
        });

        return response()->json([
            'ok'      => true,
            'schools' => $schools,
        ], 200, [
            'Access-Control-Allow-Origin'  => $corsOrigin,
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Accept, X-HPC-Token',
        ]);
    }

    /**
     * Return the allowed CORS origin for public extension-facing endpoints.
     * Accurately supports the PARAKH portal, UDISE portal, Chrome extension ID,
     * and local/app URLs.
     */
    private function getAllowedCorsOrigin(): string
    {
        $incoming = request()->header('Origin');
        if (! empty($incoming)) {
            $allowedPatterns = [
                'https://hpc.parakh.ncert.gov.in',
                'https://sdms.udiseplus.gov.in',
                'chrome-extension://',
                rtrim(config('app.url', 'http://localhost'), '/'),
                'http://127.0.0.1',
                'http://localhost',
            ];
            foreach ($allowedPatterns as $pattern) {
                if (str_starts_with($incoming, $pattern)) {
                    return $incoming;
                }
            }
        }

        $extId = config('hpc.extension_id', '');
        if ($extId !== '') {
            return "chrome-extension://{$extId}";
        }
        return rtrim(config('app.url', 'http://localhost'), '/');
    }

    // ─── Step 4: Poll run progress & results ──────────────────────────────────

    public function runStatus()
    {
        $file = $this->getSchoolStorageDir() . '/run_results.json';
        if (! file_exists($file)) {
            return response()->json(['started' => false, 'results' => []]);
        }

        $data = json_decode(file_get_contents($file), true) ?? [];
        return response()->json($data);
    }

    // ─── Reset run results ───────────────────────────────────────────────────

    public function runReset()
    {
        $file = $this->getSchoolStorageDir() . '/run_results.json';
        if (file_exists($file)) {
            unlink($file);
        }
        return response()->json(['ok' => true]);
    }

    // ─── School Profile Management ───────────────────────────────────────────

    public function getSchoolsData(): array
    {
        if (\Illuminate\Support\Facades\Auth::check()) {
            $user = \Illuminate\Support\Facades\Auth::user();
            $dbSchool = $user->getEffectiveSchool();
            if ($dbSchool) {
                return [
                    'active' => 'school_' . $dbSchool->id,
                    'schools' => [
                        [
                            'id'    => 'school_' . $dbSchool->id,
                            'name'  => $dbSchool->name,
                            'udise' => $dbSchool->udise_code,
                            'dir'   => 'schools/' . $dbSchool->id . '/chrome-profile',
                        ]
                    ]
                ];
            }
        }

        $file = storage_path('app/hpc/schools.json');
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if (is_array($data) && isset($data['schools']) && is_array($data['schools'])) {
                return $data;
            }
        }
        $default = [
            'active' => 'default',
            'schools' => [
                [
                    'id'   => 'default',
                    'name' => 'Default School',
                    'dir'  => 'chrome-profile',
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
            if ($school['id'] === $activeId) {
                return $school;
            }
        }
        return $data['schools'][0] ?? ['id' => 'default', 'name' => 'Default School', 'dir' => 'chrome-profile'];
    }

    public function getActiveProfileDir($school = null): string
    {
        if (! $school && \Illuminate\Support\Facades\Auth::check()) {
            $school = \Illuminate\Support\Facades\Auth::user()->getEffectiveSchool();
        }

        if ($school) {
            $dir = storage_path('app/hpc/schools/' . $school->id . '/chrome-profile');
            // If school is #1 and legacy chrome-profile exists, preserve compatibility
            if (! is_dir($dir) && $school->id === 1 && is_dir(storage_path('app/hpc/chrome-profile'))) {
                return storage_path('app/hpc/chrome-profile');
            }
            return $dir;
        }

        $active = $this->getActiveSchool();
        $dir = $active['dir'] ?? 'chrome-profile';
        return storage_path('app/hpc/' . $dir);
    }

    public function switchSchool(Request $request)
    {
        $schoolId = trim((string) $request->input('id'));
        $data = $this->getSchoolsData();
        $found = false;
        foreach ($data['schools'] as $s) {
            if ($s['id'] === $schoolId) {
                $found = true;
                break;
            }
        }
        if (! $found) {
            return response()->json(['ok' => false, 'error' => 'School profile not found.']);
        }

        // Close running Chrome instances if supported
        if (function_exists('exec')) {
            @exec("pkill -f 'storage/app/hpc' 2>/dev/null");
            @exec("pkill -f 'drivers/chromedriver' 2>/dev/null");
        }

        $data['active'] = $schoolId;
        file_put_contents(storage_path('app/hpc/schools.json'), json_encode($data, JSON_PRETTY_PRINT));

        return response()->json(['ok' => true, 'message' => 'Switched active school successfully.']);
    }

    public function addSchool(Request $request)
    {
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return response()->json(['ok' => false, 'error' => 'Please provide a valid School Name or UDISE.']);
        }

        $data = $this->getSchoolsData();
        $id = 'school_' . time();
        $dir = 'profiles/' . $id;

        $data['schools'][] = [
            'id'   => $id,
            'name' => $name,
            'dir'  => $dir,
        ];
        $data['active'] = $id;

        @mkdir(storage_path('app/hpc/' . $dir), 0777, true);
        file_put_contents(storage_path('app/hpc/schools.json'), json_encode($data, JSON_PRETTY_PRINT));

        // Close running Chrome if supported
        if (function_exists('exec')) {
            @exec("pkill -f 'storage/app/hpc' 2>/dev/null");
            @exec("pkill -f 'drivers/chromedriver' 2>/dev/null");
        }

        return response()->json(['ok' => true, 'id' => $id, 'name' => $name, 'message' => "School '{$name}' added and selected."]);
    }

    public function deleteSchool(Request $request)
    {
        $id = trim((string) $request->input('id'));
        if ($id === 'default') {
            return response()->json(['ok' => false, 'error' => 'Default school cannot be deleted.']);
        }

        $data = $this->getSchoolsData();
        $targetDir = null;
        $newList = [];
        foreach ($data['schools'] as $s) {
            if ($s['id'] === $id) {
                $targetDir = storage_path('app/hpc/' . $s['dir']);
            } else {
                $newList[] = $s;
            }
        }

        if ($targetDir && is_dir($targetDir)) {
            if (function_exists('exec')) {
                @exec("rm -rf " . escapeshellarg($targetDir));
            } else {
                $files = glob($targetDir . '/*');
                foreach ($files as $f) { if (is_file($f)) @unlink($f); }
                @rmdir($targetDir);
            }
        }

        $data['schools'] = $newList;
        if ($data['active'] === $id) {
            $data['active'] = $newList[0]['id'] ?? 'default';
        }

        file_put_contents(storage_path('app/hpc/schools.json'), json_encode($data, JSON_PRETTY_PRINT));

        return response()->json(['ok' => true, 'message' => 'School profile deleted.']);
    }

    // ─── Session Management: End Session & Start Fresh ───────────────────────

    public function endSession()
    {
        $profileDir = $this->getActiveProfileDir();

        // Kill Chrome processes associated with active profile or chromedriver
        if (function_exists('exec')) {
            @exec("pkill -f 'storage/app/hpc' 2>/dev/null");
            @exec("pkill -f 'drivers/chromedriver' 2>/dev/null");
        }

        // Delete active profile directory to wipe cookies/cache for this school
        if (is_dir($profileDir)) {
            if (function_exists('exec')) {
                @exec("rm -rf " . escapeshellarg($profileDir));
            } else {
                $files = glob($profileDir . '/*');
                foreach ($files as $f) { if (is_file($f)) @unlink($f); }
                @rmdir($profileDir);
            }
        }

        return response()->json(['ok' => true, 'message' => 'Session ended for the active school profile. You can log in with new credentials.']);
    }

    public function startSession()
    {
        $loginUrl   = config('hpc.login_url', 'https://hpc.parakh.ncert.gov.in/en/login');
        $profileDir = $this->getActiveProfileDir();
        @mkdir($profileDir, 0777, true);
        @file_put_contents($profileDir . '/session_active.txt', now()->toIso8601String());

        // Open visible Chrome with this school's profile in background if supported
        if (function_exists('exec')) {
            $macChrome = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
            if (file_exists($macChrome)) {
                exec(
                    'nohup ' . escapeshellarg($macChrome)
                    . ' --user-data-dir=' . escapeshellarg($profileDir)
                    . ' ' . escapeshellarg($loginUrl)
                    . ' > /dev/null 2>&1 &'
                );
            }
        }

        $active = $this->getActiveSchool();
        return response()->json([
            'ok'      => true,
            'url'     => $loginUrl,
            'message' => "HPC School session connected for {$active['name']}. Please log into the portal.",
        ]);
    }

    // ─── Dynamic Form Discovery: Fetch fields of any URL ─────────────────────

    public function discoverForm(Request $request)
    {
        $url = trim($request->input('url') ?: config('hpc.add_student_url'));
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return response()->json(['ok' => false, 'error' => 'Please provide a valid form URL (e.g. https://hpc.parakh.ncert.gov.in/en/students/add).']);
        }

        // Save active form URL
        file_put_contents(storage_path('app/hpc/current_form.txt'), $url);

        $artisan = base_path('artisan');
        $php     = PHP_BINARY;
        $cmd     = escapeshellcmd("{$php} {$artisan} hpc:discover --skip-login --url=" . escapeshellarg($url));

        $output   = [];
        $exitCode = 0;
        if (function_exists('exec')) {
            exec($cmd . ' 2>&1', $output, $exitCode);
        }

        $fieldsFile = storage_path('app/hpc/fields.json');
        if (! file_exists($fieldsFile)) {
            $msg = ! function_exists('exec') 
                ? 'CLI command execution is disabled on this server environment. Using default form field mappings.' 
                : 'Discovery did not generate fields. Output: ' . implode("\n", $output);
            return response()->json(['ok' => false, 'error' => $msg]);
        }

        $fieldsData = json_decode(file_get_contents($fieldsFile), true);
        $fieldsList = $fieldsData['fields'] ?? [];

        return response()->json([
            'ok'     => true,
            'url'    => $url,
            'total'  => count($fieldsList),
            'fields' => $fieldsList,
        ]);
    }

    // ─── Sync students already on HPC portal (Step 3 source) ─────────────────

    /**
     * Receives a list of students already present on the PARAKH HPC portal,
     * scraped by the Chrome extension from the portal's student list page.
     * Saves them with source='hpc_portal' and status='success'.
     * Deduplicates against existing Step-2 students so they are automatically
     * marked as submitted and removed from the pending queue.
     *
     * Public endpoint — protected by X-HPC-Token header.
     */
    public function syncFromPortal(Request $request): \Illuminate\Http\JsonResponse
    {
        $corsOrigin = $this->getAllowedCorsOrigin();
        if ($request->isMethod('OPTIONS')) {
            return response()->json(['ok' => true], 200, [
                'Access-Control-Allow-Origin'  => $corsOrigin,
                'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Accept, X-HPC-Token',
            ]);
        }

        $schoolId = $request->integer('school_id', 0);
        $school   = $schoolId ? School::find($schoolId) : \Illuminate\Support\Facades\Auth::user()?->getEffectiveSchool();
        if (! $school && $request->filled('udise_code')) {
            $school = School::where('udise_code', $request->input('udise_code'))->first();
        }
        if (! $school) {
            return response()->json(['ok' => false, 'error' => 'School not found.'], 404, [
                'Access-Control-Allow-Origin'  => $corsOrigin,
                'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Accept, X-HPC-Token',
            ]);
        }

        // Token authentication (same as runProgress / getStudentQueue)
        $token    = $request->header('X-HPC-Token') ?? $request->input('_hpc_token', '');
        $expected = config('hpc.progress_token', '');
        $schoolToken = substr(hash('sha256', $school->id . '_' . $school->udise_code . '_' . config('app.key')), 0, 32);
        if ($expected !== '' && $token !== $expected && $token !== $schoolToken) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 401, [
                'Access-Control-Allow-Origin'  => $corsOrigin,
                'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Accept, X-HPC-Token',
            ]);
        }

        $request->validate([
            'students'  => 'required|array|max:5000',
            'school_id' => 'nullable|integer',
        ]);

        $incoming   = $request->input('students', []);
        $newCount   = 0;
        $matchCount = 0;

        foreach ($incoming as $raw) {
            // Split full name into first / last
            $fullName  = trim($raw['name'] ?? '');
            if (empty($fullName) || strlen($fullName) < 2) continue;
            if (in_array(strtolower($fullName), ['select', 'student', 'action', 'actions', 'name', 's.no', 'sr.no', 'sr no', 's no', 'status', 'view', 'edit', 'delete'])) {
                continue;
            }

            $nameParts = $fullName ? explode(' ', $fullName, 2) : [];
            $firstName = trim($raw['first_name'] ?? ($nameParts[0] ?? ''));
            $lastName  = trim($raw['last_name']  ?? ($nameParts[1] ?? ''));

            $apaar     = preg_replace('/[^0-9]/', '', (string)($raw['apaar_id'] ?? ''));
            $hpcId     = trim((string)($raw['hpc_student_id'] ?? ($raw['id'] ?? '')));
            $penCode   = trim($raw['pen_code'] ?? '');
            $admNo     = trim($raw['admission_no'] ?? '');

            // Deduplication Matcher: Find existing student in this school
            $existing = null;
            if ($hpcId) {
                $existing = Student::where('school_id', $school->id)->where('hpc_student_id', $hpcId)->first();
            }
            if (! $existing && $apaar && strlen($apaar) === 12) {
                $existing = Student::where('school_id', $school->id)->where('apaar_id', $apaar)->first();
            }
            if (! $existing && $admNo) {
                $existing = Student::where('school_id', $school->id)->where('admission_no', $admNo)->first();
            }
            if (! $existing && $fullName) {
                $existing = Student::where('school_id', $school->id)->where('name', $fullName)->first();
            }
            if (! $existing && $firstName) {
                $existing = Student::where('school_id', $school->id)
                    ->where('first_name', $firstName)
                    ->when($lastName, fn($q) => $q->where('last_name', $lastName))
                    ->first();
            }

            if ($existing) {
                $updateData = [
                    'hpc_fetched_at'   => now(),
                    'hpc_submitted_at' => $existing->hpc_submitted_at ?? now(),
                    'status'           => Student::STATUS_SUCCESS,
                ];
                if ($hpcId && empty($existing->hpc_student_id)) {
                    $updateData['hpc_student_id'] = $hpcId;
                }
                if ($apaar && empty($existing->apaar_id)) {
                    $updateData['apaar_id'] = $apaar;
                }
                if ($admNo && empty($existing->admission_no)) {
                    $updateData['admission_no'] = $admNo;
                }
                $existing->update($updateData);
                $matchCount++;
                continue;
            }

            // Build normalised data array for upsert
            $data = [
                'first_name'     => $firstName ?: 'Student',
                'last_name'      => $lastName,
                'name'           => $fullName ?: trim("{$firstName} {$lastName}"),
                'dob'            => trim($raw['dob']    ?? ''),
                'gender'         => trim($raw['gender'] ?? ''),
                'grade'          => trim($raw['grade']  ?? ''),
                'section'        => trim($raw['section'] ?? 'A'),
                'apaar_id'       => $apaar,
                'pen_code'       => $penCode,
                'admission_no'   => $admNo,
                'hpc_student_id' => $hpcId,
                'email'          => trim($raw['email'] ?? ''),
                'errors'         => [],
                'remark'         => 'Fetched from PARAKH HPC Portal',
            ];

            $student = $this->upsertStudentToDatabase($school, $data, Student::SOURCE_HPC);
            if ($student->wasRecentlyCreated) {
                $newCount++;
            } else {
                $matchCount++;
            }
        }

        // Rebuild the school's Step 2 preview so the extension queue is accurate
        $step2 = $school->students()->step2()->orderBy('id')->get();
        $preview = $step2->map(fn ($st, $idx) => $st->toPreviewArray($idx + 1))->values()->all();
        $this->writePreviewFile($this->getPreviewFile($school), $preview);

        $remaining = $step2->count();
        $corsOrigin = $this->getAllowedCorsOrigin();

        return response()->json([
            'ok'              => true,
            'new'             => $newCount,
            'matched'         => $matchCount,
            'total_hpc'       => $newCount + $matchCount,
            'step2_remaining' => $remaining,
            'message'         => "Synced {$newCount} new + {$matchCount} already-known HPC students. {$remaining} students remain in your pending queue.",
        ], 200, [
            'Access-Control-Allow-Origin'  => $corsOrigin,
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Accept, X-HPC-Token',
        ]);
    }

    /**
     * Returns Step 3 students: all records confirmed on the PARAKH HPC portal
     * (fetched from portal list OR successfully submitted by HPC Assist).
     * Called by the dashboard /api and also available to the extension.
     */
    public function getStep3Students(Request $request): \Illuminate\Http\JsonResponse
    {
        $school = \Illuminate\Support\Facades\Auth::user()?->getEffectiveSchool();
        if (! $school) {
            return response()->json(['ok' => false, 'error' => 'School not found.'], 404);
        }

        $perPage = max(1, (int) $request->input('per_page', 50));
        $page    = max(1, (int) $request->input('page', 1));

        $query = $school->students()->step3()->orderByDesc('hpc_submitted_at')->orderByDesc('hpc_fetched_at');

        $total    = $query->count();
        $students = $query->forPage($page, $perPage)
            ->get()
            ->map(fn ($st, $idx) => $st->toPreviewArray(($page - 1) * $perPage + $idx + 1))
            ->values()
            ->all();

        return response()->json([
            'ok'       => true,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'students' => $students,
        ]);
    }
}
