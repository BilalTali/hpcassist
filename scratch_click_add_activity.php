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

    echo "Clicking '+ Add Activity' button on Part-B...\n";
    $driver->executeScript(<<<'JS'
        const btns = Array.from(document.querySelectorAll('button'));
        const addBtn = btns.find(b => b.innerText.includes('Add Activity'));
        if (addBtn) addBtn.click();
JS);

    sleep(2);
    $driver->takeScreenshot(storage_path('app/hpc/add_activity_modal.png'));

    $modalInfo = $driver->executeScript(<<<'JS'
        const modal = document.querySelector('.modal, [role="dialog"], .fade.show');
        const inputs = Array.from((modal || document).querySelectorAll('input, select, textarea, .custom-dropdown-wrapper')).map(el => ({
            tag: el.tagName,
            type: el.type || el.className,
            name: el.name || el.id,
            placeholder: el.placeholder || '',
            value: el.value || '',
            label: el.closest('div')?.querySelector('label')?.innerText?.trim() || ''
        }));

        const buttons = Array.from((modal || document).querySelectorAll('button')).map(b => b.innerText.trim()).filter(b => b.length > 0 && b.length < 40);

        return {
            modalFound: !!modal,
            modalTitle: modal?.querySelector('.modal-title, h4, h5')?.innerText?.trim(),
            inputs: inputs,
            buttons: buttons,
            bodySample: (modal || document.body).innerText.slice(0, 1000)
        };
JS);

    echo "Activity modal info:\n" . json_encode($modalInfo, JSON_PRETTY_PRINT) . "\n";
    file_put_contents(storage_path('app/hpc/activity_modal_inspect.json'), json_encode($modalInfo, JSON_PRETTY_PRINT));

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
