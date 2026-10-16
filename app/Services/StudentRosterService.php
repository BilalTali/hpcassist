<?php

namespace App\Services;

use App\Models\School;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class StudentRosterService
{
    public function __construct(
        protected StudentNormalizationService $normalizer,
        protected StudentSpreadsheetService $spreadsheetService
    ) {}

    public function getSchoolStorageDir($school = null): string
    {
        if ($school instanceof School) {
            $dir = storage_path('app/hpc/schools/' . $school->id);
            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            return $dir;
        }

        $default = storage_path('app/hpc');
        if (! is_dir($default)) {
            @mkdir($default, 0755, true);
        }
        return $default;
    }

    public function getPreviewFile($school = null): string
    {
        return $this->getSchoolStorageDir($school) . '/preview.json';
    }

    public function writePreviewFile(string $path, array $data): void
    {
        $dir = dirname($path);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            Log::warning("[HPC] writePreviewFile: could not create dir {$dir}");
        }
        if (file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
            Log::warning("[HPC] writePreviewFile: could not write to {$path}");
        }
    }

    public function getUdiseStudents($school = null): array
    {
        if (! $school && Auth::check()) {
            $school = Auth::user()->getEffectiveSchool();
        }

        if ($school) {
            $schoolDir = $this->getSchoolStorageDir($school);
            $schoolRaw = $schoolDir . '/udise_students_raw.json';
            if (file_exists($schoolRaw)) {
                return json_decode(file_get_contents($schoolRaw), true) ?: [];
            }
        }

        return [];
    }

    /**
     * Upsert a student record in the MySQL database, scoped strictly to the school and UDISE code.
     */
    public function upsertStudentToDatabase(School $school, array $data, string $source = 'udise_sync'): Student
    {
        $apaar = ! empty($data['apaar_id']) ? preg_replace('/[^0-9]/', '', (string) $data['apaar_id']) : null;
        
        // National PEN is 11 digits in UDISE+ (studentCodeNat)
        $pen = ! empty($data['studentCodeNat']) ? trim((string) $data['studentCodeNat']) : (! empty($data['pen_code']) ? trim((string) $data['pen_code']) : null);
        
        // School Admission Number is local school number (admnNumber)
        $admissionNo = ! empty($data['admission_no']) ? trim((string) $data['admission_no']) : (! empty($data['admnNumber']) ? trim((string) $data['admnNumber']) : null);
        
        // Guard: School admission number must NEVER be an HPC Login ID (e.g. S3F44CB1E4), PEN, or APAAR
        if (! empty($admissionNo) && (preg_match('/^S[0-9A-Z]{6,16}$/i', $admissionNo) || $admissionNo === $pen || $admissionNo === $apaar)) {
            $admissionNo = null;
        }

        // Clean short dummy values that were accidentally placed in pen_code
        if (! empty($pen) && strlen($pen) < 8 && empty($admissionNo)) {
            $admissionNo = $pen;
            $pen = null;
        }

        $firstName = trim($data['first_name'] ?? '');
        $lastName  = trim($data['last_name'] ?? '');
        $name      = trim($data['name'] ?? trim("{$firstName} {$lastName}"));
        $dob       = trim($data['dob'] ?? '');
        $grade     = $this->normalizer->normalizeGrade($data['grade'] ?? '');
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
                    if ($apaar && ! empty($cand->apaar_id) && $cand->apaar_id !== $apaar) continue;
                    if (! empty($pen) && ! empty($cand->pen_code) && $cand->pen_code !== $pen) continue;
                    if (! empty($admissionNo) && ! empty($cand->admission_no) && $cand->admission_no !== $admissionNo) continue;
                    if (! empty($gender) && ! empty($cand->gender) && strtolower($cand->gender) !== strtolower($gender)) continue;
                    if (! empty($grade) && ! empty($cand->grade) && strtolower($cand->grade) !== strtolower($grade)) continue;

                    $student = $cand;
                    break;
                }
            }
        }

        $errors = $data['errors'] ?? [];

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
            'udise_code'       => $school->udise_code,
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

        if ($source === Student::SOURCE_HPC) {
            $payload['hpc_fetched_at'] = now();
            $payload['hpc_submitted_at'] = $payload['hpc_submitted_at'] ?? now();
        }

        if ($student) {
            if (empty($payload['apaar_id']) && ! empty($student->apaar_id)) {
                $payload['apaar_id'] = $student->apaar_id;
            }
            if (empty($payload['pen_code']) && ! empty($student->pen_code)) {
                $payload['pen_code'] = $student->pen_code;
            }
            if (empty($payload['admission_no']) && ! empty($student->admission_no)) {
                $payload['admission_no'] = $student->admission_no;
            }
            if (empty($payload['udise_student_id']) && ! empty($student->udise_student_id)) {
                $payload['udise_student_id'] = $student->udise_student_id;
            }
            if (empty($payload['hpc_student_id']) && ! empty($student->hpc_student_id)) {
                $payload['hpc_student_id'] = $student->hpc_student_id;
            }

            if ($student->source === Student::SOURCE_HPC) {
                unset($payload['source'], $payload['status'], $payload['remark']);
            }

            $student->update($payload);
            return $student->fresh();
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

    /**
     * Preload UDISE students: saves students.xlsx and writes preview.json for school.
     */
    public function preloadUdise($school = null, ?array $customStudents = null): array
    {
        if (! $school && Auth::check()) {
            $school = Auth::user()->getEffectiveSchool();
        }

        $udiseStudents = $customStudents ?? $this->getUdiseStudents($school);
        if (empty($udiseStudents)) {
            return [
                'ok'    => false,
                'error' => 'No UDISE student records found for this school.',
            ];
        }

        $gradeMap = $this->normalizer->getGradeMap();

        // 1. Generate and save students.xlsx in school storage
        $spreadsheet = $this->spreadsheetService->buildStudentSpreadsheet($udiseStudents);
        $dir = $this->getSchoolStorageDir($school);
        @mkdir($dir, 0755, true);
        $fullPath = $dir . '/students.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($fullPath);

        // 2. Generate preview summary from normalised UDISE rows
        $preview = [];
        foreach ($udiseStudents as $idx => $st) {
            $norm = $this->normalizer->normalizeUdiseStudent($st, $idx, $gradeMap);

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
                'udise_code'       => $school?->udise_code ?? '',
                'errors'           => [],
            ];
        }

        $previewFile = $this->getPreviewFile($school);
        $this->writePreviewFile($previewFile, $preview);

        return [
            'ok'      => true,
            'message' => 'Successfully synced ' . count($preview) . ' students from UDISE.',
            'total'   => count($preview),
            'valid'   => count($preview),
            'invalid' => 0,
            'errors'  => [],
            'preview' => $preview,
        ];
    }
}
