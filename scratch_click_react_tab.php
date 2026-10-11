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

    $res = $driver->executeScript(<<<'JS'
        const cLi = Array.from(document.querySelectorAll('li')).find(li => li.innerText?.trim() === '6\n\nC' || li.innerText?.trim() === '6\nC' || li.querySelector('.part-name')?.innerText?.trim() === 'C');
        if (!cLi) return { error: 'Not found' };

        // Check react props
        const key = Object.keys(cLi).find(k => k.startsWith('__reactProps'));
        const props = key ? cLi[key] : null;

        let called = false;
        if (props && props.onClick) {
            props.onClick({ preventDefault: () => {}, stopPropagation: () => {} });
            called = true;
        } else {
            cLi.click();
        }

        return {
            hasProps: !!props,
            hasOnClick: !!(props && props.onClick),
            called: called
        };
JS);

    echo "React click result: " . json_encode($res, JSON_PRETTY_PRINT) . "\n";
    sleep(3);
    echo "Current URL: " . $driver->getCurrentURL() . "\n";
    $driver->takeScreenshot(storage_path('app/hpc/tab_c_react_clicked.png'));

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
