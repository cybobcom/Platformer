<?php

/**
 * Modern Application Entry Point
 * 
 * Features:
 * - Enhanced security headers
 * - Proper error handling
 * - Performance monitoring
 * - Environment detection
 * - Graceful degradation
 */

// ================================================================
// ENVIRONMENT SETUP
// ================================================================

// Performance measurement start
$startTime = microtime(true);
$startMemory = memory_get_usage();

// Error reporting based on environment
if (getenv('ENVIRONMENT') === 'production') {
	ini_set('display_errors', 0);
	ini_set('display_startup_errors', 0);
	error_reporting(0);
} else {
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);
	
	// Development constants
	define('DEBUG_MODE', true);
	define('ENABLE_PROFILING', true);
}

// ================================================================
// SECURITY HEADERS
// ================================================================

// Basic security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Content Security Policy (adjust as needed)
if (getenv('ENVIRONMENT') === 'production') {
	header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';");
}

// ================================================================
// SESSION MANAGEMENT
// ================================================================

// Secure session configuration
if (session_status() === PHP_SESSION_NONE) {
	// Security settings
	ini_set('session.cookie_httponly', 1);
	ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
	ini_set('session.cookie_samesite', 'Strict');
	ini_set('session.use_strict_mode', 1);
	
	session_start();
}

// ================================================================
// PATH CONFIGURATION
// ================================================================

// Dynamic path detection (from original index.php)
$basePath = __DIR__ . "/../src/";
echo "basePath<pre>"; print_r($basePath); echo "</pre>";


// Validate path exists
if (!is_dir($basePath)) {
	http_response_code(500);
	die('Configuration error: Source directory not found');
}

// ================================================================
// BOOTSTRAP SEQUENCE
// ================================================================

try {
	// 1. Load core functions first (preserved from original)
	$functionsFile = $basePath . "capps/modules/core/functions.php";
	if (file_exists($functionsFile)) {
		include_once($functionsFile);
	}
	
	// 2. Load local configuration (preserved from original)
	$configFile = $basePath . 'capps/inc.localconf.php';
	if (file_exists($configFile)) {
		include_once($configFile);
	} else {
		throw new \RuntimeException('Configuration file not found: ' . $configFile);
	}
	
	// 3. Validate required constants
	$requiredConstants = ['CAPPS', 'BASEURL', 'BASEDIR', 'PLATTFORM_IDENTIFIER'];
	foreach ($requiredConstants as $constant) {
		if (!defined($constant)) {
			throw new \RuntimeException("Required constant not defined: {$constant}");
		}
	}
	
	// 4. Load the new modernized core.php
	$coreFile = $basePath . "capps/modules/core/core.php";
	if (file_exists($coreFile)) {
		include_once($coreFile);
	} else {
		throw new \RuntimeException('Core file not found: ' . $coreFile);
	}
	
} catch (\Throwable $e) {
	// ================================================================
	// ERROR HANDLING
	// ================================================================
	
	// Log error
	error_log('CRITICAL INDEX ERROR: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
	
	// Show appropriate error page
	http_response_code(500);
	
	if (defined('DEBUG_MODE') && DEBUG_MODE) {
		// Development error page
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<title>Application Error</title>
			<style>
				body { font-family: Arial, sans-serif; margin: 40px; }
				.error { background: #fee; border: 1px solid #fcc; padding: 20px; border-radius: 5px; }
				.trace { background: #f5f5f5; padding: 15px; margin-top: 15px; overflow-x: auto; }
				pre { white-space: pre-wrap; }
			</style>
		</head>
		<body>
			<div class="error">
				<h1>🚫 Application Bootstrap Error</h1>
				<p><strong>Message:</strong> <?= htmlspecialchars($e->getMessage()) ?></p>
				<p><strong>File:</strong> <?= $e->getFile() ?> (Line: <?= $e->getLine() ?>)</p>
				
				<div class="trace">
					<strong>Stack Trace:</strong>
					<pre><?= htmlspecialchars($e->getTraceAsString()) ?></pre>
				</div>
				
				<h3>Environment Info:</h3>
				<ul>
					<li><strong>PHP Version:</strong> <?= PHP_VERSION ?></li>
					<li><strong>Memory Usage:</strong> <?= number_format(memory_get_usage() / 1024 / 1024, 2) ?> MB</li>
					<li><strong>Base Path:</strong> <?= htmlspecialchars($basePath) ?></li>
					<li><strong>Document Root:</strong> <?= htmlspecialchars($_SERVER["DOCUMENT_ROOT"]) ?></li>
				</ul>
			</div>
		</body>
		</html>
		<?php
	} else {
		// Production error page
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<title>Service Unavailable</title>
			<style>
				body { font-family: Arial, sans-serif; text-align: center; margin-top: 100px; }
				.error { color: #666; }
			</style>
		</head>
		<body>
			<div class="error">
				<h1>🛠️ Service Temporarily Unavailable</h1>
				<p>We're experiencing technical difficulties. Please try again later.</p>
				<p><small>Error ID: <?= uniqid() ?></small></p>
			</div>
		</body>
		</html>
		<?php
	}
	
	exit;
}

// ================================================================
// PERFORMANCE MONITORING (Optional)
// ================================================================

if (defined('ENABLE_PROFILING') && ENABLE_PROFILING) {
	register_shutdown_function(function() use ($startTime, $startMemory) {
		$endTime = microtime(true);
		$endMemory = memory_get_usage();
		$peakMemory = memory_get_peak_usage();
		
		$executionTime = round(($endTime - $startTime) * 1000, 2); // in milliseconds
		$memoryUsed = round(($endMemory - $startMemory) / 1024 / 1024, 2); // in MB
		$peakMemoryMB = round($peakMemory / 1024 / 1024, 2); // in MB
		
		if (defined('DEBUG_MODE') && DEBUG_MODE) {
			error_log("PERFORMANCE: Execution: {$executionTime}ms, Memory: {$memoryUsed}MB, Peak: {$peakMemoryMB}MB");
			
			// Optional: Add performance header for development
			if (!headers_sent()) {
				header("X-Execution-Time: {$executionTime}ms");
				header("X-Memory-Usage: {$memoryUsed}MB");
				header("X-Peak-Memory: {$peakMemoryMB}MB");
			}
		}
		
		// Log slow requests
		if ($executionTime > 1000) { // > 1 second
			error_log("SLOW REQUEST: {$executionTime}ms - " . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
		}
		
		// Log high memory usage
		if ($peakMemoryMB > 64) { // > 64MB
			error_log("HIGH MEMORY: {$peakMemoryMB}MB - " . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
		}
	});
}

// ================================================================
// DEBUGGING HELPERS (Development only)
// ================================================================

if (defined('DEBUG_MODE') && DEBUG_MODE && isset($_GET['debug'])) {
	switch ($_GET['debug']) {
		case 'phpinfo':
			phpinfo();
			exit;
			
		case 'session':
			echo '<pre>SESSION: ' . print_r($_SESSION, true) . '</pre>';
			break;
			
		case 'request':
			echo '<pre>REQUEST: ' . print_r($_REQUEST, true) . '</pre>';
			break;
			
		case 'server':
			echo '<pre>SERVER: ' . print_r($_SERVER, true) . '</pre>';
			break;
			
		case 'constants':
			$constants = get_defined_constants(true)['user'];
			echo '<pre>USER CONSTANTS: ' . print_r($constants, true) . '</pre>';
			break;
	}
}

?>