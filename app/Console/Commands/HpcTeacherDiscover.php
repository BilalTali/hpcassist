<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Panther\Client;
use App\Services\HpcBrowserManager;

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

        $this->info("Connecting to Chrome...");
        try {
            $client = HpcBrowserManager::createOrConnect($driverBin, $profileDir);
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

        // ── 3. Fetch Students from Stages (Middle & Preparatory) ─────────────
        $targetStages = [];
        if ($targetStage && $targetStage !== 'all') {
            foreach ($stages as $st) {
                if ($st['slug'] === $targetStage || str_contains($st['slug'], $targetStage)) {
                    $targetStages[] = $st;
                    break;
                }
            }
        }
        if (empty($targetStages)) {
            $targetStages = $stages; // Default to all stages
        }

        $allStudents = [];
        $globalIndex = 1;

        foreach ($targetStages as $selectedStage) {
            $this->info("\nFetching students for stage: {$selectedStage['title']} ({$selectedStage['slug']})");
            $stageUrl = $selectedStage['url'];

            try {
                $client->request('GET', $stageUrl);
                sleep(3);
            } catch (\Throwable $e) {
                $this->warn("Failed to open stage URL ({$stageUrl}): " . $e->getMessage());
                continue;
            }

            $rowsData = $client->executeScript(<<<'JS'
                const rows = Array.from(document.querySelectorAll('tbody tr')).map((tr, idx) => {
                    const cells = Array.from(tr.querySelectorAll('td')).map(td => td.innerText.trim());
                    if (cells.length === 1 && cells[0].toLowerCase().includes('no rows')) {
                        return null;
                    }
                    return {
                        row_key: tr.getAttribute('data-tv-row-key') || '',
                        apaar_id: cells[0] || '',
                        login_id: cells[1] || '',
                        name: cells[2] || '',
                        status: cells[3] || 'HPC Pending'
                    };
                }).filter(Boolean);

                return rows;
            JS);

            $this->info("Found " . count($rowsData) . " student(s) in {$selectedStage['title']}.");

            foreach ($rowsData as $r) {
                $allStudents[] = [
                    'row'        => $globalIndex++,
                    'apaar_id'   => $r['apaar_id'],
                    'login_id'   => $r['login_id'],
                    'name'       => $r['name'],
                    'status'     => $r['status'],
                    'stage'      => $selectedStage['slug'],
                    'stage_title'=> $selectedStage['title'],
                    'row_key'    => $r['row_key'],
                ];
            }
        }

        // Save combined students
        file_put_contents($storagePath . '/teacher_students.json', json_encode($allStudents, JSON_PRETTY_PRINT));
        $this->newLine();
        $this->info("✅ Successfully saved " . count($allStudents) . " students to teacher_students.json!");

        $tableRows = array_map(fn($s) => [
            $s['row'],
            $s['apaar_id'] ?: '—',
            $s['login_id'],
            $s['name'],
            $s['status'],
            $s['stage_title']
        ], $allStudents);

        $this->table(['#', 'APAAR ID', 'HPC Login ID', 'Student Name', 'Status', 'Stage'], $tableRows);

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
