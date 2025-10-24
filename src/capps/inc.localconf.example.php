<?php

/**
 * ============================================================================
 * Platformer - Configuration Template
 * ============================================================================
 *
 * IMPORTANT: This is a template file!
 *
 * INSTALLATION STEPS:
 * 1. Copy this file to: inc.localconf.php
 * 2. Update all configuration values below
 * 3. DO NOT commit inc.localconf.php to version control!
 *
 * The inc.localconf.php file is ignored by .gitignore for security reasons.
 * ============================================================================
 */

// ============================================================================
// PHP CONFIGURATION
// ============================================================================

ini_set('max_execution_time', 60);
ini_set('memory_limit', '512M');

// Error reporting (adjust for production)
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 'off'); // Set to 'on' for development, 'off' for production
error_reporting(E_ALL ^ E_NOTICE);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

// ============================================================================
// PLATFORM CONFIGURATION
// ============================================================================

$arrConf = array();
$arrConf['platform_name'] = "Platformer Admin";

// CHANGE THESE: Admin login credentials
$arrConf['platform_login'] = "admin";
$arrConf['platform_password'] = "change_this_password";

// Platform identifier (auto-generated, do not change)
$strPlatformIdentitier = realpath(dirname(__FILE__)) . "/";
$arrConf['platform_identifier'] = md5($strPlatformIdentitier);

// ============================================================================
// PATH CONFIGURATION
// ============================================================================

// Auto-detect base URL
$arrConf['baseurl'] = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http")
    . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "";
$arrConf['baseurl'] = rtrim($arrConf['baseurl'], '/') . '/';
$arrConf['baseurl'] = str_replace("admin/", "", $arrConf['baseurl']);
$arrConf['baseurl'] = str_replace("console/", "", $arrConf['baseurl']);

// Directory configuration
$arrConf['basedir'] = $_SERVER["DOCUMENT_ROOT"] . "/";
$arrConf['sourcedir'] = str_replace("capps", "", realpath(dirname(__FILE__))) . "";
$arrConf['securedir'] = str_replace("/src/", "/websecure/", $arrConf['sourcedir']);
$arrConf["capps"] = $arrConf['sourcedir'] . "capps/";
$arrConf["custom"] = $arrConf['sourcedir'] . "custom/";

// ============================================================================
// STORAGE CONFIGURATION
// ============================================================================

$arrConf["storage_identifier"] = "id";
$arrConf["storage_configuration"] = "platform_name,chat_intro,chat_overflow,chat_question_placeholder,chat_prewritten_questions,chat_disclaimer,role_system,tonality_serios,tonality_sophisticated,tonality_youthful,openai_api_key,modal_privacy";
$arrConf["storage_configuration_filename"] = "data/configuration.xml";

// Load configuration from XML file (if exists)
if ($arrConf["storage_configuration"] != "" && $arrConf["storage_configuration_filename"] != "") {
    if (is_file($arrConf['basedir'] . "" . $arrConf["storage_configuration_filename"])) {
        $file = file_get_contents($arrConf['basedir'] . "" . $arrConf["storage_configuration_filename"]);

        $arrEntries = array();
        if ($file != "") {
            $arrEntries = parseCBXML($file);

            if (is_array($arrEntries) && count($arrEntries)) {
                $dictEntry = $arrEntries[0];
                foreach ($dictEntry as $key => $value) {
                    $arrConf[$key] = $value;
                }
            }
        }
    }
}

// Convert configuration to constants
foreach ($arrConf as $key => $value) {
    if (!is_array($value)) {
        define(strtoupper($key), $value);
    }
}

// Define configuration as constant with array
define(strtoupper("configuration"), $arrConf);

// ============================================================================
// CBINIT CONFIGURATION (Module Loading)
// ============================================================================

global $CBINIT_CONFIG;
$CBINIT_CONFIG = [
    'vendors' => [
        'capps' => [
            'path' => SOURCEDIR . 'capps/',
            'priority' => 100,
            'enabled' => true
        ],
        'custom' => [
            'path' => SOURCEDIR . 'custom/',
            'priority' => 200,
            'enabled' => true
        ]
    ],
    'validate_input' => true,
    'enable_cache' => true,
    'enable_logging' => false, // Set to true for debugging
    'fallback_enabled' => true,
    'fallback_class' => 'capps\\modules\\database\\classes\\CBObject',
    'strict_mode' => false
];

// Uncomment for debugging:
// echo "<pre>"; print_r($CBINIT_CONFIG); echo "</pre>";

global $CBINIT_CACHE, $CBINIT_STATS;
$CBINIT_CACHE = [];
$CBINIT_STATS = ['hits' => 0, 'misses' => 0, 'fallbacks' => 0, 'vendor_usage' => []];

// ============================================================================
// ENCRYPTION CONFIGURATION
// ============================================================================

// CHANGE THIS: 32-character encryption key for data encryption
define("ENCRYPTION_KEY32", "CHANGE_THIS_TO_RANDOM_32_CHARS");

// ============================================================================
// DATABASE CONFIGURATION
// ============================================================================

$arrDatabaseConfiguration = array();

// CHANGE THESE: Database credentials
$arrDatabaseConfiguration['DB_HOST'] = "localhost";
$arrDatabaseConfiguration['DB_USER'] = "root";
$arrDatabaseConfiguration['DB_PASSWORD'] = "root";
$arrDatabaseConfiguration['DB_DATABASE'] = "platformer";
$arrDatabaseConfiguration['DB_PORT'] = "3306"; // Optional: MySQL port
$arrDatabaseConfiguration['DB_CHARSET'] = "utf8mb4"; // Recommended: utf8mb4 for full Unicode support

define("DATABASE", $arrDatabaseConfiguration);

// ============================================================================
// MAIL CONFIGURATION
// ============================================================================

$arrMailConfiguration = array();

// CHANGE THESE: Mail server configuration
$arrMailConfiguration['name'] = "Platformer";
$arrMailConfiguration['login'] = "your-email@example.com";
$arrMailConfiguration['password'] = "your-email-password";
$arrMailConfiguration['email'] = "your-email@example.com";
$arrMailConfiguration['server'] = "mail.example.com";
$arrMailConfiguration['port'] = "587"; // Common ports: 25, 465 (SSL), 587 (TLS)

define("MAIL", $arrMailConfiguration);

// ============================================================================
// DEBUG CONFIGURATION
// ============================================================================

// CHANGE THIS: Debug email recipient
define("DEBUG_MAIL", "your-debug-email@example.com");

// ============================================================================
// HTTP HEADERS
// ============================================================================

// CORS configuration (adjust as needed)
header('Access-Control-Allow-Origin: *');
header('Content-Type: text/html; charset=utf-8');

// ============================================================================
// SECURITY RECOMMENDATIONS
// ============================================================================

/*
 * PRODUCTION CHECKLIST:
 *
 * 1. Set display_errors to 'off' (line 21)
 * 2. Change admin credentials (lines 27-28)
 * 3. Generate new encryption key (line 132)
 * 4. Update database credentials (lines 144-149)
 * 5. Configure mail server (lines 158-164)
 * 6. Update debug email (line 173)
 * 7. Set enable_logging to false (line 111)
 * 8. Review CORS settings (line 181)
 * 9. Use HTTPS in production
 * 10. Regular security updates
 */