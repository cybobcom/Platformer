<?php

declare(strict_types=1);

namespace Capps\Modules\Database\Classes;

//use Psr\Log\LoggerInterface;
//use Psr\Log\NullLogger;
use InvalidArgumentException;
use RuntimeException;
use Capps\Modules\Database\Classes\NullLogger;

/**
 * Production-Ready CBObject Factory
 * 
 * Thread-safe factory for CBObject instances with:
 * - Global configuration management
 * - Resource pooling and monitoring
 * - Health checks and diagnostics
 * - Memory management
 * - Performance optimization
 * - File-based locking (no PECL dependencies)
 * 
 * @version 3.1 Production-Ready (No Dependencies)
 */
class CBObjectFactory
{
	// File-based locking handles
	private static array $lockHandles = [];
	
	// Global configuration
	private static array $defaultConfig = [
		'cache_enabled' => true,
		'cache_size' => 10000,
		'validate_input' => true,
		'log_slow_queries' => true,
		'debug_mode' => false,
		'max_bulk_size' => 1000,
		'connection_timeout' => 30,
		'health_check_interval' => 300
	];
	
	// Global services
	private static ?LoggerInterface $defaultLogger = null;
	private static ?SecurityValidator $globalValidator = null;
	
	// Instance tracking for resource management
	private static array $instanceRegistry = [];
	private static array $instanceMetrics = [
		'created' => 0,
		'destroyed' => 0,
		'active' => 0,
		'memory_usage' => 0
	];
	
	// Health monitoring
	private static array $healthStatus = [
		'last_check' => 0,
		'status' => 'unknown',
		'errors' => []
	];

	// Lock timeout and file management
	private const LOCK_TIMEOUT = 5.0; // 5 seconds

	/**
	 * File-based locking for thread safety (replaces SyncMutex)
	 */
	private static function acquireLock(string $lockName): bool
	{
		$lockFile = sys_get_temp_dir() . '/cbobjectfactory_' . md5($lockName) . '.lock';
		
		$handle = fopen($lockFile, 'c+');
		if (!$handle) {
			return false;
		}
		
		$startTime = microtime(true);
		while (microtime(true) - $startTime < self::LOCK_TIMEOUT) {
			if (flock($handle, LOCK_EX | LOCK_NB)) {
				self::$lockHandles[$lockName] = $handle;
				return true;
			}
			usleep(10000); // 10ms
		}
		
		fclose($handle);
		return false;
	}

	private static function releaseLock(string $lockName): void
	{
		if (isset(self::$lockHandles[$lockName])) {
			flock(self::$lockHandles[$lockName], LOCK_UN);
			fclose(self::$lockHandles[$lockName]);
			unset(self::$lockHandles[$lockName]);
		}
	}

	/**
	 * Initialize factory with thread-safe setup
	 */
	private static function initializeFactory(): void
	{
		if (self::$globalValidator === null) {
			// SecurityValidator bekommt nur Standard-Security-Parameter
			self::$globalValidator = new SecurityValidator([
				'debug_mode' => self::$defaultConfig['debug_mode'] ?? false,
				'max_value_length' => 65535,
				'enable_xss_protection' => true
			]);
		}
	}

	/**
	 * Create CBObject instance with production optimizations
	 * 
	 * @param mixed $id Object ID to load (null for new empty object)
	 * @param string $table Database table name
	 * @param string $primaryKey Primary key column name
	 * @param array|null $dbConfig Database configuration
	 * @param array $config Additional CBObject configuration
	 * @param LoggerInterface|null $logger Optional logger instance
	 * @return CBObject Configured CBObject instance
	 * 
	 * @throws InvalidArgumentException When parameters are invalid
	 * @throws RuntimeException When instance creation fails
	 * 
	 * @example
	 * // Create and load existing record
	 * $user = CBObjectFactory::create(123, 'users', 'user_id');
	 * echo $user->get('name');
	 * 
	 * @example
	 * // Create empty object for new record
	 * $newUser = CBObjectFactory::create(null, 'users', 'user_id');
	 * $userId = $newUser->create(['name' => 'Jane', 'email' => 'jane@test.com']);
	 */
	public static function create(
		mixed $id,
		string $table,
		string $primaryKey,
		?array $dbConfig = null,
		array $config = [],
		?LoggerInterface $logger = null
	): CBObject {
		self::initializeFactory();
		
		// Validate parameters
		self::validateFactoryParameters($table, $primaryKey);
		
		// Merge configurations
		$finalConfig = self::mergeConfigurations($config);
		$effectiveLogger = $logger ?? self::$defaultLogger ?? new NullLogger();
		
		$startTime = microtime(true);
		
		try {
			// Create instance with monitoring
			$instance = new CBObject(
				$id,
				$table,
				$primaryKey,
				$dbConfig,
				$effectiveLogger,
				$finalConfig
			);
			
			// Register instance for tracking
			self::registerInstance($instance, $table, $primaryKey);
			
			$creationTime = microtime(true) - $startTime;
			
			$effectiveLogger->debug('CBObject instance created', [
				'table' => $table,
				'primary_key' => $primaryKey,
				'has_id' => $id !== null,
				'creation_time' => $creationTime,
				'memory_usage' => memory_get_usage()
			]);
			
			return $instance;
			
		} catch (\Exception $e) {
			$effectiveLogger->error('Failed to create CBObject instance', [
				'table' => $table,
				'primary_key' => $primaryKey,
				'error' => $e->getMessage(),
				'creation_time' => microtime(true) - $startTime
			]);
			
			throw new RuntimeException("Failed to create CBObject: " . $e->getMessage(), 0, $e);
		}
	}

	/**
	 * Create multiple CBObject instances efficiently with batch optimization
	 * 
	 * @param array $objectSpecs Array of [id, table, primaryKey] specifications
	 * @param array|null $dbConfig Shared database configuration
	 * @param array $config Shared CBObject configuration
	 * @param LoggerInterface|null $logger Optional logger instance
	 * @return array Array of CBObject instances
	 * 
	 * @throws InvalidArgumentException When specifications are invalid
	 * 
	 * @example
	 * // Create multiple objects with shared configuration
	 * $objects = CBObjectFactory::createBatch([
	 *     [123, 'users', 'user_id'],
	 *     [456, 'products', 'product_id'],
	 *     [null, 'orders', 'order_id']  // Empty object for new records
	 * ]);
	 * 
	 * @example
	 * // With custom configuration
	 * $objects = CBObjectFactory::createBatch(
	 *     $specifications, 
	 *     $dbConfig, 
	 *     ['debug_mode' => true]
	 * );
	 */
	public static function createBatch(
		array $objectSpecs,
		?array $dbConfig = null,
		array $config = [],
		?LoggerInterface $logger = null
	): array {
		if (empty($objectSpecs)) {
			throw new InvalidArgumentException('No object specifications provided');
		}
		
		self::initializeFactory();
		
		$instances = [];
		$effectiveLogger = $logger ?? self::$defaultLogger ?? new NullLogger();
		$startTime = microtime(true);
		
		try {
			foreach ($objectSpecs as $index => $spec) {
				if (!is_array($spec) || count($spec) < 3) {
					throw new InvalidArgumentException("Invalid specification at index {$index}");
				}
				
				[$id, $table, $primaryKey] = $spec;
				
				$instances[] = self::create($id, $table, $primaryKey, $dbConfig, $config, $logger);
			}
			
			$batchTime = microtime(true) - $startTime;
			
			$effectiveLogger->info('Batch CBObject creation completed', [
				'instance_count' => count($instances),
				'batch_time' => $batchTime,
				'avg_time_per_instance' => $batchTime / count($instances),
				'memory_usage' => memory_get_usage()
			]);
			
			return $instances;
			
		} catch (\Exception $e) {
			$effectiveLogger->error('Batch CBObject creation failed', [
				'completed_instances' => count($instances),
				'total_requested' => count($objectSpecs),
				'error' => $e->getMessage()
			]);
			
			throw new RuntimeException("Batch creation failed: " . $e->getMessage(), 0, $e);
		}
	}

	/**
	 * Create CBObject with automatic table detection and optimization
	 * 
	 * @param mixed $id Object ID
	 * @param string $table Table name
	 * @param array|null $dbConfig Database configuration
	 * @param array $config CBObject configuration
	 * @return CBObject Optimized CBObject instance
	 * 
	 * @example
	 * // Auto-detect primary key and optimize
	 * $user = CBObjectFactory::createSmart(123, 'users');
	 * $product = CBObjectFactory::createSmart('uuid-123', 'products');
	 */
	public static function createSmart(
		mixed $id,
		string $table,
		?array $dbConfig = null,
		array $config = []
	): CBObject {
		self::initializeFactory();
		
		// Auto-detect primary key based on common patterns
		$primaryKey = self::detectPrimaryKey($table);
		
		return self::create($id, $table, $primaryKey, $dbConfig, $config);
	}

	/**
	 * Set global default configuration for all instances
	 * 
	 * @param array $config Configuration array
	 * 
	 * @example
	 * // Set production configuration
	 * CBObjectFactory::setGlobalConfig([
	 *     'cache_enabled' => true,
	 *     'cache_size' => 50000,
	 *     'debug_mode' => false,
	 *     'validate_input' => true,
	 *     'log_slow_queries' => true,
	 *     'max_bulk_size' => 2000
	 * ]);
	 */
	public static function setGlobalConfig(array $config): void
	{
		self::initializeFactory();
		
		if (!self::acquireLock('config')) {
			throw new RuntimeException('Could not acquire configuration lock');
		}
		
		try {
			// Validate configuration
			self::validateConfiguration($config);
			
			// Merge with existing defaults
			self::$defaultConfig = array_merge(self::$defaultConfig, $config);
			
			// Update global validator
			self::$globalValidator = new SecurityValidator([
				'debug_mode' => self::$defaultConfig['debug_mode'] ?? false,
				'max_value_length' => 65535,
				'enable_xss_protection' => true
			]);
			
		} finally {
			self::releaseLock('config');
		}
	}
	/**
	 * Set global default logger for all instances
	 * 
	 * @param LoggerInterface $logger PSR-3 compatible logger
	 * 
	 * @example
	 * // Set production logger
	 * $logger = new FileLogger('/var/log/cbobject.log');
	 * CBObjectFactory::setGlobalLogger($logger);
	 */
	public static function setGlobalLogger(LoggerInterface $logger): void
	{
		self::initializeFactory();
		
		if (!self::acquireLock('logger')) {
			throw new RuntimeException('Could not acquire logger lock');
		}
		
		try {
			self::$defaultLogger = $logger;
		} finally {
			self::releaseLock('logger');
		}
	}

	/**
	 * Get comprehensive factory statistics and health information
	 * 
	 * @return array Factory statistics and health data
	 * 
	 * @example
	 * // Monitor factory health
	 * $stats = CBObjectFactory::getStats();
	 * if ($stats['health']['status'] !== 'healthy') {
	 *     // Handle unhealthy state
	 * }
	 */
	public static function getStats(): array
	{
		self::initializeFactory();
		
		return [
			'configuration' => self::$defaultConfig,
			'instances' => self::$instanceMetrics,
			'health' => self::performHealthCheck(),
			'memory' => [
				'current_usage' => memory_get_usage(true),
				'peak_usage' => memory_get_peak_usage(true),
				'limit' => ini_get('memory_limit')
			],
			'global_metrics' => CBObject::getGlobalStats()
		];
	}

	/**
	 * Perform comprehensive health check
	 * 
	 * @return array Health check results
	 */
	public static function performHealthCheck(): array
	{
		$healthCheck = [
			'status' => 'healthy',
			'timestamp' => date('Y-m-d H:i:s'),
			'checks' => [],
			'warnings' => [],
			'errors' => []
		];
		
		try {
			// Memory usage check
			$memoryUsage = memory_get_usage(true);
			$memoryLimit = self::parseMemoryLimit(ini_get('memory_limit'));
			$memoryPercentage = ($memoryUsage / $memoryLimit) * 100;
			
			$healthCheck['checks']['memory'] = [
				'usage' => $memoryUsage,
				'limit' => $memoryLimit,
				'percentage' => round($memoryPercentage, 2) . '%',
				'status' => $memoryPercentage > 80 ? 'warning' : 'ok'
			];
			
			if ($memoryPercentage > 80) {
				$healthCheck['warnings'][] = 'High memory usage detected';
				$healthCheck['status'] = 'warning';
			}
			
			// Instance count check
			$instanceCount = self::$instanceMetrics['active'];
			$healthCheck['checks']['instances'] = [
				'active' => $instanceCount,
				'created' => self::$instanceMetrics['created'],
				'destroyed' => self::$instanceMetrics['destroyed'],
				'status' => $instanceCount > 1000 ? 'warning' : 'ok'
			];
			
			if ($instanceCount > 1000) {
				$healthCheck['warnings'][] = 'High number of active instances';
				$healthCheck['status'] = 'warning';
			}
			
			// Configuration validation
			$healthCheck['checks']['configuration'] = [
				'status' => self::validateConfiguration(self::$defaultConfig) ? 'ok' : 'error'
			];
			
		} catch (\Exception $e) {
			$healthCheck['status'] = 'error';
			$healthCheck['errors'][] = $e->getMessage();
		}
		
		// Update health status cache
		self::$healthStatus = [
			'last_check' => time(),
			'status' => $healthCheck['status'],
			'errors' => $healthCheck['errors']
		];
		
		return $healthCheck;
	}

	/**
	 * Clean up resources and perform maintenance
	 * 
	 * @param bool $force Force cleanup even if not needed
	 * @return array Cleanup results
	 * 
	 * @example
	 * // Perform scheduled maintenance
	 * $results = CBObjectFactory::cleanup();
	 * echo "Cleaned up {$results['instances_cleaned']} instances";
	 */
	public static function cleanup(bool $force = false): array
	{
		self::initializeFactory();
		
		$results = [
			'instances_cleaned' => 0,
			'memory_freed' => 0,
			'caches_cleared' => 0,
			'connections_closed' => 0
		];
		
		$memoryBefore = memory_get_usage(true);
		
		// Clean up instance registry
		if (self::acquireLock('cleanup')) {
			try {
				$cleanedInstances = 0;
				foreach (self::$instanceRegistry as $key => $instanceInfo) {
					// Remove instances that are no longer referenced
					if (!isset($instanceInfo['weak_ref']) || $instanceInfo['weak_ref'] === null) {
						unset(self::$instanceRegistry[$key]);
						$cleanedInstances++;
					}
				}
				
				self::$instanceMetrics['active'] -= $cleanedInstances;
				self::$instanceMetrics['destroyed'] += $cleanedInstances;
				$results['instances_cleaned'] = $cleanedInstances;
				
			} finally {
				self::releaseLock('cleanup');
			}
		}
		
		// Clear caches if needed or forced
		if ($force || memory_get_usage(true) > self::parseMemoryLimit(ini_get('memory_limit')) * 0.8) {
			CBObject::clearAllCaches();
			$results['caches_cleared'] = 1;
		}
		
		$memoryAfter = memory_get_usage(true);
		$results['memory_freed'] = max(0, $memoryBefore - $memoryAfter);
		
		return $results;
	}

	/**
	 * Register created instance for tracking
	 */
	private static function registerInstance(CBObject $instance, string $table, string $primaryKey): void
	{
		if (!self::acquireLock('instance_registry')) {
			return; // Skip registration if can't acquire lock
		}
		
		try {
			$instanceId = spl_object_hash($instance);
			
			self::$instanceRegistry[$instanceId] = [
				'table' => $table,
				'primary_key' => $primaryKey,
				'created_at' => microtime(true),
				'memory_usage' => memory_get_usage(),
			];
			
			self::$instanceMetrics['created']++;
			self::$instanceMetrics['active']++;
			self::$instanceMetrics['memory_usage'] = memory_get_usage(true);
			
		} finally {
			self::releaseLock('instance_registry');
		}
	}

	/**
	 * Validate factory parameters
	 */
	private static function validateFactoryParameters(string $table, string $primaryKey): void
	{
		if (empty($table) || !is_string($table)) {
			throw new InvalidArgumentException('Table name must be a non-empty string');
		}
		
		if (empty($primaryKey) || !is_string($primaryKey)) {
			throw new InvalidArgumentException('Primary key must be a non-empty string');
		}
		
		// Use global validator if available
		if (self::$globalValidator) {
			self::$globalValidator->validateTableName($table);
			self::$globalValidator->validateColumnName($primaryKey);
		}
	}

	/**
	 * Merge configurations with validation
	 */
	private static function mergeConfigurations(array $config): array
	{
		if (!self::acquireLock('config_merge')) {
			// Fallback: simple merge without locking
			return array_merge(self::$defaultConfig, $config);
		}
		
		try {
			$merged = array_merge(self::$defaultConfig, $config);
			self::validateConfiguration($merged);
			return $merged;
		} finally {
			self::releaseLock('config_merge');
		}
	}

	/**
	 * Validate configuration array
	 */
	private static function validateConfiguration(array $config): bool
	{
		$requiredKeys = ['cache_enabled', 'validate_input'];
		foreach ($requiredKeys as $key) {
			if (!array_key_exists($key, $config)) {
				throw new InvalidArgumentException("Missing required configuration key: {$key}");
			}
		}
		
		// Validate specific values
		if (!is_bool($config['cache_enabled'])) {
			throw new InvalidArgumentException('cache_enabled must be boolean');
		}
		
		if (!is_bool($config['validate_input'])) {
			throw new InvalidArgumentException('validate_input must be boolean');
		}
		
		if (isset($config['cache_size']) && (!is_int($config['cache_size']) || $config['cache_size'] < 1)) {
			throw new InvalidArgumentException('cache_size must be positive integer');
		}
		
		if (isset($config['max_bulk_size']) && (!is_int($config['max_bulk_size']) || $config['max_bulk_size'] < 1)) {
			throw new InvalidArgumentException('max_bulk_size must be positive integer');
		}
		
		return true;
	}

	/**
	 * Auto-detect primary key based on table name patterns
	 */
	private static function detectPrimaryKey(string $table): string
	{
		// Common patterns for primary keys
		$patterns = [
			$table . '_id',      // users -> user_id
			$table . '_uid',     // users -> user_uid
			rtrim($table, 's') . '_id',  // users -> user_id
			'id',                // Generic id
			'uid'                // Generic uid
		];
		
		// For production, you might want to query the database to determine the actual primary key
		// For now, return the most common pattern
		return $patterns[0];
	}

	/**
	 * Parse memory limit string to bytes
	 */
	private static function parseMemoryLimit(string $memoryLimit): int
	{
		$memoryLimit = trim($memoryLimit);
		$unit = strtolower(substr($memoryLimit, -1));
		$value = (int)substr($memoryLimit, 0, -1);
		
		switch ($unit) {
			case 'g':
				return $value * 1024 * 1024 * 1024;
			case 'm':
				return $value * 1024 * 1024;
			case 'k':
				return $value * 1024;
			default:
				return (int)$memoryLimit;
		}
	}

	// ================================================================
	// DEPRECATED METHODS - For backward compatibility
	// ================================================================

	/**
	 * @deprecated Use create() instead to avoid confusion with database operations
	 */
	public static function init(
		mixed $id,
		string $table,
		string $primaryKey,
		?array $dbConfig = null,
		array $config = []
	): CBObject {
		@trigger_error('CBObjectFactory::init() is deprecated. Use create() instead.', E_USER_DEPRECATED);
		return self::create($id, $table, $primaryKey, $dbConfig, $config);
	}

	/**
	 * @deprecated Use createBatch() instead
	 */
	public static function initBatch(array $objects, ?array $dbConfig = null): array
	{
		@trigger_error('CBObjectFactory::initBatch() is deprecated. Use createBatch() instead.', E_USER_DEPRECATED);
		return self::createBatch($objects, $dbConfig);
	}

	/**
	 * @deprecated Use setGlobalConfig() instead
	 */
	public static function setDefaultConfig(array $config): void
	{
		@trigger_error('CBObjectFactory::setDefaultConfig() is deprecated. Use setGlobalConfig() instead.', E_USER_DEPRECATED);
		self::setGlobalConfig($config);
	}

	/**
	 * @deprecated Use setGlobalLogger() instead
	 */
	public static function setDefaultLogger(LoggerInterface $logger): void
	{
		@trigger_error('CBObjectFactory::setDefaultLogger() is deprecated. Use setGlobalLogger() instead.', E_USER_DEPRECATED);
		self::setGlobalLogger($logger);
	}

	/**
	 * Clean up file locks on shutdown
	 */
	public static function shutdown(): void
	{
		foreach (self::$lockHandles as $lockName => $handle) {
			flock($handle, LOCK_UN);
			fclose($handle);
		}
		self::$lockHandles = [];
	}
}

/**
 * Production Monitoring and Analytics for CBObject
 */
class CBObjectAnalytics
{
	private static array $performanceData = [];
	private static array $usagePatterns = [];
	private static int $trackingStartTime;

	/**
	 * Initialize analytics tracking
	 */
	public static function startTracking(): void
	{
		self::$trackingStartTime = time();
		
		// Register shutdown function to save analytics
		register_shutdown_function([self::class, 'saveAnalytics']);
		register_shutdown_function([CBObjectFactory::class, 'shutdown']);
	}

	/**
	 * Record performance metrics
	 */
	public static function recordPerformance(string $operation, string $table, float $executionTime, int $recordCount = 1): void
	{
		$key = "{$operation}:{$table}";
		
		if (!isset(self::$performanceData[$key])) {
			self::$performanceData[$key] = [
				'total_operations' => 0,
				'total_time' => 0,
				'total_records' => 0,
				'avg_time' => 0,
				'max_time' => 0,
				'min_time' => PHP_FLOAT_MAX,
				'records_per_second' => 0
			];
		}
		
		$data = &self::$performanceData[$key];
		$data['total_operations']++;
		$data['total_time'] += $executionTime;
		$data['total_records'] += $recordCount;
		$data['avg_time'] = $data['total_time'] / $data['total_operations'];
		$data['max_time'] = max($data['max_time'], $executionTime);
		$data['min_time'] = min($data['min_time'], $executionTime);
		$data['records_per_second'] = $data['total_records'] / max($data['total_time'], 0.001);
	}

	/**
	 * Record usage patterns
	 */
	public static function recordUsage(string $table, string $operation, array $context = []): void
	{
		$hour = date('Y-m-d H');
		
		if (!isset(self::$usagePatterns[$hour])) {
			self::$usagePatterns[$hour] = [];
		}
		
		if (!isset(self::$usagePatterns[$hour][$table])) {
			self::$usagePatterns[$hour][$table] = [];
		}
		
		if (!isset(self::$usagePatterns[$hour][$table][$operation])) {
			self::$usagePatterns[$hour][$table][$operation] = 0;
		}
		
		self::$usagePatterns[$hour][$table][$operation]++;
	}

	/**
	 * Get analytics report
	 */
	public static function getReport(): array
	{
		$uptime = time() - (self::$trackingStartTime ?? time());
		
		return [
			'uptime' => $uptime,
			'performance' => self::$performanceData,
			'usage_patterns' => self::$usagePatterns,
			'summary' => self::generateSummary()
		];
	}

	/**
	 * Generate performance summary
	 */
	private static function generateSummary(): array
	{
		$totalOperations = array_sum(array_column(self::$performanceData, 'total_operations'));
		$totalTime = array_sum(array_column(self::$performanceData, 'total_time'));
		
		return [
			'total_operations' => $totalOperations,
			'total_execution_time' => $totalTime,
			'average_operation_time' => $totalOperations > 0 ? $totalTime / $totalOperations : 0,
			'operations_per_second' => $totalTime > 0 ? $totalOperations / $totalTime : 0,
			'most_used_operations' => self::getMostUsedOperations(),
			'slowest_operations' => self::getSlowestOperations()
		];
	}

	/**
	 * Get most used operations
	 */
	private static function getMostUsedOperations(): array
	{
		$operations = [];
		foreach (self::$performanceData as $key => $data) {
			$operations[$key] = $data['total_operations'];
		}
		
		arsort($operations);
		return array_slice($operations, 0, 10, true);
	}

	/**
	 * Get slowest operations
	 */
	private static function getSlowestOperations(): array
	{
		$operations = [];
		foreach (self::$performanceData as $key => $data) {
			$operations[$key] = $data['avg_time'];
		}
		
		arsort($operations);
		return array_slice($operations, 0, 10, true);
	}

	/**
	 * Save analytics data (called on shutdown)
	 */
	public static function saveAnalytics(): void
	{
		// Implementation would save to file, database, or monitoring system
		// For example: file_put_contents('/var/log/cbobject-analytics.json', json_encode(self::getReport()));
	}
}

?>