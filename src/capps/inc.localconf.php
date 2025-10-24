<?php

/**
 * Platform Configuration
 *
 * Single source of truth for all platform configuration.
 * Copy inc.localconf.example.php to inc.localconf.php for setup.
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
$arrConf['platform_name'] = "Admin";
$arrConf['platform_login'] = "dev25elop";
$arrConf['platform_password'] = "de06velop";

// Platform identifier (based on file location)
$strPlatformIdentitier = realpath(dirname(__FILE__)) . "/";
$arrConf['platform_identifier'] = md5($strPlatformIdentitier);

// Apply robust detection
$arrConf['baseurl'] = detectBaseUrl();
$arrConf['basedir'] = detectBasedir();
$arrConf['sourcedir'] = detectSourcedir();
$arrConf['securedir'] = str_replace('/src/', '/websecure/', $arrConf['sourcedir']);
$arrConf['capps'] = $arrConf['sourcedir'] . 'capps/';
$arrConf['custom'] = $arrConf['sourcedir'] . 'custom/';

// ================================================================
// UNIFIED CONFIGURATION
// All module configurations in one place
// ================================================================

$arrConf['cbinit'] = [
    'vendors' => [
        'capps' => [
            'path' => $arrConf['sourcedir'] . 'capps/',
            'priority' => 100,
            'enabled' => true
        ],
        'custom' => [
            'path' => $arrConf['sourcedir'] . 'custom/',
            'priority' => 200,
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

// ================================================================
// CONVERT TO CONSTANTS
// ================================================================

// Individual constants for common use
foreach ($arrConf as $key => $value) {
    if (!is_array($value)) {
        define(strtoupper($key), $value);
    }
}

// Full configuration as single constant (PHPStorm-friendly)
define('CONFIGURATION', $arrConf);

// Setup CBINIT globals (for backward compatibility)
global $CBINIT_CONFIG, $CBINIT_CACHE, $CBINIT_STATS;
$CBINIT_CONFIG = $arrConf['cbinit'];
$CBINIT_CACHE = [];
$CBINIT_STATS = ['hits' => 0, 'misses' => 0, 'fallbacks' => 0, 'vendor_usage' => []];

// ================================================================
// DATABASE CONFIGURATION (Separate for security)
// ================================================================

// TODO: Later move credentials to environment variables
// or separate encrypted config file
define('DATABASE', [
    'DB_HOST' => 'localhost',
    'DB_USER' => 'root',
    'DB_PASSWORD' => 'root',
    'DB_DATABASE' => 'platformer',
    'DB_CHARSET' => 'utf8'
]);

// ================================================================
// MAIL CONFIGURATION (Separate for clarity)
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
// CONFIGURATION VALIDATION (Debug mode only)
// ================================================================

if (defined('DEBUG_MODE') && DEBUG_MODE && isset($_GET['show_config'])) {
    echo '<h2>Configuration Validation</h2>';
    echo '<pre>';
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