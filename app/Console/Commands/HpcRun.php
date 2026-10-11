<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Panther\Client;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverSelect;
use Facebook\WebDriver\WebDriverExpectedCondition;

class HpcRun extends Command
{
    protected $signature   = 'hpc:run';
    protected $description = 'Read students from preview.json and submit each to the HPC portal';

    public function handle(): int
    {
        $storagePath   = config('hpc.storage_path');
        $profileDir    = base_path(config('hpc.profile_dir'));

        // Check if a specific school profile is active
        $schoolsFile = $storagePath . '/schools.json';
        if (file_exists($schoolsFile)) {
            $schoolsData = json_decode(file_get_contents($schoolsFile), true) ?? [];
            $activeId    = $schoolsData['active'] ?? 'default';
            foreach ($schoolsData['schools'] ?? [] as $sch) {
                if (($sch['id'] ?? '') === $activeId) {
                    $profileDir = $storagePath . '/' . ($sch['dir'] ?? 'chrome-profile');
                    break;
                }
            }
        }

        $driverBin     = config('hpc.chromedriver_path');
        $loginUrl      = config('hpc.login_url');
        $addStudentUrl = config('hpc.add_student_url');
        $previewFile   = $storagePath . '/preview.json';
        $resultsFile   = $storagePath . '/run_results.json';

        // ── Load students ─────────────────────────────────────────────────────
        if (! file_exists($previewFile)) {
            $this->writeStatus([
                'started' => true,
                'done'    => true,
                'fatal'   => 'No student data found. Please upload an Excel file first.',
                'results' => []
            ], $resultsFile);
            return self::FAILURE;
        }

        $all      = json_decode(file_get_contents($previewFile), true) ?? [];
        $students = array_values(array_filter($all, fn($s) => empty($s['errors'])));
        $total    = count($students);

        if ($total === 0) {
            $this->writeStatus([
                'started' => true,
                'done'    => true,
                'fatal'   => 'No valid student entries found in the uploaded file.',
                'results' => []
            ], $resultsFile);
            return self::FAILURE;
        }

        $this->writeStatus([
            'started'    => true,
            'done'       => false,
            'login_wait' => true,
            'total'      => $total,
            'processed'  => 0,
            'results'    => [],
        ], $resultsFile);

        // ── Start Chrome ──────────────────────────────────────────────────────
        if (file_exists($driverBin) && ! is_executable($driverBin)) {
            @chmod($driverBin, 0755);
        }

        $_SERVER['PANTHER_NO_HEADLESS'] = '1';
        putenv('PANTHER_NO_HEADLESS=1');

        try {
            $client = Client::createChromeClient($driverBin, [
                '--user-data-dir=' . $profileDir,
                '--window-size=1400,900',
                '--no-sandbox',
                '--disable-dev-shm-usage',
            ]);
        } catch (\Throwable $e) {
            $this->writeStatus([
                'started' => true,
                'done'    => true,
                'fatal'   => 'Could not launch Chrome: ' . $e->getMessage(),
                'total'   => $total,
                'processed' => 0,
                'results' => [],
            ], $resultsFile);
            return self::FAILURE;
        }

        // ── Check if already logged in (Try Add Student URL first) ────────────
        $loggedIn = false;
        try {
            $client->request('GET', $addStudentUrl);
            sleep(3);
            $url = $client->getCurrentURL();
            if (! str_contains($url, '/login') && ! str_contains($url, '/signin') && ! str_contains($url, '/auth')) {
                $loggedIn = true;
            }
        } catch (\Throwable) {}

        // If not already logged in, navigate to login page and wait
        if (! $loggedIn) {
            try {
                $client->request('GET', $loginUrl);
            } catch (\Throwable $e) {
                $this->finishFatal($client, $resultsFile, [], $total, 'Could not open login page: ' . $e->getMessage());
                return self::FAILURE;
            }

            $maxWait = 180;
            $waited  = 0;
            while ($waited < $maxWait) {
                sleep(2);
                $waited += 2;
                try {
                    $url = $client->getCurrentURL();
                    if (! str_contains($url, '/login') && ! str_contains($url, '/signin') && ! str_contains($url, '/auth')) {
                        $loggedIn = true;
                        break;
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        if (! $loggedIn) {
            $this->finishFatal($client, $resultsFile, [], $total, 'Session timed out. Please log into the HPC portal.');
            return self::FAILURE;
        }

        // Login confirmed — update status
        $this->writeStatus([
            'started'    => true,
            'done'       => false,
            'login_wait' => false,
            'login_done' => true,
            'total'      => $total,
            'processed'  => 0,
            'results'    => [],
        ], $resultsFile);

        // ── Process each student ──────────────────────────────────────────────
        $results = [];
        $delayMs = config('hpc.delay_ms') ?: 700;

        foreach ($students as $idx => $student) {
            $name = trim(($student['name'] ?? '') ?: (($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')));

            try {
                $result = $this->submitStudent($client, $student, $addStudentUrl, $delayMs, $storagePath, $idx + 1);
            } catch (\Throwable $e) {
                $result = ['status' => 'error', 'message' => 'Unexpected error: ' . $e->getMessage()];
            }

            $results[] = [
                'row'      => $student['row'] ?? ($idx + 1),
                'name'     => $name,
                'grade'    => $student['grade']   ?? '',
                'section'  => $student['section'] ?? '',
                'email'    => $student['email']   ?? '',
                'apaar_id' => $student['apaar_id'] ?? '',
                'status'   => $result['status'],
                'message'  => $result['message'],
            ];

            $this->writeStatus([
                'started'    => true,
                'done'       => false,
                'login_done' => true,
                'total'      => $total,
                'processed'  => $idx + 1,
                'results'    => $results,
            ], $resultsFile);
        }

        // ── Done ──────────────────────────────────────────────────────────────
        $this->writeStatus([
            'started'    => true,
            'done'       => true,
            'login_done' => true,
            'total'      => $total,
            'processed'  => $total,
            'results'    => $results,
        ], $resultsFile);

        try {
            $client->quit();
        } catch (\Throwable) {}

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────────────────
    private function submitStudent(Client $client, array $s, string $url, int $delayMs, string $storagePath, int $idx): array
    {
        // Navigate to the Add Student form
        $client->request('GET', $url);
        usleep($delayMs * 4 * 1000);

        // Switch to active tab
        try {
            $handles = $client->getWindowHandles();
            if (! empty($handles)) {
                $client->switchTo()->window(end($handles));
            }
        } catch (\Throwable) {}

        $wd = $client->getWebDriver();

        // ── Extract first name and last name ──────────────────────────────────
        $firstName = trim($s['first_name'] ?? '');
        $lastName  = trim($s['last_name'] ?? '');
        if ($firstName === '' && ! empty($s['name'])) {
            $parts = explode(' ', trim($s['name']), 2);
            $firstName = $parts[0] ?? '';
            $lastName  = $parts[1] ?? '';
        }

        // ── Fill First Name ───────────────────────────────────────────────────
        $writeHereInputs = $wd->findElements(WebDriverBy::cssSelector('input[placeholder="Write here"]'));
        if (count($writeHereInputs) < 1) {
            return ['status' => 'error', 'message' => 'Form fields not found. Session may have expired.'];
        }

        $this->clearAndType($writeHereInputs[0], $firstName);

        // ── Fill Last Name ────────────────────────────────────────────────────
        if (isset($writeHereInputs[1])) {
            $this->clearAndType($writeHereInputs[1], $lastName);
        }

        // ── Fill Date of Birth (React Datepicker) ─────────────────────────────
        $dobRaw = $s['dob'] ?? '';
        $dt = null;
        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y', 'm/d/Y', 'j/n/Y', 'Y/m/d'] as $fmt) {
            $parsed = \DateTime::createFromFormat($fmt, $dobRaw);
            if ($parsed) {
                $dt = $parsed;
                break;
            }
        }

        if ($dt) {
            $targetDay   = (int) $dt->format('j');
            $targetMonth = ((int) $dt->format('n')) - 1; // 0-indexed for react-datepicker
            $targetYear  = $dt->format('Y');

            try {
                $dobInputs = $wd->findElements(WebDriverBy::cssSelector('input[placeholder="Select"]'));
                if (! empty($dobInputs)) {
                    $dobInputs[0]->click();
                    usleep(500000);

                    $client->executeScript("
                        const day = {$targetDay};
                        const month = {$targetMonth};
                        const year = '{$targetYear}';

                        const yearSelect = document.querySelector('.react-datepicker__year-select');
                        if (yearSelect) {
                            yearSelect.value = year;
                            yearSelect.dispatchEvent(new Event('change', { bubbles: true }));
                        }

                        const monthSelect = document.querySelector('.react-datepicker__month-select');
                        if (monthSelect) {
                            monthSelect.value = String(month);
                            monthSelect.dispatchEvent(new Event('change', { bubbles: true }));
                        }

                        setTimeout(() => {
                            const days = Array.from(document.querySelectorAll('.react-datepicker__day:not(.react-datepicker__day--outside-month)'));
                            const dayEl = days.find(d => d.innerText.trim() === String(day));
                            if (dayEl) dayEl.click();
                        }, 250);
                    ");
                    usleep($delayMs * 1500);
                }
            } catch (\Throwable $e) {}
        }

        // ── Fill Email ────────────────────────────────────────────────────────
        if (isset($writeHereInputs[2])) {
            $this->clearAndType($writeHereInputs[2], $s['email'] ?? '');
        } else {
            try {
                $emailInputs = $wd->findElements(WebDriverBy::cssSelector('input[type="email"]'));
                if (! empty($emailInputs)) {
                    $this->clearAndType($emailInputs[0], $s['email'] ?? '');
                }
            } catch (\Throwable) {}
        }

        // ── Select Grade (.custom-dropdown-wrapper) ───────────────────────────
        $rawGrade = trim((string)($s['grade'] ?? ''));
        $gradeCandidates = [strtolower($rawGrade)];

        if (preg_match('/^([1-8])$/', $rawGrade, $m)) {
            $gradeCandidates[] = 'grade ' . $m[1];
            $gradeCandidates[] = 'grade' . $m[1];
        } elseif (preg_match('/^(?:grade|class)\s*([1-8])$/i', $rawGrade, $m)) {
            $gradeCandidates[] = 'grade ' . $m[1];
            $gradeCandidates[] = 'grade' . $m[1];
            $gradeCandidates[] = $m[1];
        } elseif (preg_match('/^(?:b|balvatika)\s*[-_]?\s*([1-3])$/i', $rawGrade, $m)) {
            $gradeCandidates[] = 'balvatika ' . $m[1];
            $gradeCandidates[] = 'balvatika' . $m[1];
        }

        $gradeSelected = false;
        try {
            $dropdownHeaders = $wd->findElements(WebDriverBy::cssSelector('.custom-dropdown-header'));
            if (! empty($dropdownHeaders)) {
                $dropdownHeaders[0]->click();
                usleep(500000);

                $selected = $client->executeScript("
                    const candidates = " . json_encode($gradeCandidates) . ";
                    const items = Array.from(document.querySelectorAll('.custom-dropdown-item'));
                    const item = items.find(it => {
                        const t = it.innerText.trim().toLowerCase();
                        return candidates.includes(t) || candidates.some(c => t === ('grade ' + c) || t === c);
                    });
                    if (item) {
                        item.click();
                        return true;
                    }
                    return false;
                ");
                $gradeSelected = (bool) $selected;
                usleep($delayMs * 1500);
            }
        } catch (\Throwable $e) {}

        if (! $gradeSelected) {
            return ['status' => 'error', 'message' => 'Could not select Grade "' . $rawGrade . '"'];
        }

        // ── Select Section (.custom-dropdown-wrapper) ─────────────────────────
        $rawSection = strtoupper(trim((string)($s['section'] ?? '')));
        if (preg_match('/(?:SECTION\s*)?([A-H])/i', $rawSection, $m)) {
            $rawSection = strtoupper($m[1]);
        }

        $sectionSelected = false;
        try {
            $dropdownHeaders = $wd->findElements(WebDriverBy::cssSelector('.custom-dropdown-header'));
            if (isset($dropdownHeaders[1])) {
                $dropdownHeaders[1]->click();
                usleep(500000);

                $selected = $client->executeScript("
                    const target = " . json_encode($rawSection) . ";
                    const items = Array.from(document.querySelectorAll('.custom-dropdown-item'));
                    const item = items.find(it => {
                        const t = it.innerText.trim().toUpperCase();
                        return t === target || t === ('SECTION ' + target);
                    });
                    if (item) {
                        item.click();
                        return true;
                    }
                    return false;
                ");
                $sectionSelected = (bool) $selected;
                usleep($delayMs * 1500);
            }
        } catch (\Throwable $e) {}

        if (! $sectionSelected) {
            return ['status' => 'error', 'message' => 'Could not select Section "' . $rawSection . '"'];
        }

        // ── Fill APAAR ID (segmented 12-box input) ────────────────────────────
        $apaarId = preg_replace('/\D/', '', $s['apaar_id'] ?? '');
        if (strlen($apaarId) === 12) {
            try {
                $apaarBoxes = $wd->findElements(WebDriverBy::cssSelector('input[maxlength="1"]'));
                foreach (str_split($apaarId) as $i => $digit) {
                    if (isset($apaarBoxes[$i])) {
                        $apaarBoxes[$i]->clear();
                        $apaarBoxes[$i]->sendKeys($digit);
                    }
                }
            } catch (\Throwable) {}
        }

        // ── Click Save / Submit ───────────────────────────────────────────────
        $saved = false;
        try {
            $clicked = $client->executeScript("
                const buttons = Array.from(document.querySelectorAll('button, input[type=submit]'));
                const saveBtn = buttons.find(b => {
                    const txt = (b.innerText || b.value || '').toLowerCase().trim();
                    return (txt.includes('save') || txt.includes('submit')) && !txt.includes('another');
                });
                if (saveBtn) {
                    saveBtn.click();
                    return true;
                }
                return false;
            ");
            if ($clicked) {
                $saved = true;
            }
        } catch (\Throwable) {}

        if (! $saved) {
            foreach (['button[type="submit"]', 'button.btn-save', 'button'] as $sel) {
                try {
                    $btns = $wd->findElements(WebDriverBy::cssSelector($sel));
                    foreach ($btns as $btn) {
                        $txt = strtolower(trim($btn->getText()));
                        if ((str_contains($txt, 'save') || str_contains($txt, 'submit')) && ! str_contains($txt, 'another')) {
                            $btn->click();
                            $saved = true;
                            break 2;
                        }
                    }
                } catch (\Throwable) {}
            }
        }

        if (! $saved) {
            return ['status' => 'error', 'message' => 'Save button could not be clicked'];
        }

        // ── Wait and check result with polling (up to 7 seconds) ──────────────
        $detectedResult = null;
        for ($waitTick = 0; $waitTick < 14; $waitTick++) {
            usleep(500000); // 500ms intervals

            // 1. Check URL redirect
            try {
                $currentUrl = $client->getCurrentURL();
                if (! str_contains($currentUrl, '/students/add')) {
                    $detectedResult = ['status' => 'success', 'message' => 'Student added successfully'];
                    break;
                }
            } catch (\Throwable) {}

            // 2. Execute script to inspect modals, toasts, popups, and validation errors
            try {
                $check = $client->executeScript("
                    const bodyText = document.body.innerText || '';

                    // Check for explicit success in dialog / modal
                    if (bodyText.includes('Student added successfully') ||
                        bodyText.includes('successfully added') ||
                        bodyText.includes('Student added') ||
                        bodyText.includes('saved successfully')) {

                        // Click OK / Close on modal to dismiss it
                        const btns = Array.from(document.querySelectorAll('button, [role=button]'));
                        const okBtn = btns.find(b => {
                            const t = (b.innerText || '').trim().toLowerCase();
                            return t === 'ok' || t === 'close' || t === 'done';
                        });
                        if (okBtn) {
                            try { okBtn.click(); } catch (e) {}
                        }
                        return { type: 'success', message: 'Student added successfully!' };
                    }

                    // Check for error modals or dialogs
                    const dialogs = document.querySelectorAll('[role=dialog], .modal, div[class*=modal], div[class*=popup], .alert-danger, .toast-error');
                    for (const d of dialogs) {
                        const txt = (d.innerText || '').trim();
                        if (txt.length > 3) {
                            const lower = txt.toLowerCase();
                            if (lower.includes('already exists') || lower.includes('duplicate') || lower.includes('failed') || lower.includes('error')) {
                                const btn = d.querySelector('button');
                                if (btn) { try { btn.click(); } catch(e) {} }
                                return { type: 'error', message: txt.replace(/\\n+/g, ' ') };
                            }
                        }
                    }

                    // Check field-level validation errors
                    const errEls = document.querySelectorAll('.invalid-feedback, .text-danger, span[class*=error], div[class*=error]');
                    const fieldErrors = [];
                    for (const el of errEls) {
                        if (el.closest('.react-datepicker, .custom-dropdown-wrapper')) continue;
                        const t = el.innerText.trim();
                        if (t.length > 2 && t.length < 150 && !fieldErrors.includes(t)) {
                            fieldErrors.push(t);
                        }
                    }
                    if (fieldErrors.length > 0) {
                        return { type: 'error', message: fieldErrors.join(' | ') };
                    }

                    return null;
                ");

                if ($check && isset($check['type'])) {
                    $detectedResult = ['status' => $check['type'], 'message' => $check['message']];
                    break;
                }
            } catch (\Throwable) {}
        }

        // Capture snapshot after outcome
        try {
            $client->takeScreenshot($storagePath . "/result_{$idx}.png");
        } catch (\Throwable) {}

        if ($detectedResult) {
            return $detectedResult;
        }

        // Check if form fields cleared after Save without errors
        try {
            $fn = $client->executeScript("
                const inp = document.querySelector('input[placeholder=\"Write here\"]');
                return inp ? inp.value.trim() : null;
            ");
            if ($fn === '') {
                return ['status' => 'success', 'message' => 'Student added successfully (form reset)'];
            }
        } catch (\Throwable) {}

        return ['status' => 'error', 'message' => 'Submission failed — no confirmation received or validation rejected'];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function clearAndType($element, string $value): void
    {
        $element->clear();
        if ($value !== '') {
            $element->sendKeys($value);
        }
    }

    private function normalizeDob(string $dob, string $targetFormat): string
    {
        if ($dob === '') return '';
        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y', 'm/d/Y', 'j/n/Y', 'Y/m/d'] as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $dob);
            if ($dt) return $dt->format($targetFormat);
        }
        return $dob;
    }

    private function writeStatus(array $data, string $file): void
    {
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    }

    private function finishFatal(Client $client, string $file, array $results, int $total, string $msg): void
    {
        $this->writeStatus([
            'started'   => true,
            'done'      => true,
            'fatal'     => $msg,
            'total'     => $total,
            'processed' => count($results),
            'results'   => $results,
        ], $file);
        try {
            $client->quit();
        } catch (\Throwable) {}
    }
}
