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

    echo "Current page: " . $driver->getCurrentURL() . "\n";

    // Check if clicking A(2) tab directly works
    $clickResult = $driver->executeScript(<<<'JS'
        const tabs = Array.from(document.querySelectorAll('li, div, p')).filter(el => {
            const t = el.innerText?.trim() || '';
            return t === 'A(2)';
        });
        if (tabs.length > 0) {
            const target = tabs[0].closest('li') || tabs[0];
            target.click();
            return { clicked: true, tag: target.tagName, text: target.innerText };
        }
        return { clicked: false };
JS);

    echo "Clicked A(2): " . json_encode($clickResult) . "\n";
    sleep(3);

    echo "URL after clicking A(2): " . $driver->getCurrentURL() . "\n";
    $driver->takeScreenshot(storage_path('app/hpc/tab_a2_clicked.png'));

    // Check what the page shows now
    $pageInfo = $driver->executeScript(<<<'JS'
        const headings = Array.from(document.querySelectorAll('h1, h2, h3, h4, h5, h6, .card-title, .section-title, legend, strong')).map(h => h.innerText.trim()).filter(h => h.length > 0 && h.length < 80);
        return {
            title: document.title,
            url: window.location.href,
            headings: Array.from(new Set(headings)).slice(0, 20),
            bodyPreview: document.body.innerText.slice(0, 500)
        };
JS);

    echo "Page info:\n" . json_encode($pageInfo, JSON_PRETTY_PRINT) . "\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
