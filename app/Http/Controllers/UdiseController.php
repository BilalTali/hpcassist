<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/**
 * UdiseController — Browser-Bridge UDISE+ Sync
 *
 * Architecture:
 *  1. Dashboard opens /udise-bridge?school_id=X&token=Y in a new tab (user's browser)
 *  2. That page redirects to UDISE SDMS login
 *  3. After login the page polls for login success, then runs a fetch() inside
 *     the user's browser to grab students from the UDISE API
 *  4. Students are POSTed to /udise/ingest (CSRF-exempt) — same as the bookmarklet approach
 *  5. Dashboard polls /udise/ingest-poll until the school record shows synced
 */
class UdiseController extends Controller
{
    /**
     * Download the HPC Assist UDISE+ Chrome Extension zip archive.
     */
    public function downloadExtension()
    {
        $school = Auth::user()?->getEffectiveSchool();
        $schoolId = $school ? $school->id : 0;
        $schoolUdise = $school ? $school->udise_code : '';
        $schoolName = $school ? $school->name : '';
        $schoolToken = $school ? substr(hash('sha256', $school->id . '_' . $school->udise_code . '_' . config('app.key')), 0, 32) : '';

        $zipPath = public_path('downloads/hpc-assist-udise-extension.zip');
        @mkdir(dirname($zipPath), 0755, true);

        $progressToken = config('hpc.progress_token', '') ?: $schoolToken;
        $configJs = "// HPC Assist pre-configuration (generated for " . addslashes($schoolName) . ")\n" .
                    "window.__HPC_CONFIG = " . json_encode([
                        'school_id'      => $schoolId,
                        'school_name'    => $schoolName,
                        'udise_code'     => $schoolUdise,
                        'token'          => $schoolToken,
                        'progress_token' => $progressToken,
                        'server_url'     => url('/'),
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . ";\n";

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $zip->addFile(base_path('extension/manifest.json'), 'manifest.json');
            $zip->addFile(base_path('extension/content.js'), 'content.js');
            $zip->addFile(base_path('extension/hpc_content.js'), 'hpc_content.js');
            if (file_exists(base_path('extension/dashboard_bridge.js'))) {
                $zip->addFile(base_path('extension/dashboard_bridge.js'), 'dashboard_bridge.js');
            }
            $zip->addFromString('config.js', $configJs);
            $zip->close();
        }

        return response()->download($zipPath, 'hpc-assist-udise-extension.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }

    // ─── Bridge page (public, no auth) ──────────────────────────────────────

    /**
     * Serve the UDISE browser-bridge HTML page.
     * Opens in user's tab, handles the full login→fetch→post cycle client-side.
     */
    public function bridge(Request $request)
    {
        $schoolId   = (int) $request->query('school_id', 0);
        $token      = (string) $request->query('token', '');
        $udiseCode  = (string) $request->query('udise_code', '');
        $ingestUrl  = url('/udise/ingest');
        $dashUrl    = url('/dashboard?step=2');

        // UDISE SDMS student list URL — the page where students are listed
        // We navigate the user there so the fetch() runs with their session cookies
        $udiseBaseUrl = 'https://sdms.udiseplus.gov.in/';

        return response()->view('udise.bridge', compact(
            'schoolId', 'token', 'udiseCode', 'ingestUrl', 'dashUrl', 'udiseBaseUrl'
        ))->header('Content-Security-Policy', '');
    }

    // ─── Ingest (CSRF-exempt, called by bridge page) ────────────────────────

    /**
     * Receive students array posted by the bridge page after fetching from UDISE.
     * Also called by the Chrome extension and console bookmarklet.
     */
    public function ingest(Request $request)
    {
        $origin = $request->header('Origin');
        $allowedOrigin = $origin ?: '*';
        $headers = [
            'Access-Control-Allow-Origin'      => $allowedOrigin,
            'Access-Control-Allow-Methods'     => 'POST, GET, OPTIONS',
            'Access-Control-Allow-Headers'     => 'Content-Type, X-Requested-With, Authorization, X-HPC-Token, X-CSRF-TOKEN, Accept',
            'Access-Control-Allow-Credentials' => 'true',
        ];

        if ($request->isMethod('OPTIONS')) {
            return response()->json(['ok' => true], 200, $headers);
        }

        $data           = $request->all();
        $schoolId       = $request->input('school_id');
        $udiseSchoolId  = $request->input('udise_school_id');
        $token          = (string) $request->input('token', '');
        $udiseCode      = (string) $request->input('udise_code', '');
        $schoolName     = (string) $request->input('school_name', '');
        $students       = $request->input('students', []);

        // Support direct array or wrapped 'data' key from raw UDISE responses
        if (empty($students)) {
            if (isset($data['data']) && is_array($data['data'])) {
                $students = $data['data'];
            } elseif (is_array($data) && isset($data[0]['studentName'])) {
                $students = $data;
            } elseif (is_array($data) && isset($data[0]['studentId'])) {
                $students = $data;
            }
        }

        if (empty($students) || ! is_array($students)) {
            return response()->json(['ok' => false, 'error' => 'No student records received.'], 400, $headers);
        }

        // Auto-extract UDISE code from student records if missing
        if (empty($udiseCode)) {
            foreach ($students as $st) {
                if (! empty($st['schUdiseCode'])) {
                    $udiseCode = (string) $st['schUdiseCode'];
                    break;
                }
            }
        }

        // Auto-extract school name if missing
        if (empty($schoolName)) {
            foreach ($students as $st) {
                if (! empty($st['schoolName'])) {
                    $schoolName = (string) $st['schoolName'];
                    break;
                }
            }
        }

        \Illuminate\Support\Facades\Log::info("UDISE ingest request received", [
            'udise_code'      => $udiseCode,
            'school_id'       => $schoolId,
            'udise_school_id' => $udiseSchoolId,
            'school_name'     => $schoolName,
            'total_students'  => count($students),
        ]);

        // Identify school — multi-tiered resolution
        $school = null;

        // 1. Logged-in user in active web session
        if (Auth::check()) {
            $school = Auth::user()->getEffectiveSchool();
        }

        // 2. Token match
        if (! $school && $token) {
            foreach (\App\Models\School::all() as $candidate) {
                $expectedToken = substr(hash('sha256', $candidate->id . '_' . $candidate->udise_code . '_' . config('app.key')), 0, 32);
                if ($token === $expectedToken || $token === (string) $candidate->udise_code) {
                    $school = $candidate;
                    break;
                }
            }
        }

        // 3. By UDISE code (Exact or partial match)
        if (! $school && $udiseCode) {
            $cleanCode = preg_replace('/[^0-9]/', '', $udiseCode);
            if (! empty($cleanCode)) {
                $school = \App\Models\School::where('udise_code', $cleanCode)->first()
                       ?: \App\Models\School::where('udise_code', 'like', "%{$cleanCode}%")->first();
            }
        }

        // 4. By UDISE internal ID (e.g. 1007872 or 1007846)
        if (! $school && ($udiseSchoolId || $schoolId)) {
            $targetUdiseId = (string) ($udiseSchoolId ?: $schoolId);
            $school = \App\Models\School::where('udise_internal_id', $targetUdiseId)->first();
        }

        // 5. By HPC Assist School Primary Key
        if (! $school && $schoolId && is_numeric($schoolId)) {
            $candidate = \App\Models\School::find((int) $schoolId);
            if ($candidate) {
                $school = $candidate;
            }
        }

        // 6. By School Name
        if (! $school && $schoolName) {
            $cleanName = trim($schoolName);
            if (! empty($cleanName)) {
                $school = \App\Models\School::where('name', 'like', "%{$cleanName}%")->first();
            }
        }

        // 7. Fallback if single school exists in system
        if (! $school && \App\Models\School::count() === 1) {
            $school = \App\Models\School::first();
        }

        if (! $school) {
            \Illuminate\Support\Facades\Log::warning("UDISE ingest could not identify school", [
                'udise_code'      => $udiseCode,
                'school_id'       => $schoolId,
                'udise_school_id' => $udiseSchoolId,
                'school_name'     => $schoolName,
            ]);

            return response()->json([
                'ok'    => false,
                'error' => "Cannot identify school for UDISE '{$udiseCode}'. Please check your 11-digit UDISE code in the HPC Assist widget and retry.",
            ], 404, $headers);
        }

        // Cross-school mismatch verification: verify that student records belong to this school
        $rosterUdise = null;
        foreach ($students as $st) {
            if (! empty($st['schUdiseCode'])) {
                $rosterUdise = preg_replace('/[^0-9]/', '', (string) $st['schUdiseCode']);
                break;
            }
        }

        if ($school && $rosterUdise && ! empty($school->udise_code)) {
            $schoolCleanUdise = preg_replace('/[^0-9]/', '', $school->udise_code);
            if ($schoolCleanUdise !== $rosterUdise) {
                // If DB has the school matching this roster, redirect student data to the correct school
                $correctSchool = \App\Models\School::where('udise_code', $rosterUdise)->first();
                if ($correctSchool) {
                    \Illuminate\Support\Facades\Log::warning("UDISE ingest rerouted: payload targeted school {$school->id} ({$school->name}), but student records belong to school {$correctSchool->id} ({$correctSchool->name})");
                    $school = $correctSchool;
                } else {
                    return response()->json([
                        'ok'    => false,
                        'error' => "UDISE mismatch: Received student records for school {$rosterUdise}, but request was mapped to {$school->name} ({$school->udise_code}).",
                    ], 422, $headers);
                }
            }
        }

        // Internal ID mismatch verification: verify payload's internal school ID matches this school
        if ($school && $udiseSchoolId && ! empty($school->udise_internal_id)) {
            if ((string)$school->udise_internal_id !== (string)$udiseSchoolId) {
                $matchingInternalSchool = \App\Models\School::where('udise_internal_id', (string)$udiseSchoolId)->first();
                if ($matchingInternalSchool) {
                    \Illuminate\Support\Facades\Log::warning("UDISE ingest rerouted: payload internal ID {$udiseSchoolId} belongs to school {$matchingInternalSchool->id} ({$matchingInternalSchool->name}), not {$school->id} ({$school->name})");
                    $school = $matchingInternalSchool;
                }
            }
        }

        // Save / update udise_internal_id if discovered
        $discoveredInternalId = (string) ($udiseSchoolId ?: ($schoolId > 1000 ? $schoolId : ''));
        if (! empty($discoveredInternalId) && $school->udise_internal_id !== $discoveredInternalId) {
            $school->update(['udise_internal_id' => $discoveredInternalId]);
        }

        // Persist raw students
        $dir = storage_path('app/hpc/schools/' . $school->id);
        @mkdir($dir, 0755, true);
        file_put_contents($dir . '/udise_students_raw.json', json_encode($students, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Count APAAR
        $totalStudents = count($students);
        $totalApaar    = count(array_filter($students, fn($s) => ! empty($s['apaarId']) && ctype_digit((string) $s['apaarId']) && strlen((string) $s['apaarId']) === 12));

        // Build downloadable Excel template from UDISE data (no DB writes — user must upload the Excel for HPC)
        $hpc = app(HpcController::class);
        $hpc->preloadUdise(null, $school, $students);

        // Save to global legacy location (so older code paths still work)
        file_put_contents(
            storage_path('app/hpc/udise_latest_fetch.json'),
            json_encode([
                'school_id'      => $school->id,
                'school_name'    => $school->name,
                'udise_code'     => $school->udise_code,
                'total_students' => $totalStudents,
                'total_apaar'    => $totalApaar,
                'fetched_at'     => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT)
        );

        // Update school DB
        $school->update([
            'udise_status'         => 'synced',
            'udise_synced_at'      => now(),
            'udise_students_count' => $totalStudents,
            'udise_apaar_count'    => $totalApaar,
        ]);

        \Illuminate\Support\Facades\Log::info("UDISE ingest successful", [
            'school_id'      => $school->id,
            'school_name'    => $school->name,
            'total_students' => $totalStudents,
            'total_apaar'    => $totalApaar,
        ]);

        return response()->json([
            'ok'          => true,
            'total'       => $totalStudents,
            'apaar_count' => $totalApaar,
            'school_name' => $school->name,
            'message'     => "Synced {$totalStudents} students ({$totalApaar} APAAR IDs) to {$school->name}!",
            'redirect'    => route('dashboard', ['step' => 2]),
        ], 200, $headers);
    }

    // ─── Poll endpoint (auth required) ──────────────────────────────────────

    /**
     * Dashboard polls this endpoint every 2 seconds.
     * Returns the current sync status of the logged-in user's school.
     */
    public function ingestPoll(Request $request)
    {
        $school = Auth::user()?->getEffectiveSchool();
        if (! $school) {
            return response()->json(['ok' => false, 'synced' => false, 'error' => 'No school found.']);
        }

        $school->refresh();

        if ($school->udise_status === 'synced' && $school->udise_students_count > 0) {
            return response()->json([
                'ok'          => true,
                'synced'      => true,
                'total'       => $school->udise_students_count,
                'apaar_count' => $school->udise_apaar_count ?? 0,
                'school_name' => $school->name,
            ]);
        }

        return response()->json(['ok' => true, 'synced' => false]);
    }

    // ─── Download the master UDISE Excel (optional) ─────────────────────────

    public function download()
    {
        $school = Auth::user()?->getEffectiveSchool();
        $dir    = $school ? storage_path('app/hpc/schools/' . $school->id) : storage_path('app/hpc');
        $file   = $dir . '/students.xlsx';

        if (! file_exists($file)) {
            $fallback = storage_path('app/hpc/01041800702_Students_Full_Details_with_Unmasked_APAAR.xlsx');
            if (file_exists($fallback)) {
                $file = $fallback;
            } else {
                abort(404, 'Student export not found. Please sync from UDISE first.');
            }
        }

        $udise    = $school?->udise_code ?? '00000000000';
        $filename = "{$udise}_Students_APAAR.xlsx";

        return response()->download($file, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ─── Status (kept for backward compat) ──────────────────────────────────

    public function status()
    {
        $school = Auth::user()?->getEffectiveSchool();
        if ($school && $school->udise_status === 'synced' && $school->udise_students_count > 0) {
            return response()->json([
                'ok'       => true,
                'has_data' => true,
                'data'     => [
                    'school_name'    => $school->name,
                    'udise_code'     => $school->udise_code,
                    'total_students' => $school->udise_students_count,
                    'total_apaar'    => $school->udise_apaar_count ?? 0,
                    'fetched_at'     => $school->udise_synced_at,
                ],
            ]);
        }

        return response()->json(['ok' => true, 'has_data' => false]);
    }
}
