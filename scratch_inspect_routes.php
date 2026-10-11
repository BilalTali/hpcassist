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

    $routes = $driver->executeScript(<<<'JS'
        // Search scripts or window for route definitions
        const scripts = Array.from(document.querySelectorAll('script[src]')).map(s => s.src);
        
        // Check window objects or navigation tabs
        const navList = Array.from(document.querySelectorAll('.part-name, li p')).map(el => ({
            text: el.innerText.trim(),
            parentClass: el.parentElement?.className,
            tag: el.tagName
        }));

        return {
            scripts: scripts,
            navList: navList
        };
JS);

    echo "Navigation and scripts:\n" . json_encode($routes, JSON_PRETTY_PRINT) . "\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
