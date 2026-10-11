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

    $creditUrl = 'https://hpc.parakh.ncert.gov.in/en/hpc/middle-stage/MTIzMTI4LTI2Mjctams=/hpc-credit';
    echo "Navigating to: {$creditUrl} ...\n";
    $driver->get($creditUrl);
    sleep(4);

    echo "Current URL: " . $driver->getCurrentURL() . "\n";
    $driver->takeScreenshot(storage_path('app/hpc/hpc_credit_page.png'));

    $pageData = $driver->executeScript(<<<'JS'
        const headings = Array.from(document.querySelectorAll('h1, h2, h3, h4, h5, h6, strong, .card-title')).map(h => h.innerText.trim()).filter(h => h.length > 0 && h.length < 80);

        const buttons = Array.from(document.querySelectorAll('button, input[type="submit"]')).map(b => ({
            text: b.innerText.trim() || b.value || '',
            className: b.className,
            type: b.type,
            disabled: b.disabled
        })).filter(b => b.text.length > 0 && b.text.length < 40);

        const inputs = Array.from(document.querySelectorAll('input, select, textarea')).map(el => ({
            tag: el.tagName,
            type: el.type,
            name: el.name || el.id,
            placeholder: el.placeholder || '',
            value: el.value || '',
            checked: el.checked || false,
            label: el.closest('div')?.querySelector('label')?.innerText?.trim() || ''
        }));

        return {
            title: document.title,
            url: window.location.href,
            headings: Array.from(new Set(headings)),
            buttons: buttons,
            inputs: inputs,
            bodySample: document.body.innerText.slice(0, 1500)
        };
JS);

    file_put_contents(storage_path('app/hpc/hpc_credit_inspect.json'), json_encode($pageData, JSON_PRETTY_PRINT));
    echo "Saved to storage/app/hpc/hpc_credit_inspect.json and screenshot to hpc_credit_page.png\n";
    echo "Body snippet:\n" . substr($pageData['bodySample'], 0, 600) . "\n";
    echo "Buttons found:\n" . json_encode($pageData['buttons'], JSON_PRETTY_PRINT) . "\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
