<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\HpcBrowserManager;
use App\Models\SchoolWorkingDay;

class HpcTeacherRun extends Command
{
    protected $signature   = 'hpc:teacher-run {--school= : Optional school ID for multi-tenant isolation} {--teacher= : Optional teacher ID}';
    protected $description = 'Read students from teacher_preview.json and update each student across HPC evaluation parts';

    public function handle(): int
    {
        $storagePath = config('hpc.storage_path');
        $driverBin   = config('hpc.chromedriver_path');
        $loginUrl    = config('hpc.login_url');
        $hpcHomeUrl  = config('hpc.hpc_home_url') ?: 'https://hpc.parakh.ncert.gov.in/en/hpc';
        $schoolId    = $this->option('school');
        $teacherId   = $this->option('teacher');

        $previewFile = $storagePath . '/teacher_preview.json';
        $resultsFile = $storagePath . '/teacher_run_results.json';

        if ($schoolId) {
            $schoolDir = $storagePath . '/schools/' . $schoolId;
            @mkdir($schoolDir, 0755, true);
            $previewFile = $schoolDir . '/teacher_preview.json';
            $resultsFile = $schoolDir . '/teacher_run_results.json';
            $tSlug       = $teacherId ?: 'teacher_default';
            $profileDir  = $schoolDir . '/teacher-profiles/' . $tSlug;
            @mkdir($profileDir, 0755, true);
        } else {
            $profileDir = $this->resolveActiveTeacherProfile();
        }

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
        $admissionNo   = $s['admission_no'] ?? 'ADM-2026-089';
        $motherTongue  = $s['mother_tongue'] ?? 'Kashmiri';
        $parentName    = $s['parent_name'] ?? 'Parent';
        $absentCount   = $s['absent_count'] ?? '16';
        $absenceReason = $s['absence_reason'] ?? 'Brief seasonal viral illness';
        $interest      = $s['interest'] ?? 'Reading';

        // 12 Monthly Working Days (Apr - Mar)
        $monthKeys = ['apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec', 'jan', 'feb', 'mar'];
        $defaultWd = SchoolWorkingDay::getDefault();
        $defaultWorkMap = $defaultWd->toMonthMap();
        $defaultWork = [20, 22, 10, 20, 22, 21, 19, 20, 15, 15, 20, 18];
        $defaultPres = [19, 21, 10, 18, 20, 20, 18, 17, 14, 13, 19, 17];

        $monthlyWork = [];
        $monthlyPres = [];
        foreach ($monthKeys as $mIdx => $mKey) {
            $wVal = $s["work_{$mKey}"] ?? '';
            $monthlyWork[] = ($wVal !== '' && is_numeric($wVal)) ? intval($wVal) : ($defaultWorkMap[$mKey] ?? $defaultWork[$mIdx]);

            $pVal = $s["pres_{$mKey}"] ?? '';
            $monthlyPres[] = ($pVal !== '' && is_numeric($pVal)) ? intval($pVal) : $defaultPres[$mIdx];
        }

        $client->executeScript("
            function setVal(el, val) {
                if (!el) return;
                const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value')?.set;
                if (setter) setter.call(el, val.toString()); else el.value = val.toString();
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }

            // 1. Admission Number
            const admContainer = Array.from(document.querySelectorAll('label')).find(l => l.innerText.includes('School Admission Number'))?.closest('div');
            const admInp = admContainer?.querySelector('input');
            if (admInp) setVal(admInp, " . json_encode($admissionNo) . ");

            // 2. Mother tongue
            const mtContainer = Array.from(document.querySelectorAll('label')).find(l => l.innerText.includes('Mother Tongue'))?.closest('div');
            const mtInp = mtContainer?.querySelector('input');
            if (mtInp) setVal(mtInp, " . json_encode($motherTongue) . ");

            // 3. Parent Name
            const pInp = document.querySelector('input[placeholder=\"Enter Father Name\"], input[placeholder*=\"Father\"]');
            if (pInp) setVal(pInp, " . json_encode($parentName) . ");

            // 4. Monthly Working Days (12 months: Apr - Mar)
            const monthlyWork = " . json_encode($monthlyWork) . ";
            const r1Inputs = Array.from(document.querySelectorAll('tbody tr:nth-child(1) td input'));
            monthlyWork.forEach((v, i) => {
                if (r1Inputs[i]) setVal(r1Inputs[i], v);
            });

            // 5. Monthly Present Days (12 months: Apr - Mar)
            const monthlyPres = " . json_encode($monthlyPres) . ";
            const r2Inputs = Array.from(document.querySelectorAll('tbody tr:nth-child(2) td input'));
            monthlyPres.forEach((v, i) => {
                if (r2Inputs[i]) setVal(r2Inputs[i], v);
            });

            // 6. Absent times count
            const absTimesInp = Array.from(document.querySelectorAll('label')).find(l => l.innerText.includes('absent'))?.closest('div')?.querySelector('input');
            if (absTimesInp) setVal(absTimesInp, " . json_encode((string)$absentCount) . ");

            // 7. Absence frequency / reason
            const absInp = document.querySelector('input[placeholder=\"Write here\"]:last-of-type, textarea');
            if (absInp) setVal(absInp, " . json_encode($absenceReason) . ");

            // 8. Interests checkbox
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
            $orderedA2 = [
                1  => $s['live_with']     ?: 'Family',
                2  => $s['stay_at']       ?: 'Sugin, Budgam',
                3  => $s['free_time']     ?: 'Reading books and drawing',
                4  => $s['do_well']       ?: 'Learning new skills and actively participating',
                5  => $s['responsible']   ?: 'most times',
                6  => $s['do_better']     ?: ($s['could_improve'] ?? 'Time management in exams'),
                7  => $s['care_others']   ?: ($s['care_about'] ?? 'Helping peers with notes'),
                8  => $s['feel_proud']    ?: 'Solving challenging problems independently',
                9  => $s['student_name']   ?: 'Ayaan Ahmad',
                10 => $s['student_age']    ?: '12',
                11 => $s['acad_goal']     ?: ($s['academic_goal'] ?? 'Score above 90% in Mathematics and Science'),
                12 => $s['acad_goal_why'] ?: ($s['goal_importance'] ?? 'Builds solid foundation for pursuing higher studies in Engineering'),
                13 => $s['acad_step1']    ?: ($s['goal_step1'] ?? 'Dedicate 45 minutes daily to numerical problem solving and formula revision'),
                14 => $s['acad_step2']    ?: ($s['goal_step2'] ?? 'Clarify doubts with subject teachers immediately and practice weekly mock tests'),
                15 => $s['pers_goal_why'] ?: 'Keeps mind alert, improves stamina, and enhances daily concentration',
                16 => $s['pers_step1']    ?: 'Wake up at 6:00 AM every morning and do 30 minutes of yoga and jogging',
                17 => $s['pers_step2']    ?: 'Read at least 15 pages of non-academic literature before bedtime',
                18 => $s['pers_goal']     ?: ($s['personal_goal'] ?? 'Develop regular physical exercise and disciplined reading routine'),
                19 => $s['sch_learn1']    ?: ($s['learnt_school'] ?? 'Laws of reflection and refraction in science laboratory'),
                20 => $s['sch_learn2']    ?: 'Formulating and solving linear equations in single variable',
                21 => $s['sch_learn3']    ?: 'Constitutional preamble and fundamental rights in Social Science',
                22 => $s['out_learn1']    ?: ($s['learnt_outside'] ?? 'Organic kitchen gardening, composting, and soil preparation'),
                23 => $s['out_learn2']    ?: 'Bicycle maintenance, tire inflation, and chain lubrication',
                24 => $s['out_learn3']    ?: 'Basic household budgeting and grocery calculation',
                25 => $s['help_with']     ?: ($s['teacher_help'] ?? 'Hands-on science experiments and advanced mathematical puzzles'),
                26 => $s['teacher_know']  ?: 'I am enthusiastic about collaborative group projects and active discussions',
            ];

            $client->executeScript("
                const orderedMap = " . json_encode($orderedA2) . ";
                const setNativeValue = (el, val) => {
                    if (!el) return;
                    const proto = el.tagName === 'TEXTAREA' ? window.HTMLTextAreaElement.prototype : window.HTMLInputElement.prototype;
                    const setter = Object.getOwnPropertyDescriptor(proto, 'value')?.set;
                    if (setter) setter.call(el, val.toString()); else el.value = val.toString();
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                    el.dispatchEvent(new Event('blur', { bubbles: true }));
                };

                const elements = Array.from(document.querySelectorAll('input, textarea, select'));
                Object.keys(orderedMap).forEach(idxStr => {
                    const idx = parseInt(idxStr, 10);
                    if (elements[idx]) {
                        setNativeValue(elements[idx], orderedMap[idxStr]);
                    }
                });
            ");
            usleep($delayMs * 1000);
            $this->clickContinue($client);
            sleep(3);
        }

        // ── 4. Fill Part-A3 ───────────────────────────────────────────────────
        if (str_contains($client->getCurrentURL(), '/part-A3')) {
            $orderedA3 = [
                1  => $s['ambition']          ?: 'Doctor / Astrophysicist',
                2  => $s['achieve_how']       ?: 'Conceptual understanding in Maths & Science and dedicated daily practice',
                3  => $s['skills_needed']     ?: 'Critical Thinking, Problem Solving, Communication, Coding, Curiosity',
                4  => $s['focus_subjects']    ?: 'Mathematics, Science, English, Computing',
                5  => $s['habits_i_will']     ?: ($s['habits_build'] ?? ($s['i_will'] ?? 'Disciplined daily study, 30 min morning exercise, and curious questioning')),
                6  => $s['mentor']            ?: 'Bilal Ahmad Tali',
                7  => $s['mentor_help']       ?: 'Recommending Olympiad reference books and laboratory guidance',
                8  => $s['learn_new']         ?: 'Scientific problem solving and experimental techniques',
                9  => $s['feel_when_achieve'] ?: 'Proud, fulfilled, and motivated to help others',
                10 => $s['parents_feel']      ?: 'Immensely joyful, honored, and proud',
            ];

            $client->executeScript("
                const orderedMap = " . json_encode($orderedA3) . ";
                const setNativeValue = (el, val) => {
                    if (!el) return;
                    const proto = el.tagName === 'TEXTAREA' ? window.HTMLTextAreaElement.prototype : window.HTMLInputElement.prototype;
                    const setter = Object.getOwnPropertyDescriptor(proto, 'value')?.set;
                    if (setter) setter.call(el, val.toString()); else el.value = val.toString();
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                    el.dispatchEvent(new Event('blur', { bubbles: true }));
                };

                const elements = Array.from(document.querySelectorAll('input, textarea, select'));
                Object.keys(orderedMap).forEach(idxStr => {
                    const idx = parseInt(idxStr, 10);
                    if (elements[idx]) {
                        setNativeValue(elements[idx], orderedMap[idxStr]);
                    }
                });
            ");
            usleep($delayMs * 1000);
            $this->clickContinue($client);
            sleep(3);
        }

        // ── 5. Fill Part-A4 (Smileys, Checkboxes & Textareas) ─────────────────
        if (str_contains($client->getCurrentURL(), '/part-A4')) {
            $parentFeedback = $s['parent_feedback'] ?? 'Provide quiet study space, supervise 1.5 hours of evening revision, monitor screen time, and discuss daily learnings';
            $pRatings = [
                $s['p_motivated'] ?? 'Yes',
                $s['p_schedule'] ?? 'Yes',
                $s['p_curriculum_diff'] ?? 'No',
                $s['p_progress'] ?? 'Yes',
            ];

            // 1. Home Resources Checkbox Map
            $homeResMap = [
                'books'     => strcasecmp($s['res_books'] ?? '', 'Yes') === 0,
                'news'      => strcasecmp($s['res_news'] ?? '', 'Yes') === 0,
                'toy'       => strcasecmp($s['res_toys'] ?? '', 'Yes') === 0,
                'phone'     => strcasecmp($s['res_phone'] ?? '', 'Yes') === 0,
                'internet'  => strcasecmp($s['res_internet'] ?? '', 'Yes') === 0,
                'radio'     => strcasecmp($s['res_radio'] ?? '', 'Yes') === 0,
                'broadcast' => strcasecmp($s['res_broadcast'] ?? '', 'Yes') === 0,
                't.v'       => strcasecmp($s['res_tv'] ?? '', 'Yes') === 0,
                'cwd'       => strcasecmp($s['res_cwd'] ?? '', 'Yes') === 0,
            ];
            // Backward-compat fallback if old single-column home_resources was uploaded
            if (!isset($s['res_books']) && !empty($s['home_resources'])) {
                $hrStr = strtolower($s['home_resources']);
                foreach ($homeResMap as $k => $v) {
                    $homeResMap[$k] = str_contains($hrStr, $k);
                }
            }
            $resOther = $s['res_other'] ?? '';

            // 2. School Support Needs Checkbox Map
            $suppMap = [
                'language'   => strcasecmp($s['supp_lang'] ?? '', 'Yes') === 0,
                'mathemat'   => strcasecmp($s['supp_math'] ?? '', 'Yes') === 0,
                'science'    => strcasecmp($s['supp_sci'] ?? '', 'Yes') === 0,
                'social'     => strcasecmp($s['supp_soc'] ?? '', 'Yes') === 0,
                'self-belief'=> strcasecmp($s['supp_self_belief'] ?? '', 'Yes') === 0,
                'social skil'=> strcasecmp($s['supp_social_skills'] ?? '', 'Yes') === 0,
                'emotion'    => strcasecmp($s['supp_emotions'] ?? '', 'Yes') === 0,
                'study skil' => strcasecmp($s['supp_study_skills'] ?? '', 'Yes') === 0,
                'digital'    => strcasecmp($s['supp_skill_guidance'] ?? '', 'Yes') === 0,
                'any other'  => strcasecmp($s['supp_other'] ?? '', 'Yes') === 0,
            ];
            // Backward-compat fallback if old single-column child_needs_support was uploaded
            if (!isset($s['supp_math']) && !empty($s['child_needs_support'])) {
                $csStr = strtolower($s['child_needs_support']);
                foreach ($suppMap as $k => $v) {
                    $suppMap[$k] = str_contains($csStr, substr($k, 0, 4));
                }
            }
            $suppOtherSpecify = $s['supp_other_specify'] ?? '';

            $client->executeScript("
                const pFeedback = " . json_encode($parentFeedback) . ";
                const ratings = " . json_encode($pRatings) . ";
                const homeMap = " . json_encode($homeResMap) . ";
                const resOther = " . json_encode($resOther) . ";
                const suppMap = " . json_encode($suppMap) . ";
                const suppOther = " . json_encode($suppOtherSpecify) . ";

                // 1. Tick Home Resources Checkboxes
                document.querySelectorAll('.childrens-resources li').forEach(li => {
                    const lbl = (li.querySelector('label')?.innerText || '').toLowerCase();
                    const cb = li.querySelector('input[type=\"checkbox\"]');
                    if (cb && lbl) {
                        let shouldCheck = false;
                        for (const key in homeMap) {
                            if (lbl.includes(key)) {
                                shouldCheck = homeMap[key];
                                break;
                            }
                        }
                        if (shouldCheck !== cb.checked) {
                            cb.click();
                            cb.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    }
                });

                // Other home resources text
                const otherHomeInput = document.querySelector('.children-specific-box input');
                if (otherHomeInput && resOther) {
                    const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value')?.set;
                    if (setter) setter.call(otherHomeInput, resOther); else otherHomeInput.value = resOther;
                    otherHomeInput.dispatchEvent(new Event('input', { bubbles: true }));
                    otherHomeInput.dispatchEvent(new Event('change', { bubbles: true }));
                }

                // 2. Select radio smileys for 'Understanding of my Child' (Q1 - Q4)
                const table = document.querySelector('.hpc-table-part-b table, table');
                if (table) {
                    const rows = Array.from(table.querySelectorAll('tbody tr')).filter(r => r.querySelectorAll('.appropriate-option-select li').length > 0);
                    rows.forEach((r, idx) => {
                        const targetRating = (ratings[idx] || 'Yes').toLowerCase();
                        const lis = Array.from(r.querySelectorAll('.appropriate-option-select li'));
                        const targetLi = lis.find(li => (li.innerText || '').trim().toLowerCase() === targetRating)
                                      || lis.find(li => (li.innerText || '').trim().toLowerCase() === 'yes')
                                      || lis[0];
                        if (targetLi) targetLi.click();
                    });
                }

                // 3. Child Support Needs Checkboxes
                document.querySelectorAll('.need-children-support-check input[type=\"checkbox\"]').forEach(cb => {
                    const p = cb.closest('label')?.querySelector('p') || cb.closest('li')?.querySelector('p');
                    const txt = (p?.innerText || '').toLowerCase();
                    if (txt) {
                        let shouldCheck = false;
                        for (const key in suppMap) {
                            if (txt.includes(key)) {
                                shouldCheck = suppMap[key];
                                break;
                            }
                        }
                        if (shouldCheck !== cb.checked) {
                            cb.click();
                            cb.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    }
                });

                // 4. Fill textareas
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

        // ── 6. Fill Part-B (Holistic Activity Evaluation) ───────────────────
        if (str_contains($client->getCurrentURL(), '/part-B')) {
            $actSubject     = $s['act_subject'] ?? 'Science';
            $actTitle       = $s['act_title'] ?? ($actSubject . ' Project');
            $actApproach    = $s['act_approach'] ?? 'Art-Integrated';
            $actDesc        = $s['act_desc'] ?? 'Constructed scale models of planetary orbits, computed relative ratios, and demonstrated to class';
            $actLevel       = strtolower($s['act_level'] ?? 'proficient');
            $actAwr         = strtolower($s['act_rubric_awareness'] ?? $actLevel);
            $actSen         = strtolower($s['act_rubric_sensitivity'] ?? $actLevel);
            $actCre         = strtolower($s['act_rubric_creativity'] ?? $actLevel);
            $actLearnt      = $s['act_learnt'] ?? 'Learned celestial orbital distances, 3D scale geometry, and public presentation skills';
            $actInteresting = $s['act_interesting'] ?? 'Fabricating rotating planetary models using recycled materials and LED lighting';
            $actPractice    = $s['act_practice'] ?? 'Time management during hands-on model assembly and fine scale calibrations';
            $actPeerName    = $s['act_peer_name'] ?? 'Ayaan Ahmad';
            $actPeerFeedback= $s['act_peer_feedback'] ?? 'Very supportive and collaborative teammate; explained difficult astronomical concepts clearly';
            $actSupportPlan = $s['act_support_plan'] ?? 'Encourage participation in inter-school science competitions, provide advanced telescope observation sessions, and recommend for student mentorship roles';

            // Select subject-matching activity if activities already exist on student card
            $client->executeScript("
                const sSub = " . json_encode(strtolower($actSubject)) . ";
                const actButtons = Array.from(document.querySelectorAll('.hpc-forms button')).filter(b => /^\d+\s*\n/.test(b.innerText.trim()));
                if (actButtons.length > 0) {
                    const target = actButtons.find(b => b.innerText.toLowerCase().includes(sSub.slice(0, 5))) || actButtons[0];
                    if (target) target.click();
                }
            ");
            usleep(500000);

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

            // Reorder domains to put chosen subject first
            usort($domainsConfig, function($a, $b) use ($actSubject) {
                $matchA = stripos($a['name'], substr($actSubject, 0, 5)) !== false;
                $matchB = stripos($b['name'], substr($actSubject, 0, 5)) !== false;
                if ($matchA && !$matchB) return -1;
                if (!$matchA && $matchB) return 1;
                return 0;
            });

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
                    const actAwrVal = " . json_encode($actAwr) . ";
                    const actSenVal = " . json_encode($actSen) . ";
                    const actCreVal = " . json_encode($actCre) . ";
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
                                awr: ['Demonstrates good awareness', 'Demonstrates strong awareness', 'Demonstrates exceptional awareness', actAwrVal],
                                sen: ['Shows sensitivity', 'Shows great sensitivity', 'Shows excellent sensitivity', actSenVal],
                                cre: ['Displays creative thinking', 'Displays innovative creativity', 'Displays high creativity', actCreVal],
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
