<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\WebDriverBy;

$options = new ChromeOptions();
$options->setExperimentalOption('debuggerAddress', '127.0.0.1:9222');

$capabilities = DesiredCapabilities::chrome();
$capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

try {
    $driver = RemoteWebDriver::create('http://127.0.0.1:9515', $capabilities, 10000, 10000);
    
    // Always find and switch to the tab that has 'hpc.parakh.ncert.gov.in'
    $targetHandle = null;
    foreach ($driver->getWindowHandles() as $h) {
        $driver->switchTo()->window($h);
        if (str_contains($driver->getCurrentURL(), 'hpc.parakh.ncert.gov.in')) {
            $targetHandle = $h;
            break;
        }
    }

    if (!$targetHandle) {
        die("Could not find HPC tab.\n");
    }

    echo "On HPC tab: " . $driver->getCurrentURL() . "\n";

    // 1. Click the action dropdown header
    $header = $driver->findElement(WebDriverBy::cssSelector('tbody tr:first-child .custom-dropdown-header'));
    $header->click();
    echo "Clicked action dropdown header...\n";
    usleep(500000);

    // 2. Click the 'Open' option
    $clicked = $driver->executeScript(<<<'JS'
        const items = Array.from(document.querySelectorAll('.custom-dropdown-item'));
        const openItem = items.find(it => it.innerText.trim().toLowerCase() === 'open');
        if (openItem) {
            openItem.click();
            return true;
        }
        return false;
JS);

    echo "Clicked 'Open': " . ($clicked ? "YES" : "NO") . "\n";
    sleep(4);

    // Re-check URL
    $newUrl = $driver->getCurrentURL();
    echo "Current URL now: " . $newUrl . "\n";

    $driver->takeScreenshot(storage_path('app/hpc/student_hpc_part_a1.png'));
    echo "Screenshot saved to storage/app/hpc/student_hpc_part_a1.png\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
