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

    echo "Filling Part-A1 dummy fields...\n";

    $res = $driver->executeScript(<<<'JS'
        function setVal(el, val) {
            if (!el) return;
            const nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value')?.set;
            if (nativeInputValueSetter) {
                nativeInputValueSetter.call(el, val);
            } else {
                el.value = val;
            }
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // 1. Mother tongue: find input inside container with 'Mother Tongue'
        const mtContainer = Array.from(document.querySelectorAll('label')).find(l => l.innerText.includes('Mother Tongue'))?.closest('.col-md-4, .col-md-6, div');
        const mtInput = mtContainer?.querySelector('input[placeholder="Write here"]');
        if (mtInput) setVal(mtInput, 'Kashmiri');

        // 2. Father Name
        const fatherInput = document.querySelector('input[placeholder="Enter Father Name"]');
        if (fatherInput) setVal(fatherInput, 'Mohammad Ahmad');

        // 3. Attendance working days (April to March)
        const workingDaysInputs = Array.from(document.querySelectorAll('tr:nth-child(1) input, table tbody tr:nth-child(1) td input'));
        workingDaysInputs.forEach(inp => setVal(inp, '22'));

        // 4. Attendance present days
        const presentDaysInputs = Array.from(document.querySelectorAll('tr:nth-child(2) input, table tbody tr:nth-child(2) td input'));
        presentDaysInputs.forEach(inp => setVal(inp, '20'));

        // 5. Absent times
        const absentInp = Array.from(document.querySelectorAll('label')).find(l => l.innerText.includes('absent'))?.closest('div')?.querySelector('input') || document.querySelector('input[placeholder="Write here"]:last-of-type');
        if (absentInp) setVal(absentInp, '2');

        // 6. Interest
        const interestCb = document.querySelector('input[value="STU_INTRST_READING"], input[type="checkbox"]');
        if (interestCb && !interestCb.checked) interestCb.click();

        return {
            motherTongue: mtInput?.value,
            father: fatherInput?.value,
            workingDaysFilled: workingDaysInputs.length,
            presentDaysFilled: presentDaysInputs.length
        };
JS);

    echo "Fill result:\n" . json_encode($res, JSON_PRETTY_PRINT) . "\n";
    usleep(800000);

    echo "Clicking 'Continue'...\n";
    $driver->executeScript(<<<'JS'
        const continueBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.trim().toLowerCase() === 'continue');
        if (continueBtn) continueBtn.click();
JS);

    sleep(4);

    echo "New URL after Continue: " . $driver->getCurrentURL() . "\n";
    $driver->takeScreenshot(storage_path('app/hpc/page_after_a1_continue.png'));

    $newInfo = $driver->executeScript(<<<'JS'
        return {
            url: window.location.href,
            activeTab: document.querySelector('li.active .part-name')?.innerText?.trim(),
            heading: document.querySelector('h1, h2, h3, h4')?.innerText?.trim(),
            toasts: Array.from(document.querySelectorAll('.Toastify__toast, .toast, .alert')).map(t => t.innerText.trim()),
            bodySample: document.body.innerText.slice(0, 600)
        };
JS);

    echo "New Page Info:\n" . json_encode($newInfo, JSON_PRETTY_PRINT) . "\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
