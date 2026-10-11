<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Panther\Client;
use Facebook\WebDriver\WebDriverBy;

class HpcTeacherDiscover extends Command
{
    protected $signature   = 'hpc:teacher-discover {--stage= : Specific stage slug (e.g. middle-stage)} {--skip-login : Go straight to HPC page}';
    protected $description = 'Discover HPC stages and all students in the teacher portal';

    public function handle(): int
    {
        $storagePath = config('hpc.storage_path');
        $driverBin   = config('hpc.chromedriver_path');
        $loginUrl    = config('hpc.login_url');
        $hpcHomeUrl  = config('hpc.hpc_home_url') ?: 'https://hpc.parakh.ncert.gov.in/en/hpc';
        $skipLogin   = $this->option('skip-login');
        $targetStage = $this->option('stage');

        // Resolve teacher profile directory
        $profileDir = $this->resolveActiveTeacherProfile();
        $this->info("Teacher Profile: {$profileDir}");

        if (file_exists($driverBin) && ! is_executable($driverBin)) {
            @chmod($driverBin, 0755);
        }

        $_SERVER['PANTHER_NO_HEADLESS'] = '1';
        putenv('PANTHER_NO_HEADLESS=1');

        $this->info("Launching Chrome...");
        try {
            $client = Client::createChromeClient($driverBin, [
                '--user-data-dir=' => $profileDir,
                '--window-size=1400,900',
                '--no-sandbox',
                '--disable-dev-shm-usage',
            ]);
        } catch (\Throwable $e) {
            $this->error("Could not launch Chrome: " . $e->getMessage());
            return self::FAILURE;
        }

        // ── 1. Check or Perform Login ─────────────────────────────────────────
        if (! $skipLogin) {
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
                $this->info("Navigating to login page: {$loginUrl}");
                try {
                    $client->request('GET', $loginUrl);
                } catch (\Throwable $e) {
                    $this->error("Failed to open login page: " . $e->getMessage());
                    $client->quit();
                    return self::FAILURE;
                }

                $this->info("Please log in as TEACHER in the open browser window...");
                $maxWait = 180;
                $waited  = 0;
                while ($waited < $maxWait) {
                    sleep(2);
                    $waited += 2;
                    try {
                        $url = $client->getCurrentURL();
                        if (! str_contains($url, '/login') && ! str_contains($url, '/signin') && ! str_contains($url, '/auth')) {
                            $loggedIn = true;
                            $this->info("Login detected! Current URL: {$url}");
                            break;
                        }
                    } catch (\Throwable) {
                        continue;
                    }
                }

                if (! $loggedIn) {
                    $this->error("Timed out waiting for login.");
                    $client->quit();
                    return self::FAILURE;
                }
            }
        }

        // ── 2. Discover Available Stages from /en/hpc ──────────────────────────
        $this->info("Navigating to HPC Portal: {$hpcHomeUrl}");
        try {
            $client->request('GET', $hpcHomeUrl);
            sleep(4);
        } catch (\Throwable $e) {
            $this->error("Failed to navigate to HPC Home: " . $e->getMessage());
            $client->quit();
            return self::FAILURE;
        }

        $stages = $client->executeScript(<<<'JS'
            const links = Array.from(document.querySelectorAll('a')).filter(a => {
                const href = a.href || '';
                return href.includes('/en/hpc/') && !href.includes('#') && (href.includes('-stage') || href.includes('stage'));
            }).map(a => {
                const href = a.href;
                const slug = href.split('/en/hpc/')[1]?.replace(/\/$/, '') || '';
                const text = a.innerText.trim().replace(/^Go to\s+/i, '');
                return {
                    title: text || slug,
                    slug: slug,
                    url: href
                };
            });

            // Unique by slug
            const map = new Map();
            links.forEach(s => {
                if (s.slug && !map.has(s.slug)) map.set(s.slug, s);
            });
            return Array.from(map.values());
JS);

        if (empty($stages)) {
            // Fallback default stages
            $stages = [
                ['title' => 'Middle Stage', 'slug' => 'middle-stage', 'url' => 'https://hpc.parakh.ncert.gov.in/en/hpc/middle-stage'],
                ['title' => 'Preparatory Stage', 'slug' => 'preparatory-stage', 'url' => 'https://hpc.parakh.ncert.gov.in/en/hpc/preparatory-stage']
            ];
        }

        file_put_contents($storagePath . '/stages.json', json_encode($stages, JSON_PRETTY_PRINT));
        $this->info("Discovered " . count($stages) . " stages:");
        foreach ($stages as $st) {
            $this->line("  • {$st['title']} ({$st['slug']})");
        }

        // Pick stage to scrape students from
        $selectedStage = null;
        if ($targetStage) {
            foreach ($stages as $st) {
                if ($st['slug'] === $targetStage || str_contains($st['slug'], $targetStage)) {
                    $selectedStage = $st;
                    break;
                }
            }
        }
        if (! $selectedStage) {
            $selectedStage = $stages[0]; // Default to first available stage
        }

        $this->info("\nFetching students for stage: {$selectedStage['title']} ({$selectedStage['slug']})");
        $stageUrl = $selectedStage['url'];

        try {
            $client->request('GET', $stageUrl);
            sleep(4);
        } catch (\Throwable $e) {
            $this->error("Failed to open stage URL: " . $e->getMessage());
            $client->quit();
            return self::FAILURE;
        }

        $wd = $client->getWebDriver();

        // ── 3. Read Student Rows ──────────────────────────────────────────────
        $rowsData = $client->executeScript(<<<'JS'
            const rows = Array.from(document.querySelectorAll('tbody tr')).map((tr, idx) => {
                const cells = Array.from(tr.querySelectorAll('td')).map(td => td.innerText.trim());
                if (cells.length === 1 && cells[0].toLowerCase().includes('no rows')) {
                    return null;
                }
                return {
                    row_index: idx,
                    apaar_id: cells[0] || '',
                    login_id: cells[1] || '',
                    name: cells[2] || '',
                    status: cells[3] || 'HPC Pending'
                };
            }).filter(Boolean);

            return rows;
JS);

        if (empty($rowsData)) {
            $this->warn("No students found in {$selectedStage['title']}.");
            file_put_contents($storagePath . '/teacher_students.json', json_encode([], JSON_PRETTY_PRINT));
            $client->quit();
            return self::SUCCESS;
        }

        $this->info("Found " . count($rowsData) . " students. Resolving direct HPC assessment form URLs...");

        // ── 4. Extract Direct Assessment Form URL for Each Student ────────────
        $students = [];
        foreach ($rowsData as $idx => $r) {
            $this->line("  [#" . ($idx + 1) . "] {$r['name']} ({$r['login_id']})...");

            try {
                // Click the action dropdown header on this specific row
                $dropdownHeaders = $wd->findElements(WebDriverBy::cssSelector('tbody tr .custom-dropdown-header'));
                if (isset($dropdownHeaders[$idx])) {
                    $dropdownHeaders[$idx]->click();
                    usleep(400000);

                    // Click 'Open' in the dropdown
                    $client->executeScript(<<<'JS'
                        const items = Array.from(document.querySelectorAll('.custom-dropdown-item'));
                        const openItem = items.find(it => it.innerText.trim().toLowerCase() === 'open');
                        if (openItem) openItem.click();
JS);
                    sleep(3);

                    // Grab form URL
                    $formUrl = $client->getCurrentURL();
                    $encodedId = '';
                    if (preg_match('#/' . preg_quote($selectedStage['slug'], '#') . '/([^/]+)/#', $formUrl, $m)) {
                        $encodedId = $m[1];
                    }

                    $students[] = [
                        'row'        => $idx + 1,
                        'name'       => $r['name'],
                        'login_id'   => $r['login_id'],
                        'apaar_id'   => $r['apaar_id'],
                        'status'     => $r['status'],
                        'stage'      => $selectedStage['slug'],
                        'encoded_id' => $encodedId,
                        'form_url'   => $formUrl,
                    ];

                    $this->line("      ➔ URL: {$formUrl}");

                    // Navigate back to stage table for next student
                    $client->request('GET', $stageUrl);
                    sleep(2);
                } else {
                    $students[] = array_merge($r, ['stage' => $selectedStage['slug'], 'form_url' => '', 'encoded_id' => '']);
                }
            } catch (\Throwable $e) {
                $this->warn("      Could not resolve URL for {$r['name']}: " . $e->getMessage());
                $students[] = array_merge($r, ['stage' => $selectedStage['slug'], 'form_url' => '', 'encoded_id' => '']);
                try {
                    $client->request('GET', $stageUrl);
                    sleep(2);
                } catch (\Throwable) {}
            }
        }

        // Save students
        file_put_contents($storagePath . '/teacher_students.json', json_encode($students, JSON_PRETTY_PRINT));
        $this->newLine();
        $this->info("✅ Successfully saved " . count($students) . " students to teacher_students.json!");

        $tableRows = array_map(fn($s) => [
            $s['row'],
            $s['name'],
            $s['login_id'],
            $s['status'],
            !empty($s['encoded_id']) ? 'Ready' : 'Pending'
        ], $students);

        $this->table(['#', 'Student Name', 'Login ID', 'Status', 'URL Ready'], $tableRows);

        $client->quit();
        return self::SUCCESS;
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
