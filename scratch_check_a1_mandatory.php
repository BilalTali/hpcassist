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

    // Let's click "Continue" without filling to see what validation errors trigger!
    $clickContinue = $driver->executeScript(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button, input[type="submit"]'));
        const continueBtn = buttons.find(b => b.innerText.trim().toLowerCase() === 'continue');
        if (continueBtn) {
            continueBtn.click();
            return true;
        }
        return false;
JS);

    echo "Clicked 'Continue' button: " . ($clickContinue ? "YES" : "NO") . "\n";
    usleep(800000);

    // Now inspect all validation error messages or highlighted fields
    $errors = $driver->executeScript(<<<'JS'
        const errorElements = Array.from(document.querySelectorAll('.invalid-feedback, .text-danger, .error, span[class*="error"], div[class*="error"], p[class*="error"], .required-field-error')).map(el => ({
            text: el.innerText.trim(),
            tag: el.tagName,
            className: el.className
        })).filter(x => x.text.length > 0);

        // Also check any fields marked with red border or aria-invalid
        const invalidFields = Array.from(document.querySelectorAll('[aria-invalid="true"], :invalid, .is-invalid')).map(el => ({
            tag: el.tagName,
            name: el.name,
            id: el.id,
            placeholder: el.placeholder,
            label: el.closest('div')?.querySelector('label')?.innerText.trim() || ''
        }));

        // Also check any toast/alert
        const alerts = Array.from(document.querySelectorAll('.toast, .alert, [role="alert"], .swal2-container, .modal')).map(a => a.innerText.trim()).filter(t => t.length > 0);

        return {
            errors: errorElements,
            invalidFields: invalidFields,
            alerts: alerts
        };
JS);

    echo "Validation inspection:\n" . json_encode($errors, JSON_PRETTY_PRINT) . "\n";
    $driver->takeScreenshot(storage_path('app/hpc/a1_validation_errors.png'));

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
