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

    echo "Selecting 'Pick from Activity Bank' and clicking Continue in modal...\n";
    $driver->executeScript(<<<'JS'
        const labels = Array.from(document.querySelectorAll('label, div, p, span'));
        const bankLabel = labels.find(l => l.innerText.includes('Pick from Activity Bank'));
        if (bankLabel) {
            const radio = bankLabel.closest('div')?.querySelector('input[type="radio"]') || bankLabel;
            radio.click();
        }

        // Click Continue in modal
        const modal = document.querySelector('.modal, [role="dialog"], .fade.show');
        const contBtn = Array.from(modal.querySelectorAll('button')).find(b => b.innerText.trim() === 'Continue');
        if (contBtn) contBtn.click();
JS);

    sleep(3);
    $driver->takeScreenshot(storage_path('app/hpc/activity_bank_opened.png'));

    $info = $driver->executeScript(<<<'JS'
        return {
            url: window.location.href,
            body: document.body.innerText.slice(0, 1000)
        };
JS);

    echo "Activity bank info:\n" . json_encode($info, JSON_PRETTY_PRINT) . "\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
