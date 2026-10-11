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
    $driver = RemoteWebDriver::create('http://127.0.0.1:9515', $capabilities, 15000, 15000);
    
    foreach ($driver->getWindowHandles() as $h) {
        $driver->switchTo()->window($h);
        if (str_contains($driver->getCurrentURL(), 'hpc.parakh.ncert.gov.in')) break;
    }

    // Step 1: Activity Name
    echo "Filling Activity Name...\n";
    $driver->executeScript(<<<'JS'
        function setVal(el, val) {
            if (!el) return;
            el.value = val;
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }

        const nameInp = document.querySelector('input[placeholder="Write here"], input[name*="name"], input[placeholder*="Name"], textarea');
        if (nameInp) setVal(nameInp, 'Science Project Activity');

        // Approach of activity checkboxes
        document.querySelectorAll('input[type="checkbox"]').forEach(c => {
            if (!c.checked) c.click();
        });
JS);

    usleep(500000);
    // Click Continue
    $driver->executeScript("document.querySelector('button.custom-btn:not(.custom-btn-secondary)')?.click();");
    sleep(3);
    echo "After Step 1: " . $driver->executeScript("return document.body.innerText.slice(0, 400);") . "\n";

    // Now try clicking Finish Activity or Continue
    echo "Clicking Finish Activity button if available...\n";
    $driver->executeScript(<<<'JS'
        const btns = Array.from(document.querySelectorAll('button'));
        const finishBtn = btns.find(b => b.innerText.includes('Finish Activity'));
        if (finishBtn) finishBtn.click();
JS);
    sleep(3);

    // Take screenshot
    $driver->takeScreenshot(storage_path('app/hpc/activity_finish_state.png'));

    // Check if C is clickable or if /part-C can be reached
    echo "Checking Part C tab...\n";
    $driver->get('https://hpc.parakh.ncert.gov.in/en/hpc/middle-stage/MTIzMTI4LTI2Mjctams=/part-C');
    sleep(3);
    echo "URL now: " . $driver->getCurrentURL() . "\n";
    $driver->takeScreenshot(storage_path('app/hpc/part_c_attempt.png'));
    echo "Body snippet: " . $driver->executeScript("return document.body.innerText.slice(0, 500);") . "\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
