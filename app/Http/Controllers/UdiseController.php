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
        $zipPath = public_path('downloads/hpc-assist-udise-extension.zip');
        if (! file_exists($zipPath) || filemtime(base_path('extension/content.js')) > filemtime($zipPath)) {
            @mkdir(dirname($zipPath), 0755, true);
            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                $zip->addFile(base_path('extension/manifest.json'), 'manifest.json');
                $zip->addFile(base_path('extension/content.js'), 'content.js');
                $zip->close();
            }
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
     * Also called by the console bookmarklet.
     */
    public function ingest(Request $request)
    {
        $headers = [
            'Access-Control-Allow-Origin'  => '*',
            'Access-Control-Allow-Methods' => 'POST, GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With, Authorization',
        ];

        if ($request->isMethod('OPTIONS')) {
            return response()->json(['ok' => true], 200, $headers);
        }

        $data = $request->all();
        $schoolId  = $request->input('school_id');
        $token     = $request->input('token', '');
        $udiseCode = $request->input('udise_code', '');
        $students  = $request->input('students', []);

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
        if (empty($udiseCode) && ! empty($students[0]['schUdiseCode'])) {
            $udiseCode = (string) $students[0]['schUdiseCode'];
        }

        // Identify school — logged-in user first, then by token/ID/UDISE code
        $school = null;
        if (Auth::check()) {
            $school = Auth::user()->getEffectiveSchool();
        }

        if (! $school && $schoolId) {
            $candidate = \App\Models\School::find($schoolId);
            if ($candidate) {
                $expectedToken = substr(hash('sha256', $candidate->id . '_' . $candidate->udise_code . '_' . config('app.key')), 0, 32);
                if ($token === $expectedToken || $token === (string) $candidate->udise_code || empty($token)) {
                    $school = $candidate;
                }
            }
        }

        if (! $school && $udiseCode) {
            $cleanCode = preg_replace('/[^0-9]/', '', $udiseCode);
            $school = \App\Models\School::where('udise_code', $cleanCode)->first();
            if (! $school) {
                // Try fuzzy match or first school if only one exists
                $school = \App\Models\School::where('udise_code', 'like', "%{$cleanCode}%")->first();
            }
        }

        if (! $school && \App\Models\School::count() === 1) {
            $school = \App\Models\School::first();
        }

        if (! $school) {
            return response()->json([
                'ok'    => false,
                'error' => "Cannot identify school for UDISE code '{$udiseCode}'. Please ensure a school is registered with this UDISE code in HPC Assist.",
            ], 404, $headers);
        }

        // Persist raw students
        $dir = storage_path('app/hpc/schools/' . $school->id);
        @mkdir($dir, 0755, true);
        file_put_contents($dir . '/udise_students_raw.json', json_encode($students, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Count APAAR
        $totalStudents = count($students);
        $totalApaar    = count(array_filter($students, fn($s) => ! empty($s['apaarId']) && ctype_digit((string) $s['apaarId']) && strlen((string) $s['apaarId']) === 12));

        // Build preview.json + students.xlsx
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
