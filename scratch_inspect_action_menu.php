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
    
    // Switch to HPC tab
    $handles = $driver->getWindowHandles();
    foreach ($handles as $h) {
        $driver->switchTo()->window($h);
        if (str_contains($driver->getCurrentURL(), 'hpc.parakh.ncert.gov.in')) {
            break;
        }
    }

    echo "Active tab URL: " . $driver->getCurrentURL() . "\n";
    if (!str_contains($driver->getCurrentURL(), '/middle-stage')) {
        $driver->get('https://hpc.parakh.ncert.gov.in/en/hpc/middle-stage');
        sleep(3);
    }

    $actionInspect = $driver->executeScript(<<<'JS'
        const rows = document.querySelectorAll('tbody tr');
        if (rows.length === 0) return { error: 'No rows' };

        const firstRow = rows[0];
        const lastCell = firstRow.querySelector('td:last-child');
        
        // Find trigger inside lastCell
        const trigger = lastCell.querySelector('button, [role="button"], svg, i, span, div, a');
        if (trigger) {
            trigger.click();
        }

        return {
            lastCellHtml: lastCell.innerHTML,
            triggerTag: trigger?.tagName,
            triggerClass: trigger?.className
        };
JS);

    echo "Action cell trigger info:\n" . json_encode($actionInspect, JSON_PRETTY_PRINT) . "\n";
    usleep(800000);

    // Inspect what popped up
    $menuItems = $driver->executeScript(<<<'JS'
        const allItems = Array.from(document.querySelectorAll('*')).filter(el => {
            const t = el.innerText?.trim() || '';
            const isClickable = el.tagName === 'A' || el.tagName === 'BUTTON' || el.getAttribute('role') === 'menuitem' || el.classList.contains('dropdown-item');
            return isClickable && (t.includes('Edit') || t.includes('HPC') || t.includes('View') || t.includes('Card') || t.includes('Fill') || t.includes('Download') || t.includes('Part'));
        }).map(el => ({
            tag: el.tagName,
            text: el.innerText.trim(),
            href: el.href || el.getAttribute('href') || null,
            className: el.className
        }));

        // Also check any new links on page
        const newLinks = Array.from(document.querySelectorAll('a')).map(a => ({
            text: a.innerText.trim(),
            href: a.href
        })).filter(a => a.href && (a.href.includes('/part-') || a.href.includes('/hpc/')));

        return {
            items: allItems,
            newLinks: newLinks
        };
JS);

    echo "Menu items after click:\n" . json_encode($menuItems, JSON_PRETTY_PRINT) . "\n";
    $driver->takeScreenshot(storage_path('app/hpc/action_menu_opened.png'));
    file_put_contents(storage_path('app/hpc/action_menu_opened.json'), json_encode(['cell' => $actionInspect, 'menu' => $menuItems], JSON_PRETTY_PRINT));

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
