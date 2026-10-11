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

    echo "Filling Part-A2 dummy values...\n";

    $fillResult = $driver->executeScript(<<<'JS'
        function setVal(el, val) {
            if (!el) return;
            try {
                const proto = el.tagName === 'TEXTAREA' ? window.HTMLTextAreaElement.prototype : window.HTMLInputElement.prototype;
                const setter = Object.getOwnPropertyDescriptor(proto, 'value')?.set;
                if (setter) {
                    setter.call(el, val);
                } else {
                    el.value = val;
                }
            } catch(e) {
                el.value = val;
            }
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }

        let filled = 0;
        document.querySelectorAll('input[type="text"], textarea').forEach(el => {
            if (!el.value) {
                setVal(el, 'Good');
                filled++;
            }
        });

        // Any radio buttons
        document.querySelectorAll('input[type="radio"]').forEach(r => {
            if (!r.checked) {
                r.click();
            }
        });

        return { filledInputs: filled };
JS);

    echo "Filled on Part-A2: " . json_encode($fillResult) . "\n";
    usleep(800000);

    // 3. Click Continue
    echo "Clicking Continue to go to Part-A3...\n";
    $driver->executeScript(<<<'JS'
        const continueBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.trim().toLowerCase() === 'continue');
        if (continueBtn) continueBtn.click();
JS);

    sleep(4);

    echo "Current URL now: " . $driver->getCurrentURL() . "\n";
    $driver->takeScreenshot(storage_path('app/hpc/page_after_a2_continue.png'));

    $body = $driver->executeScript("return document.body.innerText.slice(0, 600);");
    echo "Body sample:\n" . $body . "\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
