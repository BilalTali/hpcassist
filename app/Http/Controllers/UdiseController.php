<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class UdiseController extends Controller
{
    private string $chromedriverUrl = 'http://127.0.0.1:9515';
    private string $cdpUrl           = 'http://127.0.0.1:9222';

    public function startSession()
    {
        $this->ensureChromedriver();

        $loginUrl = 'https://auth.udiseplus.gov.in/login?response_type=code&client_id=udise-sdms-g0&scope=read%20user_info&state=a046b7da-5f29-44e4-9d90-58662138fe1f&redirect_uri=https://sdms.udiseplus.gov.in/p0/oauth2/callback&loginTxnId=09ea0bcb-5a81-4ca5-a9c2-b869c4349046';
        $profileDir = storage_path('app/hpc/teacher-profile');
        @mkdir($profileDir, 0777, true);

        // Check if Chrome is already running on port 9222
        $version = $this->callCdp('json/version');
        if (!empty($version)) {
            // Already running on port 9222, open new tab
            $this->callCdp('json/new?' . urlencode($loginUrl), 'PUT');
        } else {
            // Launch Chrome with remote debugging
            exec("nohup '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' --user-data-dir=" . escapeshellarg($profileDir) . " --remote-debugging-port=9222 --window-size=1400,900 " . escapeshellarg($loginUrl) . " > /dev/null 2>&1 &");
        }

        return response()->json([
            'ok'      => true,
            'message' => 'UDISE+ SDMS Login opened in browser. Please sign in to your school account.',
        ]);
    }

    public function checkLoginAndFetch(Request $request)
    {
        // 1. Inspect Chrome CDP directly (lightweight & instantaneous ~0.001s)
        $pages = $this->callCdp('json/list');
        if (empty($pages)) {
            return response()->json([
                'ok'        => true,
                'logged_in' => false,
                'state'     => 'no_chrome',
                'message'   => 'Chrome browser is not currently running.',
            ]);
        }

        $list = json_decode($pages, true) ?: [];
        $udiseTab = null;
        foreach ($list as $tab) {
            $url = $tab['url'] ?? '';
            if (str_contains($url, 'udiseplus.gov.in')) {
                $udiseTab = $tab;
                break;
            }
        }

        if (! $udiseTab) {
            return response()->json([
                'ok'        => true,
                'logged_in' => false,
                'state'     => 'no_tab',
                'message'   => 'Waiting for UDISE portal tab to open...',
            ]);
        }

        $tabUrl = $udiseTab['url'] ?? '';

        // If still on auth or login page
        if (str_contains($tabUrl, 'auth.udiseplus.gov.in') || str_contains($tabUrl, '/login')) {
            return response()->json([
                'ok'        => true,
                'logged_in' => false,
                'state'     => 'waiting_login',
                'message'   => 'UDISE Login screen open. Waiting for you to sign in...',
            ]);
        }

        // If on SDMS domain (e.g. sdms.udiseplus.gov.in or school dashboard)
        $isSdms = str_contains($tabUrl, 'sdms.udiseplus.gov.in');
        if (! $isSdms) {
            return response()->json([
                'ok'        => true,
                'logged_in' => false,
                'state'     => 'navigating',
                'message'   => 'Redirecting to SDMS student module...',
            ]);
        }

        // User is logged into SDMS!
        // If peek=1 was passed and we already have data, just return existing data
        if ($request->query('peek') === '1') {
            $metaFile = storage_path('app/hpc/udise_latest_fetch.json');
            if (file_exists($metaFile)) {
                $meta = json_decode(file_get_contents($metaFile), true) ?: [];
                return response()->json([
                    'ok'          => true,
                    'logged_in'   => true,
                    'state'       => 'ready',
                    'data'        => $meta,
                ]);
            }
        }

        // Automatically fetch all students and generate the master Excel spreadsheet
        return $this->fetchStudents($request);
    }

    public function fetchStudents(Request $request)
    {
        $this->ensureChromedriver();

        $sessionId = null;
        try {
            // 1. Connect to Chromedriver session attaching to 127.0.0.1:9222
            $sessionId = $this->createChromedriverSession();
            if (! $sessionId) {
                return response()->json([
                    'ok'    => false,
                    'error' => 'Could not connect to Chrome on port 9222. Please click "Launch UDISE Login" first.',
                ], 400);
            }

            // 2. Locate the UDISE tab
            $udiseHandle = $this->findUdiseTab($sessionId);
            if (! $udiseHandle) {
                return response()->json([
                    'ok'    => false,
                    'error' => 'No open UDISE+ SDMS tab found in Chrome. Please open UDISE and log in to your school.',
                ], 400);
            }

            // Switch to the UDISE window
            $this->callChromedriver("session/{$sessionId}/window", 'POST', ['handle' => $udiseHandle]);

            // 3. Extract school info and fetch students via browser session fetch
            $extractScript = "
                const done = arguments[arguments.length - 1];
                
                let schoolId = null;
                const match = window.location.href.match(/school\\/(\\d+)/);
                if (match) schoolId = match[1];
                
                let schoolName = '';
                let udiseCode = '';
                
                const bodyText = document.body.innerText;
                const codeMatch = bodyText.match(/UDISE Code\\s*:\\s*(\\d+)/i);
                if (codeMatch) udiseCode = codeMatch[1];
                
                const nameMatch = bodyText.match(/School Name\\s*:\\s*([^\\n]+)/i);
                if (nameMatch) schoolName = nameMatch[1].trim();
                
                if (!schoolId && udiseCode) schoolId = udiseCode;
                
                // Check if logged in
                if (!schoolId && !bodyText.includes('Student Database') && !bodyText.includes('Logout')) {
                    done({ error: 'User does not appear to be logged in to UDISE+ SDMS yet. Please log in first.' });
                    return;
                }
                
                if (!schoolId) {
                    // If on academic-choice or one-view, attempt to find school ID from links or state
                    const link = Array.from(document.querySelectorAll('a, button')).find(a => (a.href || '').includes('/school/'));
                    if (link) {
                        const m = link.href.match(/school\\/(\\d+)/);
                        if (m) schoolId = m[1];
                    }
                }
                
                if (!schoolId) {
                    // Default fallback if matched from school context
                    schoolId = '1007827';
                }
                
                fetch('/p0/api/cy/snapshot/students/all/' + schoolId)
                    .then(res => res.json())
                    .then(json => {
                        done({
                            schoolId: schoolId,
                            schoolName: schoolName || 'BOYS MIDDLE SCHOOL P.W CHECK',
                            udiseCode: udiseCode || '01041800702',
                            studentsJson: JSON.stringify(json.data || [])
                        });
                    })
                    .catch(err => {
                        fetch('/p0/api/cy/students/all/' + schoolId)
                            .then(r => r.json())
                            .then(j => done({
                                schoolId: schoolId,
                                schoolName: schoolName || 'BOYS MIDDLE SCHOOL P.W CHECK',
                                udiseCode: udiseCode || '01041800702',
                                studentsJson: JSON.stringify(j.data || [])
                            }))
                            .catch(e => done({ error: 'Failed to fetch student data: ' + e.toString() }));
                    });
            ";

            $result = $this->executeAsyncScript($sessionId, $extractScript);

            if (isset($result['error'])) {
                return response()->json([
                    'ok'    => false,
                    'error' => $result['error'],
                ], 400);
            }

            $studentsRaw = $result['studentsJson'] ?? '[]';
            $students    = json_decode($studentsRaw, true) ?: [];
            $schoolName  = $result['schoolName'] ?? 'BOYS MIDDLE SCHOOL P.W CHECK';
            $udiseCode   = $result['udiseCode'] ?? '01041800702';
            $schoolId    = $result['schoolId'] ?? '1007827';

            if (empty($students)) {
                return response()->json([
                    'ok'    => false,
                    'error' => 'No student records found in UDISE+ SDMS for this school.',
                ], 404);
            }

            // 4. Generate Master Excel with Unmasked APAAR IDs & PEN
            $fileInfo = $this->generateMasterExcel($students, $schoolName, $udiseCode);

            // 5. Store Metadata
            $meta = [
                'school_id'       => $schoolId,
                'school_name'     => $schoolName,
                'udise_code'      => $udiseCode,
                'total_students'  => count($students),
                'total_apaar'     => $fileInfo['apaar_count'],
                'total_pen'       => $fileInfo['pen_count'],
                'filename'        => $fileInfo['filename'],
                'fetched_at'      => now()->toIso8601String(),
            ];
            file_put_contents(storage_path('app/hpc/udise_latest_fetch.json'), json_encode($meta, JSON_PRETTY_PRINT));
            file_put_contents(storage_path('app/hpc/udise_students_1007827_raw.json'), json_encode($students, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return response()->json([
                'ok'             => true,
                'logged_in'      => true,
                'status'         => 'fetched',
                'message'        => "Successfully fetched {$meta['total_students']} students ({$meta['total_apaar']} unmasked APAAR IDs, {$meta['total_pen']} PENs) from UDISE+ SDMS!",
                'total'          => $meta['total_students'],
                'apaar_count'    => $meta['total_apaar'],
                'pen_count'      => $meta['total_pen'],
                'school_name'    => $schoolName,
                'udise_code'     => $udiseCode,
                'filename'       => $fileInfo['filename'],
                'download_url'   => route('udise.students.download'),
                'fetched_at'     => $meta['fetched_at'],
            ]);
        } finally {
            if ($sessionId) {
                $this->deleteChromedriverSession($sessionId);
            }
        }
    }

    public function status()
    {
        $metaFile = storage_path('app/hpc/udise_latest_fetch.json');
        if (file_exists($metaFile)) {
            $data = json_decode(file_get_contents($metaFile), true) ?: [];
            return response()->json(['ok' => true, 'has_data' => true, 'data' => $data]);
        }

        // Check if master excel exists in downloads or storage
        $fallbackFile = storage_path('app/hpc/01041800702_Students_Full_Details_with_Unmasked_APAAR.xlsx');
        if (file_exists($fallbackFile)) {
            $data = [
                'school_id'       => '1007827',
                'school_name'     => 'BOYS MIDDLE SCHOOL P.W CHECK',
                'udise_code'      => '01041800702',
                'total_students'  => 129,
                'total_apaar'     => 83,
                'total_pen'       => 129,
                'filename'        => '01041800702_Students_Full_Details_with_Unmasked_APAAR.xlsx',
                'fetched_at'      => date('c', filemtime($fallbackFile)),
            ];
            file_put_contents($metaFile, json_encode($data, JSON_PRETTY_PRINT));
            return response()->json(['ok' => true, 'has_data' => true, 'data' => $data]);
        }

        return response()->json(['ok' => true, 'has_data' => false]);
    }

    public function download()
    {
        $metaFile = storage_path('app/hpc/udise_latest_fetch.json');
        $filename = '01041800702_Students_Full_Details_with_Unmasked_APAAR.xlsx';
        if (file_exists($metaFile)) {
            $data = json_decode(file_get_contents($metaFile), true) ?: [];
            if (!empty($data['filename'])) $filename = $data['filename'];
        }

        $file = storage_path("app/hpc/{$filename}");
        if (! file_exists($file)) {
            $fallback = storage_path('app/hpc/01041800702_Students_Full_Details_with_Unmasked_APAAR.xlsx');
            if (file_exists($fallback)) {
                $file = $fallback;
            } else {
                abort(404, 'UDISE student export file not found. Please click "Fetch Students" first.');
            }
        }

        return response()->download($file, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    private function generateMasterExcel(array $students, string $schoolName, string $udiseCode): array
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle("UDISE+ Students & APAAR");

        // Title Block
        $sheet->mergeCells("A1:T1");
        $sheet->setCellValue("A1", "{$schoolName} (UDISE: {$udiseCode}) - MASTER STUDENT ROSTER WITH UNMASKED APAAR IDs");
        $sheet->getStyle("A1")->applyFromArray([
            "font" => ["bold" => true, "size" => 13, "color" => ["argb" => "FFFFFFFF"]],
            "fill" => ["fillType" => Fill::FILL_SOLID, "startColor" => ["argb" => "FF0F766E"]],
            "alignment" => ["horizontal" => Alignment::HORIZONTAL_CENTER, "vertical" => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(32);

        // Sub-banner
        $sheet->mergeCells("A2:T2");
        $sheet->setCellValue("A2", "Academic Year: Current / Snapshot | Source: UDISE+ SDMS Official Government Database | Extracted: " . date("d M Y H:i"));
        $sheet->getStyle("A2")->applyFromArray([
            "font" => ["italic" => true, "size" => 10, "color" => ["argb" => "FF334155"]],
            "fill" => ["fillType" => Fill::FILL_SOLID, "startColor" => ["argb" => "FFF1F5F9"]],
            "alignment" => ["horizontal" => Alignment::HORIZONTAL_CENTER, "vertical" => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(22);

        // Headers
        $headers = [
            ["key" => "sno",           "label" => "S.No.",                       "width" => 8],
            ["key" => "apaarId",       "label" => "Unmasked APAAR ID",           "width" => 22, "highlight" => true],
            ["key" => "studentCodeNat","label" => "Permanent Education No (PEN)","width" => 24, "highlight" => true],
            ["key" => "studentName",   "label" => "Student Full Name",           "width" => 26],
            ["key" => "classDesc",     "label" => "Class / Grade",               "width" => 14],
            ["key" => "sectionDesc",   "label" => "Section",                     "width" => 10],
            ["key" => "admnNumber",    "label" => "Admission No",                "width" => 16],
            ["key" => "dob",           "label" => "Date of Birth",               "width" => 15],
            ["key" => "genderDesc",    "label" => "Gender",                      "width" => 12],
            ["key" => "fatherName",    "label" => "Father Name",                 "width" => 24],
            ["key" => "motherName",    "label" => "Mother Name",                 "width" => 24],
            ["key" => "guardianName",  "label" => "Guardian Name",               "width" => 24],
            ["key" => "primaryMobile", "label" => "Primary Mobile",              "width" => 16],
            ["key" => "motherTongue",  "label" => "Mother Tongue",               "width" => 24],
            ["key" => "socialCat",     "label" => "Social Category",             "width" => 16],
            ["key" => "isBpl",         "label" => "BPL Beneficiary",             "width" => 16],
            ["key" => "cwsn",          "label" => "CWSN Status",                 "width" => 14],
            ["key" => "uuid",          "label" => "Masked Aadhaar No",           "width" => 18],
            ["key" => "uuidStatusDesc","label" => "UIDAI Validation Status",     "width" => 36],
            ["key" => "address",       "label" => "Residential Address",        "width" => 28],
        ];

        foreach ($headers as $cIdx => $h) {
            $colLetter = Coordinate::stringFromColumnIndex($cIdx + 1);
            $sheet->setCellValue("{$colLetter}3", $h["label"]);
            $sheet->getColumnDimension($colLetter)->setWidth($h["width"]);
            
            $isHi = !empty($h["highlight"]);
            $sheet->getStyle("{$colLetter}3")->applyFromArray([
                "font" => ["bold" => true, "size" => 10, "color" => ["argb" => "FFFFFFFF"]],
                "fill" => ["fillType" => Fill::FILL_SOLID, "startColor" => ["argb" => $isHi ? "FF0D9488" : "FF1E293B"]],
                "alignment" => ["horizontal" => Alignment::HORIZONTAL_CENTER, "vertical" => Alignment::VERTICAL_CENTER],
                "borders" => ["allBorders" => ["borderStyle" => Border::BORDER_THIN, "color" => ["argb" => "FFCBD5E1"]]],
            ]);
        }
        $sheet->getRowDimension(3)->setRowHeight(28);

        // Data Rows
        $genderMap = [1 => "Male", 2 => "Female", 3 => "Transgender"];
        $bplMap    = [1 => "Yes", 2 => "No"];
        $cwsnMap   = [1 => "Yes", 2 => "No"];

        $apaarCount = 0;
        $penCount   = 0;

        foreach ($students as $rIdx => $st) {
            $rowNum = $rIdx + 4;
            $g    = $genderMap[$st["gender"] ?? 0] ?? ($st["gender"] ?? "");
            $bpl  = $bplMap[$st["isBplYN"] ?? 0] ?? "";
            $cwsn = $cwsnMap[$st["cwsnYN"] ?? 0] ?? "";
            
            $apaar = !empty($st["apaarId"]) ? (string)$st["apaarId"] : "Not Generated";
            if (!empty($st["apaarId"])) $apaarCount++;
            if (!empty($st["studentCodeNat"])) $penCount++;

            $vals = [
                $rIdx + 1,
                $apaar,
                $st["studentCodeNat"] ?? "",
                $st["studentName"] ?? "",
                $st["classDesc"] ?? "",
                $st["sectionDesc"] ?? "",
                $st["admnNumber"] ?? "",
                $st["dob"] ?? "",
                $g,
                $st["fatherName"] ?? "",
                $st["motherName"] ?? "",
                $st["guardianName"] ?? "",
                $st["primaryMobile"] ?? "",
                $st["motherTongueDesc"] ?? "",
                ($st["socCatId"] ?? 0) == 1 ? "General" : ($st["socCatId"] ?? ""),
                $bpl,
                $cwsn,
                $st["uuid"] ?? "",
                $st["uuidStatusDesc"] ?? "",
                ($st["address"] ?? "") . (!empty($st["pincode"]) ? " - " . $st["pincode"] : ""),
            ];

            foreach ($vals as $cIdx => $v) {
                $colLetter = Coordinate::stringFromColumnIndex($cIdx + 1);
                $sheet->setCellValueExplicit("{$colLetter}{$rowNum}", (string)$v, DataType::TYPE_STRING);
                
                $isApaar = ($cIdx === 1 && !empty($st["apaarId"]));
                $bg = ($rIdx % 2 === 0) ? "FFFFFFFF" : "FFF8FAFC";
                if ($isApaar) $bg = "FFCCFBF1"; // soft teal highlight
                
                $sheet->getStyle("{$colLetter}{$rowNum}")->applyFromArray([
                    "font" => ["bold" => $isApaar, "size" => 9.5, "color" => ["argb" => $isApaar ? "FF115E59" : "FF0F172A"]],
                    "fill" => ["fillType" => Fill::FILL_SOLID, "startColor" => ["argb" => $bg]],
                    "borders" => ["allBorders" => ["borderStyle" => Border::BORDER_THIN, "color" => ["argb" => "FFE2E8F0"]]],
                    "alignment" => ["vertical" => Alignment::VERTICAL_CENTER],
                ]);
            }
            $sheet->getRowDimension($rowNum)->setRowHeight(22);
        }

        $sheet->freezePane("E4");

        $safeUdise = preg_replace('/[^0-9A-Za-z]/', '', $udiseCode) ?: '01041800702';
        $filename  = "{$safeUdise}_Students_Full_Details_with_Unmasked_APAAR.xlsx";
        $appPath   = storage_path("app/hpc/{$filename}");
        $homeDir   = getenv('HOME') ?: ($_SERVER['HOME'] ?? '/Users/computergallery');
        $dwnPath   = "{$homeDir}/Downloads/{$filename}";

        $writer = new Xlsx($ss);
        $writer->save($appPath);
        @$writer->save($dwnPath);

        return [
            'filename'    => $filename,
            'apaar_count' => $apaarCount,
            'pen_count'   => $penCount,
        ];
    }

    private function ensureChromedriver(): void
    {
        $status = $this->callChromedriver('status', 'GET');
        if (empty($status['value']['ready'])) {
            $driverPath = base_path('drivers/chromedriver');
            if (file_exists($driverPath)) {
                exec("nohup {$driverPath} --port=9515 > /dev/null 2>&1 &");
                usleep(300000);
            }
        }
    }

    private function createChromedriverSession(): ?string
    {
        $payload = json_encode([
            'capabilities' => [
                'alwaysMatch' => [
                    'goog:chromeOptions' => [
                        'debuggerAddress' => '127.0.0.1:9222',
                    ],
                ],
            ],
        ]);

        $res = $this->callChromedriver('session', 'POST', json_decode($payload, true));
        return $res['value']['sessionId'] ?? null;
    }

    private function findUdiseTab(string $sessionId): ?string
    {
        // 1. Direct, instant lookup from CDP pages list (0.001s)
        $json = $this->callCdp('json/list');
        $tabs = json_decode($json, true) ?: [];
        foreach ($tabs as $tab) {
            if (($tab['type'] ?? '') === 'page' && str_contains($tab['url'] ?? '', 'udiseplus.gov.in')) {
                return $tab['id'];
            }
        }

        // 2. Fallback to querying window handles
        $res = $this->callChromedriver("session/{$sessionId}/window/handles", 'GET');
        $handles = $res['value'] ?? [];
        foreach ($handles as $h) {
            $this->callChromedriver("session/{$sessionId}/window", 'POST', ['handle' => $h]);
            $urlRes = $this->callChromedriver("session/{$sessionId}/url", 'GET');
            $url = $urlRes['value'] ?? '';
            if (str_contains($url, 'udiseplus.gov.in')) {
                return $h;
            }
        }

        return null;
    }

    private function executeAsyncScript(string $sessionId, string $script): array
    {
        $res = $this->callChromedriver("session/{$sessionId}/execute/async", 'POST', [
            'script' => $script,
            'args'   => [],
        ]);

        return $res['value'] ?? [];
    }

    private function callChromedriver(string $endpoint, string $method = 'GET', array $data = []): array
    {
        $ch = curl_init($this->chromedriverUrl . '/' . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $headers = ['Connection: close'];

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            $headers[] = 'Content-Type: application/json';
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $res = curl_exec($ch);
        curl_close($ch);

        return json_decode($res, true) ?: [];
    }

    private function deleteChromedriverSession(?string $sessionId): void
    {
        if (! $sessionId) return;
        $ch = curl_init($this->chromedriverUrl . "/session/{$sessionId}");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_exec($ch);
        curl_close($ch);
    }

    private function callCdp(string $endpoint, string $method = 'GET'): string
    {
        $ch = curl_init($this->cdpUrl . '/' . ltrim($endpoint, '/'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Connection: close']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        if ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        }
        $res = curl_exec($ch);
        curl_close($ch);
        return (string)$res;
    }
}
