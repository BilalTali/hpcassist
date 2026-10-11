<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$_SERVER['PANTHER_NO_HEADLESS'] = '1';
putenv('PANTHER_NO_HEADLESS=1');

$client = Symfony\Component\Panther\Client::createChromeClient(config('hpc.chromedriver_path'), [
    '--user-data-dir=' . base_path(config('hpc.profile_dir')),
    '--window-size=1400,900',
]);

$client->request('GET', config('hpc.add_student_url'));
sleep(3);

$wd = $client->getWebDriver();
$dobInput = $wd->findElement(Facebook\WebDriver\WebDriverBy::cssSelector('input[placeholder="Select"]'));
$dobInput->click();
usleep(800000);

$calData = $client->executeScript(<<<'JS'
    const out = {};
    const selects = Array.from(document.querySelectorAll('.react-datepicker select, select')).map(s => ({
        className: s.className,
        options: Array.from(s.options).map(o => ({ value: o.value, text: o.text })),
        currentValue: s.value
    }));
    out.selects = selects;

    const days = Array.from(document.querySelectorAll('.react-datepicker__day')).slice(0, 10).map(d => ({
        className: d.className,
        text: d.innerText.trim(),
        ariaLabel: d.getAttribute('aria-label')
    }));
    out.days = days;

    return out;
JS);

file_put_contents('storage/app/hpc/datepicker_detail.json', json_encode($calData, JSON_PRETTY_PRINT));
echo "Inspected Datepicker.\n";

$client->quit();
