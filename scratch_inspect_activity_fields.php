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

    echo "On Part-B activity. Inspecting activity fields...\n";
    $driver->takeScreenshot(storage_path('app/hpc/activity_step1.png'));

    $fields = $driver->executeScript(<<<'JS'
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
                label: (lbl || containerLbl || '').trim()
            };
        }).filter(x => x.type !== 'hidden');

        return {
            inputs: inputs,
            body: document.body.innerText.slice(0, 1200)
        };
JS);

    file_put_contents(storage_path('app/hpc/activity_step1_fields.json'), json_encode($fields, JSON_PRETTY_PRINT));
    echo "Found " . count($fields['inputs']) . " inputs in Activity Step 1.\n";
    echo "Body snippet:\n" . $fields['body'] . "\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
