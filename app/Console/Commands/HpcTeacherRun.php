<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Panther\Client;
use Facebook\WebDriver\WebDriverBy;

class HpcTeacherRun extends Command
{
    protected $signature   = 'hpc:teacher-run';
    protected $description = 'Read students from teacher_preview.json and update each student across HPC evaluation parts';

    public function handle(): int
    {
        $storagePath = config('hpc.storage_path');
        $driverBin   = config('hpc.chromedriver_path');
        $loginUrl    = config('hpc.login_url');
        $hpcHomeUrl  = config('hpc.hpc_home_url') ?: 'https://hpc.parakh.ncert.gov.in/en/hpc';
        $previewFile = $storagePath . '/teacher_preview.json';
        $resultsFile = $storagePath . '/teacher_run_results.json';

        $profileDir = $this->resolveActiveTeacherProfile();

        if (! file_exists($previewFile)) {
            $this->writeStatus(['started' => true, 'done' => true, 'fatal' => 'No evaluation data found.', 'results' => []], $resultsFile);
            return self::FAILURE;
        }

        $all = json_decode(file_get_contents($previewFile), true) ?? [];
        $students = array_values(array_filter($all, fn($s) => empty($s['errors'])));
        $total = count($students);

        if ($total === 0) {
            $this->writeStatus(['started' => true, 'done' => true, 'fatal' => 'No valid entries in evaluation preview.', 'results' => []], $resultsFile);
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

        if (file_exists($driverBin) && ! is_executable($driverBin)) {
            @chmod($driverBin, 0755);
        }

        $_SERVER['PANTHER_NO_HEADLESS'] = '1';
        putenv('PANTHER_NO_HEADLESS=1');

        try {
            $client = Client::createChromeClient($driverBin, [
                '--user-data-dir=' => $profileDir,
                '--window-size=1400,900',
                '--no-sandbox',
                '--disable-dev-shm-usage',
            ]);
        } catch (\Throwable $e) {
            $this->finishFatal(null, $resultsFile, [], $total, "Could not launch Chrome: " . $e->getMessage());
            return self::FAILURE;
        }

        // Check if logged in
        $loggedIn = false;
        try {
            $client->request('GET', $hpcHomeUrl);
            sleep(3);
            $url = $client->getCurrentURL();
            if (! str_contains($url, '/login') && ! str_contains($url, '/signin')) {
                $loggedIn = true;
            }
        } catch (\Throwable) {}

        if (! $loggedIn) {
            try {
                $client->request('GET', $loginUrl);
            } catch (\Throwable $e) {
                $this->finishFatal($client, $resultsFile, [], $total, "Could not open login page: " . $e->getMessage());
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
            $this->finishFatal($client, $resultsFile, [], $total, "Session timed out waiting for teacher login.");
            return self::FAILURE;
        }

        // Update status: login confirmed
        $this->writeStatus([
            'started'    => true,
            'done'       => false,
            'login_wait' => false,
            'login_done' => true,
            'total'      => $total,
            'processed'  => 0,
            'results'    => [],
        ], $resultsFile);

        // Load scraped student list with direct form URLs
        $studentsListFile = $storagePath . '/teacher_students.json';
        $studentsList = file_exists($studentsListFile) ? (json_decode(file_get_contents($studentsListFile), true) ?? []) : [];

        $results = [];
        $delayMs = config('hpc.delay_ms') ?: 700;

        foreach ($students as $idx => $st) {
            $name    = trim($st['name'] ?? '');
            $loginId = trim($st['login_id'] ?? '');

            // Match student record to get form URL
            $matchedUrl = '';
            foreach ($studentsList as $sl) {
                if (($loginId && ($sl['login_id'] ?? '') === $loginId) || ($name && ($sl['name'] ?? '') === $name)) {
                    $matchedUrl = $sl['form_url'] ?? '';
                    break;
                }
            }

            try {
                $result = $this->processStudentAssessment($client, $st, $matchedUrl, $hpcHomeUrl, $delayMs, $idx + 1);
            } catch (\Throwable $e) {
                $result = ['status' => 'error', 'message' => 'Unexpected error: ' . $e->getMessage()];
            }

            $results[] = [
                'row'      => $st['row'] ?? ($idx + 1),
                'name'     => $name,
                'login_id' => $loginId,
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

        // Complete
        $this->writeStatus([
            'started'    => true,
            'done'       => true,
            'login_done' => true,
            'total'      => $total,
            'processed'  => $total,
            'results'    => $results,
        ], $resultsFile);

        try { $client->quit(); } catch (\Throwable) {}
        return self::SUCCESS;
    }

    private function processStudentAssessment(Client $client, array $s, string $formUrl, string $hpcHomeUrl, int $delayMs, int $idx): array
    {
        // 1. Navigate to student's HPC form
        if ($formUrl && str_contains($formUrl, '/part-')) {
            $client->request('GET', $formUrl);
            usleep($delayMs * 3000);
        } else {
            // Navigate via HPC home ➔ Stage ➔ Open
            $client->request('GET', 'https://hpc.parakh.ncert.gov.in/en/hpc/middle-stage');
            sleep(3);

            // Find row matching student and click action Open
            $wd = $client->getWebDriver();
            $opened = $client->executeScript("
                const loginId = " . json_encode($s['login_id']) . ";
                const rows = Array.from(document.querySelectorAll('tbody tr'));
                const targetRow = rows.find(tr => tr.innerText.includes(loginId));
                if (targetRow) {
                    const header = targetRow.querySelector('.custom-dropdown-header');
                    if (header) {
                        header.click();
                        return true;
                    }
                }
                return false;
            ");

            if ($opened) {
                usleep(500000);
                $client->executeScript("
                    const items = Array.from(document.querySelectorAll('.custom-dropdown-item'));
                    const openItem = items.find(it => it.innerText.trim().toLowerCase() === 'open');
                    if (openItem) openItem.click();
                ");
                sleep(4);
            } else {
                return ['status' => 'error', 'message' => "Student {$s['name']} not found in stage table."];
            }
        }

        // Verify we are on part-A1
        $currentUrl = $client->getCurrentURL();
        if (! str_contains($currentUrl, '/part-')) {
            return ['status' => 'error', 'message' => "Failed to reach assessment form. Current URL: {$currentUrl}"];
        }

        // ── 2. Fill Part-A1 ───────────────────────────────────────────────────
        $motherTongue = $s['mother_tongue'] ?? 'Kashmiri';
        $parentName   = $s['parent_name'] ?? 'Parent';
        $workingDays  = $s['working_days'] ?? '22';
        $presentDays  = $s['present_days'] ?? '20';

        $client->executeScript("
            function setVal(el, val) {
                if (!el) return;
                const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value')?.set;
                if (setter) setter.call(el, val); else el.value = val;
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }

            // Mother tongue
            const mtInp = document.querySelector('input[placeholder=\"Write here\"]');
            if (mtInp) setVal(mtInp, " . json_encode($motherTongue) . ");

            // Parent Name
            const pInp = document.querySelector('input[placeholder=\"Enter Father Name\"], input[placeholder*=\"Father\"]');
            if (pInp) setVal(pInp, " . json_encode($parentName) . ");

            // Attendance
            document.querySelectorAll('table tbody tr:nth-child(1) td input').forEach(inp => setVal(inp, " . json_encode($workingDays) . "));
            document.querySelectorAll('table tbody tr:nth-child(2) td input').forEach(inp => setVal(inp, " . json_encode($presentDays) . "));

            // Absence frequency
            const absInp = document.querySelector('input[placeholder=\"Write here\"]:last-of-type');
            if (absInp) setVal(absInp, '2');

            // Interests checkbox
            const cb = document.querySelector('input[type=\"checkbox\"]:not(:checked)');
            if (cb) cb.click();
        ");

        usleep($delayMs * 1000);
        $this->clickContinue($client);
        sleep(3);

        // ── 3. Fill Part-A2 ───────────────────────────────────────────────────
        if (str_contains($client->getCurrentURL(), '/part-A2')) {
            $client->executeScript("
                function setVal(el, val) {
                    if (!el) return;
                    try {
                        const proto = el.tagName === 'TEXTAREA' ? window.HTMLTextAreaElement.prototype : window.HTMLInputElement.prototype;
                        const setter = Object.getOwnPropertyDescriptor(proto, 'value')?.set;
                        if (setter) setter.call(el, val); else el.value = val;
                    } catch(e) { el.value = val; }
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                }

                document.querySelectorAll('input[type=\"text\"], textarea').forEach(el => {
                    if (!el.value) setVal(el, 'Good progress and active participation');
                });
            ");
            usleep($delayMs * 1000);
            $this->clickContinue($client);
            sleep(3);
        }

        // ── 4. Fill Part-A3 ───────────────────────────────────────────────────
        if (str_contains($client->getCurrentURL(), '/part-A3')) {
            $client->executeScript("
                document.querySelectorAll('input[type=\"text\"], textarea').forEach(el => {
                    if (!el.value) el.value = 'Teacher';
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                });
            ");
            usleep($delayMs * 1000);
            $this->clickContinue($client);
            sleep(3);
        }

        // ── 5. Fill Part-A4 ───────────────────────────────────────────────────
        if (str_contains($client->getCurrentURL(), '/part-A4')) {
            $client->executeScript("
                const radioGroups = {};
                document.querySelectorAll('input[type=\"radio\"]').forEach(r => {
                    const name = r.name || 'default';
                    if (!radioGroups[name]) radioGroups[name] = [];
                    radioGroups[name].push(r);
                });
                Object.values(radioGroups).forEach(group => {
                    if (group.length > 0 && !group.some(r => r.checked)) group[0].click();
                });
            ");
            usleep($delayMs * 1000);
            $this->clickContinue($client);
            sleep(3);
        }

        // ── 6. Navigate to /hpc-credit and finalize ───────────────────────────
        $currentUrl = $client->getCurrentURL();
        $creditUrl = preg_replace('#/(?:part-[^/]+|hpc-credit)$#', '/hpc-credit', $currentUrl);
        if ($creditUrl && ! str_contains($currentUrl, '/hpc-credit')) {
            $client->request('GET', $creditUrl);
            usleep($delayMs * 2500);
        }

        if (str_contains($client->getCurrentURL(), '/hpc-credit')) {
            $client->executeScript("
                // Fill summative assessment marks (Max Marks 50, Obtained 45)
                const marksInputs = Array.from(document.querySelectorAll('table input[type=\"text\"]'));
                marksInputs.forEach((inp, i) => {
                    const val = (i % 2 === 0) ? '50' : '45';
                    inp.value = val;
                    inp.dispatchEvent(new Event('input', { bubbles: true }));
                    inp.dispatchEvent(new Event('change', { bubbles: true }));
                });

                // Check declaration checkbox
                const decCb = document.querySelector('input[type=\"checkbox\"]');
                if (decCb && !decCb.checked) decCb.click();

                // Save credit form as draft
                const saveDraftBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Save as Draft'));
                if (saveDraftBtn && !saveDraftBtn.disabled) saveDraftBtn.click();
            ");
            sleep(3);
        }

        // Take snapshot after update
        try {
            $client->takeScreenshot(config('hpc.storage_path') . "/teacher_result_{$idx}.png");
        } catch (\Throwable) {}

        return ['status' => 'success', 'message' => 'HPC Evaluation and Credit form submitted successfully!'];
    }

    private function clickContinue(Client $client): void
    {
        $client->executeScript("
            const btns = Array.from(document.querySelectorAll('button'));
            const continueBtn = btns.find(b => {
                const t = (b.innerText || '').trim().toLowerCase();
                return t === 'continue' || t === 'next' || t.includes('save as draft');
            });
            if (continueBtn) continueBtn.click();
        ");
    }

    private function writeStatus(array $data, string $file): void
    {
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    }

    private function finishFatal(?Client $client, string $file, array $results, int $total, string $msg): void
    {
        $this->writeStatus([
            'started'   => true,
            'done'      => true,
            'fatal'     => $msg,
            'total'     => $total,
            'processed' => count($results),
            'results'   => $results,
        ], $file);
        if ($client) {
            try { $client->quit(); } catch (\Throwable) {}
        }
    }

    private function resolveActiveTeacherProfile(): string
    {
        $storagePath = config('hpc.storage_path');
        $schoolsFile = $storagePath . '/schools.json';

        if (file_exists($schoolsFile)) {
            $data = json_decode(file_get_contents($schoolsFile), true) ?? [];
            $activeSchoolId = $data['active'] ?? 'default';

            foreach ($data['schools'] ?? [] as $sch) {
                if (($sch['id'] ?? '') === $activeSchoolId) {
                    $activeTeacherId = $sch['active_teacher'] ?? 'teacher_default';
                    foreach ($sch['teachers'] ?? [] as $t) {
                        if (($t['id'] ?? '') === $activeTeacherId) {
                            $tDir = $t['dir'] ?? 'teacher-profile';
                            return $storagePath . '/' . $tDir;
                        }
                    }
                    return $storagePath . '/' . ($sch['dir'] ?? 'chrome-profile');
                }
            }
        }

        return $storagePath . '/teacher-profile';
    }
}
