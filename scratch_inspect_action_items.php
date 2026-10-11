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
    
    $handles = $driver->getWindowHandles();
    foreach ($handles as $h) {
        $driver->switchTo()->window($h);
        if (str_contains($driver->getCurrentURL(), 'hpc.parakh.ncert.gov.in')) break;
    }

    $header = $driver->findElement(WebDriverBy::cssSelector('tbody tr:first-child .custom-dropdown-header'));
    $header->click();
    usleep(600000);

    $items = $driver->executeScript(<<<'JS'
        const elements = Array.from(document.querySelectorAll('.custom-dropdown-item, .custom-dropdown-body *, .tv-action-dropdown *')).map(el => ({
            tag: el.tagName,
            className: el.className,
            text: (el.innerText || '').trim(),
            href: el.href || el.getAttribute('href') || null
        })).filter(x => x.text.length > 0 && x.text.length < 50);

        // Check DOM elements inside custom-dropdown-body
        const bodyEl = document.querySelector('.custom-dropdown-body, .dropdown-menu');
        const bodyHtml = bodyEl ? bodyEl.outerHTML : null;

        return { items: elements, bodyHtml: bodyHtml };
JS);

    echo "Found options:\n" . json_encode($items, JSON_PRETTY_PRINT) . "\n";
    $driver->takeScreenshot(storage_path('app/hpc/action_dropdown_clicked.png'));

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
