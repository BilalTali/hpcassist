<?php

namespace App\Services;

use App\Models\School;
use App\Models\Student;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class StudentSpreadsheetService
{
    public function __construct(
        protected StudentNormalizationService $normalizer
    ) {}

    /**
     * Build an in-memory PhpSpreadsheet template pre-populated with UDISE students or sample row.
     */
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

        $gradeMap  = $this->normalizer->getGradeMap();
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
                    $grade       = $this->normalizer->normalizeGrade($st['grade'] ?? '');
                    $section     = trim($st['section'] ?? 'A');
                    $apaarId     = trim((string)($st['apaar_id'] ?? ''));
                } else {
                    // Raw UDISE student row — normalise via shared helper
                    $norm        = $this->normalizer->normalizeUdiseStudent($st, $idx, $gradeMap);
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

    /**
     * Parse an uploaded Excel or CSV file into structured student rows.
     */
    public function parseUploadedSpreadsheet(string $fullPath): array
    {
        $errors  = [];
        $preview = [];

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
                elseif (str_contains($k, 'grade') || str_contains($k, 'class') || str_contains($k, 'standard') || str_contains($k, 'std') || str_contains($k, 'कक्षा') || str_contains($k, 'kaksha')) $headerMap['grade'] = $ltr;
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
        $sampleTest = strtolower(trim((string)$ws->getCell("A{$startRow}")->getValue()));
        if (str_contains($sampleTest, 'first name from') || str_contains($sampleTest, 'dd/mm/yyyy')) {
            $startRow++;
        }

        for ($r = $startRow; $r <= $max; $r++) {
            $pen = '';
            if ($headerRow > 0) {
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
                $rawGrade = ! empty($headerMap['grade']) ? trim((string)$ws->getCell($headerMap['grade'] . $r)->getValue()) : '';
                $grade    = $this->normalizer->normalizeGrade($rawGrade);
                $section  = ! empty($headerMap['section']) ? (trim((string)$ws->getCell($headerMap['section'] . $r)->getValue()) ?: 'A') : 'A';
                $apaarId  = ! empty($headerMap['apaar']) ? preg_replace('/[^0-9]/', '', (string)$ws->getCell($headerMap['apaar'] . $r)->getValue()) : '';

            } else {
                // Positional fallback
                $firstName = trim((string) $ws->getCell("A{$r}")->getValue());
                $lastName  = trim((string) $ws->getCell("B{$r}")->getValue());

                $dobCell  = $ws->getCell("C{$r}");
                if (ExcelDate::isDateTime($dobCell)) {
                    $dob = ExcelDate::excelToDateTimeObject($dobCell->getValue())->format('d/m/Y');
                } else {
                    $dob = trim((string) $dobCell->getFormattedValue());
                }

                $valD = trim((string) $ws->getCell("D{$r}")->getValue());
                if (in_array(strtolower($valD), ['male', 'female', 'other', 'm', 'f', 'boy', 'girl'])) {
                    $gender      = $valD;
                    $email       = trim((string) $ws->getCell("E{$r}")->getValue());
                    $admissionNo = trim((string) $ws->getCell("F{$r}")->getValue());
                    $grade       = $this->normalizer->normalizeGrade($ws->getCell("G{$r}")->getValue());
                    $section     = trim((string) $ws->getCell("H{$r}")->getValue());
                    $apaarId     = trim((string) $ws->getCell("I{$r}")->getValue());
                } else {
                    $gender      = '';
                    $email       = $valD;
                    $admissionNo = '';
                    $grade       = $this->normalizer->normalizeGrade($ws->getCell("E{$r}")->getValue());
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

            $validated = $this->normalizer->validateStudentRow([
                'first_name'   => $firstName,
                'last_name'    => $lastName,
                'gender'       => $gender,
                'dob'          => $dob,
                'email'        => $email,
                'admission_no' => $admissionNo,
                'pen_code'     => $pen,
                'grade'        => $grade,
                'section'      => $section,
                'apaar_id'     => $apaarId,
            ], count($preview) + 1);

            $preview[] = $validated;
            if (! empty($validated['errors'])) {
                $errors[] = "Row {$r} ({$firstName}): " . implode(', ', $validated['errors']);
            }
        }

        return ['preview' => $preview, 'errors' => $errors];
    }

    /**
     * Sync in-memory preview array into the school's local students.xlsx spreadsheet.
     */
    public function syncSpreadsheetFromPreview(array $preview, $school = null): void
    {
        try {
            $sheet = $this->buildStudentSpreadsheet($preview);
            $writer = new Xlsx($sheet);
            $dir = $school instanceof School 
                ? storage_path('app/hpc/schools/' . $school->id)
                : storage_path('app/hpc');
            @mkdir($dir, 0755, true);
            $writer->save($dir . '/students.xlsx');
        } catch (\Throwable $e) {
            Log::warning('Could not sync students.xlsx: ' . $e->getMessage());
        }
    }
}
