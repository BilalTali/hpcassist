<?php

namespace App\Services;

use App\Models\Student;

class StudentNormalizationService
{
    /**
     * Get dictionary of grade/class aliases mapping to standard labels.
     */
    public function getGradeMap(): array
    {
        return [
            // Balvatika / Pre-Primary
            'Balvatika 1'   => 'Balvatika 1',
            'Balvatika 2'   => 'Balvatika 2',
            'Balvatika 3'   => 'Balvatika 3',
            'Balvatika-1'   => 'Balvatika 1',
            'Balvatika-2'   => 'Balvatika 2',
            'Balvatika-3'   => 'Balvatika 3',
            'Balvatika'     => 'Balvatika 1',
            'PP-1'          => 'Balvatika 1',
            'PP-2'          => 'Balvatika 2',
            'PP-3'          => 'Balvatika 3',
            'PP1'           => 'Balvatika 1',
            'PP2'           => 'Balvatika 2',
            'PP3'           => 'Balvatika 3',
            'PP 1'          => 'Balvatika 1',
            'PP 2'          => 'Balvatika 2',
            'PP 3'          => 'Balvatika 3',
            '-1'            => 'Balvatika 1',
            '-2'            => 'Balvatika 2',
            '-3'            => 'Balvatika 3',
            'Nursery'       => 'Balvatika 1',
            'LKG'           => 'Balvatika 2',
            'UKG'           => 'Balvatika 3',
            'Pre-Primary 1' => 'Balvatika 1',
            'Pre-Primary 2' => 'Balvatika 2',
            'Pre-Primary 3' => 'Balvatika 3',
            'Pre-Primary'   => 'Balvatika 1',
            'Pre Primary 1' => 'Balvatika 1',
            'Pre Primary 2' => 'Balvatika 2',
            'Pre Primary 3' => 'Balvatika 3',

            // Roman Numerals
            'I'             => 'Grade 1',
            'II'            => 'Grade 2',
            'III'           => 'Grade 3',
            'IV'            => 'Grade 4',
            'V'             => 'Grade 5',
            'VI'            => 'Grade 6',
            'VII'           => 'Grade 7',
            'VIII'          => 'Grade 8',
            'IX'            => 'Grade 9',
            'X'             => 'Grade 10',
            'XI'            => 'Grade 11',
            'XII'           => 'Grade 12',

            // Arabic Digits
            '1'             => 'Grade 1',
            '2'             => 'Grade 2',
            '3'             => 'Grade 3',
            '4'             => 'Grade 4',
            '5'             => 'Grade 5',
            '6'             => 'Grade 6',
            '7'             => 'Grade 7',
            '8'             => 'Grade 8',
            '9'             => 'Grade 9',
            '10'            => 'Grade 10',
            '11'            => 'Grade 11',
            '12'            => 'Grade 12',

            // Ordinal format
            '1st'           => 'Grade 1',
            '2nd'           => 'Grade 2',
            '3rd'           => 'Grade 3',
            '4th'           => 'Grade 4',
            '5th'           => 'Grade 5',
            '6th'           => 'Grade 6',
            '7th'           => 'Grade 7',
            '8th'           => 'Grade 8',
            '9th'           => 'Grade 9',
            '10th'          => 'Grade 10',
            '11th'          => 'Grade 11',
            '12th'          => 'Grade 12',

            // Standard Grade string
            'Grade 1'       => 'Grade 1',
            'Grade 2'       => 'Grade 2',
            'Grade 3'       => 'Grade 3',
            'Grade 4'       => 'Grade 4',
            'Grade 5'       => 'Grade 5',
            'Grade 6'       => 'Grade 6',
            'Grade 7'       => 'Grade 7',
            'Grade 8'       => 'Grade 8',
            'Grade 9'       => 'Grade 9',
            'Grade 10'      => 'Grade 10',
            'Grade 11'      => 'Grade 11',
            'Grade 12'      => 'Grade 12',

            // Class string
            'Class 1'       => 'Grade 1',
            'Class 2'       => 'Grade 2',
            'Class 3'       => 'Grade 3',
            'Class 4'       => 'Grade 4',
            'Class 5'       => 'Grade 5',
            'Class 6'       => 'Grade 6',
            'Class 7'       => 'Grade 7',
            'Class 8'       => 'Grade 8',
            'Class 9'       => 'Grade 9',
            'Class 10'      => 'Grade 10',
            'Class 11'      => 'Grade 11',
            'Class 12'      => 'Grade 12',
        ];
    }

    /**
     * Normalise any class/grade string from UDISE+, Excel uploads, or HPC portal into
     * standard format: "Balvatika 1-3" or "Grade 1-12".
     */
    public function normalizeGrade($rawGrade): string
    {
        if ($rawGrade === null || $rawGrade === '') {
            return 'Grade 1';
        }

        $raw = trim((string) $rawGrade);
        if ($raw === '') {
            return 'Grade 1';
        }

        $lower = strtolower($raw);

        // Pre-primary / Balvatika keywords
        if (
            str_contains($lower, 'balvatika') ||
            str_contains($lower, 'pre-primary') ||
            str_contains($lower, 'pre primary') ||
            str_contains($lower, 'pre-school') ||
            str_contains($lower, 'pre school') ||
            str_contains($lower, 'bal vatika') ||
            str_contains($lower, 'balwadi') ||
            str_contains($lower, 'anganwadi') ||
            preg_match('/\bpp[\s\-_]?[1-3]?\b/i', $lower)
        ) {
            if (str_contains($lower, '3') || str_contains($lower, 'iii') || str_contains($lower, 'ukg') || str_contains($lower, 'sr')) {
                return 'Balvatika 3';
            }
            if (str_contains($lower, '2') || str_contains($lower, 'ii') || str_contains($lower, 'lkg') || str_contains($lower, 'jr')) {
                return 'Balvatika 2';
            }
            return 'Balvatika 1';
        }

        // Direct standard preschool aliases
        if (in_array($lower, ['nursery', 'nur', 'pp1', 'pp-1', '-1', 'pre-1', 'pre 1'], true)) {
            return 'Balvatika 1';
        }
        if (in_array($lower, ['lkg', 'kg-1', 'kg1', 'jr kg', 'junior kg', 'pp2', 'pp-2', '-2', 'pre-2', 'pre 2'], true)) {
            return 'Balvatika 2';
        }
        if (in_array($lower, ['ukg', 'kg-2', 'kg2', 'sr kg', 'senior kg', 'pp3', 'pp-3', '-3', 'pre-3', 'pre 3'], true)) {
            return 'Balvatika 3';
        }

        // Check standard grade map directly (case-sensitive and case-insensitive)
        $gradeMap = $this->getGradeMap();
        if (isset($gradeMap[$raw])) {
            return $gradeMap[$raw];
        }
        foreach ($gradeMap as $k => $v) {
            if (strcasecmp($k, $raw) === 0) {
                return $v;
            }
        }

        // Check patterns like "Grade 6", "Class 6", "6th", "6"
        if (preg_match('/\b(1[0-2]|[1-9])(?:st|nd|rd|th)?\b/i', $raw, $m)) {
            return 'Grade ' . (int)$m[1];
        }

        // Check standalone Roman numerals
        $romanMap = [
            'xii'  => 'Grade 12',
            'xi'   => 'Grade 11',
            'x'    => 'Grade 10',
            'ix'   => 'Grade 9',
            'viii' => 'Grade 8',
            'vii'  => 'Grade 7',
            'vi'   => 'Grade 6',
            'v'    => 'Grade 5',
            'iv'   => 'Grade 4',
            'iii'  => 'Grade 3',
            'ii'   => 'Grade 2',
            'i'    => 'Grade 1',
        ];

        $cleanLetters = strtolower(trim(preg_replace('/[^a-z]/i', '', $raw)));
        if (isset($romanMap[$cleanLetters])) {
            return $romanMap[$cleanLetters];
        }

        // Digits anywhere (e.g. "C-1", "Sec-5", etc.)
        $digits = preg_replace('/[^0-9]/', '', $raw);
        if ($digits !== '' && (int)$digits >= 1 && (int)$digits <= 12) {
            return 'Grade ' . (int)$digits;
        }

        return $raw ?: 'Grade 1';
    }

    /**
     * Normalise a raw UDISE+ student API record into structured array.
     */
    public function normalizeUdiseStudent(array $st, int $idx, ?array $gradeMap = null): array
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

        $rawClass = trim($st['classDesc'] ?? ($st['class_desc'] ?? ($st['class'] ?? ($st['grade'] ?? ''))));
        $grade    = $this->normalizeGrade($rawClass);
        $section  = ! empty($st['sectionDesc']) ? trim($st['sectionDesc']) : (! empty($st['section']) ? trim($st['section']) : 'A');
        $apaarId  = ! empty($st['apaarId']) ? (string) $st['apaarId'] : '';

        return compact('firstName', 'lastName', 'dob', 'gender', 'email', 'cleanPen', 'admissionNo', 'grade', 'section', 'apaarId');
    }

    /**
     * Validate an individual student row and return validation errors if any.
     */
    public function validateStudentRow(array $data, int $rowNum = 1): array
    {
        $firstName   = trim((string)($data['first_name'] ?? ''));
        $lastName    = trim((string)($data['last_name'] ?? ''));
        $gender      = trim((string)($data['gender'] ?? ''));
        $dob         = trim((string)($data['dob'] ?? ''));
        $email       = trim((string)($data['email'] ?? ''));
        $admissionNo = trim((string)($data['admission_no'] ?? ($data['admnNumber'] ?? '')));
        $penCode     = trim((string)($data['pen_code'] ?? ($data['studentCodeNat'] ?? '')));
        $grade       = $this->normalizeGrade($data['grade'] ?? '');
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
}
