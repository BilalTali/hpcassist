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

    echo "Clicking 'Mark as Complete'...\n";
    $driver->executeScript(<<<'JS'
        const markBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Mark as Complete'));
        if (markBtn && !markBtn.disabled) {
            markBtn.click();
        }
JS);

    sleep(2);
    $driver->takeScreenshot(storage_path('app/hpc/mark_as_complete_modal.png'));

    $modal = $driver->executeScript(<<<'JS'
        const modal = document.querySelector('.modal, [role="dialog"], .swal2-container, .fade.show');
        const text = (modal || document.body).innerText;
        const buttons = Array.from((modal || document).querySelectorAll('button')).map(b => b.innerText.trim());

        return {
            modalFound: !!modal,
            buttons: buttons,
            snippet: text.slice(0, 500)
        };
JS);

    echo "Modal info:\n" . json_encode($modal, JSON_PRETTY_PRINT) . "\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
