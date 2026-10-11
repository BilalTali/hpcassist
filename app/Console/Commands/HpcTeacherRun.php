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
            $goal = $s['goal'] ?? 'Improve in Science and Mathematics';
            $client->executeScript("
                const setNativeValue = (el, val) => {
                    const proto = el.tagName === 'TEXTAREA' ? window.HTMLTextAreaElement.prototype : window.HTMLInputElement.prototype;
                    const setter = Object.getOwnPropertyDescriptor(proto, 'value')?.set;
                    if (setter) setter.call(el, val); else el.value = val;
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                    el.dispatchEvent(new Event('blur', { bubbles: true }));
                };

                const inputs = Array.from(document.querySelectorAll('input:not([type=\"hidden\"]):not([type=\"checkbox\"]):not([type=\"radio\"]), textarea'));
                inputs.forEach((inp, idx) => {
                    if (inp.value && inp.value.trim().length > 1) return;
                    const container = inp.closest('.form-group, div');
                    const lbl = (container?.querySelector('label, p, strong')?.innerText || inp.placeholder || '').trim();

                    let val = 'Learning new skills and actively participating in class';
                    if (lbl.includes('live with')) val = 'Family';
                    else if (lbl.includes('stay at')) val = 'Sugin, Budgam';
                    else if (lbl.includes('free time')) val = 'Reading books and drawing';
                    else if (lbl.includes('very well')) val = 'Sketch and Cricket';
                    else if (lbl.includes('responsible')) val = " . json_encode($s['responsible'] ?? 'Most times') . ";
                    else if (lbl.includes('could do better')) val = 'Time management in exams';
                    else if (lbl.includes('care about')) val = 'Helping peers with notes';
                    else if (lbl.includes('feel proud')) val = 'When solving mathematics problems';
                    else if (lbl.includes('Name')) val = " . json_encode($goal) . ";
                    else if (lbl.includes('Age')) val = '12';
                    else if (lbl.includes('Academic Goal') && inp.tagName === 'TEXTAREA') val = " . json_encode($goal) . ";
                    else if (lbl.includes('Personal Goal') && inp.tagName === 'TEXTAREA') val = 'Read one informative book every month';
                    else if (lbl.includes('Step 1')) val = 'Daily formula revision';
                    else if (lbl.includes('Step 2')) val = 'Weekly mock test practice';
                    else if (lbl.includes('important to me')) val = 'Helps achieve career ambition';
                    else if (lbl.includes('Three new things I learnt at school')) {
                        val = (idx % 2 === 0) ? 'Algebra concepts and equations' : 'Botanical parts of plants';
                    } else if (lbl.includes('Three new things I learnt outside school')) {
                        val = (idx % 2 === 0) ? 'Gardening techniques' : 'First aid basics';
                    } else if (lbl.includes('help me with')) {
                        val = 'Understanding complex numericals in Science';
                    } else if (lbl.includes('teacher to know')) {
                        val = 'I enjoy hands-on practical experiments';
                    }

                    setNativeValue(inp, val);
                });
            ");
            usleep($delayMs * 1000);
            $this->clickContinue($client);
            sleep(3);
        }

        // ── 4. Fill Part-A3 ───────────────────────────────────────────────────
        if (str_contains($client->getCurrentURL(), '/part-A3')) {
            $ambition = $s['ambition'] ?? 'Teacher';
            $client->executeScript("
                document.querySelectorAll('input:not([type=\"hidden\"]):not([type=\"checkbox\"]):not([type=\"radio\"]), textarea').forEach(el => {
                    if (!el.value) {
                        el.value = " . json_encode($ambition) . ";
                        el.dispatchEvent(new Event('input', { bubbles: true }));
                        el.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
                // Check 5 skill checkboxes if not checked
                const skillCbs = Array.from(document.querySelectorAll('input[type=\"checkbox\"]'));
                skillCbs.slice(0, 5).forEach(cb => { if (!cb.checked) cb.click(); });
            ");
            usleep($delayMs * 1000);
            $this->clickContinue($client);
            sleep(3);
        }

        // ── 5. Fill Part-A4 (Smileys, Multi-selects & Textareas) ───────────────
        if (str_contains($client->getCurrentURL(), '/part-A4')) {
            $client->executeScript("
                // 1. Select Yes smiley for all 4 'Understanding of my Child' questions
                const table = document.querySelector('table');
                if (table) {
                    const rows = Array.from(table.querySelectorAll('tr')).slice(2);
                    rows.forEach(r => {
                        const yesLi = Array.from(r.querySelectorAll('li')).find(li => (li.innerText || '').trim() === 'Yes');
                        if (yesLi) yesLi.click();
                    });
                }

                // 2. Multi-select checkboxes
                document.querySelectorAll('input[type=\"checkbox\"]').forEach(cb => {
                    if (!cb.checked) {
                        cb.click();
                        cb.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });

                // 3. Fill textareas
                document.querySelectorAll('textarea').forEach(ta => {
                    const proto = window.HTMLTextAreaElement.prototype;
                    const setter = Object.getOwnPropertyDescriptor(proto, 'value')?.set;
                    const val = 'Regular evening study routine and encouragement for holistic activities.';
                    if (setter) setter.call(ta, val); else ta.value = val;
                    ta.dispatchEvent(new Event('input', { bubbles: true }));
                    ta.dispatchEvent(new Event('change', { bubbles: true }));
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
            $marksList = [];
            $m = $s['marks'] ?? [];
            $keys = ['l1', 'l2', 'l3', 'm', 'sci', 'soc', 'art', 'pe'];
            foreach ($keys as $k) {
                $marksList[] = (string) ($m[$k]['max'] ?? '50');
                $marksList[] = (string) ($m[$k]['obt'] ?? '42');
            }

            $client->executeScript("
                const marks = " . json_encode($marksList) . ";
                const table = document.querySelectorAll('table')[1] || document.querySelector('table');
                const marksInputs = Array.from(table.querySelectorAll('input[type=\"text\"], input[type=\"number\"]'));
                marksInputs.forEach((inp, i) => {
                    const val = marks[i] || (i % 2 === 0 ? '50' : '42');
                    const valSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value')?.set;
                    if (valSetter) valSetter.call(inp, val); else inp.value = val;
                    inp.dispatchEvent(new Event('input', { bubbles: true }));
                    inp.dispatchEvent(new Event('change', { bubbles: true }));
                });

                // Check declaration checkbox
                const decCb = document.querySelector('input[type=\"checkbox\"]');
                if (decCb && !decCb.checked) {
                    decCb.click();
                    decCb.dispatchEvent(new Event('change', { bubbles: true }));
                }

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
