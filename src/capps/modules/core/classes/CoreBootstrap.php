<?php

declare(strict_types=1);

namespace Capps\Modules\Core\Classes;

use Capps\Modules\Database\Classes\CBObject;

/**
 * Improved Core Bootstrap using CBAutoloader
 * 
 * File: capps/modules/core/classes/CoreBootstrap.php
 * 
 * Features:
 * - Uses existing CBAutoloader
 * - Follows existing namespace convention
 * - Separation of Concerns
 * - Security First (NO eval!)
 * - Performance Optimized
 */
class CoreBootstrap
{
	private ServiceContainer $container;
	private RouterService $router;
	private SecurityService $security;
	private TemplateService $templateEngine;
	
	public function __construct()
	{
		$this->initializeAutoloader();
		$this->container = new ServiceContainer();
		$this->initializeServices();
	}
	
	/**
	 * Initialize CBAutoloader with existing configuration
	 */
	private function initializeAutoloader(): void
	{
		// CBAutoloader ist bereits in core.php initialisiert
		// Wir nutzen die bestehende Konfiguration
		
		// Falls zusätzliche Namespaces benötigt werden:
		global $autoloader;
		if (isset($autoloader)) {
			// Zusätzliche Core-Services registrieren falls nötig
			$autoloader->addNamespace('Capps\\Modules\\Core\\Services\\', CAPPS . 'modules/core/services');
		}
	}
	
	/**
	 * Initialize core services
	 */
	private function initializeServices(): void
	{
		// Register core services
		$this->container->register('security', new SecurityService());
		$this->container->register('router', new RouterService());
		$this->container->register('template', new TemplateService());
		$this->container->register('user', new UserService());
		$this->container->register('cache', new CacheService());
		$this->container->register('logger', new LoggerService());
		
		// Initialize frequently used services
		$this->router = $this->container->get('router');
		$this->security = $this->container->get('security');
		$this->templateEngine = $this->container->get('template');
	}
	
	/**
	 * Main application entry point - replaces existing core.php logic
	 */
	public function run(): void
	{
		try {
			// 1. Security validation (replace direct $_REQUEST usage)
			$request = $this->security->validateRequest();

			// 2. Parse route (replace manual route parsing)
			$route = $this->router->parseRequest($request);
			
			// 3. Load and check user permissions (replace existing user logic)
			$user = $this->loadCurrentUser();
			if (!$this->security->hasAccess($route, $user)) {
				$this->handleUnauthorized($route);
				return;
			}
			
			// 4. Execute route (replace existing if/else chain)
			$response = $this->executeRoute($route, $user);
			
			// 5. Render response (replace eval() calls)
			$this->renderResponse($response);
			
		} catch (\Exception $e) {
			$this->handleError($e);
		}
	}
	
	/**
	 * Load current user - integrates with existing user system
	 */
	private function loadCurrentUser(): CBObject
	{
		// Use existing user loading from core.php
		$userId = $_SESSION[PLATTFORM_IDENTIFIER]["login_user_identifier"] ?? "";
		
		$user = CBObject::make($userId, 'capps_address', 'address_uid');
		
		// Apply existing email logic
		$userEmail = $user->get("login");
		if (!validateEmail($userEmail) && $user->get("email") !== "") {
			$userEmail = $user->get("email");
		}
		$user->set("user_email", $userEmail);
		
		return $user;
	}
	
	/**
	 * Execute route safely - replaces existing route handling
	 */
	private function executeRoute(Route $route, CBObject $user): Response
	{
		switch ($route->getType()) {
			case 'script':
				return $this->executeScriptRoute($route);
			case 'agent':
				return $this->executeAgentRoute($route);
			case 'tool':
				return $this->executeToolRoute($route);
			case 'page':
				return $this->executePageRoute($route, $user);
			default:
				throw new \InvalidArgumentException('Unknown route type: ' . $route->getType());
		}
	}
	
	/**
	 * Execute script route - replaces file includes with security
	 */
	private function executeScriptRoute(Route $route): Response
	{
		$scriptPath = $this->security->validateScriptPath($route->getScriptPath());
		
		if (!file_exists($scriptPath)) {
			throw new \RuntimeException("Script not found: {$scriptPath}");
		}
		
		// Safe script execution with output buffering
		ob_start();
		
		// Set safe globals
		$GLOBALS['strModule'] = $route->getModule();
		$GLOBALS['BASEURL'] = BASEURL;
		$GLOBALS['BASEDIR'] = BASEDIR;
		
		include $scriptPath;
		$content = ob_get_clean();
		
		// Apply template if needed
		if ($route->needsTemplate()) {
			$content = $this->templateEngine->wrapContent($content, $route->getTemplateType());
		}
		
		return new Response($content);
	}
	
	/**
	 * Execute page route - replaces complex page rendering logic
	 */
	private function executePageRoute(Route $route, CBObject $user): Response
	{
		// Load structure with caching (replaces existing structure loading)
		$structure = $this->loadStructure($route->getStructureId());
        CBLog("executePageRoute");
        CBLog($route);
        CBLog($structure);
		
		// Check structure permissions (replaces existing permission check)
		if (!$this->checkStructureAccess($structure, $user)) {
			throw new UnauthorizedException('Access denied to structure');
		}
		
		// Check if structure is active
		if ($structure->get('active') !== '1') {
			throw new \RuntimeException('Structure not active');
		}
		
		// Load content efficiently (replaces N+1 query problem)
		$contentItems = $this->loadContentForStructure($structure, $user);
		
		// Build template data
		$templateData = [
			'structure' => $structure,
			'content' => $contentItems,
			'user' => $user,
			'baseUrl' => BASEURL,
			'basedir' => BASEDIR,
			'module' => $route->getModule(),
			'random' => time()
		];
		
		// Render using safe template system (NO eval!)
		$content = $this->templateEngine->renderStructure($structure, $templateData);
		
		return new Response($content);
	}
	
	/**
	 * Load structure with caching
	 */
	private function loadStructure(int $structureId): CBObject
	{
		return $this->container->get('cache')->remember(
			"structure_{$structureId}",
			3600,
			fn() => CBObject::make($structureId, 'capps_structure', 'structure_id')
		);
	}
	
	/**
	 * Load content efficiently - solves N+1 problem from original core.php
	 */
	private function loadContentForStructure(CBObject $structure, CBObject $user): array
	{
		$cacheKey = "content_structure_{$structure->get('structure_id')}";
		
		return $this->container->get('cache')->remember(
			$cacheKey,
			1800,
			function() use ($structure, $user) {
				$content = CBObject::make(null, 'capps_content', 'content_id');
				
				$conditions = [
					'language_id' => '1',
					'structure_id' => $structure->get('structure_id'),
					'active' => '1'
				];
				
				$allContent = $content->findAll($conditions, ['order' => 'position']);
				
				// Filter by user permissions
				$filteredContent = [];
				foreach ($allContent as $contentData) {
					$contentObj = CBObject::make($contentData['content_id'], 'capps_content', 'content_id');
					
					if ($this->checkContentAccess($contentObj, $user)) {
						$filteredContent[] = $contentObj;
					}
				}
				
				return $filteredContent;
			}
		);
	}
	
	/**
	 * Check structure access - replaces existing permission logic
	 */
	private function checkStructureAccess(CBObject $structure, CBObject $user): bool
	{
		$structureGroups = $structure->get('addressgroups');
		if (empty($structureGroups)) {
			return true; // No restrictions
		}
		
		$userGroups = $user->get('addressgroups');
		return checkIntersection($userGroups, $structureGroups);
	}
	
	/**
	 * Check content access
	 */
	private function checkContentAccess(CBObject $content, CBObject $user): bool
	{
		$contentGroups = $content->get('addressgroups');
		if (empty($contentGroups)) {
			return true; // No restrictions
		}
		
		$userGroups = $user->get('addressgroups');
		return checkIntersection($userGroups, $contentGroups);
	}
	
	/**
	 * Safe response rendering - replaces eval() calls
	 */
	private function renderResponse(Response $response): void
	{
		// Set headers
		foreach ($response->getHeaders() as $header) {
			header($header);
		}
		
		// Output content (NO eval!)
		echo $response->getContent();
	}
	
	/**
	 * Proper error handling
	 */
	private function handleError(\Exception $e): void
	{
		$logger = $this->container->get('logger');
		$logger->error('Core error: ' . $e->getMessage(), [
			'exception' => $e,
			'request' => $_REQUEST,
			'trace' => $e->getTraceAsString()
		]);
		
		if ($this->isProduction()) {
			$this->show500Page();
		} else {
			$this->showDebugError($e);
		}
	}
	
	private function handleUnauthorized(Route $route): void
	{
		// Redirect logic from original core.php
		$redirectUrl = BASEURL;
		
		if ($route->isAdminRoute()) {
			$redirectUrl .= 'admin/';
		} elseif ($route->isConsoleRoute()) {
			$redirectUrl .= 'console/';
		}
		
		header("Location: {$redirectUrl}");
		exit;
	}
	
	private function isProduction(): bool
	{
		return !defined('DEBUG_MODE') || !DEBUG_MODE;
	}
	
	private function show500Page(): void
	{
		http_response_code(500);
		echo 'Internal Server Error';
	}
	
	private function showDebugError(\Exception $e): void
	{
		http_response_code(500);
		echo '<h1>Debug Error</h1>';
		echo '<p><strong>Message:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
		echo '<p><strong>File:</strong> ' . $e->getFile() . ' (Line: ' . $e->getLine() . ')</p>';
		echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
	}
}

/**
 * Simple Service Container
 */
class ServiceContainer
{
	private array $services = [];
	private array $instances = [];
	
	public function register(string $name, object $service): void
	{
		$this->services[$name] = $service;
	}
	
	public function get(string $name): object
	{
		if (!isset($this->instances[$name])) {
			if (!isset($this->services[$name])) {
				throw new \InvalidArgumentException("Service not found: {$name}");
			}
			$this->instances[$name] = $this->services[$name];
		}
		
		return $this->instances[$name];
	}
}

/**
 * Route Object
 */
class Route
{
	private string $type;
	private string $module;
	private string $option;
	private ?int $structureId = null;
	private ?string $scriptPath = null;
	
	public function __construct(string $type, string $module, string $option = '')
	{
		$this->type = $type;
		$this->module = $module;
		$this->option = $option;
	}
	
	public function getType(): string { return $this->type; }
	public function getModule(): string { return $this->module; }
	public function getOption(): string { return $this->option; }
	public function getStructureId(): ?int { return $this->structureId; }
	public function getScriptPath(): ?string { return $this->scriptPath; }
	
	public function setStructureId(int $id): self { $this->structureId = $id; return $this; }
	public function setScriptPath(string $path): self { $this->scriptPath = $path; return $this; }
	
	public function isAdminRoute(): bool { return str_starts_with($this->option, 'admin'); }
	public function isConsoleRoute(): bool { return str_starts_with($this->option, 'console'); }
	public function needsTemplate(): bool { return in_array($this->option, ['main', 'admin']); }
	public function getTemplateType(): string { return $this->isAdminRoute() ? 'admin' : 'main'; }
}

/**
 * Response Object
 */
class Response
{
	private string $content;
	private array $headers = [];
	private int $statusCode = 200;
	
	public function __construct(string $content, int $statusCode = 200)
	{
		$this->content = $content;
		$this->statusCode = $statusCode;
	}
	
	public function getContent(): string { return $this->content; }
	public function getHeaders(): array { return $this->headers; }
	public function addHeader(string $header): self { $this->headers[] = $header; return $this; }
}

/**
 * Exception Classes
 */
class UnauthorizedException extends \Exception {}