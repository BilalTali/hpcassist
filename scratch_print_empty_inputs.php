<?php

$data = json_decode(file_get_contents('storage/app/hpc/a1_all_inputs.json'), true);
foreach ($data as $d) {
    if ($d['value'] === '' && $d['type'] !== 'checkbox') {
        echo "[idx {$d['index']}] id={$d['id']} label={$d['label']} placeholder={$d['placeholder']}\n";
    }
}
