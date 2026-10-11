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

    echo "On HPC Credit page. Checking declaration and marks...\n";

    $statusBefore = $driver->executeScript(<<<'JS'
        // Fill summative assessment marks: Max Marks (50) and Marks Obtained (45)
        const inputs = Array.from(document.querySelectorAll('table input[type="text"]'));
        let filled = 0;
        inputs.forEach((inp, idx) => {
            const val = (idx % 2 === 0) ? '50' : '45';
            inp.value = val;
            inp.dispatchEvent(new Event('input', { bubbles: true }));
            inp.dispatchEvent(new Event('change', { bubbles: true }));
            filled++;
        });

        // Click declaration checkbox
        const decCb = document.querySelector('input[type="checkbox"]');
        if (decCb && !decCb.checked) {
            decCb.click();
        }

        const markBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Mark as Complete'));
        const saveDraftBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Save as Draft'));

        return {
            inputsFilled: filled,
            decChecked: decCb?.checked,
            markBtnDisabled: markBtn?.disabled,
            saveDraftDisabled: saveDraftBtn?.disabled
        };
JS);

    echo "Status after filling:\n" . json_encode($statusBefore, JSON_PRETTY_PRINT) . "\n";
    sleep(2);

    $driver->takeScreenshot(storage_path('app/hpc/credit_filled.png'));

    // Try clicking "Save as Draft"
    echo "Clicking 'Save as Draft' on HPC Credit page...\n";
    $driver->executeScript(<<<'JS'
        const saveDraftBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Save as Draft'));
        if (saveDraftBtn && !saveDraftBtn.disabled) {
            saveDraftBtn.click();
        }
JS);

    sleep(3);

    $toast = $driver->executeScript(<<<'JS'
        const toasts = Array.from(document.querySelectorAll('.Toastify__toast, .toast, .alert, .modal')).map(t => t.innerText.trim());
        const markBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Mark as Complete'));
        return {
            url: window.location.href,
            toasts: toasts,
            markBtnDisabled: markBtn?.disabled
        };
JS);

    echo "Result after Save as Draft:\n" . json_encode($toast, JSON_PRETTY_PRINT) . "\n";
    $driver->takeScreenshot(storage_path('app/hpc/credit_after_save.png'));

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
