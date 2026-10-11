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

class HpcController extends Controller
{
    // ─── Dashboard View ───────────────────────────────────────────────────────

    public function dashboard()
    {
        $fieldsFile  = storage_path('app/hpc/fields.json');
        $previewFile = storage_path('app/hpc/preview.json');
        $resultsFile = storage_path('app/hpc/run_results.json');

        $schoolsData  = $this->getSchoolsData();
        $activeSchool = $this->getActiveSchool();
        $profileDir   = $this->getActiveProfileDir();

        $hasFields  = file_exists($fieldsFile);
        $hasProfile = is_dir($profileDir) && count(scandir($profileDir)) > 2;

        $preview = [];
        if (file_exists($previewFile)) {
            $preview = json_decode(file_get_contents($previewFile), true) ?? [];
        }

        $lastRun = null;
        if (file_exists($resultsFile)) {
            $lastRun = json_decode(file_get_contents($resultsFile), true);
        }

        $currentFormUrl = file_exists(storage_path('app/hpc/current_form.txt'))
            ? trim(file_get_contents(storage_path('app/hpc/current_form.txt')))
            : config('hpc.add_student_url');

        return view('hpc.dashboard', compact('hasFields', 'hasProfile', 'preview', 'lastRun', 'currentFormUrl', 'schoolsData', 'activeSchool'));
    }

    // ─── Step 1: Download the blank Excel template ───────────────────────────

    public function downloadTemplate()
    {
        $sheet = new Spreadsheet();
        $ws    = $sheet->getActiveSheet();
        $ws->setTitle('Students');

        // Columns we expose in the template
        $columns = [
            ['key' => 'first_name', 'label' => 'First Name *',   'width' => 20, 'note' => 'As per Aadhar / school records'],
            ['key' => 'last_name',  'label' => 'Last Name',       'width' => 20, 'note' => 'Optional'],
            ['key' => 'dob',        'label' => 'Date of Birth *', 'width' => 18, 'note' => 'DD/MM/YYYY e.g. 15/08/2012'],
            ['key' => 'email',      'label' => 'Email *',         'width' => 28, 'note' => 'Valid email address'],
            ['key' => 'grade',      'label' => 'Grade *',         'width' => 18, 'note' => 'Balvatika 1-3, Grade 1-8'],
            ['key' => 'section',    'label' => 'Section *',       'width' => 14, 'note' => 'A, B, C, D...'],
            ['key' => 'apaar_id',   'label' => 'APAAR ID',        'width' => 20, 'note' => '12 digits (optional)'],
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

        // ── Sample row (Row 3 — gentle example row) ──────────────────────────
        $sample = ['Aarav', 'Sharma', '15/08/2012', 'aarav.sharma@example.com', 'Grade 6', 'A', '123456789012'];
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

        // ── Data rows style (Rows 4–200 blank rows) ──────────────────────────
        $dataStyle = [
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFFFFF']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ];
        $lastCol = chr(64 + count($columns));
        $ws->getStyle("A4:{$lastCol}200")->applyFromArray($dataStyle);

        // ── In-cell Dropdown Data Validation for Grade and Section ────────────
        $gradeFormula   = '"Balvatika 1,Balvatika 2,Balvatika 3,Grade 1,Grade 2,Grade 3,Grade 4,Grade 5,Grade 6,Grade 7,Grade 8"';
        $sectionFormula = '"A,B,C,D,E,F,G,H"';

        for ($r = 3; $r <= 200; $r++) {
            // Grade dropdown (Column E)
            $gv = $ws->getCell("E{$r}")->getDataValidation();
            $gv->setType(DataValidation::TYPE_LIST);
            $gv->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $gv->setAllowBlank(false);
            $gv->setShowDropDown(true);
            $gv->setShowInputMessage(true);
            $gv->setPromptTitle('Grade Selection');
            $gv->setPrompt('Select Grade 1-8 or Balvatika 1-3');
            $gv->setFormula1($gradeFormula);

            // Section dropdown (Column F)
            $sv = $ws->getCell("F{$r}")->getDataValidation();
            $sv->setType(DataValidation::TYPE_LIST);
            $sv->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $sv->setAllowBlank(false);
            $sv->setShowDropDown(true);
            $sv->setFormula1($sectionFormula);
        }

        // Freeze header rows
        $ws->freezePane('A3');

        // ── Instructions sheet ───────────────────────────────────────────────
        $info = $sheet->createSheet();
        $info->setTitle('Instructions');
        $info->setCellValue('A1', '🎓 HPC Student Entry Tool — Excel Guide');
        $info->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $info->setCellValue('A3', 'Column');
        $info->setCellValue('B3', 'Required');
        $info->setCellValue('C3', 'Format & Allowed Options');
        $info->getStyle('A3:C3')->getFont()->setBold(true);
        $info->getStyle('A3:C3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEDE9FE');

        $rows = [
            ['First Name',   'YES', 'Student first name as per records.'],
            ['Last Name',    'No',  'Student last name (optional).'],
            ['Date of Birth','YES', 'Format: DD/MM/YYYY (e.g. 15/08/2012).'],
            ['Email',        'YES', 'Valid email address.'],
            ['Grade',        'YES', 'Choose from dropdown: Balvatika 1, Balvatika 2, Balvatika 3, Grade 1, Grade 2, Grade 3, Grade 4, Grade 5, Grade 6, Grade 7, Grade 8 (Numbers 1-8 also accepted)'],
            ['Section',      'YES', 'Choose from dropdown: A, B, C, D, E, F, G, H'],
            ['APAAR ID',     'No',  '12-digit APAAR ID number (optional).'],
        ];

        foreach ($rows as $ri => $row) {
            $line = $ri + 4;
            $info->setCellValue("A{$line}", $row[0]);
            $info->setCellValue("B{$line}", $row[1]);
            $info->setCellValue("C{$line}", $row[2]);
        }

        $info->getColumnDimension('A')->setWidth(18);
        $info->getColumnDimension('B')->setWidth(12);
        $info->getColumnDimension('C')->setWidth(50);

        // Select first sheet by default
        $sheet->setActiveSheetIndex(0);

        // ── Stream the file ──────────────────────────────────────────────────
        $filename = 'hpc_students_template.xlsx';
        $writer   = new Xlsx($sheet);

        return response()->stream(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
        ]);
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

        $dir = storage_path('app/hpc');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fullPath = $dir . '/students.xlsx';
        $uploadedFile->move($dir, 'students.xlsx');

        $errors  = [];
        $preview = [];

        try {
            $ss  = \PhpOffice\PhpSpreadsheet\IOFactory::load($fullPath);
            $ws  = $ss->getActiveSheet();
            $max = $ws->getHighestDataRow();

            // We start reading from row 3 (skip row 1 header and row 2 note)
            for ($r = 3; $r <= $max; $r++) {
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
                    'row'        => $r,
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
        file_put_contents(storage_path('app/hpc/preview.json'), json_encode($preview, JSON_PRETTY_PRINT));

        return response()->json([
            'ok'      => true,
            'total'   => count($preview),
            'valid'   => count(array_filter($preview, fn($r) => empty($r['errors']))),
            'invalid' => count(array_filter($preview, fn($r) => ! empty($r['errors']))),
            'errors'  => $errors,
            'preview' => $preview,
        ]);
    }

    // ─── Step 3: Start Automation ────────────────────────────────────────────

    public function runStart(Request $request)
    {
        $previewFile = storage_path('app/hpc/preview.json');
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
        $resultsFile = storage_path('app/hpc/run_results.json');
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

        // Launch hpc:run command in the background
        $artisan = base_path('artisan');
        $logFile = storage_path('logs/hpc_run.log');
        $php = PHP_BINARY;

        exec("nohup {$php} {$artisan} hpc:run > {$logFile} 2>&1 &");

        return response()->json([
            'ok'    => true,
            'total' => $total,
        ]);
    }

    // ─── Step 4: Poll run progress & results ──────────────────────────────────

    public function runStatus()
    {
        $file = storage_path('app/hpc/run_results.json');
        if (! file_exists($file)) {
            return response()->json(['started' => false, 'results' => []]);
        }

        $data = json_decode(file_get_contents($file), true) ?? [];
        return response()->json($data);
    }

    // ─── Reset run results ───────────────────────────────────────────────────

    public function runReset()
    {
        $file = storage_path('app/hpc/run_results.json');
        if (file_exists($file)) {
            unlink($file);
        }
        return response()->json(['ok' => true]);
    }

    // ─── School Profile Management ───────────────────────────────────────────

    public function getSchoolsData(): array
    {
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
                    'name' => 'BMS PWC Sugin',
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

    public function getActiveProfileDir(): string
    {
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
        exec("pkill -f 'storage/app/hpc' 2>/dev/null");
        exec("pkill -f 'drivers/chromedriver' 2>/dev/null");

        // Delete active profile directory to wipe cookies/cache for this school
        if (is_dir($profileDir)) {
            exec("rm -rf " . escapeshellarg($profileDir));
        }

        return response()->json(['ok' => true, 'message' => 'Session ended for the active school profile. You can log in with new credentials.']);
    }

    public function startSession()
    {
        $loginUrl   = config('hpc.login_url');
        $profileDir = $this->getActiveProfileDir();
        @mkdir($profileDir, 0777, true);

        // Open visible Chrome with this school's profile in background
        exec("nohup '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' --user-data-dir=" . escapeshellarg($profileDir) . " " . escapeshellarg($loginUrl) . " > /dev/null 2>&1 &");

        $active = $this->getActiveSchool();
        return response()->json(['ok' => true, 'message' => "Chrome opened for {$active['name']}. Please log in to your portal."]);
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
