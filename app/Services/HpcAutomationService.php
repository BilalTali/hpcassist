<?php

namespace App\Services;

use App\Models\School;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class HpcAutomationService
{
    public function __construct(
        protected StudentRosterService $rosterService,
        protected StudentNormalizationService $normalizer
    ) {}

    /**
     * Return the allowed CORS origin for public extension-facing endpoints.
     */
    public function getAllowedCorsOrigin(): string
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

    public function getActiveProfileDir($school = null): string
    {
        if (! $school && Auth::check()) {
            $school = Auth::user()->getEffectiveSchool();
        }

        if ($school instanceof School) {
            $dir = storage_path('app/hpc/schools/' . $school->id . '/profile');
            if (! is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            return $dir;
        }

        $default = storage_path('app/hpc/profile');
        if (! is_dir($default)) {
            @mkdir($default, 0777, true);
        }
        return $default;
    }

    /**
     * Initialize submission queue and optionally launch background runner CLI.
     */
    public function runStart($school): array
    {
        $previewFile = $this->rosterService->getPreviewFile($school);

        $allStudents = [];
        if (file_exists($previewFile)) {
            $allStudents = json_decode(file_get_contents($previewFile), true) ?? [];
        } elseif ($school && $school->students()->exists()) {
            $allStudents = $school->students()->orderBy('id')->get()->map(fn($st, $idx) => $st->toPreviewArray($idx + 1))->values()->all();
            @mkdir(dirname($previewFile), 0755, true);
            file_put_contents($previewFile, json_encode($allStudents, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        if (empty($allStudents)) {
            return ['ok' => false, 'error' => 'No student data found. Please upload an Excel file first (download the UDISE template if needed).'];
        }

        $validStudents = array_values(array_filter($allStudents, fn($s) => empty($s['errors'])));
        if (empty($validStudents)) {
            return ['ok' => false, 'error' => 'All rows have validation errors. Please fix and re-upload.'];
        }

        $total = count($validStudents);

        // Initialize run_results.json
        $resultsFile = $this->rosterService->getSchoolStorageDir($school) . '/run_results.json';
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

        return [
            'ok'           => true,
            'total'        => $total,
            'launched_cli' => $launchedCli,
            'students'     => $validStudents,
            'add_url'      => config('hpc.add_student_url', 'https://hpc.parakh.ncert.gov.in/en/students/add'),
            'message'      => $launchedCli ? 'Background server automation started.' : 'Student queue ready for browser-assisted submission.',
        ];
    }

    /**
     * Handle progress ping from Chrome extension during submission run.
     */
    public function runProgress(Request $request): JsonResponse
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
        $school   = $schoolId ? School::find($schoolId) : Auth::user()?->getEffectiveSchool();
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

        // Token authentication
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

        $resultsFile = $this->rosterService->getSchoolStorageDir($school) . '/run_results.json';

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
                if ($studentId) {
                    $st = Student::where('school_id', $school->id)->find($studentId);
                }
                if (! $st && $apaarId && strlen((string)$apaarId) === 12) {
                    $st = Student::where('school_id', $school->id)->where('apaar_id', $apaarId)->first();
                }
                if (! $st && ! empty($admissionNo)) {
                    $st = Student::where('school_id', $school->id)->where('admission_no', $admissionNo)->first();
                }
                if (! $st && $name) {
                    $st = Student::where('school_id', $school->id)->where('name', $name)->first();
                }

                if ($st) {
                    $st->update([
                        'status'           => Student::STATUS_SUCCESS,
                        'hpc_submitted_at' => now(),
                        'remark'           => 'Submitted to HPC portal via extension runner',
                    ]);
                }
            } elseif ($status === 'failed' || $status === 'error') {
                $studentId = $request->input('student_id');
                $st = $studentId ? Student::where('school_id', $school->id)->find($studentId) : null;
                if ($st) {
                    $st->update([
                        'status'         => Student::STATUS_FAILED,
                        'failure_reason' => $message ?: 'Submission failed on HPC portal',
                    ]);
                }
            }
        }

        file_put_contents($resultsFile, json_encode($data, JSON_PRETTY_PRINT));

        return response()->json(['ok' => true, 'updated' => true], 200, [
            'Access-Control-Allow-Origin'  => $corsOrigin,
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Accept, X-HPC-Token',
        ]);
    }

    /**
     * Provide queued pending students to the Chrome extension runner.
     */
    public function getStudentQueue(Request $request): JsonResponse
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
        $school   = $schoolId ? School::find($schoolId) : Auth::user()?->getEffectiveSchool();
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

        $previewFile = $this->rosterService->getPreviewFile($school);
        $students    = [];
        if ($school->students()->exists()) {
            $students = $school->students()->forSchool($school)->step2()->orderBy('id')->get()->map(fn($st, $idx) => $st->toPreviewArray($idx + 1))->values()->all();
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

    /**
     * Provide school list to extension.
     */
    public function getSchoolsList(Request $request): JsonResponse
    {
        $corsOrigin = $this->getAllowedCorsOrigin();
        if ($request->isMethod('OPTIONS')) {
            return response()->json(['ok' => true], 200, [
                'Access-Control-Allow-Origin'  => $corsOrigin,
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Accept, X-HPC-Token',
            ]);
        }

        $schools = School::orderBy('name')->get()->map(function (School $sc) {
            return [
                'id'         => $sc->id,
                'name'       => $sc->name,
                'udise_code' => $sc->udise_code,
                'pending'    => $sc->students()->forSchool($sc)->step2()->count(),
                'submitted'  => $sc->students()->forSchool($sc)->step3()->count(),
                'total'      => $sc->students()->forSchool($sc)->count(),
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

    public function runStatus($school): array
    {
        $file = $this->rosterService->getSchoolStorageDir($school) . '/run_results.json';
        if (! file_exists($file)) {
            return ['started' => false, 'results' => []];
        }

        return json_decode(file_get_contents($file), true) ?? [];
    }

    public function runReset($school): void
    {
        $file = $this->rosterService->getSchoolStorageDir($school) . '/run_results.json';
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    public function endSession($school): array
    {
        $profileDir = $this->getActiveProfileDir($school);

        if (function_exists('exec')) {
            @exec("pkill -f 'storage/app/hpc' 2>/dev/null");
            @exec("pkill -f 'drivers/chromedriver' 2>/dev/null");
        }

        if (is_dir($profileDir)) {
            if (function_exists('exec')) {
                @exec("rm -rf " . escapeshellarg($profileDir));
            } else {
                $files = glob($profileDir . '/*');
                foreach ($files as $f) { if (is_file($f)) @unlink($f); }
                @rmdir($profileDir);
            }
        }

        return ['ok' => true, 'message' => 'Session ended for the active school profile. You can log in with new credentials.'];
    }

    public function startSession($school): array
    {
        $loginUrl   = config('hpc.login_url', 'https://hpc.parakh.ncert.gov.in/en/login');
        $profileDir = $this->getActiveProfileDir($school);
        @mkdir($profileDir, 0777, true);
        @file_put_contents($profileDir . '/session_active.txt', now()->toIso8601String());

        if (function_exists('exec')) {
            $macChrome = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
            if (file_exists($macChrome)) {
                exec('nohup ' . escapeshellarg($macChrome) . ' --user-data-dir=' . escapeshellarg($profileDir) . ' ' . escapeshellarg($loginUrl) . ' > /dev/null 2>&1 &');
            }
        }

        $schoolName = $school ? $school->name : 'Active School';
        return [
            'ok'      => true,
            'url'     => $loginUrl,
            'message' => "HPC School session connected for {$schoolName}. Please log into the portal.",
        ];
    }

    public function discoverForm(Request $request): JsonResponse
    {
        $url = trim($request->input('url') ?: config('hpc.add_student_url'));
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return response()->json(['ok' => false, 'error' => 'Please provide a valid form URL (e.g. https://hpc.parakh.ncert.gov.in/en/students/add).']);
        }

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

    /**
     * Receives scraped students from the PARAKH portal, marks them confirmed as Step 3.
     */
    public function syncFromPortal(Request $request): JsonResponse
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
        $school   = $schoolId ? School::find($schoolId) : Auth::user()?->getEffectiveSchool();
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
            $fullName  = trim($raw['name'] ?? '');
            if (empty($fullName) || strlen($fullName) < 2) continue;
            if (in_array(strtolower($fullName), ['select', 'student', 'action', 'actions', 'name', 's.no', 'sr.no', 'sr no', 's no', 'status', 'view', 'edit', 'delete'])) {
                continue;
            }

            $nameParts = $fullName ? explode(' ', $fullName, 2) : [];
            $firstName = trim($raw['first_name'] ?? ($nameParts[0] ?? ''));
            $lastName  = trim($raw['last_name']  ?? ($nameParts[1] ?? ''));

            $apaar     = preg_replace('/[^0-9]/', '', (string)($raw['apaar_id'] ?? ''));
            $hpcId     = trim((string)($raw['hpc_student_id'] ?? ($raw['login_id'] ?? ($raw['id'] ?? ''))));
            $penCode   = trim($raw['pen_code'] ?? '');
            $admNo     = trim($raw['admission_no'] ?? '');

            if (preg_match('/^S[0-9A-Z]{6,16}$/i', $admNo) || $admNo === $hpcId || $admNo === $penCode || $admNo === $apaar) {
                if (empty($hpcId)) {
                    $hpcId = $admNo;
                }
                $admNo = '';
            }

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

            $data = [
                'first_name'     => $firstName ?: 'Student',
                'last_name'      => $lastName,
                'name'           => $fullName ?: trim("{$firstName} {$lastName}"),
                'dob'            => trim($raw['dob']    ?? ''),
                'gender'         => trim($raw['gender'] ?? ''),
                'grade'          => $this->normalizer->normalizeGrade($raw['grade'] ?? ''),
                'section'        => trim($raw['section'] ?? 'A'),
                'apaar_id'       => $apaar,
                'pen_code'       => $penCode,
                'admission_no'   => $admNo,
                'hpc_student_id' => $hpcId,
                'email'          => trim($raw['email'] ?? ''),
                'errors'         => [],
                'remark'         => 'Fetched from PARAKH HPC Portal',
            ];

            $student = $this->rosterService->upsertStudentToDatabase($school, $data, Student::SOURCE_HPC);
            if ($student->wasRecentlyCreated) {
                $newCount++;
            } else {
                $matchCount++;
            }
        }

        $step2 = $school->students()->forSchool($school)->step2()->orderBy('id')->get();
        $preview = $step2->map(fn ($st, $idx) => $st->toPreviewArray($idx + 1))->values()->all();
        $this->rosterService->writePreviewFile($this->rosterService->getPreviewFile($school), $preview);

        $remaining = $step2->count();

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
}
