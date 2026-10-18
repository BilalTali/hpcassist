<?php

namespace App\Console\Commands;

use App\Http\Controllers\HpcController;
use App\Models\School;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Console\Command;

class UdiseFetch extends Command
{
    protected $signature = 'udise:fetch {--school-id= : UDISE internal school ID or code} {--timeout=300 : Timeout in seconds to wait for login}';
    protected $description = 'Automatically fetch student data with unmasked APAAR IDs from active UDISE+ session';

    public function handle(): int
    {
        $driverBin = config('hpc.chromedriver_path');
        $profileDir = storage_path('app/hpc/udise-chrome-profile');
        @mkdir($profileDir, 0777, true);

        $this->info("🚀 UDISE+ Automatic Student Data Fetcher");
        $this->line("──────────────────────────────────────────────────");

        // 1. Ensure ChromeDriver is running on 9515
        $this->ensureChromeDriver($driverBin);

        // 2. Check if Chrome is listening on 9222, otherwise launch it
        $this->ensureChromeRunning($profileDir);

        // 3. Connect via ChromeDriver
        $driver = $this->connectToChrome();
        if (! $driver) {
            $this->error("❌ Could not connect to Chrome on port 9222.");
            return self::FAILURE;
        }

        $this->info("✅ Connected to active Chrome window.");

        // 4. Locate or navigate to UDISE SDMS
        $timeout = (int) $this->option('timeout');
        $startTime = time();
        $schoolUdiseId = $this->option('school-id');

        $this->info("⏳ Checking for active UDISE+ SDMS login (timeout: {$timeout}s)...");

        $udiseHandle = null;
        while ((time() - $startTime) < $timeout) {
            $handles = $driver->getWindowHandles();
            foreach ($handles as $h) {
                $driver->switchTo()->window($h);
                $url = $driver->getCurrentURL();
                if (str_contains($url, 'sdms.udiseplus.gov.in') || str_contains($url, 'auth.udiseplus.gov.in')) {
                    $udiseHandle = $h;
                    break;
                }
            }

            if ($udiseHandle) {
                $currentUrl = $driver->getCurrentURL();
                if (str_contains($currentUrl, 'sdms.udiseplus.gov.in/g0/#/school/') ||
                    str_contains($currentUrl, 'sdms.udiseplus.gov.in/g0/#/academic-choice') ||
                    str_contains($currentUrl, 'sdms.udiseplus.gov.in/g0/#/one-view')) {
                    break;
                }
            }

            $this->output->write(".");
            sleep(2);
        }
        $this->newLine();

        if (! $udiseHandle) {
            $this->error("❌ UDISE+ SDMS session not detected within timeout.");
            $this->line("Please open https://sdms.udiseplus.gov.in/ and log in.");
            return self::FAILURE;
        }

        $driver->switchTo()->window($udiseHandle);
        $currentUrl = $driver->getCurrentURL();
        $this->info("📍 Current UDISE page: {$currentUrl}");

        // 5. Extract School Info & Internal ID
        $extractJs = <<<'JS'
            let schoolId = null;
            const urlMatch = window.location.href.match(/school\/(\d+)/);
            if (urlMatch) schoolId = urlMatch[1];

            // Check performance API requests
            if (!schoolId) {
                const entries = window.performance.getEntriesByType("resource");
                for (const e of entries) {
                    const m = e.name.match(/\/api\/(?:cy\/)?(?:snapshot\/students\/all|school)\/(\d+)/);
                    if (m) { schoolId = m[1]; break; }
                }
            }

            // Check links
            if (!schoolId) {
                const link = Array.from(document.querySelectorAll('a, button')).find(a => (a.href || '').includes('/school/'));
                if (link) {
                    const m = link.href.match(/school\/(\d+)/);
                    if (m) schoolId = m[1];
                }
            }

            let schoolName = '';
            let udiseCode = '';
            const body = document.body ? document.body.innerText : '';
            const codeM = body.match(/UDISE Code\s*:\s*(\d+)/i);
            if (codeM) udiseCode = codeM[1];

            const nameM = body.match(/School Name\s*:\s*([^\n]+)/i);
            if (nameM) schoolName = nameM[1].trim();

            return {
                schoolId: schoolId,
                schoolName: schoolName,
                udiseCode: udiseCode,
                url: window.location.href
            };
JS;

        $info = $driver->executeScript($extractJs);
        $schoolId = $schoolUdiseId ?: ($info['schoolId'] ?? null);
        $schoolName = $info['schoolName'] ?? '';
        $udiseCode = $info['udiseCode'] ?? '';

        if (! $schoolId) {
            $this->warn("⚠️  Could not auto-detect internal UDISE School ID from current page.");
            $this->line("Navigating to Academic Year snapshot to discover school ID...");
            
            // Try clicking the academic year link or navigating
            $driver->executeScript("
                const btn = Array.from(document.querySelectorAll('a, button')).find(el => el.innerText.includes('2026-27') || el.innerText.includes('Go to'));
                if (btn) btn.click();
            ");
            sleep(3);
            
            $info = $driver->executeScript($extractJs);
            $schoolId = $info['schoolId'] ?? null;
        }

        if (! $schoolId) {
            $schoolId = $this->ask("Please enter the internal UDISE School ID (number from the URL after /school/):");
        }

        if (! $schoolId) {
            $this->error("❌ School ID is required to fetch students.");
            return self::FAILURE;
        }

        $this->info("🎯 Targeting UDISE School ID: {$schoolId}");
        if ($schoolName) $this->line("🏫 School Name: {$schoolName}");
        if ($udiseCode) $this->line("🔢 UDISE Code: {$udiseCode}");

        // 6. Fetch students via executeAsyncScript inside the authenticated browser tab
        $this->info("📥 Fetching all student records with APAAR IDs from UDISE API...");

        $fetchScript = <<<JS
            const done = arguments[arguments.length - 1];
            fetch('/p0/api/cy/snapshot/students/all/{$schoolId}')
                .then(r => r.json())
                .then(data => done(data))
                .catch(err => {
                    fetch('/p0/api/cy/students/all/{$schoolId}')
                        .then(r => r.json())
                        .then(d => done(d))
                        .catch(e => done({ error: e.toString() }));
                });
JS;

        $response = $driver->executeAsyncScript($fetchScript);

        if (isset($response['error'])) {
            $this->error("❌ Error fetching from UDISE: " . $response['error']);
            return self::FAILURE;
        }

        $students = $response['data'] ?? ($response['students'] ?? (is_array($response) && isset($response[0]) ? $response : []));

        if (empty($students)) {
            $this->error("❌ No student records returned by UDISE API for school ID {$schoolId}.");
            return self::FAILURE;
        }

        $total = count($students);
        $apaarCount = count(array_filter($students, fn($s) => ! empty($s['apaarId']) && ctype_digit((string) $s['apaarId']) && strlen((string) $s['apaarId']) === 12));
        $firstUdiseCode = $students[0]['schUdiseCode'] ?? $udiseCode;

        $this->info("🎉 Successfully fetched {$total} students ({$apaarCount} APAAR IDs)!");

        // 7. Match or create school in database
        $school = null;
        if ($firstUdiseCode) {
            $school = School::where('udise_code', $firstUdiseCode)->first();
        }
        if (! $school && $udiseCode) {
            $school = School::where('udise_code', $udiseCode)->first();
        }
        if (! $school) {
            $school = School::first(); // fallback to primary school
        }

        if ($school) {
            $this->info("💾 Saving data for School: {$school->name} (ID: {$school->id}, UDISE: {$school->udise_code})");

            // Persist raw students
            $dir = storage_path('app/hpc/schools/' . $school->id);
            @mkdir($dir, 0755, true);
            file_put_contents($dir . '/udise_students_raw.json', json_encode($students, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // Legacy save
            file_put_contents(storage_path('app/hpc/udise_students_' . $schoolId . '_raw.json'), json_encode($students, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // Generate downloadable Excel template from UDISE data (no DB writes — user must upload for HPC)
            $hpc = app(HpcController::class);
            $hpc->preloadUdise(null, $school, $students);

            // Update school status
            $school->update([
                'udise_status'         => 'synced',
                'udise_synced_at'      => now(),
                'udise_students_count' => $total,
                'udise_apaar_count'    => $apaarCount,
            ]);

            $this->info("✅ School record updated to 'synced'!");
            $this->info("📁 Excel template generated at: storage/app/hpc/schools/{$school->id}/students.xlsx");
            $this->info("📋 Download the template, review it, and upload via Step 2 for HPC processing.");
        }

        return self::SUCCESS;
    }

    private function ensureChromeDriver(string $driverBin): void
    {
        $fp = @fsockopen('127.0.0.1', 9515, $errno, $errstr, 0.5);
        if ($fp) {
            fclose($fp);
            return;
        }

        if (file_exists($driverBin) && ! is_executable($driverBin)) {
            @chmod($driverBin, 0755);
        }

        exec("nohup {$driverBin} --port=9515 > /dev/null 2>&1 &");
        sleep(1);
    }

    private function ensureChromeRunning(string $profileDir): void
    {
        $fp = @fsockopen('127.0.0.1', 9222, $errno, $errstr, 0.5);
        if ($fp) {
            fclose($fp);
            return;
        }

        $loginUrl = 'https://sdms.udiseplus.gov.in/';
        $chromeApp = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
        if (file_exists($chromeApp)) {
            $cmd = "nohup '{$chromeApp}' --user-data-dir=" . escapeshellarg($profileDir) . " --remote-debugging-port=9222 --window-size=1400,900 " . escapeshellarg($loginUrl) . " > /dev/null 2>&1 &";
            exec($cmd);
            sleep(2);
        }
    }

    private function connectToChrome(): ?RemoteWebDriver
    {
        try {
            $options = new ChromeOptions();
            $options->setExperimentalOption('debuggerAddress', '127.0.0.1:9222');
            $capabilities = DesiredCapabilities::chrome();
            $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

            return RemoteWebDriver::create('http://127.0.0.1:9515', $capabilities, 15000, 15000);
        } catch (\Throwable $e) {
            $this->warn("Connect error: " . $e->getMessage());
            return null;
        }
    }
}
