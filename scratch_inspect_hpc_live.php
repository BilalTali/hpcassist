<?php

// Check active tabs in running Chrome
$json = @file_get_contents('http://127.0.0.1:9222/json/list');
if (!$json) {
    echo "Chrome remote debugging is not reachable on port 9222.\n";
    exit(1);
}

$tabs = json_decode($json, true);
$pages = array_filter($tabs, fn($t) => ($t['type'] ?? '') === 'page');

echo "Open Pages (" . count($pages) . "):\n";
foreach ($pages as $p) {
    echo " - [{$p['id']}] {$p['title']} => {$p['url']}\n";
}
