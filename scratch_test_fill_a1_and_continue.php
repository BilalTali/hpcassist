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

    echo "On Part-A1, filling dummy values for testing navigation...\n";

    $fillResult = $driver->executeScript(<<<'JS'
        // 1. Mother Tongue
        const motherTongueInp = document.querySelector('input[placeholder="Write here"]');
        if (motherTongueInp && !motherTongueInp.value) {
            motherTongueInp.value = 'Kashmiri';
            motherTongueInp.dispatchEvent(new Event('input', { bubbles: true }));
            motherTongueInp.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // 2. Father Name
        const fatherInp = document.querySelector('input[placeholder="Enter Father Name"], input[placeholder*="Father"]');
        if (fatherInp && !fatherInp.value) {
            fatherInp.value = 'Mohammad Ahmad';
            fatherInp.dispatchEvent(new Event('input', { bubbles: true }));
            fatherInp.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // 3. Check an interest checkbox
        const interestCb = document.querySelector('input[value="STU_INTRST_READING"], input[type="checkbox"]');
        if (interestCb && !interestCb.checked) {
            interestCb.click();
        }

        return {
            motherTongue: motherTongueInp?.value,
            fatherName: fatherInp?.value,
            interestChecked: interestCb?.checked
        };
JS);

    echo "Filled fields:\n" . json_encode($fillResult, JSON_PRETTY_PRINT) . "\n";
    usleep(600000);

    // Click Continue
    echo "Clicking 'Continue'...\n";
    $driver->executeScript(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button, input[type="submit"]'));
        const continueBtn = buttons.find(b => b.innerText.trim().toLowerCase() === 'continue');
        if (continueBtn) {
            continueBtn.click();
        }
JS);

    sleep(3);

    echo "URL after Continue: " . $driver->getCurrentURL() . "\n";
    $driver->takeScreenshot(storage_path('app/hpc/page_after_continue.png'));

    // Check Toast / Alerts
    $toast = $driver->executeScript(<<<'JS'
        const toasts = Array.from(document.querySelectorAll('.Toastify__toast, .toast, .alert')).map(t => t.innerText.trim());
        return {
            url: window.location.href,
            toasts: toasts,
            h1: document.querySelector('h1, h2, h3, h4')?.innerText?.trim()
        };
JS);

    echo "Outcome:\n" . json_encode($toast, JSON_PRETTY_PRINT) . "\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
