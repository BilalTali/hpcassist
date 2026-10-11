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

    $requiredFields = $driver->executeScript(<<<'JS'
        const stars = Array.from(document.querySelectorAll('*')).filter(el => {
            const t = el.innerText || '';
            return t.includes('*') && el.children.length === 0 && t.length < 80;
        }).map(el => {
            const container = el.closest('.form-group, .col, .row, div, td, th');
            const inputs = Array.from(container?.querySelectorAll('input, select, textarea, .custom-dropdown-wrapper') || []).map(inp => ({
                tag: inp.tagName,
                type: inp.type || inp.className,
                name: inp.name || inp.id,
                value: inp.value,
                checked: inp.checked,
                placeholder: inp.placeholder
            }));

            return {
                labelWithStar: el.innerText.trim(),
                parentTag: el.parentElement?.tagName,
                inputsInContainer: inputs
            };
        });

        // Also check attendance table
        const attendanceInputs = Array.from(document.querySelectorAll('table input')).map(i => ({
            name: i.name,
            placeholder: i.placeholder,
            value: i.value
        }));

        return {
            stars: stars,
            attendanceInputCount: attendanceInputs.length
        };
JS);

    echo "Mandatory fields found on Part-A1:\n" . json_encode($requiredFields, JSON_PRETTY_PRINT) . "\n";
    file_put_contents(storage_path('app/hpc/a1_mandatory_fields.json'), json_encode($requiredFields, JSON_PRETTY_PRINT));

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
