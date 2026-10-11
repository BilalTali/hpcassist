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

            // RULE 1: If demo sample guide row, skip immediately!
            if (!empty($st['is_demo']) || ($st['login_id'] ?? '') === 'DEMO_SAMPLE' || stripos($name, 'SAMPLE') !== false || stripos($name, 'DEMO') !== false) {
                $this->info("Skipping demo sample guide row: {$name}.");
                $results[] = [
                    'row'      => $st['row'] ?? ($idx + 1),
                    'apaar_id' => $apaarId,
                    'name'     => $name,
                    'login_id' => $loginId,
                    'status'   => 'skipped',
                    'message'  => 'Demo Sample (Guide only - skipped)',
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

            // RULE 2: If status is 'HPC Completed', leave untouched / skip!
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
        $motherTongue  = $s['mother_tongue'] ?? 'Kashmiri';
        $parentName    = $s['parent_name'] ?? 'Parent';
        $workingDays   = $s['working_days'] ?? '220';
        $presentDays   = $s['present_days'] ?? '204';
        $absenceReason = $s['absence_reason'] ?? 'Illness / fever';
        $interest      = $s['interest'] ?? 'Reading';

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
            document.querySelectorAll('table tbody tr:nth-child(1) td input').forEach(inp => setVal(inp, " . json_encode((string)$workingDays) . "));
            document.querySelectorAll('table tbody tr:nth-child(2) td input').forEach(inp => setVal(inp, " . json_encode((string)$presentDays) . "));

            // Absence frequency / reason
            const absInp = document.querySelector('input[placeholder=\"Write here\"]:last-of-type');
            if (absInp) setVal(absInp, " . json_encode($absenceReason) . ");

            // Interests checkbox
            const interestTarget = " . json_encode(strtolower($interest)) . ";
            const cbs = Array.from(document.querySelectorAll('input[type=\"checkbox\"]'));
            const matchingCb = cbs.find(c => {
                const label = (c.closest('label')?.innerText || '').toLowerCase();
                return label.includes(interestTarget);
            }) || cbs[0];
            if (matchingCb && !matchingCb.checked) matchingCb.click();
        ");

        usleep($delayMs * 1000);
        $this->clickContinue($client);
        sleep(3);

        // ── 3. Fill Part-A2 ───────────────────────────────────────────────────
        if (str_contains($client->getCurrentURL(), '/part-A2')) {
            $a2Values = [
                'live_with'       => $s['live_with'] ?? 'Parents and Grandparents',
                'stay_at'         => $s['stay_at'] ?? 'Sugin, Budgam',
                'free_time'       => $s['free_time'] ?? 'Reading books and cricket',
                'do_well'         => $s['do_well'] ?? 'Solving puzzles and sketching',
                'responsible'     => $s['responsible'] ?? 'Most times',
                'could_improve'   => $s['could_improve'] ?? 'Time management in exams',
                'care_about'      => $s['care_about'] ?? 'Helping classmates with notes',
                'feel_proud'      => $s['feel_proud'] ?? 'When solving difficult math problems',
                'academic_goal'   => $s['academic_goal'] ?? 'Excel in Science and Mathematics',
                'goal_importance' => $s['goal_importance'] ?? 'Build strong conceptual foundations for engineering',
                'goal_step1'      => $s['goal_step1'] ?? 'Daily formula revision and note making',
                'goal_step2'      => $s['goal_step2'] ?? 'Weekly practice test solving',
                'personal_goal'   => $s['personal_goal'] ?? 'Read one informative book every month',
                'learnt_school'   => $s['learnt_school'] ?? 'Algebraic equations and botanical plant parts',
                'learnt_outside'  => $s['learnt_outside'] ?? 'Kitchen gardening and basic cycling repair',
                'teacher_help'    => $s['teacher_help'] ?? 'Guidance in laboratory practical experiments',
                'teacher_know'    => $s['teacher_know'] ?? 'I learn best through hands-on practical activities',
            ];

            $client->executeScript("
                const a2 = " . json_encode($a2Values) . ";
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
                    const lbl = (container?.querySelector('label, p, strong')?.innerText || inp.placeholder || '').toLowerCase();

                    let val = a2.academic_goal;
                    if (lbl.includes('live with')) val = a2.live_with;
                    else if (lbl.includes('stay at')) val = a2.stay_at;
                    else if (lbl.includes('free time')) val = a2.free_time;
                    else if (lbl.includes('very well') || lbl.includes('do well')) val = a2.do_well;
                    else if (lbl.includes('responsible')) val = a2.responsible;
                    else if (lbl.includes('could do better') || lbl.includes('improve')) val = a2.could_improve;
                    else if (lbl.includes('care about')) val = a2.care_about;
                    else if (lbl.includes('feel proud')) val = a2.feel_proud;
                    else if (lbl.includes('personal goal')) val = a2.personal_goal;
                    else if (lbl.includes('academic goal') || lbl.includes('goal')) val = a2.academic_goal;
                    else if (lbl.includes('step 1')) val = a2.goal_step1;
                    else if (lbl.includes('step 2')) val = a2.goal_step2;
                    else if (lbl.includes('important to me') || lbl.includes('importance')) val = a2.goal_importance;
                    else if (lbl.includes('learnt at school')) val = a2.learnt_school;
                    else if (lbl.includes('learnt outside school')) val = a2.learnt_outside;
                    else if (lbl.includes('help me with') || lbl.includes('help')) val = a2.teacher_help;
                    else if (lbl.includes('teacher to know') || lbl.includes('teacher know')) val = a2.teacher_know;

                    setNativeValue(inp, val);
                });
            ");
            usleep($delayMs * 1000);
            $this->clickContinue($client);
            sleep(3);
        }

        // ── 4. Fill Part-A3 ───────────────────────────────────────────────────
        if (str_contains($client->getCurrentURL(), '/part-A3')) {
            $a3Values = [
                'ambition'       => $s['ambition'] ?? 'Teacher',
                'ambition_how'   => $s['ambition_how'] ?? 'Consistent daily study and active curiosity',
                'skill'          => $s['skill'] ?? 'Critical Thinking',
                'focus_subjects' => $s['focus_subjects'] ?? 'Mathematics and Physical Sciences',
                'habits_build'   => $s['habits_build'] ?? 'Disciplined reading and punctual routine',
                'mentor'         => $s['mentor'] ?? 'Bilal Ahmad Tali',
            ];

            $client->executeScript("
                const a3 = " . json_encode($a3Values) . ";
                const setNativeValue = (el, val) => {
                    const proto = el.tagName === 'TEXTAREA' ? window.HTMLTextAreaElement.prototype : window.HTMLInputElement.prototype;
                    const setter = Object.getOwnPropertyDescriptor(proto, 'value')?.set;
                    if (setter) setter.call(el, val); else el.value = val;
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                };

                const inputs = Array.from(document.querySelectorAll('input:not([type=\"hidden\"]):not([type=\"checkbox\"]):not([type=\"radio\"]), textarea'));
                inputs.forEach(inp => {
                    if (inp.value && inp.value.trim().length > 1) return;
                    const container = inp.closest('.form-group, div');
                    const lbl = (container?.querySelector('label, p, strong')?.innerText || inp.placeholder || '').toLowerCase();

                    let val = a3.ambition;
                    if (lbl.includes('ambition') || lbl.includes('want to become')) val = a3.ambition;
                    else if (lbl.includes('how') || lbl.includes('achieve')) val = a3.ambition_how;
                    else if (lbl.includes('subject') || lbl.includes('focus')) val = a3.focus_subjects;
                    else if (lbl.includes('habit')) val = a3.habits_build;
                    else if (lbl.includes('mentor') || lbl.includes('role model')) val = a3.mentor;

                    setNativeValue(inp, val);
                });

                // Check skill checkboxes matching skill name or first 5
                const targetSkill = a3.skill.toLowerCase();
                const skillCbs = Array.from(document.querySelectorAll('input[type=\"checkbox\"]'));
                const matchedCb = skillCbs.find(cb => {
                    const label = (cb.closest('label')?.innerText || '').toLowerCase();
                    return label.includes(targetSkill);
                });
                if (matchedCb && !matchedCb.checked) matchedCb.click();
                skillCbs.slice(0, 5).forEach(cb => { if (!cb.checked) cb.click(); });
            ");
            usleep($delayMs * 1000);
            $this->clickContinue($client);
            sleep(3);
        }

        // ── 5. Fill Part-A4 (Smileys, Multi-selects & Textareas) ───────────────
        if (str_contains($client->getCurrentURL(), '/part-A4')) {
            $parentFeedback = $s['parent_feedback'] ?? 'Regular evening study observed; keen interest in science activities';
            $pMotivation    = $s['p_motivation'] ?? 'Yes';

            $client->executeScript("
                const pFeedback = " . json_encode($parentFeedback) . ";
                const targetRating = " . json_encode($pMotivation) . ";

                // 1. Select smiley for 'Understanding of my Child' questions
                const table = document.querySelector('table');
                if (table) {
                    const rows = Array.from(table.querySelectorAll('tr')).slice(2);
                    rows.forEach(r => {
                        const targetLi = Array.from(r.querySelectorAll('li')).find(li => (li.innerText || '').trim().toLowerCase() === targetRating.toLowerCase())
                                      || Array.from(r.querySelectorAll('li')).find(li => (li.innerText || '').trim() === 'Yes');
                        if (targetLi) targetLi.click();
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
                    if (setter) setter.call(ta, pFeedback); else ta.value = pFeedback;
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
            $actTitle       = $s['act_title'] ?? '';
            $actDesc        = $s['act_desc'] ?? 'Comprehensive practical project and engagement.';
            $actLevel       = strtolower($s['act_level'] ?? 'proficient');
            $actLearnt      = $s['act_learnt'] ?? 'Learned fundamental concepts and practical application';
            $actInteresting = $s['act_interesting'] ?? 'Collaborative experiments and model making';
            $actPractice    = $s['act_practice'] ?? 'Further numerical practice and time management';
            $actPeerName    = $s['act_peer_name'] ?? 'Ayaan';
            $actPeerFeedback= $s['act_peer_feedback'] ?? 'Cooperative, helpful, and explains concepts clearly';
            $actSupportPlan = $s['act_support_plan'] ?? 'Provide challenging tasks and continuous encouragement';

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
                $currTitle = $actTitle ? "{$domName} - {$actTitle}" : "{$domName} Practical Project";

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
                        if (setter) setter.call(nameInp, " . json_encode($currTitle) . ");
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
                    const actLevelVal = " . json_encode($actLevel) . ";
                    const actDescVal = " . json_encode($actDesc) . ";
                    const actLearntVal = " . json_encode($actLearnt) . ";
                    const actInterestingVal = " . json_encode($actInteresting) . ";
                    const actPracticeVal = " . json_encode($actPractice) . ";
                    const actPeerNameVal = " . json_encode($actPeerName) . ";
                    const actPeerFeedbackVal = " . json_encode($actPeerFeedback) . ";
                    const actSupportPlanVal = " . json_encode($actSupportPlan) . ";

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
                                awr: ['Demonstrates good awareness', 'Demonstrates strong awareness', 'Demonstrates exceptional awareness', actLevelVal],
                                sen: ['Shows sensitivity', 'Shows great sensitivity', 'Shows excellent sensitivity', actLevelVal],
                                cre: ['Displays creative thinking', 'Displays innovative creativity', 'Displays high creativity', actLevelVal],
                                desc: [actDescVal, 'Achieved all learning objectives successfully.'],
                                ml: [actLearntVal, 'Developed practical confidence.', 'Collaborated actively.', actInterestingVal],
                                slr: ['Student actively engaged throughout.', actPracticeVal, 'Completed assignments joyfully.'],
                                spt: [actSupportPlanVal, 'Continue encouragement.'],
                                tf: ['2', '2', '2'],
                                mpa: [0, 1],
                                mps: [0, 1],
                                mpc: [0, 1],
                                ppa: [0],
                                pps: [0],
                                ppc: [0],
                                pf: [actPeerNameVal, '8', 'A', actPeerFeedbackVal, 'Cooperative and helpful', 'Positive']
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

            $remarks = $s['remarks'] ?? 'Consistent performer with excellent curiosity and strong analytical reasoning';

            $client->executeScript("
                const marks = " . json_encode($marksList) . ";
                const teacherRemarks = " . json_encode($remarks) . ";
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

                // Remarks if textarea exists
                const remTa = document.querySelector('textarea');
                if (remTa) {
                    const setter = Object.getOwnPropertyDescriptor(window.HTMLTextAreaElement.prototype, 'value')?.set;
                    if (setter) setter.call(remTa, teacherRemarks); else remTa.value = teacherRemarks;
                    remTa.dispatchEvent(new Event('input', { bubbles: true }));
                    remTa.dispatchEvent(new Event('change', { bubbles: true }));
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
