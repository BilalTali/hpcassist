<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$testProfile = storage_path('app/hpc/teacher-test-profile');
if (!is_dir($testProfile)) {
    mkdir($testProfile, 0777, true);
}

$_SERVER['PANTHER_NO_HEADLESS'] = '1';
putenv('PANTHER_NO_HEADLESS=1');

$client = Symfony\Component\Panther\Client::createChromeClient(config('hpc.chromedriver_path'), [
    '--user-data-dir=' . $testProfile,
    '--window-size=1400,900',
]);

echo "Navigating to login page...\n";
$client->request('GET', config('hpc.login_url'));
sleep(4);

$pageInfo = $client->executeScript(<<<'JS'
    const inputs = Array.from(document.querySelectorAll('input, select, button')).map(el => ({
        tag: el.tagName,
        type: el.type,
        name: el.name,
        id: el.id,
        placeholder: el.placeholder,
        text: el.innerText.trim(),
        ariaLabel: el.getAttribute('aria-label')
    }));

    const tabsOrButtons = Array.from(document.querySelectorAll('button, [role="tab"], a')).map(el => ({
        tag: el.tagName,
        role: el.getAttribute('role'),
        text: el.innerText.trim(),
        className: el.className
    })).filter(b => b.text.length > 0 && b.text.length < 50);

    return {
        url: window.location.href,
        title: document.title,
        inputs: inputs,
        buttons: tabsOrButtons
    };
JS);

echo "Current URL: " . $pageInfo['url'] . "\n";
echo "Title: " . $pageInfo['title'] . "\n";
echo "Inputs found:\n" . json_encode($pageInfo['inputs'], JSON_PRETTY_PRINT) . "\n";
echo "Buttons / tabs found:\n" . json_encode($pageInfo['buttons'], JSON_PRETTY_PRINT) . "\n";

$client->quit();
