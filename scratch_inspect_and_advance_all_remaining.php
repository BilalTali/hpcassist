<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Chrome\ChromeOptions;

$options = new ChromeOptions();
$options->setExperimentalOption('debuggerAddress', '127.0.0.1:9222');

$capabilities = DesiredCapabilities::chrome();
$capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

try {
    $driver = RemoteWebDriver::create('http://127.0.0.1:9515', $capabilities, 15000, 15000);
    
    foreach ($driver->getWindowHandles() as $h) {
        $driver->switchTo()->window($h);
        if (str_contains($driver->getCurrentURL(), 'hpc.parakh.ncert.gov.in')) break;
    }

    $allPartsData = [];

    // Function to inspect current page
    $inspectCurrentPage = function($partKey) use ($driver, &$allPartsData) {
        echo "\n==================== INSPECTING $partKey ====================\n";
        echo "URL: " . $driver->getCurrentURL() . "\n";
        $driver->takeScreenshot(storage_path("app/hpc/part_{$partKey}.png"));

        $pageData = $driver->executeScript(<<<'JS'
            const inputs = Array.from(document.querySelectorAll('input, select, textarea, .custom-dropdown-wrapper')).map((el, i) => {
                const container = el.closest('.form-group, .col-md-4, .col-md-6, .col-md-3, .col-12, tr, td, div');
                const lbl = el.id ? document.querySelector('label[for="' + CSS.escape(el.id) + '"]')?.innerText : null;
                const containerLbl = container?.querySelector('label, p, span, strong')?.innerText;
                return {
                    index: i,
                    tag: el.tagName,
                    type: el.type || el.className,
                    name: el.name || el.id,
                    placeholder: el.placeholder || '',
                    value: el.value || '',
                    checked: el.checked || false,
                    label: (lbl || containerLbl || '').trim().slice(0, 100),
                    options: el.tagName === 'SELECT' ? Array.from(el.options).map(o => o.text.trim()) : []
                };
            }).filter(x => x.type !== 'hidden');

            const headings = Array.from(document.querySelectorAll('h1, h2, h3, h4, h5, h6, .card-title, .section-title, legend, strong')).map(h => h.innerText.trim()).filter(h => h.length > 0 && h.length < 80);

            const buttons = Array.from(document.querySelectorAll('button, input[type="submit"]')).map(b => b.innerText.trim() || b.value || '').filter(b => b.length > 0 && b.length < 40);

            return {
                url: window.location.href,
                headings: Array.from(new Set(headings)),
                inputCount: inputs.length,
                inputs: inputs,
                buttons: buttons,
                bodyText: document.body.innerText.slice(0, 1500)
            };
JS);

        $allPartsData[$partKey] = $pageData;
        file_put_contents(storage_path("app/hpc/hpc_part_{$partKey}_fields.json"), json_encode($pageData, JSON_PRETTY_PRINT));
        echo "Saved {$pageData['inputCount']} inputs for $partKey.\n";
    };

    // Helper to fill empty fields & click Continue
    $fillAndContinue = function($partKey) use ($driver) {
        echo "Filling dummy values for $partKey...\n";
        $driver->executeScript(<<<'JS'
            function setVal(el, val) {
                if (!el) return;
                try {
                    const proto = el.tagName === 'TEXTAREA' ? window.HTMLTextAreaElement.prototype : window.HTMLInputElement.prototype;
                    const setter = Object.getOwnPropertyDescriptor(proto, 'value')?.set;
                    if (setter) {
                        setter.call(el, val);
                    } else {
                        el.value = val;
                    }
                } catch(e) {
                    el.value = val;
                }
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }

            // Fill all empty text/number/textarea
            document.querySelectorAll('input[type="text"], input[type="number"], textarea').forEach(el => {
                if (!el.value) {
                    setVal(el, 'Good');
                }
            });

            // Check unselected radios (first in each group)
            const radioGroups = {};
            document.querySelectorAll('input[type="radio"]').forEach(r => {
                const name = r.name || 'default';
                if (!radioGroups[name]) radioGroups[name] = [];
                radioGroups[name].push(r);
            });
            Object.values(radioGroups).forEach(group => {
                const anyChecked = group.some(r => r.checked);
                if (!anyChecked && group.length > 0) {
                    group[0].click();
                }
            });

            // Check any unselected custom dropdowns
            document.querySelectorAll('.custom-dropdown-header.no-selection').forEach(h => {
                try { h.click(); } catch(e) {}
            });
JS);

        usleep(600000);
        echo "Clicking 'Continue' on $partKey...\n";
        $driver->executeScript(<<<'JS'
            const continueBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.trim().toLowerCase() === 'continue' || b.innerText.trim().toLowerCase() === 'next');
            if (continueBtn) continueBtn.click();
JS);
        sleep(4);
    };

    // 1. Inspect A3 (we are currently on A3)
    $inspectCurrentPage('A3');
    $fillAndContinue('A3');

    // 2. Inspect whatever page comes next (A4)
    $currentUrl = $driver->getCurrentURL();
    echo "URL after A3: $currentUrl\n";
    $inspectCurrentPage('A4');
    $fillAndContinue('A4');

    // 3. Next page (Part B)
    $currentUrl = $driver->getCurrentURL();
    echo "URL after A4: $currentUrl\n";
    $inspectCurrentPage('B');
    $fillAndContinue('B');

    // 4. Next page (Part C)
    $currentUrl = $driver->getCurrentURL();
    echo "URL after B: $currentUrl\n";
    $inspectCurrentPage('C');
    $fillAndContinue('C');

    // 5. Next page (HPC Credit)
    $currentUrl = $driver->getCurrentURL();
    echo "URL after C: $currentUrl\n";
    $inspectCurrentPage('Credit');

    // Summary of all parts
    file_put_contents(storage_path('app/hpc/all_hpc_parts_summary.json'), json_encode(array_map(fn($p) => [
        'url' => $p['url'],
        'headings' => $p['headings'],
        'inputCount' => $p['inputCount'],
        'buttons' => $p['buttons']
    ], $allPartsData), JSON_PRETTY_PRINT));

    echo "\nAll parts inspected and saved successfully!\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
