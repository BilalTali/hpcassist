<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Panther\Client;

class HpcOpen extends Command
{
    /**
     * The name and signature of the console command.
     *
     * hpc:open  — no arguments needed; everything comes from config/hpc.php.
     */
    protected $signature = 'hpc:open';

    /**
     * Short description shown in `php artisan list`.
     */
    protected $description = 'Open the HPC portal in Chrome and wait for you to log in manually';

    /**
     * Run the command.
     *
     * Returns self::SUCCESS (0) on normal exit, self::FAILURE (1) on error.
     */
    public function handle(): int
    {
        // ── 1. Resolve paths from config ──────────────────────────────────────
        // base_path() turns the relative profile_dir into an absolute path,
        // which ChromeDriver requires.
        $profile    = base_path(config('hpc.profile_dir'));
        $driverBin  = config('hpc.chromedriver_path');
        $loginUrl   = config('hpc.login_url');

        // Create the profile folder if it does not yet exist.
        // @ suppresses the "already exists" warning; true creates parent dirs.
        @mkdir($profile, 0777, true);

        $this->info("ChromeDriver : $driverBin");
        $this->info("Profile dir  : $profile");
        $this->info("Opening      : $loginUrl");

        // ── 2. Start ChromeDriver + Chrome ────────────────────────────────────
        // null  → let Panther find ChromeDriver on PATH or at the default loc.
        // We pass the binary explicitly via the env var Panther checks.
        putenv('PANTHER_CHROME_DRIVER_BINARY=' . $driverBin);

        $client = Client::createChromeClient(null, [
            // Use a persistent profile so cookies (login sessions) survive
            // between separate runs of the command.
            '--user-data-dir=' . $profile,

            // A normal desktop size so the portal renders its full layout.
            '--window-size=1400,900',

            // NO --headless flag → window is visible so you can solve CAPTCHA.
        ]);

        // ── 3. Navigate to the portal ─────────────────────────────────────────
        try {
            $client->request('GET', $loginUrl);
        } catch (\Throwable $e) {
            $this->error('Could not open the portal: ' . $e->getMessage());
            $client->quit();
            return self::FAILURE;
        }

        $title = $client->getTitle();
        $this->info("Page title   : $title");

        // ── 4. Pause for manual login ─────────────────────────────────────────
        // fgets(STDIN) blocks here — the browser window stays open and alive.
        // Solve the CAPTCHA, log in, navigate to whatever page you need, then
        // press Enter in this terminal to continue.
        $this->line('');
        $this->info('==========================================================');
        $this->info(' Browser is open. Log in and navigate to the target page. ');
        $this->info(' Then press [Enter] here to continue...                   ');
        $this->info('==========================================================');
        fgets(STDIN);

        // ── 5. Report final state ─────────────────────────────────────────────
        $currentUrl = $client->getCurrentURL();
        $this->info("You are now at: $currentUrl");

        // ── 6. Shut down cleanly ──────────────────────────────────────────────
        // quit() closes the Chrome window and stops the ChromeDriver process.
        // Without this, both keep running as orphan background processes.
        $client->quit();

        $this->info('Done. Session saved in Chrome profile.');
        return self::SUCCESS;
    }
}
