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

    echo "Clicking Continue on Part-B...\n";
    $driver->executeScript(<<<'JS'
        const continueBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.trim().toLowerCase() === 'continue');
        if (continueBtn) continueBtn.click();
JS);

    usleep(800000);

    $toast = $driver->executeScript(<<<'JS'
        const toasts = Array.from(document.querySelectorAll('.Toastify__toast, .toast, .alert, .invalid-feedback, div[class*="error"]')).map(t => t.innerText.trim());
        return {
            toasts: toasts
        };
JS);

    echo "Toast on Part-B Continue: " . json_encode($toast, JSON_PRETTY_PRINT) . "\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
