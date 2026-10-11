<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Panther\Client;

class HpcDiscover extends Command
{
    protected $signature   = 'hpc:discover {--url= : Form URL to discover} {--skip-login : Go straight to the form (session already active)}';
    protected $description = 'Navigate to the Add Student form and save all field metadata to fields.json';

    /**
     * JavaScript injected into the portal page.
     * A nowdoc (single quotes around the label) means PHP never touches
     * $ or " inside, which is essential because JS uses both heavily.
     */
    private const JS = <<<'JS'
(function () {
    const fields  = [];
    const custom  = [];
    const seen    = new Set();          // tracks radio groups already processed

    /* ── helpers ──────────────────────────────────────────────── */

    // Find a human-readable label for an element, trying several strategies.
    function labelFor(el) {
        // 1. <label for="id">
        if (el.id) {
            const lbl = document.querySelector('label[for="' + CSS.escape(el.id) + '"]');
            if (lbl) return lbl.innerText.trim();
        }
        // 2. aria-label attribute
        if (el.getAttribute('aria-label')) return el.getAttribute('aria-label').trim();
        // 3. wrapping <label>
        const parent = el.closest('label');
        if (parent) return parent.innerText.replace(el.value || '', '').trim();
        // 4. nearby preceding <label>
        const prev = el.previousElementSibling;
        if (prev && prev.tagName === 'LABEL') return prev.innerText.trim();
        // 5. nearest preceding text node or heading in parent
        const container = el.closest('div, td, th, li, section, fieldset');
        if (container) {
            const heading = container.querySelector('label, legend, p, span');
            if (heading && !heading.contains(el)) return heading.innerText.trim().slice(0, 60);
        }
        // 6. fallback: placeholder / name / id
        return el.placeholder || el.name || el.id || '';
    }

    // Build a reliable CSS selector for finding the element again later.
    function cssFor(el) {
        if (el.id)   return '#' + CSS.escape(el.id);
        if (el.name) return el.tagName.toLowerCase() + '[name="' + el.name + '"]';
        return el.tagName.toLowerCase();
    }

    /* ── skip list ─────────────────────────────────────────────── */
    const SKIP_TYPES = new Set(['hidden', 'submit', 'button', 'reset', 'image', 'file']);

    // Index counter for elements sharing the same selector
    const typeIndexMap = {};
    function nextIndex(key) {
        typeIndexMap[key] = (typeIndexMap[key] ?? -1) + 1;
        return typeIndexMap[key];
    }

    /* ── scan all native inputs ─────────────────────────────────── */
    document.querySelectorAll('input, select, textarea').forEach(el => {

        // Skip non-data controls
        if (SKIP_TYPES.has(el.type)) return;
        // NOTE: we intentionally do NOT skip by offsetParent — SPAs often render
        // inside containers that make offsetParent null even when visible.

        const name = el.name || el.id || '';

        /* ── radio group ─────────────────────────────────────────── */
        if (el.type === 'radio') {
            if (seen.has(name)) return;
            seen.add(name);
            const buttons = document.querySelectorAll('input[name="' + CSS.escape(name) + '"]');
            const options = Array.from(buttons).map(b => labelFor(b) || b.value);
            fields.push({
                label:    labelFor(el) || name,
                name:     name,
                type:     'radio',
                selector: 'input[name="' + name + '"]',
                index:    0,
                options:  options,
                required: el.required,
            });
            return;
        }

        /* ── select (dropdown) ───────────────────────────────────── */
        if (el.tagName === 'SELECT') {
            const sel  = cssFor(el);
            const idx  = nextIndex('select:' + sel);
            const options = Array.from(el.options)
                .filter(o => o.value !== '')
                .map(o => ({ text: o.text.trim(), value: o.value }));
            fields.push({
                label:    labelFor(el),
                name:     name,
                type:     'select',
                selector: sel,
                index:    idx,
                options:  options,
                required: el.required,
            });
            return;
        }

        /* ── everything else (text, number, date, textarea…) ─────── */
        const sel2 = cssFor(el);
        const idx2 = nextIndex(el.type + ':' + sel2);
        fields.push({
            label:    labelFor(el),
            name:     name,
            type:     el.type || el.tagName.toLowerCase(),
            selector: sel2,
            index:    idx2,
            options:  [],
            required: el.required,
        });
    });

    /* ── scan custom / fake dropdowns (role=combobox / aria-haspopup=listbox) ─ */
    document.querySelectorAll('[role="combobox"], [aria-haspopup="listbox"]').forEach(el => {
        custom.push({
            label:    el.getAttribute('aria-label') || el.innerText.trim().slice(0, 40),
            selector: el.id ? '#' + CSS.escape(el.id) : el.tagName.toLowerCase(),
            note:     'custom-dropdown — needs click-based filling',
        });
    });

    return { fields, custom };
})();
JS;

    public function handle(): int
    {
        $storagePath    = config('hpc.storage_path');
        $profileDir     = base_path(config('hpc.profile_dir'));
        $driverBin      = config('hpc.chromedriver_path');
        $loginUrl       = config('hpc.login_url');
        $addStudentUrl  = $this->option('url') ?: config('hpc.add_student_url');
        $outputFile     = $storagePath . '/fields.json';
        $screenshotFile = $storagePath . '/form.png';
        $skipLogin      = $this->option('skip-login');

        @mkdir($storagePath, 0777, true);
        if (file_exists($driverBin) && ! is_executable($driverBin)) {
            @chmod($driverBin, 0755);
        }

        $_SERVER['PANTHER_NO_HEADLESS'] = '1';
        putenv('PANTHER_NO_HEADLESS=1');

        $this->info("Starting Chrome…");

        $client = Client::createChromeClient($driverBin, [
            '--user-data-dir=' . $profileDir,
            '--window-size=1400,900',
            // No --headless so you can log in manually
        ]);

        // ── Step 1: Navigate to login page (unless skipping) ────────
        if (! $skipLogin) {
            $this->info("Opening login page — log in, then come back to this terminal.");
            $this->newLine();

            try {
                $client->request('GET', $loginUrl);
            } catch (\Throwable $e) {
                $this->error('Could not open login page: ' . $e->getMessage());
                $client->quit();
                return self::FAILURE;
            }

            // ── Poll until we leave the login page (max 3 minutes) ──
            $maxWait  = 180;   // seconds
            $interval = 3;     // seconds between checks
            $waited   = 0;
            $loggedIn = false;

            $this->info("Waiting for login (timeout: {$maxWait}s) …");
            $this->line("  → Log in to the portal in the Chrome window that just opened.");
            $this->line("  → Come back here once you see your dashboard.");
            $this->newLine();

            while ($waited < $maxWait) {
                sleep($interval);
                $waited += $interval;

                try {
                    $currentUrl = $client->getCurrentURL();
                } catch (\Throwable) {
                    // Browser may be busy — retry
                    continue;
                }

                $isLoginPage = str_contains($currentUrl, '/login')
                    || str_contains($currentUrl, '/signin')
                    || str_contains($currentUrl, '/auth');

                if (! $isLoginPage) {
                    $loggedIn = true;
                    $this->info("✅ Login detected! Current URL: {$currentUrl}");
                    break;
                }

                if ($waited % 15 === 0) {
                    $this->line("  Still waiting… ({$waited}s elapsed)");
                }
            }

            if (! $loggedIn) {
                $this->error("Timed out waiting for login after {$maxWait}s.");
                $client->quit();
                return self::FAILURE;
            }

        } else {
            $this->info("--skip-login set, going straight to form…");
        }

        // ── Step 2: Navigate to Add Student form ─────────────────────
        $this->info("Navigating to Add Student form…");
        $this->info("URL: $addStudentUrl");

        try {
            $client->request('GET', $addStudentUrl);
        } catch (\Throwable $e) {
            $this->error('Could not open the form: ' . $e->getMessage());
            $client->quit();
            return self::FAILURE;
        }

        $title = $client->getTitle();
        $this->info("Page title: $title");

        // Verify we're not back on the login page
        $currentUrl = $client->getCurrentURL();
        if (str_contains($currentUrl, 'login') || str_contains($currentUrl, 'signin')) {
            $this->error("Still on login page — session did not persist.");
            $client->quit();
            return self::FAILURE;
        }

        // ── Step 3: Wait for JS-heavy page to render ─────────────────
        $waitMs = config('hpc.delay_ms') * 6;
        $this->info("Waiting {$waitMs}ms for SPA to render…");
        usleep($waitMs * 1000);

        // Switch to newest tab if the form opened in its own tab
        $handles = $client->getWindowHandles();
        $client->switchTo()->window(end($handles));

        // ── Step 4: Run field-discovery JavaScript ───────────────────
        $this->info("Scanning form fields…");
        $result = $client->executeScript(self::JS);

        // Take screenshot regardless of outcome
        $client->takeScreenshot($screenshotFile);
        $this->info("Screenshot saved: $screenshotFile");

        if (empty($result) || !isset($result['fields'])) {
            $this->error("JavaScript returned no data. Check the screenshot: $screenshotFile");
            $client->quit();
            return self::FAILURE;
        }

        // ── Step 5: Write fields.json ─────────────────────────────────
        $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($outputFile, $json);

        $fieldCount  = count($result['fields']);
        $customCount = count($result['custom'] ?? []);

        $this->newLine();
        $this->info("✅ Discovery complete!");
        $this->table(
            ['', 'Count / Path'],
            [
                ['Native fields found',   $fieldCount],
                ['Custom dropdowns found', $customCount],
                ['Output file',           $outputFile],
                ['Screenshot',            $screenshotFile],
            ]
        );

        if ($fieldCount > 0) {
            $this->newLine();
            $this->line("Fields discovered:");
            foreach ($result['fields'] as $f) {
                $req = $f['required'] ? ' *' : '';
                $opts = count($f['options'] ?? []) > 0
                    ? ' [' . count($f['options']) . ' options]'
                    : '';
                $this->line("  • {$f['label']}{$req} ({$f['type']}){$opts} — {$f['selector']}");
            }
        }

        if ($customCount > 0) {
            $this->newLine();
            $this->warn("⚠️  {$customCount} custom dropdown(s) detected.");
            $this->warn("   These use <div> elements instead of <select> and need special handling.");
            $this->warn("   Check fields.json → 'custom' array for details.");
        }

        $client->quit();
        return self::SUCCESS;
    }
}
