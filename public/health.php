<?php

/**
 * Health Check Endpoint
 * 
 * Simple endpoint to check application health
 * Access via: /health.php
 */

header('Content-Type: application/json');

$health = [
	'status' => 'ok',
	'timestamp' => date('Y-m-d H:i:s'),
	'php_version' => PHP_VERSION,
	'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2) . 'MB',
	'checks' => []
];

try {
	// Check if core files exist
	$basePath = $_SERVER["DOCUMENT_ROOT"] . "/../src/";
	
	$coreFiles = [
		'core.php' => $basePath . 'capps/modules/core/core.php',
		'config' => $basePath . 'capps/inc.localconf.php',
		'functions' => $basePath . 'capps/modules/core/functions.php'
	];
	
	foreach ($coreFiles as $name => $file) {
		$health['checks'][$name] = file_exists($file) ? 'ok' : 'missing';
		if (!file_exists($file)) {
			$health['status'] = 'error';
		}
	}
	
	// Check session
	$health['checks']['session'] = session_status() === PHP_SESSION_ACTIVE ? 'ok' : 'error';
	
	// Check memory
	$memoryUsage = memory_get_usage() / 1024 / 1024;
	$health['checks']['memory'] = $memoryUsage < 64 ? 'ok' : 'warning';
	
} catch (\Exception $e) {
	$health['status'] = 'error';
	$health['error'] = $e->getMessage();
}

http_response_code($health['status'] === 'ok' ? 200 : 503);
echo json_encode($health, JSON_PRETTY_PRINT);