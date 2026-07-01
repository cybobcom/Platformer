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


/**
 * CBinitController - Complete implementation analog to CBinitObjectSimple
 *
 * Add this to: capps/modules/core/functions.php
 *
 * Supports syntax:
 * - CBinitController('Address')           → Auto-detect vendor by priority
 * - CBinitController('custom:Address')    → Force custom vendor
 * - CBinitController('agent:Address', 123) → Force agent vendor with ID
 */

// ================================================================
// CBINIT CONTROLLER (ANALOG TO CBINIT OBJECT)
// ================================================================

/**
 * Initialize CBController with full vendor support and caching
 *
 * @param string $moduleName Module specification (with optional vendor prefix)
 *                           Examples: 'Address', 'custom:Address', 'agent:Content'
 * @param mixed $id Optional ID to pass to controller
 * @return object CBController instance
 *
 * @example
 * // Auto-detect vendor (by priority)
 * $controller = CBinitController('Address');
 *
 * @example
 * // Force specific vendor
 * $controller = CBinitController('custom:Address');
 * $controller = CBinitController('agent:Content', 123);
 *
 * @example
 * // With ID
 * $controller = CBinitController('Address', 123);
 */
function CBinitController(string $moduleName, mixed $id = null): object
{
    $config = CONFIGURATION['cbinit'];

    // Parse specification: "vendor:Module" or just "Module"
    $requestedVendor = null;
    $actualModuleName = '';

    if (str_contains($moduleName, ':')) {
        [$requestedVendor, $actualModuleName] = explode(':', $moduleName, 2);
    } else {
        $actualModuleName = $moduleName;
    }

    // Validation
    if ($config['validate_input']) {
        $pattern = '/^[a-zA-Z][a-zA-Z0-9_]*$/';
        if (($requestedVendor && !preg_match($pattern, $requestedVendor)) ||
            !preg_match($pattern, $actualModuleName)) {
            throw new InvalidArgumentException("Invalid controller specification: {$moduleName}");
        }
    }

    // Cache key (CBController is always in core module)
    $cacheKey = ($requestedVendor ?? 'auto') . ':core/CBController:' . $actualModuleName;
    if ($config['enable_cache'] && CBinitCache::has($cacheKey) && $id === null) {
        CBinitCache::incrementHits();
        $cached = clone CBinitCache::get($cacheKey);
        // Re-initialize with actual module name
        $cached->__construct($actualModuleName, $id);
        return $cached;
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

        $instance = tryLoadControllerFromVendor($vendorName, $vendorConfig, $actualModuleName, $id);
        if ($instance !== null) {
            $loadedFrom = $vendorName;
            break;
        }
    }

    // Fallback
    if ($instance === null) {
        if ($config['strict_mode']) {
            throw new RuntimeException("CBController not found: {$moduleName}");
        }

        if ($config['fallback_enabled']) {
            // Use base CBController
            $fallbackClass = \Capps\Modules\Core\Classes\CBController::class;
            $instance = new $fallbackClass($actualModuleName, $id);
            $loadedFrom = 'fallback';
            CBinitCache::incrementFallbacks();
        } else {
            throw new RuntimeException("CBController not found: {$moduleName}");
        }
    }

    // Update stats
    CBinitCache::incrementVendorUsage($loadedFrom);

    // Cache (only if no ID, since ID makes it stateful)
    if ($config['enable_cache'] && $id === null) {
        CBinitCache::set($cacheKey, clone $instance);
    }

    // Logging
    if ($config['enable_logging']) {
        $actualClass = get_class($instance);
        error_log("CBinitController: {$moduleName} → {$actualClass} from {$loadedFrom}");
    }

    return $instance;
}

/**
 * Try to load CBController from specific vendor
 *
 * @internal Helper function for CBinitController
 */
function tryLoadControllerFromVendor(string $vendorName, array $vendorConfig, string $moduleName, mixed $id): ?object
{
    // CBController is always in core/classes/CBController.php
    $filePath = rtrim($vendorConfig['path'], '/') . "/modules/core/classes/CBController.php";

    // Try loading file
    if (is_file($filePath)) {
        require_once $filePath;
    }

    // Try different namespace variations (modern capitalized + legacy lowercase)
    $namespaceVariations = [
        // Modern: Capps\Modules\Core\Classes\CBController
        ucfirst($vendorName) . "\\Modules\\Core\\Classes\\CBController",
        // Legacy: capps\modules\core\classes\CBController
        $vendorName . "\\modules\\core\\classes\\CBController"
    ];

    foreach ($namespaceVariations as $fullClassName) {
        $fullClassName = "\\" . ltrim($fullClassName, "\\");

        if (!class_exists($fullClassName)) {
            continue;
        }

        $reflection = new ReflectionClass($fullClassName);
        if (!$reflection->isInstantiable()) {
            continue;
        }

        // Create instance with module name and optional ID
        try {
            return $reflection->newInstance($moduleName, $id);
        } catch (Exception $e) {
            // Try next variation
            continue;
        }
    }

    return null;
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

function parseTemplateFIRST(string $template, array $data, string $prefix = '', bool $htmlSpecialChars = true): string
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

function parseTemplateSECOND($_strTemplate, $_arrEntry, $_prefix = "", $_clean = true) {

    $strParsed = $_strTemplate;

    if ( is_array($_arrEntry) && count($_arrEntry) >= 1 ) {
        foreach ($_arrEntry as $key=>$value){
            if (!is_array($value)) {
                $arrPrefix = explode("|", $_prefix);
                foreach ($arrPrefix as $prefix) {
                    $strParsed = str_replace("###" . $prefix . $key . "###", stripslashes($value.""), $strParsed);
                    $strParsed = str_replace("###" . strtoupper($prefix . $key) . "###", stripslashes($value.""), $strParsed);
                }
            } else {
                $strParsed = parseTemplate($strParsed,$value,$_prefix,$_clean);
            }
        }
    }

    if ( defined("CONFIGURATION") ) {
        foreach ( CONFIGURATION as $key=>$value) {
            if (!is_array($value)) {
                $strParsed = str_replace("###" . $key . "###", $value . "", $strParsed);
                $strParsed = str_replace("###" . strtoupper($key) . "###", $value . "", $strParsed);
            } else {
                // TODO parse array
            }
        }
    }

    //
    if ( defined('VERSION') ) $strParsed = str_replace("###version###",VERSION,$strParsed);
    if ( defined('VERSION') ) $strParsed = str_replace("###VERSION###",VERSION,$strParsed);

    if ( defined('MODULE') ) $strParsed = str_replace("###module###",MODULE,$strParsed);
    if ( defined('MODULE') ) $strParsed = str_replace("###MODULE###",MODULE,$strParsed);

    if ( defined('CAPPS') ) $strParsed = str_replace("###capps###",CAPPS,$strParsed);
    if ( defined('CAPPS') ) $strParsed = str_replace("###CAPPS###",CAPPS,$strParsed);

    if ( defined('BASEURL') ) $strParsed = str_replace("###baseurl###",BASEURL,$strParsed);
    if ( defined('BASEURL') ) $strParsed = str_replace("###BASEURL###",BASEURL,$strParsed);

    if ( defined('BASEDIR') ) $strParsed = str_replace("###basedir###",BASEDIR,$strParsed);
    if ( defined('BASEDIR') ) $strParsed = str_replace("###BASEDIR###",BASEDIR,$strParsed);



    //
    $strParsed = str_replace("###random###", time()."", $strParsed);
    $strParsed = str_replace("###RANDOM###", time()."", $strParsed);

    // clean
    if ( $_clean) $strParsed = preg_replace('/###.*###/Us','',$strParsed);

    return $strParsed;

}


/**
 * Flexible parseTemplate with configurable delimiters
 *
 * Supports multiple syntax styles simultaneously:
 * - ###var###  (current)
 * - %%var%%    (percent)
 * - @[var]     (at-bracket)
 * - [[var]]    (double-bracket)
 * - {{var}}    (mustache-style)
 *
 * Performance: Only processes if delimiters are detected
 */
function parseTemplate($_strTemplate, $_arrEntry, $_prefix = "", $_clean = true, $_delimiters = null)
{

    $strParsed = $_strTemplate;

    // Define all supported delimiter styles
    // Format: [openTag, closeTag, detectPattern]
    $defaultDelimiters = [
        ['###', '###', '###'],           // Current (default)
        ['%%', '%%', '%%'],               // Percent
        ['@[', ']', '@['],                // At-bracket
        ['[[', ']]', '[['],               // Double-bracket
        //['{{', '}}', '{{'],               // Mustache-style - conflict with vue.js !!!
    ];

    $delimiters = $_delimiters ?? $defaultDelimiters;

    // Parse Arrays
    if (is_array($_arrEntry) && count($_arrEntry) >= 1) {
        foreach ($_arrEntry as $key => $value) {
            if (!is_array($value)) {
                $arrPrefix = explode("|", $_prefix);
                foreach ($arrPrefix as $prefix) {
                    // Try all delimiter styles
                    foreach ($delimiters as list($open, $close, $detect)) {
                        // Performance: Only process if delimiter exists
                        if (strpos($strParsed, $detect) === false) continue;

                        // Support both _ and . in variable names
                        $varName = $prefix . $key;
                        $varNameDot = str_replace('_', '.', $varName);

                        // Replace original name
                        $strParsed = str_replace(
                            $open . $varName . $close,
                            stripslashes($value . ""),
                            $strParsed
                        );

                        // Replace with dots
                        if ($varName !== $varNameDot) {
                            $strParsed = str_replace(
                                $open . $varNameDot . $close,
                                stripslashes($value . ""),
                                $strParsed
                            );
                        }

                        // Replace UPPERCASE
                        $strParsed = str_replace(
                            $open . strtoupper($varName) . $close,
                            stripslashes($value . ""),
                            $strParsed
                        );

                        // Replace UPPERCASE with dots
                        if ($varName !== $varNameDot) {
                            $strParsed = str_replace(
                                $open . strtoupper($varNameDot) . $close,
                                stripslashes($value . ""),
                                $strParsed
                            );
                        }
                    }
                }
            } else {
                $strParsed = parseTemplate($strParsed, $value, $_prefix, $_clean, $delimiters);
            }
        }
    }

    // Parse CONFIGURATION
    if (defined("CONFIGURATION")) {
        foreach (CONFIGURATION as $key => $value) {
            if (!is_array($value)) {
                foreach ($delimiters as list($open, $close, $detect)) {
                    if (strpos($strParsed, $detect) === false) continue;

                    $keyDot = str_replace('_', '.', $key);

                    $strParsed = str_replace($open . $key . $close, $value . "", $strParsed);
                    $strParsed = str_replace($open . strtoupper($key) . $close, $value . "", $strParsed);
                    if ($key !== $keyDot) {
                        $strParsed = str_replace($open . $keyDot . $close, $value . "", $strParsed);
                        $strParsed = str_replace($open . strtoupper($keyDot) . $close, $value . "", $strParsed);
                    }
                }
            }
        }
    }

    // Parse Constants
    $constants = ['VERSION', 'MODULE', 'CAPPS', 'BASEURL', 'BASEDIR', 'RANDOM'];
    foreach ($constants as $const) {
        if (defined($const) || $const === 'RANDOM') {
            $val = $const === 'RANDOM' ? time() . "" : constant($const);

            foreach ($delimiters as list($open, $close, $detect)) {
                if (strpos($strParsed, $detect) === false) continue;

                $constLower = strtolower($const);
                $constDot = str_replace('_', '.', $constLower);

                // Lowercase
                $strParsed = str_replace($open . $constLower . $close, $val, $strParsed);
                if ($constLower !== $constDot) {
                    $strParsed = str_replace($open . $constDot . $close, $val, $strParsed);
                }

                // Uppercase
                $strParsed = str_replace($open . $const . $close, $val, $strParsed);
                $constDotUpper = str_replace('_', '.', $const);
                if ($const !== $constDotUpper) {
                    $strParsed = str_replace($open . $constDotUpper . $close, $val, $strParsed);
                }
            }
        }
    }

    // Clean unparsed placeholders
    if ($_clean) {
        foreach ($delimiters as list($open, $close, $detect)) {
            if (strpos($strParsed, $detect) === false) continue;

            // Escape special regex characters
            $openEsc = preg_quote($open, '/');
            $closeEsc = preg_quote($close, '/');

            $strParsed = preg_replace('/' . $openEsc . '.*?' . $closeEsc . '/s', '', $strParsed);
        }


    }

    return $strParsed;
}

function CBLog($data, string $message = ''): void
{
    // if (defined('DEBUG_MODE') && DEBUG_MODE) {
    echo "$message: <pre>"; print_r($data); echo "</pre>";
    $logMessage = $message ? $message . ': ' : '';
    error_log($logMessage . print_r($data, true));
    //}
}

/**
 * Set auth mode for current controller/view/api/cron
 * Called from within controller files to override the default.
 *
 * Default: controller/ = secure, everything else = public
 *
 * @param string $mode 'public' = no auth required, any other value = required permission group
 */
function CBAuth(string $mode = ''): void
{
    $GLOBALS['_cbRouteAuth'] = $mode === 'public' ? 'public' : 'secure';
    $GLOBALS['_cbRouteGroup'] = $mode === 'public' ? '' : $mode;
}

/**
 * Global localize helper function
 *
 * @param string $text Text to localize
 * @param string|null $lang Optional language code (null = auto-detect)
 * @return string Localized text or original if not found
 */
function localize(string $text, ?string $lang = null): string
{
    return \Capps\Modules\Core\Classes\CBCore::localize($text, $lang);
}


//
// https://github.com/TYPO3/TYPO3.CMS/blob/master/typo3/sysext/core/Classes/Resource/Driver/LocalDriver.php
//
if ( !function_exists("sanitizeFileName") ) {
    function sanitizeFileName($fileName, $charset = 'utf-8')
    {
        $cleanFileName = preg_replace('/[' . '\\x00-\\x2C\\/\\x3A-\\x3F\\x5B-\\x60\\x7B-\\xBF' . ']/u', '_', trim($fileName));

        // Strip trailing dots and return
        $cleanFileName = rtrim($cleanFileName, '.');
        if ($cleanFileName === '') {
            //echo 'File name ' . $fileName . ' is invalid.';
        }

        $cleanFileName = mb_strtolower($cleanFileName);

        $path_parts = pathinfo($cleanFileName);
        $cleanFileName = $path_parts['filename'];

        //
        $cleanFileName = str_replace("ä", "ae", $cleanFileName);
        $cleanFileName = str_replace("ö", "oe", $cleanFileName);
        $cleanFileName = str_replace("ü", "ue", $cleanFileName);
        $cleanFileName = str_replace("ß", "ss", $cleanFileName);

        $cleanFileName = str_replace(".", "", $cleanFileName);
        $cleanFileName = str_replace("-", "", $cleanFileName);

        $cleanFileName = str_replace("__", "_", $cleanFileName);
        $cleanFileName = str_replace("__", "_", $cleanFileName);

        $cleanFileName = trim($cleanFileName,"_");

        if ( isset($path_parts['extension']) ) $cleanFileName = $cleanFileName.".".$path_parts['extension'];

        return $cleanFileName;
    }
}




function cb_makeCheckboxForm ($formName,$value="0",$checked="1",$unchecked="0",$submit="0",$ownScript="",$class="formular5") {
    //echo "<br>chfor:$value-$checked-$unchecked<br>";
    $strTmp = "";
    $tmpChecked = "";
    if ( $checked == "") $checked = "1"; // only for sure - cause problem if processed by function makeTimeForm
    if ( $unchecked == "") $unchecked = "0"; // only for sure - cause problem if processed by function makeTimeForm
    if ( $value == $checked) $tmpChecked = " checked";
    $tmpSubmit = "";
    if ( $submit == "1" ) $tmpSubmit = ' onChange="submit();"';
    if ( $ownScript != "" ) $tmpSubmit = ' '.$ownScript;
    $strTmp .= '<input name="'.$formName.'" type="hidden" value="'.$unchecked.'">';
    $strTmp .= '<input name="'.$formName.'" type="checkbox" id="'.$formName.'" value="'.$checked.'"'.$tmpChecked.$tmpSubmit.' class="'.$class.'">';

    //$strTmp .= '<input name="'.$formName.'" type="checkbox" id="'.$formName.'" value="'.$value.'"'.$tmpChecked.$tmpSubmit.' class="'.$class.'">';

    return $strTmp;
}

function cb_makeHiddenForm ($formName,$value,$class="form-control form-control-sm",$id="") {

    // 11-01-10 bob : make quotes passible in input fields
    $value = str_replace('"',"&quot;",$value."");

    $strTmp = "";
    $strTmp  = '<input name="'.$formName.'" type="hidden" class="'.$class.'" value="'.$value.'"'.(!empty($id) ? ' id="'.$id.'"' : '').'>';
    return $strTmp;
}

function cb_makeInputForm($formName,$value,$class="form-control form-control-sm",$access=0,$_readonly="",$password="0",$freeString="") {
    global $coreArrSystemAttributes;
    global $coreFlagMakeFormReadonly;
    global $coreArrSystemUser;

    // 10-09-15 bob : make quotes passible in input fields
    $value = str_replace('"',"&quot;",$value."");

    $type = "text";
    if ( $password != "0" ) $type = "password";

    $bool = true;
    if ( $coreFlagMakeFormReadonly != "" ) $bool = false;
    if ( $_readonly != "" ) $bool = false;
    //if ( $coreArrSystemUser['system_user_status_index'] >= "6" ) $bool = false;

    $strTmp = "";
    if ( $bool ) {
        if ( $access == 0 ) {
            //$strTmp  = '<input name="'.$formName.'" type="text" class="'.$class.'" value="'.$value.'">';
            // 08-08-19 bob : to avoid problems with " - TODO: testing if other problems occur
            $strTmp  = '<input name="'.$formName.'" type="'.$type.'" class="'.$class.'" value="'.$value.'" '.$freeString.'>';

        } else {
            $strTmp  = '<input name="'.$formName.'" type="hidden" class="'.$class.'" value="'.$value.'" '.$freeString.'>'.$value;
        }
    } else {
        $strTmp  = $value;
    }
    return $strTmp;
}

function cb_makeTextfieldForm ($formName,$value,$height=4,$class="form-control form-control-sm",$freeString="",$width=20) {
    global $coreArrSystemAttributes;

    $strTmp = "";
    if ( $class == "" ) $class="form-control form-control-sm";
    if ( $height == "" ) $height="4";
    /*
    if ( $coreArrSystemAttributes['system_utf8'] == "1" ) {
        $strTmp = '<textarea name="'.$formName.'" cols="20" rows="'.$height.'" id="name" class="'.$class.'">'.$value.'</textarea>';
    } else {
        $strTmp = '<textarea name="'.$formName.'" cols="20" rows="'.$height.'" id="name" class="'.$class.'">'.htmlspecialchars($value).'</textarea>';
    }
    */
    // necessary with or without utf-8
    if($match = preg_match('/.*\[(.*)\]/',$formName,$parts)){
        // print_r($parts);
        $id = $parts[1];
    } else {
        $id = $formName;
    }

    if ( $freeString == "" && $height != "" ) $freeString = 'style="height:'.($height*20).'px"';
    $strTmp = '<textarea name="'.$formName.'" cols="'.$width.'" rows="'.$height.'" id="'.$id.'" class="'.$class.'" '.$freeString.'>'.htmlspecialchars($value).'</textarea>';

    return $strTmp;
}

function cb_makeSelectForm ($formName,$arrayValues,$arrayText="",$select=NULL,$submit="0",$class="form-control form-control-sm",$no_first="",$freeString="",$multiple="") {

    $formNameID = $formName;

    foreach ( $arrayValues as $r=>$v ) {
        $arrayValues[$r] = str_replace("#COMMA#",",",$v."");
    }

    foreach ( $arrayText as $r=>$v ) {
        $arrayText[$r] = str_replace("#COMMA#",",",$v."");
    }

    if ( $multiple != "" ) {
        $multiple = ' multiple="multiple"';
        $formName .= "[]";
        //if ( $select != NULL && $select != "" )
        $select = explode(",",$select);
    }


    $strTmp = "";
    //echo $freeString."<<--";
    //echo"<br>$select-<br>";
    $strTmp  = '<select id="'.$formNameID.'" name="'.$formName.'" class="'.$class.'" '.$freeString.$multiple.'>'."\n";
    if ( $submit == "1" ) $strTmp  = '<select name="'.$formName.'" class="'.$class.'" onChange="submit();" '.$freeString.$multiple.'>'."\n";
    if ( !is_int($submit) && $submit != "0" ) {
        //echo $submit;
        $strTmp  = '<select id="'.$formNameID.'" name="'.$formName.'" class="'.$class.'" onChange="'.$submit.'" '.$freeString.$multiple.'>'."\n"; // 2008-01-22 by bob : to allow other onchange
    }
    if ($no_first == "" ) $strTmp .= '<option value="">-- keine Angabe --</option>
	';
    //echo "<pre>arrayText"; print_r($arrayText); echo "</pre>";
    //echo "<pre>arrayText"; print_r($arrayValues); echo "</pre>";
    //echo 'select='.$select.'---<br>';
    //echo count($arrayValues);
    $vstr = serialize($arrayValues);
    $tstr = serialize($arrayText);
    //if ( $vstr == $tstr ) echo "gleich";

    if ( !is_array($arrayValues) ) {
        //echo showError('no array in makeSelectForm');
    } else {
        $selected = false;
        foreach ( $arrayValues as $run=>$mfile){

            //if ( isset($arrayText[$run]) && is_array($arrayText[$run]) ) continue;
            //CBLog($arrayText[$run]);

            //09-10-26 bob : select with different value
            //if ( strstr($arrayText[$run],":") ) {
            if ( strstr($arrayText[$run],":") && ( $vstr == $tstr ) ) {
                $arrTmp = explode(":",$arrayText[$run]);
                $mfile = $arrTmp[0];
                $arrayText[$run] = $arrTmp[1];
            }

            $sel = "";

            if ( is_array($select) && count($select) >= 1 ){
                //debug_print_r($select);
                if ( $select[0] != "" ) {
                    if(in_array($mfile, $select)) { $sel = "selected='selected'"; }
                }
            }else{
                //echo "select=".$select.'  mfile='.$mfile.'--<br>';
                // vika new 2009_11_05 , wenn ein Komma mit ASCII-Zeichen vorkommt, wegen easy-Admin
                if (strstr($mfile,'&#44;')) $mfile = str_replace('&#44;',',',$mfile);
                // vika ende 2009_11_05
                if($select == $mfile && !$selected) {
                    $sel = "selected='selected'";
                    $selected = true;
                }
            }

            if ( $mfile == "disabled" ) {
                $sel = "disabled='disabled'";
            }

            /*
            if ( $run > 1 ) continue;
            $arrayText[$run] = str_replace("\n","",$arrayText[$run]);
            $mfile = str_replace("\n","",$mfile);
            */
            //$arrayText[$run] = urlencode($arrayText[$run]);

            if ( is_array($arrayText) ) {
                $strTmp .= "<option value=\"$mfile\" $sel>".$arrayText[$run]."</option>
				";
            } else {
                $strTmp .= "<option value=\"$mfile\" $sel>$mfile</option>
				";
            }
        }
    }

    //$strTmp = str_replace("&nbsp;","",$strTmp);
    //$strTmp = str_replace("\n","",$strTmp);

    $strTmp .= '</select>';
    return $strTmp;
}


if ( !function_exists("generatePreview") ) {
    function generatePreview($target,$vF,$_width=320,$_height=320) {

        //
        ini_set("memory_limit",-1);

        // not available on dsgv server
        // http://www.webdesignblog.asia/web-design/hosting/install-php-gd-on-ubuntu-without-recompiling-php/#sthash.MWGyBPYO.dpbs
        //imageantialias($im, true);

        $image = imagecreatetruecolor($_width,$_height);
        $transparency = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparency);

        //
        $arrPathInfo = pathinfo($vF);
        $file = $target . $vF;

        //
        if ( $arrPathInfo['extension'] != "pdf" ) {
            /*
            // 2021-01-17 bob : to avoid 90 degree rotated image when upload by smartphone
            if ( $arrPathInfo['extension'] == "jpg" ) correctImageOrientation($target . $vF);
            if ( $arrPathInfo['extension'] == "jpeg" ) correctImageOrientation($target . $vF);

            if ( $arrPathInfo['extension'] == "png" ) $image = @imagecreatefrompng($target.$vF);
            if ( $arrPathInfo['extension'] == "jpg" ) $image = @imagecreatefromjpeg($target.$vF);
            if ( $arrPathInfo['extension'] == "jpeg" ) $image = @imagecreatefromjpeg($target.$vF);
            if ( $arrPathInfo['extension'] == "gif" ) $image = @imagecreatefromgif($target.$vF);
            */

            // 2026-02-09 Bob: check info not extension

            /* echtes Bild prüfen */
            $imgInfo = @getimagesize($file);
            if ($imgInfo === false) {
                // kein gültiges Bild → abbrechen oder Fehler
                return;
            }

            // 2021-01-17 bob : to avoid 90 degree rotated image when upload by smartphone
            if ($imgInfo['mime'] === 'image/jpeg') {
                correctImageOrientation($file);
            }

            if ($imgInfo['mime'] === 'image/png') {
                $image = @imagecreatefrompng($file);
            }
            if ($imgInfo['mime'] === 'image/jpeg') {
                $image = @imagecreatefromjpeg($file);
            }
            if ($imgInfo['mime'] === 'image/gif') {
                $image = @imagecreatefromgif($file);
            }

        }



        //
        // pdf
        //
        if ( $arrPathInfo['extension'] == "pdf" ) {


            //
            $strName = $vF;
            $strName = str_replace(".pdf","",$strName);

            //
            $strThumbName = "_thumb_".$strName.".pdf.jpg";

            //dev
            //echo $target.$strThumbName;exit;

            //
            $dateSource = 0;
            $pathSource = $target.$vF;
            if ( !stristr($pathSource, BASEDIR) ) $pathSource = BASEDIR.$pathSource;
            if ( is_file($pathSource) ) {
                $dateSource = filemtime($pathSource);
            }
            //echo $dateSource;exit;

            //
            $dateThumb = 0;
            $pathThumb = $target."".$strThumbName;
            if ( !stristr($pathThumb, BASEDIR) ) $pathThumb = BASEDIR.$pathThumb;
            if ( is_file($pathThumb) ) {
                $dateThumb = filemtime($pathThumb);
            }
            //echo $dateThumb;exit;

            //
            $boolCheckNewerSource = false;
            if ( $dateSource > $dateThumb ) $boolCheckNewerSource = true;
            if ( isset($_REQUEST['force_generation']) && $_REQUEST['force_generation'] == "1" ) $boolCheckNewerSource = true;

            if ( is_file($target."".$strThumbName) && !$boolCheckNewerSource ) {
                //echo "DEV1";exit;

                $image = @imagecreatefromjpeg($target.$strThumbName);
                $vF = $strThumbName;

            } else {
                //echo "DEV2";exit;

                $path = "";
                $boolInstall = true;
                exec ("whereis ImageMagick",$whereis);
                //echo "exec (\"whereis ImageMagick\",$whereis);<br>";
                //echo "t<br>";
                //print_r($whereis);




                if ($whereis[0] == 'ImageMagick:') {
                    $boolInstall = false;

                    exec ("dpkg -L imagemagick",$whereis);
                    //echo "<pre>"; print_r($whereis); echo "</pre>";

                    if ( is_array($whereis) && count($whereis) >= 1 ) {

                        $strPathConvert = "";
                        foreach ( $whereis as $r=>$v ) {
                            if ( stristr($v,"convert") )  $strPathConvert = $v;
                        }
                        //echo "<pre>"; print_r($strPathConvert); echo "</pre>";

                        if ( $strPathConvert != "" ) $path = $strPathConvert;

                    }

                }
                if ($boolInstall) {

                    //echo "yes<br>";

                    exec ("whereis convert",$whereisconvert);
                    if ($whereisconvert[0] != 'convert:') {
                        $strConvertPath = str_replace('convert:','',$whereisconvert[0]);
                        list ($path,$dummy) = explode(' ',trim($strConvertPath));
                    }

                    // Path to New Image Magick, wird nicht benötigt, da TIFF Bearbeitung  nicht funktioniert
                    //$path = '/usr/local/imagemagick/bin/convert';
                    //echo $path;


                } else {
                    //echo "no";
                    exec ("whereis convert",$whereis2);
                    //echo "exec (\"whereis ImageMagick\",$whereis2);<br>";
                    //echo "t<br>";
                    //echo "<pre>"; print_r($whereis2); echo "</pre>";
                    $arrConvert = explode(" ", $whereis2[0]);
                    //echo "$whereis2<pre>"; print_r($arrConvert); echo "</pre>";

                    if ( $arrConvert[1] != "" ) $path = $arrConvert[1];

                    /*
                                    if ( !extension_loaded('imagick') ) {
                                        echo 'imagick not installed';
                                    } else {
                                        echo 'imagick IS installed';
                                    }
                    */

                    /*
                                    echo "<pre>";
                    system($arrConvert[1]." -version");
                    echo "</pre>";
                    */

                }

                if ( $path != "" ) {

                    // Path to New Image Magick, wird nicht benötigt, da TIFF Bearbeitung  nicht funktioniert
                    //$path = '/usr/local/imagemagick/bin/convert';
                    //echo $path;


                    /*
                    $f = $_REQUEST['f'];
                   // $f = str_replace(".pdf","",$f);
                    list($strName,$strExtension) = explode('.', $f,2);

                    $strNameReflect = $strName."_4";
                    */

                    /*
                    $strComado = "$path -density 300 $target".$_REQUEST['f']." ".$target.$f.".jpg";
                    $strComado = "$path -density 72 $target".$_REQUEST['f']." ".$target.$f.".jpg";
                    */




                    // In JPG Konvertieren
                    //$strComado = "$path -density 300 -colorspace cmyk $target".$_REQUEST['f']." ".$target.$strName.".jpg";
                    //$strComado = "$path -profile /home/web14/html/data/script/CMYK/CoatedFOGRA39.icc  -colorspace CMYK -density 72 $target".$_REQUEST['f']." -profile /home/web14/html/data/script/RGB/AdobeRGB1998.icc  -colorspace RGB ".$target.$strName.".jpg";
                    //$strComado = "$path -density 72  -profile ".PATH_TO_DATA."/script/CMYK/ISOcoated_v2_eci.icc -colorspace cmyk $target".$_REQUEST['f']." -resize 800 ".$target.$strName.".jpg";

                    //		  $strComado = "$path -density 72 -colorspace cmyk $target".$_REQUEST['f']." -resize 1000 ".$target.$strName.".jpg";

                    //$strName = $vF;
                    //$strName = str_replace(".pdf","",$strName);

                    $file = $target.$vF;
//				  $strComado = "$path -density 72 ".$file."[0] -resize 50% ".$target.$strThumbName;
                    $strComado = "$path -density 72 -colorspace rgb -background white -alpha remove ".$file."[0] -resize 50% ".$target.$strThumbName;
                    // DEV -verbose
                    $strComado = "$path -verbose -density 72 -colorspace rgb -background white -alpha remove ".$file."[0] -resize 50% ".$target.$strThumbName;

                    //echo "<pre>"; print_r( $strComado ); echo "</pre>";

                    //$IMagick =  new Imagick();
                    $return_var = shell_exec($strComado);
                    //print_r( $return_var );
                    //echo "return_var<pre>"; print_r( $return_var ); echo "</pre>";exit;


                    //
                    $image = @imagecreatefromjpeg($target.$strThumbName);
                    $vF = $strThumbName;

                }


            }

            //$test = new Imagick();
            //print_r($test);
        }

        //
        //
        //
        if ( stristr($vF,".mp4") || stristr($vF,".m4v") || stristr($vF,".mpg") || stristr($vF,".mpeg") ) {

            //
            $strName = $vF;
            //$strName = str_replace(".pdf","",$strName);

            //
// 		$strThumbName = "_thumbpdf_".$strName.".jpg";
            $strThumbName = "_thumb_".$strName.".jpg";


            $file = $target.$vF;
            /*
                    $file = 'https://lab.forscherhaus-gesamtschule.de/ajax/&am=Document.showItem&item=19588';
                    $file = 'https://lab.forscherhaus-gesamtschule.de/data/media/video/test.mp4';
                    $file = 'https://lab.forscherhaus-gesamtschule.de/data/script/video_passthrough.php';
            */

//		$url = "http://web1.login.cybob-five.com/api/video/?time=00:00:01&video=".$file."";
//		$url = "http://web1.login.cybob-five.com/api/video/?time=00:00:01&file=".$file."&video=https://lab.forscherhaus-gesamtschule.de/data/script/video_passthrough.php";
//		$url = "http://web1.login.cybob-five.com/api/video/?time=00:00:01&file=".$file."&video=https://www.fvsg-buende.de/preview/data/script/backend/video_passthrough.php";

            //            var url = '/controller/medialibrary/updateItem/';

            /*
            $url = "http://web1.login.cybob-five.com/api/video/?time=00:00:01&file=".$file."&video=".BASEURL."controller/medialibrary/videoPassthrough/";
            mail("robert.heuer@cybob.com","video",print_r($url,true));
            //echo "mp4 url<pre>"; print_r($url); echo "</pre>";	exit;
            $data = file_get_contents($url);
            //echo "mp4 data<pre>"; print_r($data); echo "</pre>";	exit;
            //echo "mp4 data<pre>"; print_r( $target.'---'.$strThumbName ); echo "</pre>";	exit;
            file_put_contents($target.$strThumbName, $data);
// 		file_put_contents($target.$strThumbName."_DEV", $data);
*/


            $video = $file;
            $time = "00:00:01";
            $thumbnail = BASEDIR.'../cache/thumbnail.jpg';


            shell_exec('ffmpeg -i "'.$video.'" -ss '.$time.' -vframes 1 -filter:v scale="280:-1" -f image2 -y "'.$thumbnail.'" 2>&1');


            $data = file_get_contents($thumbnail);
            file_put_contents($target.$strThumbName, $data);



            //
            $image = @imagecreatefromjpeg($target.$strThumbName);
            $vF = $strThumbName;
        }


        //$image = imagecreatefrompng($target.$vF);
        //$arrImageSize = getimagesize($target.$vF);


//        //
//        $arrPathInfo = pathinfo($vF);
//
//        //
//        if ( $arrPathInfo['extension'] != "pdf" ) {
//
//            // 2021-01-17 bob : to avoid 90 degree rotated image when upload by smartphone
//            if ( $arrPathInfo['extension'] == "jpg" ) correctImageOrientation($target . $vF);
//            if ( $arrPathInfo['extension'] == "jpeg" ) correctImageOrientation($target . $vF);
//
//            if ( $arrPathInfo['extension'] == "png" ) $image = @imagecreatefrompng($target.$vF);
//            if ( $arrPathInfo['extension'] == "jpg" ) $image = @imagecreatefromjpeg($target.$vF);
//            if ( $arrPathInfo['extension'] == "jpeg" ) $image = @imagecreatefromjpeg($target.$vF);
//            if ( $arrPathInfo['extension'] == "gif" ) $image = @imagecreatefromgif($target.$vF);
//
//        }

        // the desired width of the image
        $width = $_width;
        $height = $_height;

        $max = $_width;
        if ( $_height > $_width ) $max = $_height;

// content type
//header('Content-Type: image/jpeg');

        $width_orig = $_width;
        $height_orig = $_height;
        if ( is_file($target.$vF) ) {
            list($width_orig, $height_orig) = getimagesize($target.$vF);

            if ( $width_orig <= 0 ) $width_orig = $_width;
            if ( $height_orig <= 0 ) $height_orig = $_height;
        }

        $ratio_orig = $width_orig/$height_orig;
//echo $ratio_orig."<br>";
        if ( $ratio_orig < 1 ) {
            $width = $max * $ratio_orig;
        } else {
            $height = $max/$ratio_orig;
        }

//echo $width." - ".$height."<br>";
        /*
        $ratio_max = 1;
        if ( $width > $max ) {
            $ratio_max = $max/$width;
            //echo "w ".$ratio_max."<br>";
        }

        if ( $height > $max ) {
            $ratio_max = $max/$height;
            //echo "h ".$ratio_max."<br>";
        }

        $height = $height * $ratio_max;
        $width = $width * $ratio_max;
        */
//echo $width." - ".$height."<br>";
//echo $width_orig." - ".$height_orig."<br>";

        $offset_x = 0;
        $offset_y = 0;
        if ( $width < $max) $offset_x = ( $max - $width ) / 2;
        if ( $height < $max) $offset_y = ( $max - $height ) / 2;



        $im  = imagecreatetruecolor($max,$max);

        /*
        $rgb_bg = imagecolorallocate($im, 255, 255, 255);
        imagecolortransparent($im,$rgb_bg); // set transparence
        imagefill($im, 0, 0, $rgb_bg);
        */


// Transparent Background
        imagealphablending($im, false);
        $transparency = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefill($im, 0, 0, $transparency);
        imagesavealpha($im, true);

        //imagecopyresampled($im, $image, 0, 0, 0, 0, 100, 100, $arrImageSize[0], $arrImageSize[1]);
        imagecopyresampled($im, $image, (int) round($offset_x), (int) round($offset_y), 0, 0, (int) round($width), (int) round($height), (int) round($width_orig), (int) round($height_orig));

        //echo $im;
        //header("Content-type: image/png");
        //imagepng($im);

        ob_start();
        imagepng($im);
        $contents =  ob_get_contents();
        ob_end_clean();

        imagedestroy($im);


        return $contents;

    }
}

//
// https://medium.com/thetiltblog/fixing-rotated-mobile-image-uploads-in-php-803bb96a852c
//
if ( !function_exists("correctImageOrientation") ) {
    function correctImageOrientation($filename) {
        if (function_exists('exif_read_data')) {
            $exif = exif_read_data($filename);
            if($exif && isset($exif['Orientation'])) {
                $orientation = $exif['Orientation'];
                if($orientation != 1){
                    $img = imagecreatefromjpeg($filename);
                    $deg = 0;
                    switch ($orientation) {
                        case 3:
                            $deg = 180;
                            break;
                        case 6:
                            $deg = 270;
                            break;
                        case 8:
                            $deg = 90;
                            break;
                    }
                    if ($deg) {
                        $img = imagerotate($img, $deg, 0);
                    }
                    // then rewrite the rotated image back to the disk as $filename
                    imagejpeg($img, $filename, 80);
                } // if there is some rotation necessary
            } // if have the exif orientation info
        } // if function exists
    }
}


if ( !function_exists("getFilesizeWithPath") ) {
    function getFilesizeWithPath($path) {
        $bytes = sprintf('%u', @filesize($path));

        if ($bytes > 0)
        {
            $unit = intval(log((float)$bytes, 1024));
            $units = array('B', 'KB', 'MB', 'GB');

            if (array_key_exists($unit, $units) === true)
            {
                return sprintf('%1.1f %s', $bytes / pow(1024, $unit), $units[$unit]);
//            return sprintf('%d %s', $bytes / pow(1024, $unit), $units[$unit]);
            }
        }

        return $bytes;
    }
}

if ( !function_exists("formatSizeUnits") ) {
    function formatSizeUnits($bytes) {
        if ($bytes >= 1073741824)
        {
            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        }
        elseif ($bytes >= 1048576)
        {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        }
        elseif ($bytes >= 1024)
        {
            $bytes = number_format($bytes / 1024, 2) . ' KB';
        }
        elseif ($bytes > 1)
        {
            $bytes = $bytes . ' bytes';
        }
        elseif ($bytes == 1)
        {
            $bytes = $bytes . ' byte';
        }
        else
        {
            $bytes = '0 bytes';
        }

        return $bytes;
    }
}

/*
   function human_filesize($bytes, $decimals = 2) {
     $sz = 'BKMGTP';
     $factor = floor((strlen($bytes) - 1) / 3);
     return sprintf("%.{$decimals}f ", $bytes / pow(1024, $factor)) . @$sz[$factor];
   }
*/

//http://jeffreysambells.com/2012/10/25/human-readable-filesize-php
function human_filesize($bytes, $dec = 2) {
    $size   = array('B', 'kB', 'MB', 'GB');
    $factor = floor((strlen($bytes) - 1) / 3);

    return sprintf("%.{$dec}f", $bytes / pow(1024, $factor)) . ' ' . @$size[$factor];
}