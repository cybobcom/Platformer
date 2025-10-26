<?php

declare(strict_types=1);

namespace Capps\Modules\Core\Classes;

use Capps\Modules\Database\Classes\CBObject;
use Capps\Modules\Address\Classes\Address;

/**
 * CBCore - Simple Core Framework
 *
 * Following KISS principle like CBDatabase and CBObject.
 * One class, clear responsibilities, performant.
 *
 * Features:
 * - Simple routing
 * - Template rendering with PHP support (secure via include)
 * - Request handling
 * - Permission checks
 * - Response type management (HTML, JSON, etc.)
 * - Security headers
 * - Uses existing CBParser for CB-Tags
 */
class CBCore
{
    private CBParser $parser;
    private ?Address $user = null;
    private array $sortedStructure = [];
    private array $routeCache = [];
    private string $responseType = 'html';
    private bool $isProduction = false;

    // Localization - static properties
    private static string $currentLanguage = 'en';
    private static string $defaultLanguage = 'en';  // NEU: Master-Sprache
    private static array $translations = [];
    private static array $loadedModules = [];

    public function __construct()
    {
        $this->parser = new CBParser();
        $this->isProduction = getenv('ENVIRONMENT') === 'production';
    }

    /**
     * Initialize core with user and structure data
     */
    public function init(Address $user, array $sortedStructure, array $routeCache): void
    {
        $this->user = $user;
        $this->sortedStructure = $sortedStructure;
        $this->routeCache = $routeCache;
    }

    /**
     * Main execution - replaces complex core.php logic
     */
    public function run(): void
    {
        // 1. Parse request and determine response type
        $route = $this->parseRequest($_REQUEST, $_SERVER["REQUEST_URI"] ?? '');
        $this->detectResponseType($route);

        // 2. Set security headers
        $this->setSecurityHeaders();

        // 3. Set content-type header
        $this->setContentTypeHeader();

        // 4. Execute route
        $output = match($route['type']) {
            'script' => $this->handleScriptRoute($route),
            'page' => $this->handlePageRoute($route),
            default => $this->handlePageRoute($route)
        };

        // 5. Output (already formatted by response type)
        echo $output;
    }

    /**
     * Detect response type from route
     */
    private function detectResponseType(array $route): void
    {
        // Check if this is a JSON endpoint
        if ($route['type'] === 'script') {
            $option = $route['option'] ?? '';

            // JSON endpoints (controller/*Item.php typically return JSON)
            if (str_contains($option, 'controller/') ||
                str_contains($option, 'Item') ||
                str_contains($option, 'ajax') ||
                isset($_REQUEST['format']) && $_REQUEST['format'] === 'json') {
                $this->responseType = 'json';
                return;
            }
        }

        // Check Accept header
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (str_contains($accept, 'application/json')) {
            $this->responseType = 'json';
        }
    }

    /**
     * Set security headers
     */
    private function setSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        // Basic security headers
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Production-only headers
        if ($this->isProduction) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    /**
     * Set content-type header based on response type
     */
    private function setContentTypeHeader(): void
    {
        if (headers_sent()) {
            return;
        }

        match($this->responseType) {
            'json' => header('Content-Type: application/json; charset=utf-8'),
            'html' => header('Content-Type: text/html; charset=utf-8'),
            default => header('Content-Type: text/html; charset=utf-8')
        };
    }

    /**
     * Parse request into route information
     */
    private function parseRequest(array $request, string $requestUri): array
    {
        $cbRoute = $request['CBroute'] ?? '';

        // Empty route = page
        if (empty($cbRoute)) {
            return ['type' => 'page', 'structure_id' => $this->getDefaultStructureId()];
        }

        $segments = array_filter(explode("/", trim($cbRoute, '/')));

        if (empty($segments)) {
            return ['type' => 'page', 'structure_id' => $this->getDefaultStructureId()];
        }

        $type = strtolower($segments[0]);

        // Script routes (interface, control, view)
        if (in_array($type, ['interface', 'control', 'view', 'views', 'controller'])) {
            $module = $segments[1] ?? 'home';
            $option = $segments[2] ?? '';
            $script = $this->findScriptPath($type, $module, $option, $segments);

            return [
                'type' => 'script',
                'module' => $module,
                'option' => $option,
                'script' => $script,
                'template' => $this->getTemplateType($option)
            ];
        }

        // Page route
        return ['type' => 'page', 'structure_id' => $this->findStructureId($cbRoute)];
    }

    /**
     * Find script path - searches in ALL configured vendors
     */
    private function findScriptPath(string $type, string $module, string $option, array $segments): ?string
    {
        $option = str_replace("_", "/", $option);

        $sourcedir = defined('SOURCEDIR') ? SOURCEDIR : BASEDIR . 'src/';
        $customPath = ($segments[3] ?? '') ? '/' . $segments[3] : '';

        // Support for 'views' and 'controller' naming
        $typeVariants = [$type];
        if ($type === 'view') $typeVariants[] = 'views';
        if ($type === 'views') $typeVariants[] = 'view';
        if ($type === 'control') $typeVariants[] = 'controller';
        if ($type === 'controller') $typeVariants[] = 'control';

        // Get all configured vendors (sorted by priority)
        $vendors = CONFIGURATION['cbinit']['vendors'] ?? [];
        uasort($vendors, fn($a, $b) => ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0));

        // Search in all vendors (highest priority first)
        foreach ($vendors as $vendorName => $vendorConfig) {
            // Skip disabled vendors
            if (!($vendorConfig['enabled'] ?? true)) {
                continue;
            }

            $vendorPath = $vendorConfig['path'] ?? '';
            if (empty($vendorPath) || !is_dir($vendorPath)) {
                continue;
            }

            // Try different path patterns for this vendor
            foreach ($typeVariants as $typeVariant) {
                $paths = [
                    // Pattern 1: sourcedir/type/module/option/
                    $sourcedir . "{$typeVariant}/{$module}/{$option}{$customPath}.php",
                    // Pattern 2: sourcedir/type/modules/option/module/
                    $sourcedir . "{$typeVariant}/modules/{$option}/{$module}{$customPath}.php",
                    // Pattern 3: vendor/modules/module/type/option
                    rtrim($vendorPath, '/') . "/modules/{$module}/{$typeVariant}/{$option}.php"
                ];

                foreach ($paths as $path) {
                    if (file_exists($path)) {
                        return $path;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Get template type from option
     */
    private function getTemplateType(string $option): ?string
    {
        if ($option === 'main' || str_starts_with($option, 'main_')) {
            return 'main';
        }
        if ($option === 'admin' || str_starts_with($option, 'admin_')) {
            return 'admin';
        }
        return null;
    }

    /**
     * Find structure ID from route
     */
    private function findStructureId(string $cbRoute): int
    {
        // Try route cache first
        $routePath = rtrim($cbRoute, '/') . '/';

        // Search in cache
        foreach ($this->routeCache as $key => $route) {
            if ($route === $routePath) {
                $parts = explode(':', $key);
                return (int)($parts[0] ?? 0);
            }
        }

        return $this->getDefaultStructureId();
    }

    /**
     * Get default structure ID
     */
    private function getDefaultStructureId(): int
    {
        if (!empty($this->sortedStructure)) {
            return (int)array_key_first($this->sortedStructure);
        }
        return 1;
    }

    /**
     * Handle script route
     */
    private function handleScriptRoute(array $route): string
    {
        if ($route['script'] === null) {
            return $this->formatError("Script not found", 404);
        }

        // Execute script with output buffering
        ob_start();

        // Define constants for scripts (instead of $GLOBALS)
        if (!defined('CURRENT_MODULE')) {
            define('CURRENT_MODULE', $route['module'] ?? '');
        }

        include $route['script'];
        $content = ob_get_clean();

        // If script already set JSON header, return as-is
        if ($this->responseType === 'json') {
            return $content;
        }

        // Wrap in template if needed
        if ($route['template']) {
            return $this->wrapInMasterTemplate($content, $route['template']);
        }

        return $this->replacePlaceholders($content);
    }

    /**
     * Handle page route
     */
    private function handlePageRoute(array $route): string
    {
        $structureId = $route['structure_id'];

        //
        if ( !isset($_REQUEST['structure_id']) ) {
            $_REQUEST['structure_id'] = $structureId;
        }
//        if ( !isset($_REQUEST['content_id']) ) {
//            $_REQUEST['content_id'] = $key['content_id'];
//        }
//        if ( !isset($_REQUEST['address_id']) ) {
//            $_REQUEST['address_id'] = $key['address_id'];
//        }


        // Load structure
        $structure = new CBObject($structureId, 'capps_structure', 'structure_id');

        // Check permissions
        if (!$this->checkAccess($structure)) {
            header('Location: ' . BASEURL);
            exit;
        }

        // Check if active
        if ($structure->get('active') != '1') {
            return $this->formatError("Structure not active", 403);
        }

        // Load and render template
        return $this->renderPageTemplate($structure);
    }

    /**
     * Format error response based on response type
     */
    private function formatError(string $message, int $code = 500): string
    {
        http_response_code($code);

        if ($this->responseType === 'json') {
            return json_encode([
                'error' => true,
                'message' => $message,
                'code' => $code
            ]);
        }

        return "<h1>Error {$code}</h1><p>" . htmlspecialchars($message) . "</p>";
    }

    /**
     * Check access permissions - simple and fast
     */
    private function checkAccess(CBObject $structure): bool
    {
        $requiredGroups = $structure->get('addressgroups');

        if (empty($requiredGroups)) {
            return true;
        }

        $userGroups = $this->user->get('addressgroups');
        return checkIntersection($userGroups, $requiredGroups);
    }

    /**
     * Execute template with PHP support
     * Variables available in template: $structure, $content, $templateVars (array)
     */
    private function executeTemplate(string $templatePath, array $templateVars = []): string
    {
        if (empty($templatePath)) {
            return "";
        }

        $fullPath = BASEDIR . ltrim($templatePath, '/');

        if (!file_exists($fullPath)) {
            return "";
        }

        // Security: Only allow templates from data/template/ or src/ directory
        $allowedPaths = [
            BASEDIR . 'data/template/',
            defined('SOURCEDIR') ? SOURCEDIR : BASEDIR . 'src/'
        ];

        $realPath = realpath($fullPath);
        $isAllowed = false;

        if ($realPath !== false) {
            foreach ($allowedPaths as $allowedPath) {
                $realAllowedPath = realpath($allowedPath);
                if ($realAllowedPath !== false && strpos($realPath, $realAllowedPath) === 0) {
                    $isAllowed = true;
                    break;
                }
            }
        }

        if (!$isAllowed) {
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log("Template security: Path outside allowed directories: {$fullPath}");
            }
            return "";
        }

        // Extract variables for template
        extract($templateVars, EXTR_SKIP);

        // Execute template with output buffering
        ob_start();
        include $fullPath;
        return ob_get_clean();
    }

    /**
     * Render page template with PHP support
     */
    private function renderPageTemplate(CBObject $structure): string
    {
        // Load template file
        $templatePath = $structure->get('template');

        // Prepare variables for template
        $templateVars = [
            'structure' => $structure,
            'content' => null // Will be set in content rendering
        ];

        // Check if template contains PHP
        $templateContent = $this->loadTemplateFile($templatePath);
        if (empty($templateContent)) {
            return $this->formatError("Template not found");
        }

        // Replace structure placeholders
        //$templateContent = parseTemplate($templateContent, $structure->arrAttributes, "page_|structure_", false);

        $hasPHP = str_contains($templateContent, '<?php') || str_contains($templateContent, '<?=');

        if ($hasPHP) {
            // Execute template with PHP support
            $template = $this->executeTemplate($templatePath, $templateVars);
        } else {
            // No PHP, just load content
            $template = $templateContent;
        }

        // Remove easyadmin blocks
        $template = $this->removeEasyAdminBlocks($template);

        // Parse CB tags
        $template = $this->parser->parse($template, $structure);

        // Replace structure placeholders
        $template = parseTemplate($template, $structure->arrAttributes, "page_|structure_", false);

        // Load and render content
        $contentHtml = $this->renderContent($structure);
        $template = str_replace("###part_content###", $contentHtml, $template);

        // Replace global placeholders
        return $this->replacePlaceholders($template);
    }

    /**
     * Render content elements
     */
    private function renderContent(CBObject $structure): string
    {
        // Load content
        $content = new CBObject(null, 'capps_content', 'content_id');
        $contentItems = $content->findAll([
            'structure_id' => $structure->get('structure_id'),
            'language_id' => '1',
            'active' => '1'
        ], ['order' => 'sorting']); // position oder sorting
        //CBLog($contentItems);
        //CBLog($content->debug());

        $html = '';

        foreach ($contentItems as $item) {
            $contentObj = new CBObject($item['content_id'], 'capps_content', 'content_id');

            // Check permissions
            if (!$this->checkAccess($contentObj)) {
                continue;
            }

            // Load content template
            $contentTemplatePath = $contentObj->get('template');
            $contentTemplateContent = $this->loadTemplateFile($contentTemplatePath);

            if (empty($contentTemplateContent)) {
                continue;
            }

            // Check if content template contains PHP
            $hasPHP = str_contains($contentTemplateContent, '<?php') || str_contains($contentTemplateContent, '<?=');

            if ($hasPHP) {
                // Execute with PHP support
                $templateVars = [
                    'structure' => $structure,
                    'content' => $contentObj
                ];
                $contentTemplate = $this->executeTemplate($contentTemplatePath, $templateVars);
            } else {
                // No PHP
                $contentTemplate = $contentTemplateContent;
            }

            // Remove easyadmin blocks
            $contentTemplate = $this->removeEasyAdminBlocks($contentTemplate);

            // Replace content placeholders
            $contentTemplate = parseTemplate($contentTemplate, $contentObj->arrAttributes, "element_|content_", false);

            // Parse CB tags
            $contentTemplate = $this->parser->parse($contentTemplate, $structure, $contentObj);

            // Replace structure placeholders if needed
            if (str_contains($contentTemplate, "###page_") || str_contains($contentTemplate, "###structure_")) {
                $contentTemplate = parseTemplate($contentTemplate, $structure->arrAttributes, "page_|structure_", false);
            }

            $html .= $contentTemplate;
        }

        return $html;
    }

    /**
     * Load template file
     */
    private function loadTemplateFile(string $templatePath): string
    {
        if (empty($templatePath)) {
            return "";
        }

        $fullPath = BASEDIR . ltrim($templatePath, '/');

        if (!file_exists($fullPath)) {
            return "";
        }

        return file_get_contents($fullPath);
    }

    /**
     * Wrap content in master template
     */
    private function wrapInMasterTemplate(string $content, string $type): string
    {
        $templateFile = match($type) {
            'admin' => BASEDIR . "data/template/views/mastertemplate_admin_V1.html",
            'main' => BASEDIR . "data/template/views/mastertemplate.html",
            default => null
        };

        if (!$templateFile || !file_exists($templateFile)) {
            return $content;
        }

        $template = file_get_contents($templateFile);
        $template = str_replace("###part_content###", $content, $template);

        return $this->replacePlaceholders($template);
    }

    /**
     * Remove easyadmin blocks
     */
    private function removeEasyAdminBlocks(string $template): string
    {
        if (str_contains($template, '</cb:easyadmin>')) {
            $template = preg_replace('/<cb:easyadmin(.*)>(.*)<\/cb:easyadmin>/Us', '', $template);
        }
        return $template;
    }

    /**
     * Replace global placeholders
     */
    private function replacePlaceholders(string $template): string
    {
        $replacements = [
            '###RANDOM###' => time(),
            '###BASEURL###' => BASEURL,
            '###BASEDIR###' => BASEDIR,
            '###capps###' => CAPPS,
            '###CAPPS###' => CAPPS
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Set current language
     */
    public static function setLanguage(string $lang): void
    {
        self::$currentLanguage = $lang;
    }

    /**
     * Get current language
     */
    public static function getLanguage(): string
    {
        return self::$currentLanguage;
    }

    /**
     * Set default/master language
     */
    public static function setDefaultLanguage(string $lang): void
    {
        self::$defaultLanguage = $lang;
    }

    /**
     * Get default/master language
     */
    public static function getDefaultLanguage(): string
    {
        return self::$defaultLanguage;
    }

    /**
     * Localize text
     */
    public static function localize(string $text, ?string $lang = null): string
    {
        $lang = $lang ?? self::$currentLanguage;

        // Check if translation exists
        $key = $lang . ':' . $text;
        if (isset(self::$translations[$key])) {
            return self::$translations[$key];
        }

        // Return original text
        return $text;
    }

    /**
     * Load module translations
     */
    public static function loadModuleTranslations(string $module): void
    {
        if (isset(self::$loadedModules[$module])) {
            return;
        }

        $lang = self::$currentLanguage;
        $langFile = CAPPS . "modules/{$module}/localize/{$lang}.php";

        if (file_exists($langFile)) {
            $moduleTranslations = include($langFile);
            if (is_array($moduleTranslations)) {
                foreach ($moduleTranslations as $source => $target) {
                    self::$translations[$lang . ':' . $source] = $target;
                }
            }
        }

        self::$loadedModules[$module] = true;
    }
}