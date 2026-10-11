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
    
    $handles = $driver->getWindowHandles();
    foreach ($handles as $h) {
        $driver->switchTo()->window($h);
        if (str_contains($driver->getCurrentURL(), 'hpc.parakh.ncert.gov.in')) break;
    }

    echo "Clicking 'Open' on first student...\n";
    $driver->executeScript(<<<'JS'
        const items = Array.from(document.querySelectorAll('.custom-dropdown-item'));
        const openItem = items.find(it => it.innerText.trim() === 'Open');
        if (openItem) {
            openItem.click();
            return true;
        }
        return false;
JS);

    sleep(4);

    // If new tab opened, switch to it
    $allHandles = $driver->getWindowHandles();
    $driver->switchTo()->window(end($allHandles));

    echo "New URL after clicking Open: " . $driver->getCurrentURL() . "\n";
    echo "Title: " . $driver->getTitle() . "\n";

    $driver->takeScreenshot(storage_path('app/hpc/student_hpc_opened.png'));
    echo "Screenshot saved to storage/app/hpc/student_hpc_opened.png\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
