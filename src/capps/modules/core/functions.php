<?php

declare(strict_types=1);

// =================================================================
// VEREINFACHTE AUTO-DETECTION: KLASSENNAME = MODULNAME
// =================================================================

/**
 * NEUES PARADIGMA:
 * 
 * CBinitObject("AgentStorage") → agentstorage/AgentStorage
 * CBinitObject("PaymentGateway") → paymentgateway/PaymentGateway  
 * CBinitObject("User") → user/User
 * CBinitObject("CBObject") → cbobject/CBObject
 * 
 * VERZEICHNISSTRUKTUR:
 * capps/modules/agentstorage/classes/AgentStorage.php
 * custom/modules/agentstorage/classes/AgentStorage.php
 * capps/modules/paymentgateway/classes/PaymentGateway.php
 * capps/modules/user/classes/User.php
 */

/**
 * Stark vereinfachte CBinitObject Alternative
 * 
 * @param string $class Format: "module/Class" oder "vendor:module/Class" oder nur "Class"  
 * @param mixed $value Konstruktor-Parameter
 * @return object Instanziiertes Objekt
 * 
 * @example
 * // Klassenname wird automatisch zum Modulnamen:
 * $storage = CBinitObjectSimple("AgentStorage");        // → agentstorage/AgentStorage
 * $gateway = CBinitObjectSimple("PaymentGateway");      // → paymentgateway/PaymentGateway
 * $user = CBinitObjectSimple("User");                   // → user/User
 * 
 * @example
 * // Explizite Angaben funktionieren weiterhin:
 * $user = CBinitObjectSimple("user/User", 123);         // → user/User
 * $custom = CBinitObjectSimple("custom:user/User", 123); // → custom vendor
 */
function CBinitObjectSimple(string $class, mixed $value = null): object 
{
	global $CBINIT_CONFIG, $CBINIT_CACHE, $CBINIT_STATS;
	
	// ================================================================
	// VEREINFACHTES PARSING
	// ================================================================
	
	$requestedVendor = null;
	$module = '';
	$className = '';
	
	// Parse verschiedene Formate
	if (str_contains($class, ':')) {
		// Format: "vendor:module/class" oder "vendor:class"
		[$requestedVendor, $moduleClass] = explode(':', $class, 2);
		
		if (str_contains($moduleClass, '/')) {
			[$module, $className] = explode('/', $moduleClass, 2);
		} else {
			// vendor:class → class wird zu module/class
			$className = $moduleClass;
			$module = strtolower($className);  // ✅ HIER IST DIE VEREINFACHUNG!
		}
	} elseif (str_contains($class, '/')) {
		// Format: "module/class"
		[$module, $className] = explode('/', $class, 2);
	} else {
		// Format: nur "class" → wird zu "module/class" wo module = lowercase(class)
		$className = $class;
		$module = strtolower($className);  // ✅ KLASSENNAME = MODULNAME!
	}
	echo $className." --- ".$module;
	
	// Basic validation
	if ($CBINIT_CONFIG['validate_input']) {
		$pattern = '/^[a-zA-Z][a-zA-Z0-9_]*$/';
		if (($requestedVendor && !preg_match($pattern, $requestedVendor)) ||
			!preg_match($pattern, $module) || 
			!preg_match($pattern, $className)) {
			throw new InvalidArgumentException("Invalid class specification: {$class}");
		}
	}
	
	// ================================================================
	// CACHE CHECK
	// ================================================================
	
	$cacheKey = ($requestedVendor ?? 'auto') . ':' . $module . '/' . $className;
	if ($CBINIT_CONFIG['enable_cache'] && isset($CBINIT_CACHE[$cacheKey]) && $value === null) {
		$CBINIT_STATS['hits']++;
		return clone $CBINIT_CACHE[$cacheKey];
	}
	$CBINIT_STATS['misses']++;
	
	// ================================================================
	// VENDOR RESOLUTION & LOADING
	// ================================================================
	
	$instance = null;
	$loadedFrom = null;
	
	// Get prioritized vendors
	$vendors = $CBINIT_CONFIG['vendors'];
	if ($requestedVendor) {
		if (!isset($vendors[$requestedVendor])) {
			throw new InvalidArgumentException("Unknown vendor: {$requestedVendor}");
		}
		$vendors = [$requestedVendor => $vendors[$requestedVendor]];
	} else {
		// Sort by priority (highest first)
		uasort($vendors, fn($a, $b) => ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0));
	}
	
	// Try each vendor
	foreach ($vendors as $vendorName => $vendorConfig) {
		if (!$vendorConfig['enabled']) continue;
		
		$instance = tryLoadFromVendor($vendorName, $vendorConfig, $module, $className, $value);
		if ($instance !== null) {
			$loadedFrom = $vendorName;
			break;
		}
	}
	
	// ================================================================
	// FALLBACK
	// ================================================================
	
	if ($instance === null) {
		if ($CBINIT_CONFIG['strict_mode']) {
			throw new RuntimeException("Class not found: {$class}");
		}
		
		if ($CBINIT_CONFIG['fallback_enabled']) {
			$fallbackClass = $CBINIT_CONFIG['fallback_class'];
			$instance = $value !== null ? new $fallbackClass($value) : new $fallbackClass();
			$loadedFrom = 'fallback';
			$CBINIT_STATS['fallbacks']++;
		} else {
			throw new RuntimeException("Class not found: {$class}");
		}
	}
	
	// ================================================================
	// CACHING & STATISTICS
	// ================================================================
	
	// Update stats
	if (!isset($CBINIT_STATS['vendor_usage'][$loadedFrom])) {
		$CBINIT_STATS['vendor_usage'][$loadedFrom] = 0;
	}
	$CBINIT_STATS['vendor_usage'][$loadedFrom]++;
	
	// Cache parameter-less instances
	if ($CBINIT_CONFIG['enable_cache'] && $value === null) {
		$CBINIT_CACHE[$cacheKey] = clone $instance;
	}
	
	// Logging
	if ($CBINIT_CONFIG['enable_logging']) {
		$actualClass = get_class($instance);
		error_log("CBinitObjectSimple: {$class} → {$actualClass} from {$loadedFrom}");
	}
	
	return $instance;
}

/**
 * Try to load class from specific vendor
 */
function tryLoadFromVendor(string $vendorName, array $vendorConfig, string $module, string $className, mixed $value): ?object 
{
	// Paradigma: {vendor}/modules/{module}/classes/{Class}.php
	$filePath = rtrim($vendorConfig['path'], '/') . "/modules/{$module}/classes/{$className}.php";
	$fullClassName = "\\{$vendorName}\\modules\\{$module}\\classes\\{$className}";
	
	//echo "DEV filePath: ".$filePath."<br>";
	//echo "DEV fullClassName: ".$fullClassName."<br>";

	
	// Try file-based loading
	if (is_file($filePath)) {
		require_once $filePath;
	}
	
	// Check if class exists
	if (!class_exists($fullClassName)) {
		return null;
	}
	
	// Create instance
	$reflection = new ReflectionClass($fullClassName);
	if (!$reflection->isInstantiable()) {
		return null;
	}
	
	return $value !== null ? $reflection->newInstance($value) : $reflection->newInstance();
}

// =================================================================
// LEGACY WRAPPER (100% kompatibel)
// =================================================================

/**
 * Perfekte Legacy-Kompatibilität mit vereinfachter Logik
 */
function CBinitObject(string $class, mixed $value = null): object 
{
	try {
		return CBinitObjectSimple($class, $value);
	} catch (Exception $e) {
		// Legacy fallback behavior
		global $CBINIT_CONFIG;
		error_log("CBinitObject fallback: {$class} → " . $e->getMessage());
		
		$fallbackClass = $CBINIT_CONFIG['fallback_class'];
		return $value !== null ? new $fallbackClass($value) : new $fallbackClass();
	}
}

// =================================================================
// CONFIGURATION FUNCTIONS  
// =================================================================

function CBinitAddVendor(string $name, string $path, int $priority = 100): void 
{
	global $CBINIT_CONFIG;
	$CBINIT_CONFIG['vendors'][$name] = [
		'path' => rtrim($path, '/'),
		'priority' => $priority, 
		'enabled' => true
	];
	CBinitClearCache();
}

function CBinitConfigure(array $config): void 
{
	global $CBINIT_CONFIG;
	$CBINIT_CONFIG = array_merge($CBINIT_CONFIG, $config);
}

function CBinitClearCache(): void 
{
	global $CBINIT_CACHE;
	$CBINIT_CACHE = [];
}

function CBinitGetStats(): array 
{
	global $CBINIT_STATS, $CBINIT_CONFIG, $CBINIT_CACHE;
	
	$total = $CBINIT_STATS['hits'] + $CBINIT_STATS['misses'];
	return [
		'cache_hit_rate' => $total > 0 ? round(($CBINIT_STATS['hits'] / $total) * 100, 2) . '%' : '0%',
		'cache_size' => count($CBINIT_CACHE),
		'fallbacks' => $CBINIT_STATS['fallbacks'],
		'vendor_usage' => $CBINIT_STATS['vendor_usage'],
		'vendors' => array_keys($CBINIT_CONFIG['vendors'])
	];
}



/**
 * Global functions for backward compatibility
 */

function validateEmail(string $email): bool
{
	return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function checkIntersection(string $userGroups, string $requiredGroups): bool
{
	if (empty($requiredGroups)) {
		return true;
	}
	
	$userGroupArray = explode(',', $userGroups);
	$requiredGroupArray = explode(',', $requiredGroups);
	
	return !empty(array_intersect($userGroupArray, $requiredGroupArray));
}

function parseTemplate(string $template, array $data, string $prefix = '', bool $htmlSpecialChars = true): string
{
	foreach ($data as $key => $value) {
		if (!is_string($value) && !is_numeric($value)) {
			continue;
		}
		
		$placeholder = "###" . $prefix . $key . "###";
		$replacement = $htmlSpecialChars ? htmlspecialchars((string)$value) : (string)$value;
		$template = str_replace($placeholder, $replacement, $template);
	}
	
	return $template;
}

// CBLog function for debugging (preserved for compatibility)
function CBLog($data, string $message = ''): void
{
	if (defined('DEBUG_MODE') && DEBUG_MODE) {
		$logMessage = $message ? $message . ': ' : '';
		error_log($logMessage . print_r($data, true));
	}
}


?>