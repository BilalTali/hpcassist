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

    echo "On: " . $driver->getCurrentURL() . "\n";

    // Try clicking C tab
    $clicked = $driver->executeScript(<<<'JS'
        const cTab = Array.from(document.querySelectorAll('li, div, p')).find(el => el.innerText?.trim() === 'C');
        if (cTab) {
            const li = cTab.closest('li') || cTab;
            li.click();
            return { clicked: true, text: li.innerText };
        }
        return { clicked: false };
JS);

    echo "Click result for C: " . json_encode($clicked) . "\n";
    sleep(3);

    echo "URL now: " . $driver->getCurrentURL() . "\n";
    $driver->takeScreenshot(storage_path('app/hpc/tab_c_clicked.png'));

    // Check if URL changed or try navigating to /part-C
    if (!str_contains($driver->getCurrentURL(), 'part-C')) {
        echo "Trying direct navigate to /part-C...\n";
        $driver->get('https://hpc.parakh.ncert.gov.in/en/hpc/middle-stage/MTIzMTI4LTI2Mjctams=/part-C');
        sleep(3);
        echo "URL after direct get: " . $driver->getCurrentURL() . "\n";
        $driver->takeScreenshot(storage_path('app/hpc/part_c_direct.png'));
    }

    $body = $driver->executeScript("return document.body.innerText.slice(0, 800);");
    echo "Body on C:\n" . $body . "\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
