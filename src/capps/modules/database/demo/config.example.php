<?php
/**
 * CBObject Demo Configuration Template
 *
 * INSTRUCTIONS:
 * 1. Copy this file to "config.php"
 * 2. Update database credentials below
 * 3. Import demo schema: ../tests/demo-database.sql
 */

declare(strict_types=1);

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', 'root');
define('DB_DATABASE', 'platformer');
define('DB_PORT', 3306);
define('DB_CHARSET', 'utf8mb4');

// Database configuration array (compatible with CBDatabase)
$arrDatabaseConfiguration = [
    'DB_HOST' => DB_HOST,
    'DB_USER' => DB_USER,
    'DB_PASSWORD' => DB_PASSWORD,
    'DB_DATABASE' => DB_DATABASE,
    'DB_PORT' => DB_PORT,
    'DB_CHARSET' => DB_CHARSET
];

// Error reporting for demos
error_reporting(E_ALL);
ini_set('display_errors', '1');