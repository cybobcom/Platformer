<?php

/**
 * Platform Configuration
 *
 * Single source of truth for all platform configuration.
 */

// ================================================================
// PHP SETTINGS
// ================================================================

ini_set('max_execution_time', '60');
ini_set('memory_limit', '512M');
ini_set('error_reporting', E_ALL);
ini_set('display_errors', '1');
error_reporting(E_ALL ^ E_NOTICE);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

// ================================================================
// PLATFORM CREDENTIALS
// ================================================================

$arrConf = [];
$arrConf['platform_name'] = "Platformer";
$arrConf['platform_login'] = "dev25elop";
$arrConf['platform_password'] = "de06velop";

// Platform identifier (based on file location)
$strPlatformIdentitier = realpath(dirname(__FILE__)) . "/";
$arrConf['platform_identifier'] = md5($strPlatformIdentitier);

// ================================================================
// PATH DETECTION - Directly in localconf (NOT in functions.php!)
// ================================================================

// BASEDIR: Project root (where public/ is)
// Example: /Applications/MAMP/htdocs/Platformer/
// TODO: THIS DID NOT WORK PROPERLY
if (isset($_SERVER['DOCUMENT_ROOT'])) {
    // DOCUMENT_ROOT is usually /public/ folder
    // THIS DID NOT WORK
    //$arrConf['basedir'] = dirname($_SERVER['DOCUMENT_ROOT']) . '/';

    // valid - proofed for local installatoin
    $path = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    $arrConf['basedir'] = $_SERVER['DOCUMENT_ROOT'].$path. '/';
} else {
    // Fallback: go up from inc.localconf.php
    $arrConf['basedir'] = dirname(dirname(dirname(__FILE__))) . '/';
}


// SOURCEDIR: Where /src/ folder is
// This file is in: /src/capps/inc.localconf.php
// We want: /src/
$arrConf['sourcedir'] = dirname(__FILE__) . '/';

// BASEURL: From functions.php (this one works correctly)
$arrConf['baseurl'] = detectBaseUrl();

// Normalize all paths
$arrConf['basedir'] = str_replace('\\', '/', $arrConf['basedir']);
$arrConf['sourcedir'] = str_replace('\\', '/', $arrConf['sourcedir']);

// Derived paths
$arrConf['securedir'] = str_replace('/src/', '/websecure/', $arrConf['sourcedir']);

// ================================================================
// UNIFIED CONFIGURATION - Single source of truth for vendor paths
// ================================================================

$arrConf['cbinit'] = [
    'vendors' => [
        'capps' => [
            'path' => $arrConf['sourcedir'] . 'capps/',
            'priority' => 100,
            'enabled' => true
        ],
        'admin' => [
            'path' => $arrConf['sourcedir'] . 'admin/',
            'priority' => 200,
            'enabled' => true
        ],
        'agent' => [
            'path' => $arrConf['sourcedir'] . 'agent/',
            'priority' => 300,
            'enabled' => true
        ],
        'custom' => [
            'path' => $arrConf['sourcedir'] . 'custom/',
            'priority' => 400,
            'enabled' => true
        ]
    ],
    'validate_input' => true,
    'enable_cache' => true,
    'enable_logging' => true,
    'fallback_enabled' => true,
    'fallback_class' => 'capps\\modules\\database\\classes\\CBObject',
    'strict_mode' => false
];

// Define convenience constants from cbinit vendors
define('CAPPS', $arrConf['cbinit']['vendors']['capps']['path']);
define('ADMIN', $arrConf['cbinit']['vendors']['admin']['path']);
define('AGENT', $arrConf['cbinit']['vendors']['agent']['path']);
define('CUSTOM', $arrConf['cbinit']['vendors']['custom']['path']);

// ================================================================
// CONVERT TO CONSTANTS
// ================================================================

foreach ($arrConf as $key => $value) {
    if (!is_array($value)) {
        define(strtoupper($key), $value);
    }
}

define('CONFIGURATION', $arrConf);

// ================================================================
// DATABASE CONFIGURATION
// ================================================================

define('DATABASE', [
    'DB_HOST' => 'localhost',
    'DB_USER' => 'root',
    'DB_PASSWORD' => 'root',
    'DB_DATABASE' => 'platformer',
    'DB_CHARSET' => 'utf8'
]);

// ================================================================
// MAIL CONFIGURATION
// ================================================================

define('MAIL', [
    'name' => '',
    'login' => '',
    'password' => '',
    'email' => '',
    'server' => '',
    'port' => '110'
]);

// ================================================================
// ADDITIONAL CONSTANTS
// ================================================================

define('ENCRYPTION_KEY32', 'platf202506300000000000000000000');
define('DEBUG_MAIL', 'robert.heuer@cybob.com');

// ================================================================
// DEBUG OUTPUT (optional)
// ================================================================

if (defined('DEBUG_MODE') && DEBUG_MODE && isset($_GET['show_config'])) {
    echo '<h2>Configuration</h2>';
    echo '<pre>';
    print_r($arrConf);
    echo "\n\nConstants:\n";
    echo 'BASEURL: ' . BASEURL . "\n";
    echo 'BASEDIR: ' . BASEDIR . "\n";
    echo 'SOURCEDIR: ' . SOURCEDIR . "\n";
    echo 'CAPPS: ' . CAPPS . "\n";
    echo 'CUSTOM: ' . CUSTOM . "\n";
    echo "\nPaths exist:\n";
    echo 'BASEDIR exists: ' . (is_dir(BASEDIR) ? '✓' : '✗') . "\n";
    echo 'SOURCEDIR exists: ' . (is_dir(SOURCEDIR) ? '✓' : '✗') . "\n";
    echo 'CAPPS exists: ' . (is_dir(CAPPS) ? '✓' : '✗') . "\n";
    echo '</pre>';
    exit;
}