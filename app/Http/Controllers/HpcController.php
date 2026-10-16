<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Models\School;
use App\Models\Student;
use App\Services\StudentNormalizationService;
use App\Services\StudentSpreadsheetService;
use App\Services\StudentRosterService;
use App\Services\HpcAutomationService;

/**
 * Class HpcController
 *
 * Lightweight orchestrator controller for PARAKH HPC operations.
 * Delegates core business domains to dedicated services:
 *  - StudentNormalizationService: Grade mapping, name splitting, field validation
 *  - StudentSpreadsheetService:   Excel template generation, styling, and parsing
 *  - StudentRosterService:        UDISE & Excel storage, database upsert, deduplication
 *  - HpcAutomationService:       Extension API queue, progress polling, session control
 *
 * @package App\Http\Controllers
 */
class HpcController extends Controller
{
    public function __construct(
        protected StudentNormalizationService $normalizer,
        protected StudentSpreadsheetService $spreadsheetService,
        protected StudentRosterService $rosterService,
        protected HpcAutomationService $automationService,
    ) {}

    // ─── Dashboard View ───────────────────────────────────────────────────────

    public function dashboard()
    {
        /** @var School|null $school */
        $school       = Auth::user()?->getEffectiveSchool();
        $fieldsFile   = storage_path('app/hpc/fields.json');
        $previewFile  = $this->getPreviewFile($school);
        $resultsFile  = $this->getSchoolStorageDir($school) . '/run_results.json';

        $schoolsData  = $this->getSchoolsData();
        $activeSchool = $this->getActiveSchool();
        $profileDir   = $this->getActiveProfileDir($school);

        $hasFields  = file_exists($fieldsFile);
        $hasProfile = is_dir($profileDir) && count(scandir($profileDir)) > 2;

        $preview = [];
        if ($school && $school->students()->forSchool($school)->exists()) {
            $preview = $school->students()->forSchool($school)->orderBy('id')->get()->map(fn($st, $idx) => $st->toPreviewArray($idx + 1))->values()->all();
        } elseif (file_exists($previewFile)) {
            $preview = json_decode(file_get_contents($previewFile), true) ?? [];
        } else {
            $rawStudents = $school ? $this->getUdiseStudents($school) : [];
            if (! empty($rawStudents)) {
                $gradeMap = $this->getGradeMap();
                foreach ($rawStudents as $idx => $st) {
                    $norm = $this->normalizeUdiseStudent($st, $idx, $gradeMap);
                    $preview[] = [
                        'row'          => $idx + 1,
                        'first_name'   => $norm['firstName'],
                        'last_name'    => $norm['lastName'],
                        'name'         => trim("{$norm['firstName']} {$norm['lastName']}"),
                        'dob'          => $norm['dob'],
                        'gender'       => $norm['gender'],
                        'email'        => $norm['email'],
                        'admission_no' => $norm['admissionNo'],
                        'pen_code'     => $norm['cleanPen'],
                        'grade'        => $norm['grade'],
                        'section'      => $norm['section'],
                        'apaar_id'     => $norm['apaarId'],
                        'errors'       => [],
                    ];
                }
            }
        }

        $lastRun = null;
        if (file_exists($resultsFile)) {
            $lastRun = json_decode(file_get_contents($resultsFile), true);
        }

        $currentFormUrl = file_exists(storage_path('app/hpc/current_form.txt'))
            ? trim(file_get_contents(storage_path('app/hpc/current_form.txt')))
            : config('hpc.add_student_url');

        $udiseCount      = count($preview) ?: ($school?->udise_students_count ?? 0);
        $udiseApaarCount = count(array_filter($preview, fn($s) => !empty($s['apaar_id']) && strlen((string)$s['apaar_id']) === 12)) ?: ($school?->udise_apaar_count ?? 0);
        $schoolToken     = $school ? substr(hash('sha256', $school->id . '_' . $school->udise_code . '_' . config('app.key')), 0, 32) : '';

        return view('hpc.dashboard', compact(
            'hasFields', 'hasProfile', 'preview', 'lastRun', 'currentFormUrl',
            'schoolsData', 'activeSchool', 'udiseCount', 'udiseApaarCount', 'schoolToken'
        ));
    }

    // ─── Step 1: Download Excel Template ──────────────────────────────────────

    public function downloadTemplate(Request $request)
    {
        $wantsUdise = $request->query('source') === 'udise'
                   || $request->query('udise') === '1'
                   || $request->query('with_udise') === '1'
                   || $request->is('*/udise*');

        $isForcedBlank = $request->query('blank') === '1';

        /** @var School|null $school */
        $school = Auth::user()?->getEffectiveSchool();
        $udiseStudents = [];

        if ($wantsUdise && ! $isForcedBlank && $school) {
            $scopedQuery = $school->students()->forSchool($school)->orderBy('id');
            if ($scopedQuery->exists()) {
                $udiseStudents = $scopedQuery->get()->map(fn($st) => [
                    'first_name'   => $st->first_name,
                    'last_name'    => $st->last_name,
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

            if (empty($udiseStudents)) {
                $schoolName = $school->name ?: 'your school';
                $udiseCode  = $school->udise_code ?: 'N/A';
                return redirect()->to(route('dashboard') . '?step=2')->with('error', "⚠️ No UDISE+ students synced yet for {$schoolName} (UDISE: {$udiseCode}). Please open the UDISE+ portal and sync your students via the HPC Assist Chrome extension first, or download the Blank Template if filling manually.");
            }
        }

        $sheet = $this->buildStudentSpreadsheet($udiseStudents);

        $udiseCode = $school?->udise_code ?? 'template';
        $filename  = (! empty($udiseStudents))
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
        return $this->spreadsheetService->buildStudentSpreadsheet($udiseStudents);
    }

    public function preloadUdise(?Request $request = null, $school = null, ?array $customStudents = null): array
    {
        return $this->rosterService->preloadUdise($school, $customStudents);
    }

    public function getSchoolStorageDir($school = null): string
    {
        return $this->rosterService->getSchoolStorageDir($school);
    }

    public function getPreviewFile($school = null): string
    {
        return $this->rosterService->getPreviewFile($school);
    }

    public function getUdiseStudents($school = null): array
    {
        return $this->rosterService->getUdiseStudents($school);
    }

    public function getGradeMap(): array
    {
        return $this->normalizer->getGradeMap();
    }

    public function normalizeGrade($rawGrade): string
    {
        return $this->normalizer->normalizeGrade($rawGrade);
    }

    // ─── Step 2: Upload & Validate Filled Excel ───────────────────────────────

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

        try {
            $parsed = $this->spreadsheetService->parseUploadedSpreadsheet($fullPath);
            $preview = $parsed['preview'] ?? [];
            $errors  = $parsed['errors'] ?? [];
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'Could not read Excel file: ' . $e->getMessage()]);
        }

        /** @var School|null $schoolForFile */
        $schoolForFile = Auth::check() ? Auth::user()->getEffectiveSchool() : null;
        $this->writePreviewFile($this->getPreviewFile($schoolForFile), $preview);
        $this->syncSpreadsheetFromPreview($preview, $schoolForFile);

        if ($schoolForFile) {
            $shouldReplace = $request->boolean('replace_all');
            if ($shouldReplace) {
                Student::where('school_id', $schoolForFile->id)
                    ->where(function ($q) {
                        $q->whereNull('hpc_submitted_at')
                          ->where('source', '!=', Student::SOURCE_HPC);
                    })
                    ->delete();
            }

            foreach ($preview as $pRow) {
                $this->upsertStudentToDatabase($schoolForFile, $pRow, Student::SOURCE_EXCEL);
            }

            $apaarCount = count(array_filter($preview, fn($s) => !empty($s['apaar_id']) && strlen((string)$s['apaar_id']) === 12));
            $schoolForFile->update([
                'udise_status'         => 'synced',
                'udise_synced_at'      => now(),
                'udise_students_count' => count($preview),
                'udise_apaar_count'    => $apaarCount,
            ]);
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

    public function validateStudentRow(array $data, int $rowNum = 1): array
    {
        return $this->normalizer->validateStudentRow($data, $rowNum);
    }

    public function syncSpreadsheetFromPreview(array $preview, $school = null): void
    {
        $this->spreadsheetService->syncSpreadsheetFromPreview($preview, $school);
    }

    // ─── Student CRUD Actions ─────────────────────────────────────────────────

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

        /** @var School|null $userSchool */
        $userSchool = Auth::check() ? Auth::user()->getEffectiveSchool() : null;
        if ($userSchool) {
            $dbStudent = $this->upsertStudentToDatabase($userSchool, $validated, Student::SOURCE_MANUAL);
            $validated['id'] = $dbStudent->id;
        }

        if ($isEdit) {
            $preview[(int)$index] = $validated;
        } else {
            $preview[] = $validated;
        }

        foreach ($preview as $i => &$st) {
            $st['row'] = $i + 1;
        }
        unset($st);

        $this->writePreviewFile($previewFile, $preview);
        $this->syncSpreadsheetFromPreview($preview);

        $valid   = count(array_filter($preview, fn($r) => empty($r['errors'])));
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
        /** @var School|null $userSchool */
        $userSchool = Auth::check() ? Auth::user()->getEffectiveSchool() : null;

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

        $valid   = count(array_filter($preview, fn($r) => empty($r['errors'])));
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
        /** @var School|null $userSchool */
        $userSchool = Auth::check() ? Auth::user()->getEffectiveSchool() : null;

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
            'students'              => 'required|array|max:2000',
            'students.*.first_name' => 'required|string|max:100',
            'students.*.email'      => 'required|email|max:200',
            'students.*.gender'     => 'required|in:Male,Female,Other',
            'students.*.grade'      => 'required|string|max:30',
            'students.*.section'    => 'required|string|max:10',
            'students.*.apaar_id'   => 'nullable|digits:12',
        ]);

        $items = $request->input('students', []);
        $preview = [];
        foreach ($items as $i => $item) {
            $preview[] = $this->validateStudentRow($item, $i + 1);
        }

        $previewFile = $this->getPreviewFile();
        $this->writePreviewFile($previewFile, $preview);
        $this->syncSpreadsheetFromPreview($preview);

        /** @var School|null $userSchool */
        $userSchool = Auth::check() ? Auth::user()->getEffectiveSchool() : null;
        if ($userSchool) {
            foreach ($preview as $pRow) {
                $this->upsertStudentToDatabase($userSchool, $pRow, 'bulk_update');
            }
        }

        $valid   = count(array_filter($preview, fn($r) => empty($r['errors'])));
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

    public function upsertStudentToDatabase(School $school, array $data, string $source = 'udise_sync'): Student
    {
        return $this->rosterService->upsertStudentToDatabase($school, $data, $source);
    }

    public function deleteStudentFromDatabase(School $school, array $studentData): void
    {
        $this->rosterService->deleteStudentFromDatabase($school, $studentData);
    }

    public function normalizeUdiseStudent(array $st, int $idx, array $gradeMap): array
    {
        return $this->normalizer->normalizeUdiseStudent($st, $idx, $gradeMap);
    }

    private function writePreviewFile(string $path, array $data): void
    {
        $this->rosterService->writePreviewFile($path, $data);
    }

    // ─── HPC Automation & Extension Endpoints ─────────────────────────────────

    public function runStart(Request $request)
    {
        $school = Auth::user()?->getEffectiveSchool();
        return response()->json($this->automationService->runStart($school));
    }

    public function runProgress(Request $request): JsonResponse
    {
        return $this->automationService->runProgress($request);
    }

    public function getStudentQueue(Request $request): JsonResponse
    {
        return $this->automationService->getStudentQueue($request);
    }

    public function getSchoolsList(Request $request): JsonResponse
    {
        return $this->automationService->getSchoolsList($request);
    }

    private function getAllowedCorsOrigin(): string
    {
        return $this->automationService->getAllowedCorsOrigin();
    }

    public function runStatus(): JsonResponse
    {
        $school = Auth::user()?->getEffectiveSchool();
        return response()->json($this->automationService->runStatus($school));
    }

    public function runReset(): JsonResponse
    {
        $school = Auth::user()?->getEffectiveSchool();
        $this->automationService->runReset($school);
        return response()->json(['ok' => true, 'message' => 'Run reset successfully.']);
    }

    // ─── School Profile Management ────────────────────────────────────────────

    public function getSchoolsData(): array
    {
        if (Auth::check()) {
            /** @var School|null $dbSchool */
            $dbSchool = Auth::user()->getEffectiveSchool();
            if ($dbSchool) {
                return [
                    'active'  => 'school_' . $dbSchool->id,
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
            'active'  => 'default',
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
        return $this->automationService->getActiveProfileDir($school);
    }

    public function switchSchool(Request $request): JsonResponse
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

        if (function_exists('exec')) {
            @exec("pkill -f 'storage/app/hpc' 2>/dev/null");
            @exec("pkill -f 'drivers/chromedriver' 2>/dev/null");
        }

        $data['active'] = $schoolId;
        file_put_contents(storage_path('app/hpc/schools.json'), json_encode($data, JSON_PRETTY_PRINT));

        return response()->json(['ok' => true, 'message' => 'Switched active school successfully.']);
    }

    public function addSchool(Request $request): JsonResponse
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

        if (function_exists('exec')) {
            @exec("pkill -f 'storage/app/hpc' 2>/dev/null");
            @exec("pkill -f 'drivers/chromedriver' 2>/dev/null");
        }

        return response()->json(['ok' => true, 'id' => $id, 'name' => $name, 'message' => "School '{$name}' added and selected."]);
    }

    public function deleteSchool(Request $request): JsonResponse
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

    // ─── Session Management ───────────────────────────────────────────────────

    public function endSession(): JsonResponse
    {
        $school = Auth::user()?->getEffectiveSchool();
        return response()->json($this->automationService->endSession($school));
    }

    public function startSession(): JsonResponse
    {
        $school = Auth::user()?->getEffectiveSchool();
        return response()->json($this->automationService->startSession($school));
    }

    // ─── Form Discovery & Portal Sync ─────────────────────────────────────────

    public function discoverForm(Request $request): JsonResponse
    {
        return $this->automationService->discoverForm($request);
    }

    public function syncFromPortal(Request $request): JsonResponse
    {
        return $this->automationService->syncFromPortal($request);
    }

    public function getStep3Students(Request $request): JsonResponse
    {
        /** @var School|null $school */
        $school = Auth::user()?->getEffectiveSchool();
        if (! $school) {
            return response()->json(['ok' => false, 'error' => 'School not found.'], 404);
        }

        $perPage = max(1, (int) $request->input('per_page', 50));
        $page    = max(1, (int) $request->input('page', 1));

        $query = $school->students()->forSchool($school)->step3()
            ->orderByDesc('hpc_submitted_at')
            ->orderByDesc('hpc_fetched_at');

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
