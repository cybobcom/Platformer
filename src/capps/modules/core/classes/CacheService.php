<?php

declare(strict_types=1);

namespace Capps\Modules\Core\Classes;

/**
 * Simple Cache Service
 * 
 * File: capps/modules/core/classes/CacheService.php
 */
class CacheService
{
	private array $cache = [];
	
	public function get(string $key)
	{
		return $this->cache[$key] ?? null;
	}
	
	public function set(string $key, $value, int $ttl = 3600): void
	{
		$this->cache[$key] = [
			'value' => $value,
			'expires' => time() + $ttl
		];
	}
	
	public function remember(string $key, int $ttl, callable $callback)
	{
		$cached = $this->get($key);
		
		if ($cached !== null && $cached['expires'] > time()) {
			return $cached['value'];
		}
		
		$value = $callback();
		$this->set($key, $value, $ttl);
		
		return $value;
	}
}