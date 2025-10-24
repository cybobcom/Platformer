<?php

/**
 * Application Entry Point - Optimized
 * Single entry point for the entire platform
 */

// ================================================================
// PERFORMANCE MONITORING
// ================================================================

$startTime = microtime(true);
$startMemory = memory_get_usage();

// ================================================================
// ENVIRONMENT SETUP
// ================================================================

// Environment detection
$isProduction = getenv('ENVIRONMENT') === 'production';

if ($isProduction) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
    define('DEBUG_MODE', true);
    define('ENABLE_PROFILING', true);
}

// ================================================================
// SECURITY HEADERS
// ================================================================

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

if ($isProduction) {
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';");
}

// ================================================================
// SESSION
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? '1' : '0');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', '1');
    session_start();
}

// ================================================================
// PATH CONFIGURATION
// ================================================================

$basePath = __DIR__ . '/../src/';

if (!is_dir($basePath)) {
    http_response_code(500);
    die('Configuration error: Source directory not found');
}

// ================================================================
// BOOTSTRAP
// ================================================================

try {
    // 1. Load core functions
    $functionsFile = $basePath . 'capps/modules/core/functions.php';
    if (!file_exists($functionsFile)) {
        throw new \RuntimeException('Functions file not found');
    }
    require_once $functionsFile;

    // 2. Load configuration
    $configFile = $basePath . 'capps/inc.localconf.php';
    if (!file_exists($configFile)) {
        throw new \RuntimeException('Configuration file not found');
    }
    require_once $configFile;

    // 3. Validate required constants
    $required = ['CAPPS', 'BASEURL', 'BASEDIR', 'PLATFORM_IDENTIFIER', 'SOURCEDIR'];
    foreach ($required as $constant) {
        if (!defined($constant)) {
            throw new \RuntimeException("Required constant not defined: {$constant}");
        }
    }

    // 4. Setup autoloader
    require_once CAPPS . 'modules/core/classes/CBAutoloader.php';

    $autoloader = new \capps\modules\core\classes\CBAutoloader();
    $autoloader->register();

    // Register module namespaces
    foreach(glob(CAPPS.'modules/*', GLOB_ONLYDIR) as $dir) {
        $dirName = basename($dir);
        $autoloader->addNamespace('capps\\modules\\'.$dirName.'\\classes\\', $dir.'/classes');
        $autoloader->addNamespace('Capps\\Modules\\'.ucfirst($dirName).'\\Classes\\', $dir.'/classes');
    }

    if (defined('CUSTOM')) {
        foreach(glob(CUSTOM.'modules/*', GLOB_ONLYDIR) as $dir) {
            $dirName = basename($dir);
            $autoloader->addNamespace('custom\\modules\\'.$dirName.'\\classes\\', $dir.'/classes');
            $autoloader->addNamespace('Custom\\Modules\\'.ucfirst($dirName).'\\Classes\\', $dir.'/classes');
        }
    }

    // 5. Import classes
    use Capps\Modules\Core\Classes\CBCore;
    use Capps\Modules\Address\Classes\Address;
    use Capps\Modules\Database\Classes\CBObject;

    // 6. System attributes
    $coreArrSystemAttributes = ['seo_modrewrite' => '1'];

    // 7. Load user
    $objPlatformUser = new Address($_SESSION[PLATFORM_IDENTIFIER]["login_user_identifier"] ?? "");

    // Email validation
    $user_email = $objPlatformUser->getAttribute("login");
    if (!validateEmail($user_email)) {
        if ($objPlatformUser->getAttribute("email") != "") {
            $user_email = $objPlatformUser->getAttribute("email");
        }
    }
    $objPlatformUser->setAttribute("user_email", $user_email);

    // 8. Localization
    require_once CAPPS . "modules/core/localization.php";

    // 9. Load structure
    $objStructure = CBinitObject("Structure");
    $coreArrSortedStructure = $objStructure->generateSortedStructure();

    // 10. Load route cache
    $lid = 1;
    $sql = "SELECT * FROM capps_route WHERE language_id = {$lid} AND (data NOT LIKE '%<manual_link><![CDATA[1]]></manual_link>%' OR data IS NULL) ORDER BY content_id ASC";
    $coreArrRoute = $objStructure->get($sql);

    $arrIDtmp = [];
    if (is_array($coreArrRoute)) {
        foreach ($coreArrRoute as $value) {
            $tmp = $value['structure_id'] . ':' . $value['content_id'] . ':' . $value['address_uid'];
            $arrIDtmp[$tmp] = $value['route'];
        }
    }
    $coreArrRoute = $arrIDtmp;

    // 11. Set globals (backward compatibility)
    $GLOBALS['coreArrSystemAttributes'] = $coreArrSystemAttributes;
    $GLOBALS['objPlatformUser'] = $objPlatformUser;
    $GLOBALS['coreArrSortedStructure'] = $coreArrSortedStructure;
    $GLOBALS['coreArrRoute'] = $coreArrRoute;

    // 12. Run application
    $core = new CBCore();
    $core->init($objPlatformUser, $coreArrSortedStructure, $coreArrRoute);
    $core->run();

} catch (\Throwable $e) {
    // ================================================================
    // ERROR HANDLING
    // ================================================================

    error_log('CRITICAL ERROR: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);

    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Application Error</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
                .error { background: #fff; border-left: 4px solid #d32f2f; padding: 20px; border-radius: 4px; }
                h1 { color: #d32f2f; margin-top: 0; }
                .trace { background: #263238; color: #aed581; padding: 15px; margin-top: 15px; overflow-x: auto; border-radius: 4px; }
                pre { white-space: pre-wrap; margin: 0; }
                .info { background: #e3f2fd; padding: 15px; margin-top: 15px; border-radius: 4px; }
            </style>
        </head>
        <body>
        <div class="error">
            <h1>Application Bootstrap Error</h1>
            <p><strong>Message:</strong> <?= htmlspecialchars($e->getMessage()) ?></p>
            <p><strong>File:</strong> <?= $e->getFile() ?> (Line: <?= $e->getLine() ?>)</p>

            <div class="trace">
                <strong>Stack Trace:</strong>
                <pre><?= htmlspecialchars($e->getTraceAsString()) ?></pre>
            </div>

            <div class="info">
                <strong>Environment Info:</strong>
                <ul>
                    <li><strong>PHP Version:</strong> <?= PHP_VERSION ?></li>
                    <li><strong>Memory:</strong> <?= number_format(memory_get_usage() / 1024 / 1024, 2) ?> MB</li>
                    <li><strong>Base Path:</strong> <?= htmlspecialchars($basePath ?? 'unknown') ?></li>
                </ul>
            </div>
        </div>
        </body>
        </html>
        <?php
    } else {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Service Unavailable</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; margin-top: 100px; color: #666; }
            </style>
        </head>
        <body>
        <h1>Service Temporarily Unavailable</h1>
        <p>We're experiencing technical difficulties. Please try again later.</p>
        </body>
        </html>
        <?php
    }
    exit;
}

// ================================================================
// PERFORMANCE MONITORING
// ================================================================

if (defined('ENABLE_PROFILING') && ENABLE_PROFILING) {
    register_shutdown_function(function() use ($startTime, $startMemory) {
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);
        $memoryUsed = round((memory_get_usage() - $startMemory) / 1024 / 1024, 2);
        $peakMemory = round(memory_get_peak_usage() / 1024 / 1024, 2);

        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("PERFORMANCE: {$executionTime}ms, Memory: {$memoryUsed}MB, Peak: {$peakMemory}MB");

            if (!headers_sent()) {
                header("X-Execution-Time: {$executionTime}ms");
                header("X-Memory-Usage: {$memoryUsed}MB");
            }
        }

        // Log slow requests
        if ($executionTime > 1000) {
            error_log("SLOW REQUEST: {$executionTime}ms - " . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
        }

        // Log high memory
        if ($peakMemory > 64) {
            error_log("HIGH MEMORY: {$peakMemory}MB - " . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
        }
    });
}