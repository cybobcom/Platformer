<?php

declare(strict_types=1);

namespace Capps\Modules\Database\Classes;

/**
 * Performance Monitor for tracking operations
 */
class PerformanceMonitor
{
	private LoggerInterface $logger;
	private array $metrics = [];
	
	public function __construct(LoggerInterface $logger)
	{
		$this->logger = $logger;
	}
	
	public function recordOperation(string $operation, string $table, float $duration): void
	{
		$key = "{$operation}:{$table}";
		
		if (!isset($this->metrics[$key])) {
			$this->metrics[$key] = [
				'count' => 0,
				'total_time' => 0,
				'avg_time' => 0,
				'max_time' => 0,
				'min_time' => PHP_FLOAT_MAX
			];
		}
		
		$this->metrics[$key]['count']++;
		$this->metrics[$key]['total_time'] += $duration;
		$this->metrics[$key]['avg_time'] = $this->metrics[$key]['total_time'] / $this->metrics[$key]['count'];
		$this->metrics[$key]['max_time'] = max($this->metrics[$key]['max_time'], $duration);
		$this->metrics[$key]['min_time'] = min($this->metrics[$key]['min_time'], $duration);
		
		// Log slow operations
		if ($duration > 1.0) {
			$this->logger->warning('Slow operation detected', [
				'operation' => $operation,
				'table' => $table,
				'duration' => $duration
			]);
		}
	}
	
	public function recordCacheHit(string $table, float $duration): void
	{
		$this->recordOperation('cache_hit', $table, $duration);
	}
	
	public function recordDatabaseHit(string $table, float $duration): void
	{
		$this->recordOperation('db_hit', $table, $duration);
	}
	
	public function recordDatabaseMiss(string $table, float $duration): void
	{
		$this->recordOperation('db_miss', $table, $duration);
	}
	
	public function recordBulkOperation(string $operation, string $table, int $recordCount, float $duration): void
	{
		$this->recordOperation("bulk_{$operation}", $table, $duration);
		
		$this->logger->info('Bulk operation completed', [
			'operation' => $operation,
			'table' => $table,
			'record_count' => $recordCount,
			'duration' => $duration,
			'records_per_second' => $recordCount / max($duration, 0.001)
		]);
	}
	
	public function getMetrics(): array
	{
		return $this->metrics;
	}
}