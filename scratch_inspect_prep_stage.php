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

    $driver->get('https://hpc.parakh.ncert.gov.in/en/hpc/preparatory-stage');
    sleep(3);

    $prepData = $driver->executeScript(<<<'JS'
        const rows = Array.from(document.querySelectorAll('tbody tr')).map(tr => {
            return Array.from(tr.querySelectorAll('td')).map(td => td.innerText.trim());
        });
        return {
            url: window.location.href,
            rowCount: rows.length,
            rows: rows
        };
JS);

    echo "Preparatory stage data:\n" . json_encode($prepData, JSON_PRETTY_PRINT) . "\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
