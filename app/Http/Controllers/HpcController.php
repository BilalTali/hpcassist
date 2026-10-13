<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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

        $udiseStudents   = $this->getUdiseStudents($school);
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
                    'dob'        => $st->dob,
                    'email'      => $st->email,
                    'grade'      => $st->grade,
                    'section'    => $st->section,
                    'apaar_id'   => $st->apaar_id,
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

        // Columns exposed in the template
        $columns = [
            ['key' => 'first_name', 'label' => 'First Name *',   'width' => 22, 'note' => 'First name from UDISE / records'],
            ['key' => 'last_name',  'label' => 'Last Name',       'width' => 22, 'note' => 'Last name / surname (optional)'],
            ['key' => 'dob',        'label' => 'Date of Birth *', 'width' => 18, 'note' => 'DD/MM/YYYY e.g. 15/08/2012'],
            ['key' => 'email',      'label' => 'Email *',         'width' => 34, 'note' => 'Valid email address'],
            ['key' => 'grade',      'label' => 'Grade *',         'width' => 18, 'note' => 'Balvatika 1-3, Grade 1-8'],
            ['key' => 'section',    'label' => 'Section *',       'width' => 14, 'note' => 'Default: A'],
            ['key' => 'apaar_id',   'label' => 'APAAR ID',        'width' => 22, 'note' => '12-digit unmasked APAAR ID'],
        ];

        // ── Header row (Row 1) ────────────────────────────────────────────────
        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 11],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE0E7FF']]],
        ];

        foreach ($columns as $i => $col) {
            $letter = chr(65 + $i);
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
            $letter = chr(65 + $i);
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
                    $firstName = trim($st['first_name']);
                    $lastName  = trim($st['last_name'] ?? '');
                    $dob       = trim($st['dob'] ?? '');
                    $email     = trim($st['email'] ?? '');
                    $grade     = trim($st['grade'] ?? 'Grade 6');
                    $section   = trim($st['section'] ?? 'A');
                    $apaarId   = trim((string)($st['apaar_id'] ?? ''));
                } else {
                    // 1. First Name + Last Name from studentName
                    $rawName   = trim($st['studentName'] ?? '');
                    $parts     = preg_split('/\s+/', $rawName);
                    $firstName = $parts[0] ?? 'Student';
                    $lastName  = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';

                    // 2. Date of Birth (ensure DD/MM/YYYY)
                    $dob = trim($st['dob'] ?? '');
                    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dob, $m)) {
                        $dob = "{$m[3]}/{$m[2]}/{$m[1]}";
                    }

                    // 3. Email (use valid student email with @gmail.com, not gov.in)
                    $email = ! empty($st['email']) && filter_var($st['email'], FILTER_VALIDATE_EMAIL) ? trim($st['email']) : '';
                    if (empty($email) || str_contains(strtolower($email), 'gov.in')) {
                        $cleanFirst = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $firstName)) ?: 'student';
                        $cleanPen   = preg_replace('/[^0-9]/', '', $st['studentCodeNat'] ?? ($idx + 1));
                        $email      = "{$cleanFirst}.{$cleanPen}@gmail.com";
                    }

                    // 4. Grade mapped from classDesc
                    $rawClass = trim($st['classDesc'] ?? '');
                    $grade    = $gradeMap[$rawClass] ?? ($gradeMap[preg_replace('/[^0-9IVX]/i', '', $rawClass)] ?? 'Grade 6');

                    // 5. Section (default A)
                    $section  = ! empty($st['sectionDesc']) ? trim($st['sectionDesc']) : 'A';

                    // 6. APAAR ID (unmasked 12-digit)
                    $apaarId  = ! empty($st['apaarId']) ? (string) $st['apaarId'] : '';
                }

                $rowVals  = [$firstName, $lastName, $dob, $email, $grade, $section, $apaarId];
                $isApaar  = ! empty($apaarId);
                $rowBg    = ($idx % 2 === 0) ? 'FFFFFFFF' : 'FFF8FAFC';

                foreach ($rowVals as $cIdx => $val) {
                    $colLetter = chr(65 + $cIdx);
                    $ws->setCellValueExplicit("{$colLetter}{$r}", (string) $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                    $cellBg = ($cIdx === 6 && $isApaar) ? 'FFCCFBF1' : $rowBg; // Soft teal highlight for APAAR ID
                    $ws->getStyle("{$colLetter}{$r}")->applyFromArray([
                        'font'      => [
                            'size'  => 9.5,
                            'bold'  => ($cIdx === 6 && $isApaar),
                            'color' => ['argb' => ($cIdx === 6 && $isApaar) ? 'FF0F766E' : 'FF0F172A'],
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
            $sample = ['Aarav', 'Sharma', '15/08/2012', 'aarav.sharma@gmail.com', 'Grade 6', 'A', '123456789012'];
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
            $ws->getStyle("A4:G200")->applyFromArray($dataStyle);
        }

        // In-cell Dropdown Data Validation for Grade and Section
        $gradeFormula   = '"Balvatika 1,Balvatika 2,Balvatika 3,Grade 1,Grade 2,Grade 3,Grade 4,Grade 5,Grade 6,Grade 7,Grade 8"';
        $sectionFormula = '"A,B,C,D,E,F,G,H"';

        for ($r = 3; $r <= $totalRows; $r++) {
            $gv = $ws->getCell("E{$r}")->getDataValidation();
            $gv->setType(DataValidation::TYPE_LIST);
            $gv->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $gv->setAllowBlank(false);
            $gv->setShowDropDown(true);
            $gv->setShowInputMessage(true);
            $gv->setPromptTitle('Grade Selection');
            $gv->setPrompt('Select Grade 1-8 or Balvatika 1-3');
            $gv->setFormula1($gradeFormula);

            $sv = $ws->getCell("F{$r}")->getDataValidation();
            $sv->setType(DataValidation::TYPE_LIST);
            $sv->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $sv->setAllowBlank(false);
            $sv->setShowDropDown(true);
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
            ['First Name *',   'studentName (Word 1)', 'Student first name as per official UDISE / school records.'],
            ['Last Name',      'studentName (Rest)',   'Student last name (optional or remaining parts of full name).'],
            ['Date of Birth *','dob',                  'Format: DD/MM/YYYY (e.g. 15/08/2012).'],
            ['Email *',        'email / auto-gen',     'Official student email (auto-generated using PEN if blank).'],
            ['Grade *',        'classDesc',            'Mapped from UDISE class (PP-1 -> Balvatika 1, I-VIII -> Grade 1-8).'],
            ['Section *',      'sectionDesc',          'Defaulted to Section A.'],
            ['APAAR ID',       'apaarId',              'Unmasked 12-digit APAAR ID from UDISE SDMS.'],
        ];

        foreach ($rows as $ri => $row) {
            $line = $ri + 4;
            $info->setCellValue("A{$line}", $row[0]);
            $info->setCellValue("B{$line}", $row[1]);
            $info->setCellValue("C{$line}", $row[2]);
        }

        $info->getColumnDimension('A')->setWidth(20);
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

        // 1. Generate and save students.xlsx in school storage
        $spreadsheet = $this->buildStudentSpreadsheet($udiseStudents);
        $dir = $this->getSchoolStorageDir($school);
        @mkdir($dir, 0755, true);
        $fullPath = $dir . '/students.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($fullPath);

        // 2. Generate preview.json for Step 2
        $preview   = [];
        $gradeMap  = $this->getGradeMap();

        foreach ($udiseStudents as $idx => $st) {
            $r = $idx + 3;

            $rawName   = trim($st['studentName'] ?? '');
            $parts     = preg_split('/\s+/', $rawName);
            $firstName = $parts[0] ?? 'Student';
            $lastName  = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';

            $dob = trim($st['dob'] ?? '');
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dob, $m)) {
                $dob = "{$m[3]}/{$m[2]}/{$m[1]}";
            }

            $email = ! empty($st['email']) && filter_var($st['email'], FILTER_VALIDATE_EMAIL) ? trim($st['email']) : '';
            if (empty($email) || str_contains(strtolower($email), 'gov.in')) {
                $cleanFirst = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $firstName)) ?: 'student';
                $cleanPen   = preg_replace('/[^0-9]/', '', $st['studentCodeNat'] ?? ($idx + 1));
                $email      = "{$cleanFirst}.{$cleanPen}@gmail.com";
            }

            $rawClass = trim($st['classDesc'] ?? '');
            $grade    = $gradeMap[$rawClass] ?? ($gradeMap[preg_replace('/[^0-9IVX]/i', '', $rawClass)] ?? 'Grade 6');
            $section  = ! empty($st['sectionDesc']) ? trim($st['sectionDesc']) : 'A';
            $apaarId  = ! empty($st['apaarId']) ? (string) $st['apaarId'] : '';

            $preview[] = [
                'row'        => $r,
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'name'       => trim("{$firstName} {$lastName}"),
                'dob'        => $dob,
                'email'      => $email,
                'grade'      => $grade,
                'section'    => $section,
                'apaar_id'   => $apaarId,
                'errors'     => [],
            ];
        }

        file_put_contents($this->getPreviewFile($school), json_encode($preview, JSON_PRETTY_PRINT));

        if ($school) {
            foreach ($preview as $pRow) {
                $this->upsertStudentToDatabase($school, $pRow, 'udise_sync');
            }
        }

        return response()->json([
            'ok'      => true,
            'message' => 'Successfully loaded ' . count($preview) . ' students from UDISE with unmasked APAAR IDs into Step 2!',
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
                @mkdir($dir, 0755, true);
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
            'file' => 'required|file|max:10240',
        ]);

        $uploadedFile = $request->file('file');
        $ext = strtolower($uploadedFile->getClientOriginalExtension());
        if (! in_array($ext, ['xlsx', 'xls', 'csv'])) {
            return response()->json(['ok' => false, 'error' => 'Please upload a valid Excel (.xlsx, .xls) file.']);
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

            // Detect if uploaded file is a UDISE+ export spreadsheet
            $udiseCols = [];
            $headerRow = 0;
            for ($testRow = 1; $testRow <= min(6, $max); $testRow++) {
                $cells = [];
                for ($col = 1; $col <= 26; $col++) {
                    $ltr = Coordinate::stringFromColumnIndex($col);
                    $val = strtolower(trim((string) $ws->getCell("{$ltr}{$testRow}")->getValue()));
                    if ($val !== '') $cells[$val] = $ltr;
                }
                $hasStudentName = false;
                $hasPenOrApaar = false;
                foreach ($cells as $k => $ltr) {
                    if (str_contains($k, 'student') && str_contains($k, 'name')) $hasStudentName = true;
                    if (str_contains($k, 'pen') || str_contains($k, 'apaar') || str_contains($k, 'permanent education')) $hasPenOrApaar = true;
                }
                if ($hasStudentName && $hasPenOrApaar) {
                    $headerRow = $testRow;
                    foreach ($cells as $k => $ltr) {
                        if (str_contains($k, 'student') && str_contains($k, 'name')) $udiseCols['name'] = $ltr;
                        elseif (str_contains($k, 'apaar')) $udiseCols['apaar'] = $ltr;
                        elseif (str_contains($k, 'pen') || str_contains($k, 'permanent education')) $udiseCols['pen'] = $ltr;
                        elseif (str_contains($k, 'class') || str_contains($k, 'grade')) $udiseCols['grade'] = $ltr;
                        elseif (str_contains($k, 'section')) $udiseCols['section'] = $ltr;
                        elseif (str_contains($k, 'birth') || $k === 'dob') $udiseCols['dob'] = $ltr;
                        elseif (str_contains($k, 'email')) $udiseCols['email'] = $ltr;
                    }
                    break;
                }
            }

            $isUdise = ($headerRow > 0 && isset($udiseCols['name']));
            $startRow = $isUdise ? ($headerRow + 1) : 3;
            $gradeMap = $this->getGradeMap();

            for ($r = $startRow; $r <= $max; $r++) {
                if ($isUdise) {
                    $fullName = trim((string) $ws->getCell(($udiseCols['name'] ?? 'A') . $r)->getValue());
                    if ($fullName === '') continue;
                    $parts = preg_split('/\s+/', $fullName);
                    $firstName = $parts[0] ?? 'Student';
                    $lastName = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';

                    $dobCell = isset($udiseCols['dob']) ? $ws->getCell($udiseCols['dob'] . $r) : null;
                    if ($dobCell && ExcelDate::isDateTime($dobCell)) {
                        $dob = ExcelDate::excelToDateTimeObject($dobCell->getValue())->format('d/m/Y');
                    } else {
                        $dob = $dobCell ? trim((string) $dobCell->getFormattedValue()) : '';
                    }

                    $pen = isset($udiseCols['pen']) ? preg_replace('/[^0-9]/', '', (string)$ws->getCell($udiseCols['pen'] . $r)->getValue()) : (string)$r;
                    $rawEmail = isset($udiseCols['email']) ? trim((string)$ws->getCell($udiseCols['email'] . $r)->getValue()) : '';
                    if (empty($rawEmail) || str_contains(strtolower($rawEmail), 'gov.in') || !filter_var($rawEmail, FILTER_VALIDATE_EMAIL)) {
                        $cleanFirst = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $firstName)) ?: 'student';
                        $email = "{$cleanFirst}.{$pen}@gmail.com";
                    } else {
                        $email = $rawEmail;
                    }

                    $rawGrade = isset($udiseCols['grade']) ? trim((string)$ws->getCell($udiseCols['grade'] . $r)->getValue()) : 'Grade 6';
                    $grade = $gradeMap[$rawGrade] ?? ($gradeMap[preg_replace('/[^0-9IVX]/i', '', $rawGrade)] ?? 'Grade 6');
                    $section = isset($udiseCols['section']) ? (trim((string)$ws->getCell($udiseCols['section'] . $r)->getValue()) ?: 'A') : 'A';
                    $apaarId = isset($udiseCols['apaar']) ? preg_replace('/[^0-9]/', '', (string)$ws->getCell($udiseCols['apaar'] . $r)->getValue()) : '';
                } else {
                    $firstName = trim((string) $ws->getCell("A{$r}")->getValue());
                    $lastName  = trim((string) $ws->getCell("B{$r}")->getValue());

                    // Read DOB
                    $dobCell  = $ws->getCell("C{$r}");
                    $dobValue = $dobCell->getValue();
                    if (ExcelDate::isDateTime($dobCell)) {
                        $dob = ExcelDate::excelToDateTimeObject($dobValue)->format('d/m/Y');
                    } else {
                        $dob = trim((string) $dobCell->getFormattedValue());
                    }

                    $email   = trim((string) $ws->getCell("D{$r}")->getValue());
                    $grade   = trim((string) $ws->getCell("E{$r}")->getValue());
                    $section = trim((string) $ws->getCell("F{$r}")->getValue());
                    $apaarId = trim((string) $ws->getCell("G{$r}")->getValue());

                    // Skip sample row if untouched, or skip completely empty row
                    if ($firstName === '' && $email === '' && $grade === '') {
                        continue;
                    }
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
                    'row'        => count($preview) + 1,
                    'first_name' => $firstName,
                    'last_name'  => $lastName,
                    'name'       => trim("{$firstName} {$lastName}"),
                    'dob'        => $dob,
                    'email'      => $email,
                    'grade'      => $grade,
                    'section'    => $section,
                    'apaar_id'   => $apaarId,
                    'errors'     => $rowErrors,
                ];

                if (! empty($rowErrors)) {
                    $errors[] = "Row {$r} ({$firstName}): " . implode(', ', $rowErrors);
                }
            }
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'Could not read Excel file: ' . $e->getMessage()]);
        }

        // Save preview for the automation runner
        file_put_contents($this->getPreviewFile(), json_encode($preview, JSON_PRETTY_PRINT));
        $this->syncSpreadsheetFromPreview($preview);

        if (\Illuminate\Support\Facades\Auth::check()) {
            $userSchool = \Illuminate\Support\Facades\Auth::user()->getEffectiveSchool();
            if ($userSchool) {
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
        $firstName = trim((string)($data['first_name'] ?? ''));
        $lastName  = trim((string)($data['last_name'] ?? ''));
        $dob       = trim((string)($data['dob'] ?? ''));
        $email     = trim((string)($data['email'] ?? ''));
        $grade     = trim((string)($data['grade'] ?? ''));
        $section   = trim((string)($data['section'] ?? ''));
        $apaarId   = trim((string)($data['apaar_id'] ?? ''));

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
            'row'        => $rowNum,
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'name'       => trim("{$firstName} {$lastName}"),
            'dob'        => $dob,
            'email'      => $email,
            'grade'      => $grade,
            'section'    => $section,
            'apaar_id'   => $apaarId,
            'errors'     => $rowErrors,
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
        $previewFile = $this->getPreviewFile();
        $preview = file_exists($previewFile) ? (json_decode(file_get_contents($previewFile), true) ?? []) : [];

        $index = $request->input('index');
        $isEdit = ($index !== null && $index !== '' && (int)$index >= 0 && isset($preview[(int)$index]));
        $targetRow = $isEdit ? ((int)$index + 1) : (count($preview) + 1);

        $validated = $this->validateStudentRow($request->all(), $targetRow);

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

        file_put_contents($previewFile, json_encode($preview, JSON_PRETTY_PRINT));
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

        file_put_contents($previewFile, json_encode($preview, JSON_PRETTY_PRINT));
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

    public function updateAllStudents(Request $request)
    {
        $items = $request->input('students', []);
        $preview = [];
        foreach ($items as $i => $item) {
            $preview[] = $this->validateStudentRow($item, $i + 1);
        }

        $previewFile = $this->getPreviewFile();
        file_put_contents($previewFile, json_encode($preview, JSON_PRETTY_PRINT));
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
        $pen   = ! empty($data['pen_code']) ? trim((string) $data['pen_code']) : (! empty($data['studentCodeNat']) ? trim((string) $data['studentCodeNat']) : null);
        $firstName = trim($data['first_name'] ?? '');
        $lastName  = trim($data['last_name'] ?? '');
        $name      = trim($data['name'] ?? trim("{$firstName} {$lastName}"));
        $dob       = trim($data['dob'] ?? '');
        $grade     = trim($data['grade'] ?? 'Grade 6');
        $section   = trim($data['section'] ?? 'A');
        $email     = trim($data['email'] ?? '');

        $student = null;

        // 1. Match by 12-digit APAAR ID if present
        if ($apaar && strlen($apaar) === 12) {
            $student = Student::where('school_id', $school->id)->where('apaar_id', $apaar)->first();
        }

        // 2. Match by PEN code if APAAR did not match
        if (! $student && ! empty($pen)) {
            $student = Student::where('school_id', $school->id)->where('pen_code', $pen)->first();
        }

        // 3. Match by Name + DOB + Grade
        if (! $student && ! empty($name) && ! empty($dob)) {
            $student = Student::where('school_id', $school->id)
                ->where('name', $name)
                ->where('dob', $dob)
                ->where('grade', $grade)
                ->first();
        }

        $errors = $data['errors'] ?? [];
        $status = ! empty($errors) ? 'failed' : 'success';
        $defaultRemark = $source === 'udise_sync' ? 'Synced from UDISE+ SDMS' : ($source === 'excel_upload' ? 'Imported from Excel spreadsheet' : 'Saved via School Portal');
        $remark = ! empty($errors) ? null : ($data['remark'] ?? $defaultRemark);
        $failureReason = ! empty($errors) ? implode(', ', (array) $errors) : null;

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
            'udise_student_id' => $data['udise_student_id'] ?? ($data['studentId'] ?? null),
            'gender'           => $data['gender'] ?? ($data['genderDesc'] ?? null),
            'source'           => $source,
            'status'           => $status,
            'remark'           => $remark,
            'failure_reason'   => $failureReason,
        ];

        if ($student) {
            $student->update($payload);
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


    // ─── Step 3: Start Automation ────────────────────────────────────────────

    public function runStart(Request $request)
    {
        $previewFile = $this->getPreviewFile();
        if (! file_exists($previewFile)) {
            return response()->json(['ok' => false, 'error' => 'No student data found. Please upload an Excel file first.']);
        }

        $allStudents   = json_decode(file_get_contents($previewFile), true) ?? [];
        $validStudents = array_values(array_filter($allStudents, fn($s) => empty($s['errors'])));

        if (empty($validStudents)) {
            return response()->json(['ok' => false, 'error' => 'All rows have validation errors. Please fix and re-upload.']);
        }

        $total = count($validStudents);

        // Initialize run_results.json
        $resultsFile = $this->getSchoolStorageDir() . '/run_results.json';
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

        // Launch hpc:run command in the background scoped to current school
        $artisan     = base_path('artisan');
        $school      = \Illuminate\Support\Facades\Auth::user()?->getEffectiveSchool();
        $schoolArg   = $school ? "--school={$school->id}" : "";
        $logFile     = storage_path('logs/hpc_run.log');
        $php         = PHP_BINARY;

        exec("nohup {$php} {$artisan} hpc:run {$schoolArg} > {$logFile} 2>&1 &");

        return response()->json([
            'ok'    => true,
            'total' => $total,
        ]);
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

        // Close running Chrome instances so user doesn't conflict
        exec("pkill -f 'storage/app/hpc' 2>/dev/null");
        exec("pkill -f 'drivers/chromedriver' 2>/dev/null");

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

        // Close running Chrome
        exec("pkill -f 'storage/app/hpc' 2>/dev/null");
        exec("pkill -f 'drivers/chromedriver' 2>/dev/null");

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
            exec("rm -rf " . escapeshellarg($targetDir));
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
                @exec("nohup '{$macChrome}' --user-data-dir=" . escapeshellarg($profileDir) . " " . escapeshellarg($loginUrl) . " > /dev/null 2>&1 &");
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

        exec($cmd . ' 2>&1', $output, $exitCode);

        $fieldsFile = storage_path('app/hpc/fields.json');
        if (! file_exists($fieldsFile)) {
            return response()->json(['ok' => false, 'error' => 'Discovery did not generate fields. Output: ' . implode("\n", $output)]);
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
}
