<?php

/**
 * Platform Configuration - local defaults (MAMP: root/root, DB "platformer")
 *
 * Used automatically if src/inc.localconf.php does not exist (fresh clone works at once).
 * Own setup: copy to inc.localconf.php (ignored by git) and change the values there.
 * SECURITY: on servers replace DB credentials, ENCRYPTION_KEY32 and debug token.
 */

// ================================================================
// PHP SETTINGS
// ================================================================

ini_set('max_execution_time', '60');
ini_set('memory_limit', '512M');
// Error reporting / display_errors: set in public/index.php (ENVIRONMENT)

date_default_timezone_set('Europe/Berlin');


// ================================================================
// PLATFORM
// ================================================================

$arrConf = [];
$arrConf['platform_name'] = "Platformer";

// Platform identifier (based on file location)
$strPlatformIdentitier = realpath(dirname(__FILE__)) . "/";
$arrConf['platform_identifier'] = md5(strtolower($strPlatformIdentitier)); // lowercase: same session for /Platformer/ and /platformer/ (macOS)

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
$arrConf['basedir'] = str_replace('//', '/', $arrConf['basedir']);
$arrConf['sourcedir'] = str_replace('\\', '/', $arrConf['sourcedir']);
$arrConf['sourcedir'] = str_replace('//', '/', $arrConf['sourcedir']);

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
    'fallback_enabled' => true,
    'fallback_class' => 'capps\\modules\\database\\classes\\CBObject',
    'strict_mode' => false
];

// cache
$arrConf['cache'] = [
    'enabled' => false,      // default: off
    'ttl'     => 60,         // seconds
    'paths'   => [],         // empty = all paths
];

// debug
$arrConf['debug'] = ['token' => '', 'slow_ms' => 2]; // '' = off; set a long random token to use ?cbdebug=TOKEN

// installer (import/export of modules): always on localhost, on servers only if true
$arrConf['installer'] = false;

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

define('ENCRYPTION_KEY32', '00000000000000000000000000000000'); // SECURITY: public dev key, replace on servers (32 chars)
define('DEBUG_MAIL', '');
