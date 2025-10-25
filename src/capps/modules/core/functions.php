<?php

declare(strict_types=1);

// ================================================================
// STATIC CACHE for CBinit (replaces globals)
// ================================================================

class CBinitCache {
    private static array $cache = [];
    private static array $stats = ['hits' => 0, 'misses' => 0, 'fallbacks' => 0, 'vendor_usage' => []];

    public static function get(string $key): ?object {
        return self::$cache[$key] ?? null;
    }

    public static function set(string $key, object $instance): void {
        self::$cache[$key] = $instance;
    }

    public static function has(string $key): bool {
        return isset(self::$cache[$key]);
    }

    public static function clear(): void {
        self::$cache = [];
    }

    public static function incrementHits(): void {
        self::$stats['hits']++;
    }

    public static function incrementMisses(): void {
        self::$stats['misses']++;
    }

    public static function incrementFallbacks(): void {
        self::$stats['fallbacks']++;
    }

    public static function incrementVendorUsage(string $vendor): void {
        if (!isset(self::$stats['vendor_usage'][$vendor])) {
            self::$stats['vendor_usage'][$vendor] = 0;
        }
        self::$stats['vendor_usage'][$vendor]++;
    }

    public static function getStats(): array {
        $total = self::$stats['hits'] + self::$stats['misses'];
        return [
            'cache_hit_rate' => $total > 0 ? round((self::$stats['hits'] / $total) * 100, 2) . '%' : '0%',
            'cache_size' => count(self::$cache),
            'fallbacks' => self::$stats['fallbacks'],
            'vendor_usage' => self::$stats['vendor_usage'],
            'vendors' => array_keys(CONFIGURATION['cbinit']['vendors'])
        ];
    }
}

// ================================================================
// ROBUST PATH DETECTION
// ================================================================

function detectBaseUrl(): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $path = dirname($scriptName);
    $path = str_replace('\\', '/', $path);
    $path = rtrim($path, '/');
    $path = preg_replace('#/(admin|console)$#', '', $path);
    $baseUrl = $protocol . '://' . $host . $path;
    return rtrim($baseUrl, '/') . '/';
}

// ================================================================
// CBINIT OBJECT CREATION
// ================================================================

function CBinitObjectSimple(string $class, mixed $value = null): object
{
    $config = CONFIGURATION['cbinit'];

    // Parse class specification
    $requestedVendor = null;
    $module = '';
    $className = '';

    if (str_contains($class, ':')) {
        [$requestedVendor, $moduleClass] = explode(':', $class, 2);
        if (str_contains($moduleClass, '/')) {
            [$module, $className] = explode('/', $moduleClass, 2);
        } else {
            $className = $moduleClass;
            $module = strtolower($className);
        }
    } elseif (str_contains($class, '/')) {
        [$module, $className] = explode('/', $class, 2);
    } else {
        $className = $class;
        $module = strtolower($className);
    }

    // Validation
    if ($config['validate_input']) {
        $pattern = '/^[a-zA-Z][a-zA-Z0-9_]*$/';
        if (($requestedVendor && !preg_match($pattern, $requestedVendor)) ||
            !preg_match($pattern, $module) ||
            !preg_match($pattern, $className)) {
            throw new InvalidArgumentException("Invalid class specification: {$class}");
        }
    }

    // Cache check
    $cacheKey = ($requestedVendor ?? 'auto') . ':' . $module . '/' . $className;
    if ($config['enable_cache'] && CBinitCache::has($cacheKey) && $value === null) {
        CBinitCache::incrementHits();
        return clone CBinitCache::get($cacheKey);
    }
    CBinitCache::incrementMisses();

    // Get vendors
    $vendors = $config['vendors'];
    if ($requestedVendor) {
        if (!isset($vendors[$requestedVendor])) {
            throw new InvalidArgumentException("Unknown vendor: {$requestedVendor}");
        }
        $vendors = [$requestedVendor => $vendors[$requestedVendor]];
    } else {
        uasort($vendors, fn($a, $b) => ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0));
    }

    // Try each vendor
    $instance = null;
    $loadedFrom = null;

    foreach ($vendors as $vendorName => $vendorConfig) {
        if (!$vendorConfig['enabled']) continue;

        $instance = tryLoadFromVendor($vendorName, $vendorConfig, $module, $className, $value);
        if ($instance !== null) {
            $loadedFrom = $vendorName;
            break;
        }
    }

    // Fallback
    if ($instance === null) {
        if ($config['strict_mode']) {
            throw new RuntimeException("Class not found: {$class}");
        }

        if ($config['fallback_enabled']) {
            $fallbackClass = $config['fallback_class'];
            $instance = $value !== null ? new $fallbackClass($value) : new $fallbackClass();
            $loadedFrom = 'fallback';
            CBinitCache::incrementFallbacks();
        } else {
            throw new RuntimeException("Class not found: {$class}");
        }
    }

    // Update stats
    CBinitCache::incrementVendorUsage($loadedFrom);

    // Cache
    if ($config['enable_cache'] && $value === null) {
        CBinitCache::set($cacheKey, clone $instance);
    }

    // Logging
    if ($config['enable_logging']) {
        $actualClass = get_class($instance);
        error_log("CBinitObjectSimple: {$class} → {$actualClass} from {$loadedFrom}");
    }

    return $instance;
}

function tryLoadFromVendor(string $vendorName, array $vendorConfig, string $module, string $className, mixed $value): ?object
{
    $filePath = rtrim($vendorConfig['path'], '/') . "/modules/{$module}/classes/{$className}.php";
    $fullClassName = "\\{$vendorName}\\modules\\{$module}\\classes\\{$className}";

    if (is_file($filePath)) {
        require_once $filePath;
    }

    if (!class_exists($fullClassName)) {
        return null;
    }

    $reflection = new ReflectionClass($fullClassName);
    if (!$reflection->isInstantiable()) {
        return null;
    }

    return $value !== null ? $reflection->newInstance($value) : $reflection->newInstance();
}

function CBinitObject(string $class, mixed $value = null): object
{
    try {
        return CBinitObjectSimple($class, $value);
    } catch (Exception $e) {
        error_log("CBinitObject fallback: {$class} → " . $e->getMessage());

        $fallbackClass = CONFIGURATION['cbinit']['fallback_class'];
        return $value !== null ? new $fallbackClass($value) : new $fallbackClass();
    }
}

// ================================================================
// CONFIGURATION FUNCTIONS
// ================================================================

function CBinitClearCache(): void
{
    CBinitCache::clear();
}

function CBinitGetStats(): array
{
    return CBinitCache::getStats();
}

// ================================================================
// UTILITY FUNCTIONS
// ================================================================

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

function CBLog($data, string $message = ''): void
{
   // if (defined('DEBUG_MODE') && DEBUG_MODE) {
        echo "$message: <pre>"; print_r($data); echo "</pre>";
        $logMessage = $message ? $message . ': ' : '';
        error_log($logMessage . print_r($data, true));
    //}
}