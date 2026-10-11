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
    $driver = RemoteWebDriver::create('http://127.0.0.1:9515', $capabilities, 10000, 10000);
    
    foreach ($driver->getWindowHandles() as $h) {
        $driver->switchTo()->window($h);
        if (str_contains($driver->getCurrentURL(), 'hpc.parakh.ncert.gov.in')) break;
    }

    echo "Inspecting student HPC form on: " . $driver->getCurrentURL() . "\n";

    $formData = $driver->executeScript(<<<'JS'
        // 1. Navigation / Parts tabs
        const navParts = Array.from(document.querySelectorAll('a, button, [role="tab"], .nav-link, .nav-item')).map(el => ({
            tag: el.tagName,
            text: el.innerText.trim(),
            href: el.href || el.getAttribute('href') || null,
            className: el.className,
            role: el.getAttribute('role')
        })).filter(el => el.text.length > 0 && (el.text.includes('Part') || el.text.includes('Stage') || el.text.includes('Self') || el.text.includes('Peer') || el.text.includes('Teacher') || el.text.includes('Parent') || el.href?.includes('part-')));

        // 2. Sections / Headings
        const headings = Array.from(document.querySelectorAll('h1, h2, h3, h4, h5, h6, .card-title, .section-title, legend, strong')).map(h => h.innerText.trim()).filter(h => h.length > 0 && h.length < 100);

        // 3. Inputs, selects, textareas, checkboxes, radios
        const controls = Array.from(document.querySelectorAll('input, select, textarea, [role="radio"], [role="checkbox"], .custom-dropdown-wrapper')).map(el => {
            const container = el.closest('.form-group, .mb-3, .col, .row, div, tr');
            const labelEl = el.id ? document.querySelector('label[for="' + CSS.escape(el.id) + '"]') : null;
            const labelText = labelEl ? labelEl.innerText.trim() : (el.closest('label')?.innerText.trim() || container?.querySelector('label')?.innerText.trim() || el.getAttribute('aria-label') || el.placeholder || '');
            
            return {
                tag: el.tagName,
                type: el.type || el.getAttribute('role') || el.className,
                name: el.name || el.id || '',
                id: el.id || '',
                label: labelText.slice(0, 80),
                placeholder: el.placeholder || '',
                value: el.value || '',
                checked: el.checked || false,
                required: el.required || false,
                options: el.tagName === 'SELECT' ? Array.from(el.options).map(o => o.text.trim()) : []
            };
        });

        // 4. Buttons (Save, Next, Submit, Draft)
        const buttons = Array.from(document.querySelectorAll('button, input[type="submit"]')).map(b => ({
            text: b.innerText.trim() || b.value || '',
            type: b.type,
            className: b.className
        })).filter(b => b.text.length > 0 && b.text.length < 40);

        return {
            title: document.title,
            url: window.location.href,
            navParts: navParts,
            headings: Array.from(new Set(headings)).slice(0, 30),
            controlsCount: controls.length,
            controlsSample: controls,
            buttons: buttons,
            bodyTextSample: document.body.innerText.slice(0, 1500)
        };
JS);

    file_put_contents(storage_path('app/hpc/hpc_part_a1_inspect.json'), json_encode($formData, JSON_PRETTY_PRINT));
    echo "Saved form inspect to storage/app/hpc/hpc_part_a1_inspect.json\n";
    echo "Controls found: " . $formData['controlsCount'] . "\n";
    echo "Nav parts found: " . count($formData['navParts']) . "\n";
    echo "Buttons found: " . count($formData['buttons']) . "\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
