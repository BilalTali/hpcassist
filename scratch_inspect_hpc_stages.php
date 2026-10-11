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
    $driver = RemoteWebDriver::create('http://127.0.0.1:9515', $capabilities, 5000, 5000);
    
    echo "Navigating to https://hpc.parakh.ncert.gov.in/en/hpc ...\n";
    $driver->get('https://hpc.parakh.ncert.gov.in/en/hpc');
    sleep(4);

    echo "Current URL: " . $driver->getCurrentURL() . "\n";
    $driver->takeScreenshot(storage_path('app/hpc/stages_page.png'));

    $stagesData = $driver->executeScript(<<<'JS'
        // Find stage cards, links, buttons
        const allLinks = Array.from(document.querySelectorAll('a')).map(a => ({
            text: a.innerText.trim(),
            href: a.href,
            className: a.className
        })).filter(a => a.href && a.href.includes('/en/hpc'));

        const cards = Array.from(document.querySelectorAll('div, section, article, a')).filter(el => {
            const txt = el.innerText || '';
            return (txt.includes('Stage') || txt.includes('stage') || txt.includes('Balvatika') || txt.includes('Foundational') || txt.includes('Preparatory') || txt.includes('Middle') || txt.includes('Secondary'))
                && el.children.length < 10 && txt.length < 150 && txt.length > 3;
        }).map(el => ({
            tag: el.tagName,
            text: el.innerText.trim(),
            href: el.href || el.getAttribute('href') || null,
            className: el.className
        }));

        const buttons = Array.from(document.querySelectorAll('button, [role="button"]')).map(b => ({
            text: b.innerText.trim(),
            className: b.className
        })).filter(b => b.text.length > 0 && b.text.length < 60);

        return {
            title: document.title,
            url: window.location.href,
            hpcLinks: allLinks,
            stageCards: cards,
            buttons: buttons,
            bodyText: document.body.innerText.slice(0, 1500)
        };
JS);

    file_put_contents(storage_path('app/hpc/stages_inspect.json'), json_encode($stagesData, JSON_PRETTY_PRINT));
    echo "Saved to storage/app/hpc/stages_inspect.json and screenshot to stages_page.png\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
