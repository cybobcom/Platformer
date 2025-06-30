<?php

declare(strict_types=1);

namespace Capps\Modules\Core\Classes;

use Capps\Modules\Database\Classes\CBObject;

/**
 * Router Service - replaces manual route parsing from core.php
 * 
 * File: capps/modules/core/classes/RouterService.php
 */
class RouterService
{
	private CacheService $cache;
	private array $routeCache = [];
	
	public function __construct()
	{
		// Cache wird später injected
	}
	
	public function setCache(CacheService $cache): void
	{
		$this->cache = $cache;
	}
	
	/**
	 * Parse request - replaces manual $_REQUEST parsing
	 */
	public function parseRequest(array $request): Route
	{
		$cbRoute = $request['CBroute'] ?? '';
		$requestUri = $_SERVER["REQUEST_URI"] ?? '';
		
		// Handle special admin/console routes
		if ($requestUri === "/admin/") {
			return new Route('page', 'admin', 'admin');
		}
		if ($requestUri === "/console/") {
			return new Route('page', 'console', 'console');
		}
		
		// Parse route segments
		$segments = $this->parseRouteSegments($cbRoute);
		
		// Determine route type and build route object
		return $this->buildRoute($segments, $request);
	}
	
	/**
	 * Parse route segments - replaces explode logic
	 */
	private function parseRouteSegments(string $cbRoute): array
	{
		if (empty($cbRoute)) {
			return [];
		}
		
		$segments = explode("/", trim($cbRoute, '/'));
		return array_filter($segments, fn($segment) => $segment !== '');
	}
	
	/**
	 * Build route object - replaces complex if/else logic
	 */
	private function buildRoute(array $segments, array $request): Route
	{
		if (empty($segments)) {
			return $this->buildPageRoute($request);
		}
		
		$type = strtolower($segments[0]);
		$module = $segments[1] ?? 'home';
		$option = $segments[2] ?? '';
		
		switch ($type) {
			case 'interface':
			case 'control':
			case 'view':
				return $this->buildScriptRoute($type, $module, $option, $segments);
				
			case 'custom':
				return $this->buildCustomRoute($module, $option, $segments);
				
			case 'agent':
				return $this->buildAgentRoute($segments);
				
			case 'tool':
				return $this->buildToolRoute($segments);
				
			default:
				return $this->buildPageRoute($request, $segments);
		}
	}
	
	/**
	 * Build script route - replaces file existence checking
	 */
	private function buildScriptRoute(string $type, string $module, string $option, array $segments): Route
	{
		$route = new Route('script', $module, $option);
		
		// Build script paths (from original core.php logic)
		$option = str_replace("_", "/", $option);
		
		$scriptPath = CAPPS . "modules/{$module}/{$type}/{$option}.php";
		$customPath = SOURCEDIR . "{$type}/modules/{$option}/{$module}/" . ($segments[3] ?? '') . ".php";
		
		// Check which script exists
		if (file_exists($customPath)) {
			$route->setScriptPath($customPath);
		} elseif (file_exists($scriptPath)) {
			$route->setScriptPath($scriptPath);
		} else {
			throw new \RuntimeException("Script not found: {$scriptPath}");
		}
		
		return $route;
	}
	
	/**
	 * Build page route - replaces structure loading logic
	 */
	private function buildPageRoute(array $request, array $segments = []): Route
	{
		$route = new Route('page', 'home', 'page');
		
		// Try to find route from database (replaces original logic)
		if (!empty($segments)) {
			$routePath = implode('/', $segments);
			$routeObj = $this->findRouteInDatabase($routePath);
			
			if ($routeObj) {
				$route->setStructureId((int)$routeObj->get('structure_id'));
				return $route;
			}
		}
		
		// Fallback to default structure (from original core.php)
		$defaultStructureId = $this->getDefaultStructureId();
		$route->setStructureId($defaultStructureId);
		
		return $route;
	}
	
	/**
	 * Find route in database - replaces $objRoute->getObjectFromRoute
	 */
	private function findRouteInDatabase(string $routePath): ?CBObject
	{
		try {
			$route = CBObject::make(null, 'capps_route', 'route_id');
			
			$results = $route->findAll([
				'route' => $routePath . '/'
			], ['limit' => 1]);
			
			if (!empty($results)) {
				return CBObject::make($results[0]['route_id'], 'capps_route', 'route_id');
			}
			
			return null;
		} catch (\Exception $e) {
			return null;
		}
	}
	
	/**
	 * Get default structure ID - replaces current($coreArrSortedStructure)
	 */
	private function getDefaultStructureId(): int
	{
		// Load sorted structure (simplified version of original logic)
		$structure = CBObject::make(null, 'capps_structure', 'structure_id');
		
		$results = $structure->findAll(
			['active' => '1'],
			['order' => 'position', 'limit' => 1]
		);
		
		return !empty($results) ? (int)$results[0]['structure_id'] : 1;
	}
	
	private function buildAgentRoute(array $segments): Route
	{
		// Agent routing logic from original core.php
		$route = new Route('agent', 'agent', 'execute');
		// Additional agent-specific logic would go here
		return $route;
	}
	
	private function buildToolRoute(array $segments): Route
	{
		// Tool routing logic from original core.php
		$route = new Route('tool', 'tool', 'execute');
		// Additional tool-specific logic would go here
		return $route;
	}
	
	private function buildCustomRoute(string $module, string $option, array $segments): Route
	{
		// Custom routing logic
		$route = new Route('custom', $module, $option);
		return $route;
	}
}