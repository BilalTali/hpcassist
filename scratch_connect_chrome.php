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

// Check if chromedriver is running, if not start it on port 9515
exec("pgrep -f 'chromedriver' 2>/dev/null", $pids);
if (empty($pids)) {
    $driverBin = config('hpc.chromedriver_path');
    exec("nohup {$driverBin} --port=9515 > /dev/null 2>&1 &");
    sleep(1);
}

try {
    $driver = RemoteWebDriver::create('http://127.0.0.1:9515', $capabilities, 5000, 5000);
    echo "Connected successfully to active Chrome!\n";
    echo "Current URL: " . $driver->getCurrentURL() . "\n";
    echo "Title: " . $driver->getTitle() . "\n";
    
    // Check if we are on dashboard or hpc page
    $currentUrl = $driver->getCurrentURL();
    echo "Checking navigation elements...\n";

    $pageData = $driver->executeScript(<<<'JS'
        const links = Array.from(document.querySelectorAll('a, button, [role="button"]')).map(el => ({
            tag: el.tagName,
            text: el.innerText.trim(),
            href: el.href || null,
            role: el.getAttribute('role')
        })).filter(l => l.text.length > 0 && l.text.length < 50);

        return {
            url: window.location.href,
            links: links
        };
JS);

    echo "Found " . count($pageData['links']) . " clickable elements.\n";
    file_put_contents('storage/app/hpc/current_page_elements.json', json_encode($pageData, JSON_PRETTY_PRINT));
    echo "Saved to storage/app/hpc/current_page_elements.json\n";

} catch (\Throwable $e) {
    echo "Error connecting: " . $e->getMessage() . "\n";
}
