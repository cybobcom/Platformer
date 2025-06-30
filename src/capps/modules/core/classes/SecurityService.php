<?php

declare(strict_types=1);

namespace Capps\Modules\Core\Classes;

use Capps\Modules\Database\Classes\CBObject;

/**
 * Security Service - replaces manual security checks
 * 
 * File: capps/modules/core/classes/SecurityService.php
 */
class SecurityService
{
	/**
	 * Validate request - replaces direct $_REQUEST usage
	 */
	public function validateRequest(): array
	{
		$request = $_REQUEST;

		// Basic input sanitization
		array_walk_recursive($request, function(&$value) {
			if (is_string($value)) {
				$value = trim($value);
				// Remove null bytes
				$value = str_replace("\0", '', $value);
			}
		});
		
		return $request;
	}
	
	/**
	 * Check if user has access to route
	 */
	public function hasAccess(Route $route, CBObject $user): bool
	{
		// For page routes, permission check is done later in structure loading
		if ($route->getType() === 'page') {
			return true;
		}
		
		// For script routes, basic access control
		if ($route->getType() === 'script') {
			return $this->checkScriptAccess($route, $user);
		}
		
		return true;
	}
	
	/**
	 * Check script access permissions
	 */
	private function checkScriptAccess(Route $route, CBObject $user): bool
	{
		// Admin routes require special permissions
		if ($route->isAdminRoute()) {
			return $this->isAdminUser($user);
		}
		
		return true;
	}
	
	/**
	 * Check if user is admin
	 */
	private function isAdminUser(CBObject $user): bool
	{
		$userGroups = $user->get('addressgroups');
		// Check if user has admin group (simplified)
		return str_contains($userGroups, 'admin') || str_contains($userGroups, 'administrator');
	}
	
	/**
	 * Validate script path to prevent directory traversal
	 */
	public function validateScriptPath(string $path): string
	{
		$realPath = realpath($path);
		
		if ($realPath === false) {
			throw new \RuntimeException("Invalid script path: {$path}");
		}
		
		// Ensure path is within allowed directories
		$allowedPaths = [
			realpath(CAPPS),
			realpath(SOURCEDIR)
		];
		
		foreach ($allowedPaths as $allowedPath) {
			if (str_starts_with($realPath, $allowedPath)) {
				return $realPath;
			}
		}
		
		throw new \RuntimeException("Script path not allowed: {$path}");
	}
}