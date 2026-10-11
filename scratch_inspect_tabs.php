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

    $tabs = $driver->executeScript(<<<'JS'
        const all = Array.from(document.querySelectorAll('*')).filter(el => {
            const t = el.innerText?.trim() || '';
            return (t === 'A(1)' || t === 'A(2)' || t === 'A(3)' || t === 'A(4)' || t === 'B' || t === 'C' || t === 'HPC Credit') && el.children.length === 0;
        }).map(el => ({
            text: el.innerText.trim(),
            tag: el.tagName,
            parentTag: el.parentElement?.tagName,
            parentClass: el.parentElement?.className,
            parentHref: el.parentElement?.href || el.parentElement?.getAttribute('href') || null,
            outer: el.parentElement?.outerHTML?.slice(0, 200)
        }));

        return all;
JS);

    echo "Tabs found:\n" . json_encode($tabs, JSON_PRETTY_PRINT) . "\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
