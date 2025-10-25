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
 * - Template rendering (NO eval!)
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
            return;
        }

        // Default is HTML
        $this->responseType = 'html';
    }

    /**
     * Set security headers based on environment and response type
     */
    private function setSecurityHeaders(): void
    {
        // Basic security headers (always)
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // CORS (AJAX/JSON support)
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        // Production-only strict CSP
        if ($this->isProduction && $this->responseType === 'html') {
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';");
        }

        // HSTS in production (force HTTPS)
        if ($this->isProduction && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    /**
     * Set content-type header based on response type
     */
    private function setContentTypeHeader(): void
    {
        match($this->responseType) {
            'json' => header('Content-Type: application/json; charset=utf-8'),
            'xml' => header('Content-Type: application/xml; charset=utf-8'),
            'text' => header('Content-Type: text/plain; charset=utf-8'),
            default => header('Content-Type: text/html; charset=utf-8')
        };
    }

    /**
     * Get current response type
     */
    public function getResponseType(): string
    {
        return $this->responseType;
    }

    /**
     * Manually set response type
     */
    public function setResponseType(string $type): void
    {
        $this->responseType = $type;
    }

    /**
     * Parse request into route array - simple and fast
     */
    private function parseRequest(array $request, string $requestUri): array
    {
        // Special routes
        if ($requestUri === "/admin/") {
            return ['type' => 'script', 'template' => 'admin', 'script' => null];
        }
        if ($requestUri === "/console/") {
            return ['type' => 'script', 'template' => 'console', 'script' => null];
        }

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
        //CBLog($structureId); exit;

        // Load structure
        $structure = new CBObject($structureId, 'capps_structure', 'structure_id');
        //CBLog($structure); exit;
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
     * Render page template - NO eval!
     */
    private function renderPageTemplate(CBObject $structure): string
    {
        // Load template file
        $templatePath = $structure->get('template');
        $template = $this->loadTemplateFile($templatePath);

        if (empty($template)) {
            return $this->formatError("Template not found");
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
        ], ['order' => 'position']);

        $html = '';

        foreach ($contentItems as $item) {
            $contentObj = new CBObject($item['content_id'], 'capps_content', 'content_id');

            // Check permissions
            if (!$this->checkAccess($contentObj)) {
                continue;
            }

            // Load content template
            $contentTemplate = $this->loadTemplateFile($contentObj->get('template'));

            if (empty($contentTemplate)) {
                continue;
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

        $fullPath = BASEDIR . $templatePath;

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
}