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
    $driver = RemoteWebDriver::create('http://127.0.0.1:9515', $capabilities, 5000, 5000);
    
    echo "Navigating to https://hpc.parakh.ncert.gov.in/en/hpc/middle-stage ...\n";
    $driver->get('https://hpc.parakh.ncert.gov.in/en/hpc/middle-stage');
    sleep(4);

    echo "Current URL: " . $driver->getCurrentURL() . "\n";
    $driver->takeScreenshot(storage_path('app/hpc/middle_stage_page.png'));

    $data = $driver->executeScript(<<<'JS'
        // Find tables or student rows
        const table = document.querySelector('table');
        const headers = Array.from(document.querySelectorAll('th')).map(th => th.innerText.trim());
        
        const rows = Array.from(document.querySelectorAll('tbody tr')).map(tr => {
            const cells = Array.from(tr.querySelectorAll('td')).map(td => td.innerText.trim());
            const actionLinks = Array.from(tr.querySelectorAll('a, button')).map(a => ({
                tag: a.tagName,
                text: a.innerText.trim(),
                href: a.href || a.getAttribute('href') || null,
                title: a.getAttribute('title') || null,
                ariaLabel: a.getAttribute('aria-label') || null,
                icon: a.querySelector('i, svg')?.className || null
            }));
            return {
                cells: cells,
                actions: actionLinks
            };
        });

        // Also check any filters (Grade, Section)
        const filters = Array.from(document.querySelectorAll('select, .custom-dropdown-header, input')).map(f => ({
            tag: f.tagName,
            type: f.type,
            name: f.name,
            id: f.id,
            placeholder: f.placeholder,
            text: f.innerText?.trim()
        }));

        // All links containing /part- or /middle-stage/
        const studentHpcLinks = Array.from(document.querySelectorAll('a')).filter(a => a.href && (a.href.includes('/middle-stage/') || a.href.includes('/part-'))).map(a => ({
            text: a.innerText.trim(),
            href: a.href
        }));

        return {
            title: document.title,
            url: window.location.href,
            headers: headers,
            rowCount: rows.length,
            rowsSample: rows.slice(0, 10),
            filters: filters,
            studentHpcLinks: studentHpcLinks,
            bodyTextSample: document.body.innerText.slice(0, 800)
        };
JS);

    file_put_contents(storage_path('app/hpc/middle_stage_students.json'), json_encode($data, JSON_PRETTY_PRINT));
    echo "Saved to storage/app/hpc/middle_stage_students.json and screenshot to middle_stage_page.png\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
