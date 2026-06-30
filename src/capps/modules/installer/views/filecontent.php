<?php

global $arrConf;

$moduleKey = $_GET['module'] ?? '';
$relFile   = $_GET['file'] ?? '';

header('Content-Type: text/plain; charset=utf-8');

// moduleKey Format: "vendor/modulename"
if (!preg_match('#^([a-zA-Z0-9_]+)/([a-zA-Z0-9_\-]+)$#', $moduleKey, $m)) {
    http_response_code(400);
    echo 'Ungueltiger Modul-Key.';
    exit;
}
[$full, $vendorKey, $moduleName] = $m;

// relFile darf nur classes/, controller/ oder views/ + sichere Zeichen enthalten
if (!preg_match('#^(classes|controller|views)/[a-zA-Z0-9_\-./]+\.php$#', $relFile)) {
    http_response_code(400);
    echo 'Ungueltiger Datei-Pfad.';
    exit;
}
if (str_contains($relFile, '..')) {
    http_response_code(400);
    echo 'Ungueltiger Datei-Pfad.';
    exit;
}

$vendors = $arrConf['cbinit']['vendors'] ?? [];
if (!isset($vendors[$vendorKey]) || empty($vendors[$vendorKey]['enabled'])) {
    http_response_code(404);
    echo 'Vendor nicht gefunden.';
    exit;
}

$moduleName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $moduleName);
$filePath = rtrim($vendors[$vendorKey]['path'], '/') . '/modules/' . $moduleName . '/' . $relFile;

if (!file_exists($filePath)) {
    http_response_code(404);
    echo 'Datei nicht gefunden.';
    exit;
}

echo file_get_contents($filePath);