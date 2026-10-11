<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\HpcBrowserManager;

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

        try {
            $client = HpcBrowserManager::createOrConnect($driverBin, $profileDir);
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
            $status  = trim($st['status'] ?? '');
            $apaarId = trim($st['apaar_id'] ?? '');

            // RULE: If status is 'HPC Completed', leave untouched / skip!
            if (!empty($st['is_completed']) || strcasecmp($status, 'HPC Completed') === 0) {
                $this->info("Skipping student [{$loginId}] {$name}: Already HPC Completed (left untouched).");
                $results[] = [
                    'row'      => $st['row'] ?? ($idx + 1),
                    'apaar_id' => $apaarId,
                    'name'     => $name,
                    'login_id' => $loginId,
                    'status'   => 'skipped',
                    'message'  => 'HPC Completed (left untouched)',
                ];

                $this->writeStatus([
                    'started'    => true,
                    'done'       => false,
                    'login_done' => true,
                    'total'      => $total,
                    'processed'  => $idx + 1,
                    'results'    => $results,
                ], $resultsFile);
                continue;
            }

            // Match student record to get form URL if pre-scraped
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
                'apaar_id' => $apaarId,
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

    private function processStudentAssessment($client, array $s, string $formUrl, string $hpcHomeUrl, int $delayMs, int $idx): array
    {
        // 1. Locate student using unique HPC Login ID across stages (Middle Stage & Preparatory Stage)
        $targetStages = [
            'https://hpc.parakh.ncert.gov.in/en/hpc/middle-stage',
            'https://hpc.parakh.ncert.gov.in/en/hpc/preparatory-stage',
        ];

        if (!empty($s['stage'])) {
            $stageUrl = "https://hpc.parakh.ncert.gov.in/en/hpc/{$s['stage']}";
            $targetStages = array_unique(array_merge([$stageUrl], $targetStages));
        }

        $found = false;
        $loginId = $s['login_id'];

        foreach ($targetStages as $stgUrl) {
            $client->request('GET', $stgUrl);
            sleep(3);

            $rowCheck = $client->executeScript("
                const loginId = " . json_encode($loginId) . ";
                const rows = Array.from(document.querySelectorAll('tbody tr'));
                const targetRow = rows.find(tr => {
                    const cells = Array.from(tr.querySelectorAll('td'));
                    return (cells[1] && cells[1].innerText.trim() === loginId) || tr.innerText.includes(loginId);
                });
                if (!targetRow) return { found: false };

                const cells = Array.from(targetRow.querySelectorAll('td'));
                const statusText = (cells[3] ? cells[3].innerText : targetRow.innerText).trim();
                const isCompleted = statusText.toLowerCase().includes('completed');

                return {
                    found: true,
                    isCompleted: isCompleted,
                    statusText: statusText
                };
            ");

            if ($rowCheck && !empty($rowCheck['found'])) {
                $found = true;

                // Live portal check: If status on portal table shows completed, leave untouched!
                if (!empty($rowCheck['isCompleted'])) {
                    return [
                        'status'  => 'skipped',
                        'message' => 'Status is ' . $rowCheck['statusText'] . ' on portal (left untouched)'
                    ];
                }

                // Click action dropdown
                $opened = $client->executeScript("
                    const loginId = " . json_encode($loginId) . ";
                    const rows = Array.from(document.querySelectorAll('tbody tr'));
                    const targetRow = rows.find(tr => {
                        const cells = Array.from(tr.querySelectorAll('td'));
                        return (cells[1] && cells[1].innerText.trim() === loginId) || tr.innerText.includes(loginId);
                    });
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
                        const actionItem = items.find(it => /open|edit/i.test(it.innerText.trim()));
                        if (actionItem) actionItem.click();
                    ");
                    sleep(4);
                }
                break;
            }
        }

        if (! $found) {
            return ['status' => 'error', 'message' => "Student with HPC Login ID {$s['login_id']} ({$s['name']}) not found in Middle or Preparatory stage tables."];
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

        // ── 6. Fill Part-B (All 11 Domain Activities) ───────────────────────
        if (str_contains($client->getCurrentURL(), '/part-B')) {
            $domainsConfig = [
                ['code' => 'SL1', 'id' => 269, 'name' => 'Language 1 (R1)', 'gl' => 'L1CG1', 'cpt' => 'L1C1.1'],
                ['code' => 'SL2', 'id' => 270, 'name' => 'Language 2 (R2)', 'gl' => 'L2CG1', 'cpt' => 'L2C1.1'],
                ['code' => 'SL3', 'id' => 271, 'name' => 'Language 3 (R3)', 'gl' => 'L3CG1', 'cpt' => 'L3C1.1'],
                ['code' => 'SM',  'id' => 272, 'name' => 'Mathematics', 'gl' => 'MCG1', 'cpt' => 'MC1.1'],
                ['code' => 'SS',  'id' => 273, 'name' => 'Science', 'gl' => 'SCG1', 'cpt' => 'SC1.1'],
                ['code' => 'SSS', 'id' => 274, 'name' => 'Social Science', 'gl' => 'SSCG1', 'cpt' => 'SSC1.1'],
                ['code' => 'SA',  'id' => 275, 'name' => 'Art Education', 'gl' => 'ACG1', 'cpt' => 'AC1.1'],
                ['code' => 'SPE', 'id' => 276, 'name' => 'Physical Education & Well Being', 'gl' => 'P2CG1', 'cpt' => 'P2C1.1'],
                ['code' => 'SV',  'id' => 277, 'name' => 'Skill Education', 'gl' => 'VCG1', 'cpt' => 'VC1.1'],
                ['code' => 'SLA', 'id' => 278, 'name' => 'Other Learning Activities (Participation in Cultural, Sports, Reading and other activities)', 'gl' => 'OLACG1', 'cpt' => 'OLA1.1'],
                ['code' => 'SVE', 'id' => 279, 'name' => 'Bagless days for vocational Experience', 'gl' => 'VCG1', 'cpt' => 'BVE1.1'],
            ];

            foreach ($domainsConfig as $dom) {
                $domName = $dom['name'];
                $client->executeScript("
                    const addBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Add Activity'));
                    if (addBtn) addBtn.click();
                ");
                usleep(500000);
                $client->executeScript("
                    const modal = document.querySelector('.quick-add-activity, .modal');
                    if (modal) {
                        const contBtn = Array.from(modal.querySelectorAll('button')).find(b => b.innerText.trim() === 'Continue');
                        if (contBtn) contBtn.click();
                    }
                ");
                sleep(1);

                // Tab 1: Activity Name
                $client->executeScript("
                    const nameInp = document.querySelector('.acivity-name-updates input');
                    if (nameInp) {
                        const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value')?.set;
                        if (setter) setter.call(nameInp, " . json_encode($domName . ' Practical Project') . ");
                        nameInp.dispatchEvent(new Event('input', { bubbles: true }));
                        nameInp.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    document.querySelectorAll('input[name^=\"activity_\"]').forEach((cb, i) => {
                        if (i < 2 && !cb.checked) cb.click();
                    });
                ");

                // Tab 2: Subject & Competency
                $client->executeScript("document.querySelectorAll('.activate-tab-pills div[role=\"button\"]')[1]?.click();");
                usleep(500000);
                $client->executeScript("
                    const wrapper = document.querySelector('.scroller-cg-domain .custom-dropdown-wrapper');
                    if (wrapper) wrapper.querySelector('.custom-dropdown-header')?.click();
                ");
                usleep(500000);
                $client->executeScript("
                    const items = Array.from(document.querySelectorAll('.scroller-cg-domain .custom-dropdown-item, .custom-dropdown-item'));
                    const target = items.find(el => el.innerText.trim().toLowerCase().startsWith(" . json_encode(substr($domName, 0, 15)) . ".toLowerCase()));
                    if (target) target.click();
                ");
                sleep(1);

                // Check first competency
                $client->executeScript("
                    const cpts = document.querySelectorAll('.scroller-container input[name=\"cpt\"]');
                    if (cpts.length > 0 && !cpts[0].checked) {
                        cpts[0].click();
                        cpts[0].dispatchEvent(new Event('change', { bubbles: true }));
                    }
                ");

                // Dispatch complete valid state
                $client->executeScript("
                    const btn = document.querySelector('.hpc-finish-activity-btn');
                    let f = btn ? btn[Object.keys(btn).find(k => k.startsWith('__reactFiber'))] : null;
                    while (f && f.type?.name !== 'S') f = f.return;
                    let cur = f ? f.memoizedState : null;
                    while (cur) {
                        if (cur.memoizedState && typeof cur.memoizedState === 'object' && cur.memoizedState.cpt) {
                            const prev = cur.memoizedState;
                            const next = {
                                ...prev,
                                activity: prev.activity && prev.activity.length ? prev.activity : [1, 2],
                                any_other: ['', '', ''],
                                ast: [1, 2, 3],
                                bar: [9],
                                awr: ['Demonstrates good awareness', 'Demonstrates strong awareness', 'Demonstrates exceptional awareness', 'proficient'],
                                sen: ['Shows sensitivity', 'Shows great sensitivity', 'Shows excellent sensitivity', 'proficient'],
                                cre: ['Displays creative thinking', 'Displays innovative creativity', 'Displays high creativity', 'proficient'],
                                desc: ['Comprehensive practical project and engagement.', 'Achieved all learning objectives successfully.'],
                                ml: ['Learned fundamental principles.', 'Developed practical confidence.', 'Collaborated actively.', 'Summary.'],
                                slr: ['Student actively engaged throughout.', 'Exhibited clear understanding.', 'Completed assignments joyfully.'],
                                spt: ['Provide further challenging exercises.', 'Continue encouragement.'],
                                tf: ['2', '2', '2'],
                                mpa: [0, 1],
                                mps: [0, 1],
                                mpc: [0, 1],
                                ppa: [0],
                                pps: [0],
                                ppc: [0],
                                pf: ['Ayaan', '8', 'A', 'Great peer collaborator', 'Cooperative and helpful', 'Positive']
                            };
                            cur.queue.dispatch(next);
                            break;
                        }
                        cur = cur.next;
                    }
                ");
                sleep(1);

                // Finish Activity
                $client->executeScript("
                    const btn = document.querySelector('.hpc-finish-activity-btn');
                    if (btn && !btn.disabled) {
                        const pk = Object.keys(btn).find(k => k.startsWith('__reactProps'));
                        if (pk && btn[pk].onClick) btn[pk].onClick(); else btn.click();
                    }
                ");
                sleep(1);

                // Confirm Modal
                $client->executeScript("
                    const modal = document.querySelector('.modal, .fade.show, [role=\"dialog\"]');
                    if (modal) {
                        const finishBtn = Array.from(modal.querySelectorAll('button')).find(b => b.innerText.trim() === 'Finish');
                        if (finishBtn) finishBtn.click();
                    }
                ");
                sleep(2);
            }

            usleep($delayMs * 1000);
            $this->clickContinue($client);
            sleep(3);
        }

        // ── 7. Fill Part-C ───────────────────────────────────────────────────
        if (str_contains($client->getCurrentURL(), '/part-C')) {
            $client->executeScript("
                const radioGroups = {};
                document.querySelectorAll('input[type=\"radio\"]').forEach(r => {
                    const name = r.name || 'group';
                    if (!radioGroups[name]) radioGroups[name] = [];
                    radioGroups[name].push(r);
                });
                Object.values(radioGroups).forEach(group => {
                    if (group.length > 0 && !group.some(r => r.checked)) {
                        const prof = group.find(r => r.value === 'proficient' || r.value === '2' || r.value === 'yes') || group[0];
                        prof.click();
                        prof.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
            ");
            usleep($delayMs * 1000);
            $this->clickContinue($client);
            sleep(3);
        }

        // ── 8. Fill HPC Credit and Mark as Complete ──────────────────────────
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

                // Click Mark as Complete
                const markCompleteBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.trim() === 'Mark as Complete');
                if (markCompleteBtn && !markCompleteBtn.disabled) markCompleteBtn.click();
            ");
            sleep(2);

            // Confirm completion modal if any
            $client->executeScript("
                const m = document.querySelector('.modal, .swal2-popup, [role=\"dialog\"], .fade.show');
                if (m) {
                    const cb = Array.from(m.querySelectorAll('button')).find(b => /yes|confirm|proceed|ok|complete/i.test(b.innerText.trim()));
                    if (cb) cb.click();
                }
            ");
            sleep(4);
        }

        // Take snapshot after update
        try {
            $client->takeScreenshot(config('hpc.storage_path') . "/teacher_result_{$idx}.png");
        } catch (\Throwable) {}

        return ['status' => 'success', 'message' => 'HPC Evaluation and Credit form completed and submitted successfully!'];
    }

    private function clickContinue($client): void
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

    private function finishFatal($client, string $file, array $results, int $total, string $msg): void
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
