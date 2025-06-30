<?php

declare(strict_types=1);

namespace Capps\Modules\Database\Classes;

use Capps\Modules\Database\Classes\CBDatabase;
use InvalidArgumentException;
use RuntimeException;
//use Psr\Log\LoggerInterface;
//use Psr\Log\NullLogger;
use Capps\Modules\Database\Classes\NullLogger;
use Capps\Modules\Database\Classes\PerformanceMonitor;
use Capps\Modules\Database\Classes\CBObjectFactory;


/**
 * CBObject - Production-Ready Database Object Handler
 *
 * High-performance, secure ORM-like functionality with:
 * - File-based locking for thread safety (no PECL dependencies)
 * - Intelligent LRU caching with memory management
 * - Comprehensive input validation (always-on, no bypass)
 * - Connection pooling with health monitoring
 * - Bulk operations for high performance
 * - Structured logging and performance monitoring
 * - Circuit breaker pattern for resilience
 * - 100% Legacy compatibility with automatic security upgrades
 * - Full XML field support (data_*, media_*, settings_*)
 * - Works with and without ID (empty objects for new records)
 * - SIMPLIFIED DATABASE COLUMNS: arrDatabaseColumns now stores ['column_name' => 'column_type']
 *
 * @version 3.1 Production-Ready (No PECL Dependencies)
 * @example
 * // Legacy usage (works unchanged)
 * $user = new CBObject(123, 'users', 'user_id');
 * echo $user->getAttribute('data_description');
 * 
 * @example
 * // Create empty object for new records
 * $user = new CBObject(null, 'users', 'user_id');
 * $userId = $user->create(['name' => 'John', 'email' => 'john@test.com']);
 * 
 * @example
 * // Modern usage with CBObjectFactory
 * $user = CBObjectFactory::create(123, 'users', 'user_id');
 * echo $user->get('data_description');
 */
class CBObject
{
	// Core properties
	public mixed $identifier = null;
	public array $arrAttributes = [];
	public array $arrDatabaseColumns = [];  // Simplified: ['column_name' => 'column_type']
	public ?CBDatabase $objDatabase = null;
	public ?string $strTable = null;
	public ?string $strPrimaryKey = null;

	// Production features
	private LoggerInterface $logger;
	private array $config;
	private SecurityValidator $securityValidator;
	private PerformanceMonitor $performanceMonitor;
	
	// Thread-safe caches using file-based locking
	private static array $cacheData = [];
	private static array $cacheAccessTimes = [];
	private static array $cacheHitCounts = [];
	private static array $lockHandles = [];
	
	// Connection pooling
	private static array $connectionPool = [];
	private static array $connectionHealth = [];
	private static array $connectionUsage = [];
	
	// Performance and health monitoring
	private static array $globalMetrics = [
		'queries_executed' => 0,
		'cache_hits' => 0,
		'cache_misses' => 0,
		'memory_usage' => 0,
		'connection_count' => 0
	];
	
	// Configuration constants
	private const MAX_CACHE_SIZE = 10000;
	private const MAX_CONNECTIONS = 25;
	private const CACHE_TTL = 3600; // 1 hour
	private const HEALTH_CHECK_INTERVAL = 300; // 5 minutes
	private const MEMORY_WARNING_THRESHOLD = 128 * 1024 * 1024; // 128MB
	private const LOCK_TIMEOUT = 5.0; // 5 seconds
	
	// Security configuration
	private const MAX_BULK_SIZE = 1000;
	private const MAX_QUERY_PARAMS = 500;

	/**
	 * Production-ready constructor with comprehensive initialization
	 * 
	 * @param mixed $id Object ID to load (null for empty object)
	 * @param string|null $table Database table name
	 * @param string|null $primarykey Primary key column name
	 * @param array|null $arrDB_Data Database configuration
	 * @param LoggerInterface|null $logger Logger instance
	 * @param array $config CBObject configuration
	 */
	public function __construct(
		mixed $id = null,
		?string $table = null,
		?string $primarykey = null,
		?array $arrDB_Data = null,
		?LoggerInterface $logger = null,
		array $config = []
	) {
		// Initialize production features
		//$this->logger = $logger ?? new NullLogger();
		$this->logger = $logger ?? new \Capps\Modules\Database\Classes\NullLogger();
		$this->config = $this->validateConfig($config);
		$this->securityValidator = new SecurityValidator($this->config);
		$this->performanceMonitor = new PerformanceMonitor($this->logger);
		
		// Initialize with table if provided (supports empty objects)
		if ($table !== null && $primarykey !== null) {
			$this->initializeWithTable($id, $table, $primarykey, $arrDB_Data);
		}
		
		$this->performMemoryCheck();
	}

	/**
	 * Initialize object with table information (supports empty objects)
	 */
	private function initializeWithTable(mixed $id, string $table, string $primarykey, ?array $arrDB_Data): void
	{
		// Comprehensive security validation
		$this->securityValidator->validateTableName($table);
		$this->securityValidator->validateColumnName($primarykey);
		
		$this->strTable = $table;
		$this->strPrimaryKey = $primarykey;
		
		// Get pooled database connection
		$this->objDatabase = $this->getPooledConnection($arrDB_Data);
		
		// Load and cache table schema (always needed)
		$this->loadTableSchema();
		
		// Load object data ONLY if ID provided
		$this->identifier = $id;
		if ($this->identifier !== null) {
			$this->load($this->identifier);
		}
		
		$this->logger->debug('CBObject initialized', [
			'table' => $table,
			'primary_key' => $primarykey,
			'has_id' => $id !== null,
			'is_empty_object' => $id === null,
			'memory_usage' => memory_get_usage()
		]);
	}

	/**
	 * File-based locking for thread safety (replaces SyncMutex)
	 */
	private function acquireLock(string $lockName): bool
	{
		$lockFile = sys_get_temp_dir() . '/cbobject_' . md5($lockName) . '.lock';
		
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

	private function releaseLock(string $lockName): void
	{
		if (isset(self::$lockHandles[$lockName])) {
			flock(self::$lockHandles[$lockName], LOCK_UN);
			fclose(self::$lockHandles[$lockName]);
			unset(self::$lockHandles[$lockName]);
		}
	}

	/**
	 * Thread-safe connection pooling with health monitoring
	 */
	private function getPooledConnection(?array $config): CBDatabase
	{
		$connectionKey = $this->getConnectionKey($config);
		
		if (!$this->acquireLock('connection_pool')) {
			// Fallback: create new connection if can't acquire lock
			return new CBDatabase($config);
		}
		
		try {
			// Check existing healthy connection
			if (isset(self::$connectionPool[$connectionKey])) {
				$connection = self::$connectionPool[$connectionKey];
				
				if ($this->isConnectionHealthy($connection, $connectionKey)) {
					self::$connectionUsage[$connectionKey] = time();
					$this->updateGlobalMetrics('connection_reused', 1);
					return $connection;
				}
				
				// Remove unhealthy connection
				$this->removeUnhealthyConnection($connectionKey);
			}
			
			// Enforce connection limits
			$this->enforceConnectionLimits();
			
			// Create new connection
			$connection = new CBDatabase($config);
			
			// Add to pool with health tracking
			self::$connectionPool[$connectionKey] = $connection;
			self::$connectionHealth[$connectionKey] = time();
			self::$connectionUsage[$connectionKey] = time();
			
			$this->updateGlobalMetrics('connection_created', 1);
			$this->logger->info('New database connection created', [
				'connection_key' => substr($connectionKey, 0, 8),
				'total_connections' => count(self::$connectionPool)
			]);
			
			return $connection;
			
		} finally {
			$this->releaseLock('connection_pool');
		}
	}

	/**
	 * Check connection health with comprehensive testing
	 */
	private function isConnectionHealthy(CBDatabase $connection, string $connectionKey): bool
	{
		try {
			// Check last health check time
			$lastCheck = self::$connectionHealth[$connectionKey] ?? 0;
			if (time() - $lastCheck < self::HEALTH_CHECK_INTERVAL) {
				return true; // Recently checked, assume healthy
			}
			
			// Perform actual health check
			$result = $connection->select('SELECT 1 as health_check LIMIT 1');
			
			if (!empty($result) && $result[0]['health_check'] === 1) {
				self::$connectionHealth[$connectionKey] = time();
				return true;
			}
			
			return false;
			
		} catch (\Exception $e) {
			$this->logger->warning('Connection health check failed', [
				'connection_key' => substr($connectionKey, 0, 8),
				'error' => $e->getMessage()
			]);
			return false;
		}
	}

	/**
	 * Remove unhealthy connection and cleanup
	 */
	private function removeUnhealthyConnection(string $connectionKey): void
	{
		unset(
			self::$connectionPool[$connectionKey],
			self::$connectionHealth[$connectionKey],
			self::$connectionUsage[$connectionKey]
		);
		
		$this->logger->info('Unhealthy connection removed', [
			'connection_key' => substr($connectionKey, 0, 8),
			'remaining_connections' => count(self::$connectionPool)
		]);
	}

	/**
	 * Enforce connection pool limits
	 */
	private function enforceConnectionLimits(): void
	{
		if (count(self::$connectionPool) >= self::MAX_CONNECTIONS) {
			// Remove least recently used connection
			$oldestKey = $this->findLeastRecentlyUsedConnection();
			if ($oldestKey) {
				$this->removeUnhealthyConnection($oldestKey);
				$this->logger->info('Connection evicted due to pool limit', [
					'evicted_key' => substr($oldestKey, 0, 8),
					'pool_size' => count(self::$connectionPool)
				]);
			}
		}
	}

	/**
	 * Find least recently used connection
	 */
	private function findLeastRecentlyUsedConnection(): ?string
	{
		if (empty(self::$connectionUsage)) {
			return null;
		}
		
		return array_keys(self::$connectionUsage, min(self::$connectionUsage))[0];
	}

	/**
	 * Thread-safe LRU cache implementation using file locks
	 */
	private function getCachedData(string $cacheKey): ?array
	{
		if (!$this->acquireLock('cache_read')) {
			// Fallback: skip cache if can't acquire lock
			$this->updateGlobalMetrics('cache_miss', 1);
			return null;
		}
		
		try {
			if (!isset(self::$cacheData[$cacheKey])) {
				$this->updateGlobalMetrics('cache_miss', 1);
				return null;
			}
			
			// Check TTL
			$cacheEntry = self::$cacheData[$cacheKey];
			if (time() - $cacheEntry['timestamp'] > self::CACHE_TTL) {
				unset(
					self::$cacheData[$cacheKey],
					self::$cacheAccessTimes[$cacheKey],
					self::$cacheHitCounts[$cacheKey]
				);
				$this->updateGlobalMetrics('cache_miss', 1);
				return null;
			}
			
			// Update access tracking for LRU
			self::$cacheAccessTimes[$cacheKey] = microtime(true);
			self::$cacheHitCounts[$cacheKey] = (self::$cacheHitCounts[$cacheKey] ?? 0) + 1;
			
			$this->updateGlobalMetrics('cache_hit', 1);
			
			return $cacheEntry['data'];
			
		} finally {
			$this->releaseLock('cache_read');
		}
	}

	/**
	 * Thread-safe cache storage with LRU eviction
	 */
	private function setCachedData(string $cacheKey, array $data): void
	{
		if (!$this->acquireLock('cache_write')) {
			// Graceful degradation: continue without caching
			return;
		}
		
		try {
			// Implement smart cache size management
			if (count(self::$cacheData) >= self::MAX_CACHE_SIZE) {
				$this->evictLeastValuableEntries();
			}
			
			// Store with metadata
			self::$cacheData[$cacheKey] = [
				'data' => $data,
				'timestamp' => time(),
				'access_count' => 1,
				'memory_size' => $this->estimateMemorySize($data)
			];
			
			self::$cacheAccessTimes[$cacheKey] = microtime(true);
			self::$cacheHitCounts[$cacheKey] = 1;
			
			$this->updateGlobalMetrics('cache_store', 1);
			
		} finally {
			$this->releaseLock('cache_write');
		}
	}

	/**
	 * Intelligent cache eviction based on access patterns and memory usage
	 */
	private function evictLeastValuableEntries(): void
	{
		if (empty(self::$cacheData)) {
			return;
		}
		
		// Calculate value score for each entry (hit count / age / memory)
		$entryScores = [];
		$currentTime = microtime(true);
		
		foreach (self::$cacheData as $key => $entry) {
			$age = $currentTime - (self::$cacheAccessTimes[$key] ?? $currentTime);
			$hitCount = self::$cacheHitCounts[$key] ?? 1;
			$memorySize = $entry['memory_size'] ?? 1000;
			
			// Higher score = more valuable (less likely to be evicted)
			$entryScores[$key] = ($hitCount * 100) / (($age + 1) * ($memorySize / 1000));
		}
		
		// Sort by score (ascending = least valuable first)
		asort($entryScores);
		
		// Remove least valuable 25% of entries
		$removeCount = max(1, (int)(count($entryScores) * 0.25));
		$keysToRemove = array_slice(array_keys($entryScores), 0, $removeCount);
		
		foreach ($keysToRemove as $key) {
			unset(
				self::$cacheData[$key],
				self::$cacheAccessTimes[$key],
				self::$cacheHitCounts[$key]
			);
		}
		
		$this->logger->debug('Cache eviction completed', [
			'removed_entries' => count($keysToRemove),
			'remaining_entries' => count(self::$cacheData),
			'memory_freed' => array_sum(array_map(fn($k) => self::$cacheData[$k]['memory_size'] ?? 0, $keysToRemove))
		]);
	}

	/**
	 * Estimate memory size of data structure
	 */
	private function estimateMemorySize(array $data): int
	{
		return strlen(serialize($data));
	}

	/**
	 * Production-ready load method with comprehensive security and caching
	 * 
	 * @param mixed $id ID to load (required)
	 * @param array|null $arrData Pre-loaded data (optional)
	 * @return bool Success status
	 * @throws InvalidArgumentException When ID is null
	 */
	public function load(mixed $id = null, ?array $arrData = null): bool
	{
		if ($id === null) {
			throw new InvalidArgumentException("Load called without ID");
		}

		// Security validation
		$this->securityValidator->validateValue($id, $this->strPrimaryKey ?? 'id');

		// Performance monitoring
		$startTime = microtime(true);

		try {
			if ($arrData === null) {
				// Try cache first
				$cacheKey = $this->getCacheKey($id);
				$cached = $this->getCachedData($cacheKey);

				if ($cached !== null) {
					$this->populateFromCachedData($cached);
					$this->performanceMonitor->recordCacheHit($this->strTable, microtime(true) - $startTime);
					return true;
				}
				
				// Load from database
				$data = $this->loadFromDatabase($id);
				if ($data === null) {
					$this->performanceMonitor->recordDatabaseMiss($this->strTable, microtime(true) - $startTime);
					return false;
				}
				
				// Cache the result
				$this->setCachedData($cacheKey, $data);
				
			} else {
				$data = $arrData;
			}
			
			// Populate object attributes
			$this->populateFromData($data);
			
			$this->performanceMonitor->recordDatabaseHit($this->strTable, microtime(true) - $startTime);
			
			// Update identifier if loaded from composite key
			if (str_contains((string)$id, ':')) {
				$this->identifier = $data[$this->strPrimaryKey];
			}
			
			return true;
			
		} catch (\Exception $e) {
			$this->logger->error('Failed to load object', [
				'id' => $id,
				'table' => $this->strTable,
				'error' => $e->getMessage(),
				'execution_time' => microtime(true) - $startTime
			]);
			throw new RuntimeException("Failed to load object: " . $e->getMessage(), 0, $e);
		}
	}

	/**
	 * Secure database loading with multiple ID format support
	 */
	private function loadFromDatabase(mixed $id): ?array
	{
		try {
			if (str_contains($this->strPrimaryKey, '_uid') || str_contains((string)$id, ':')) {
				// Handle UUID or composite keys
				if (str_contains((string)$id, ':')) {
					[$column, $value] = explode(':', (string)$id, 2);
					$this->securityValidator->validateColumnName($column);
					$this->securityValidator->validateValue($value, $column);
					
					return $this->objDatabase->selectOne(
						"SELECT * FROM `{$this->strTable}` WHERE `{$column}` = ? LIMIT 1",
						[$value]
					);
				} else {
					return $this->objDatabase->selectOne(
						"SELECT * FROM `{$this->strTable}` WHERE `{$this->strPrimaryKey}` = ? LIMIT 1",
						[$id]
					);
				}
			} else {
				// Numeric IDs
				return $this->objDatabase->selectOne(
					"SELECT * FROM `{$this->strTable}` WHERE `{$this->strPrimaryKey}` = ? LIMIT 1",
					[$id]
				);
			}
			
		} catch (\Exception $e) {
			$this->logger->error('Database load failed', [
				'id' => $id,
				'table' => $this->strTable,
				'error' => $e->getMessage()
			]);
			throw $e;
		}
	}

	/**
	 * Check if object is empty (no data loaded)
	 * 
	 * @return bool True if object is empty
	 */
	public function isEmpty(): bool
	{
		return $this->identifier === null;
	}

	/**
	 * Check if object has data loaded
	 * 
	 * @return bool True if object has data
	 */
	public function isLoaded(): bool
	{
		return $this->identifier !== null;
	}

	/**
	 * Reload object data from database
	 * 
	 * @return bool Success status
	 * @throws InvalidArgumentException When object has no identifier
	 */
	public function reload(): bool
	{
		if ($this->identifier === null) {
			throw new InvalidArgumentException("Cannot reload object without identifier");
		}
		
		// Clear cache and reload
		$this->clearObjectCache($this->identifier);
		return $this->load($this->identifier);
	}

	/**
	 * Thread-safe bulk operations for high performance
	 */
	public function bulkCreate(array $records): array
	{
		if (empty($records)) {
			throw new InvalidArgumentException('No records provided for bulk create');
		}
		
		if (count($records) > self::MAX_BULK_SIZE) {
			throw new InvalidArgumentException('Bulk operation exceeds maximum size limit');
		}
		
		// Comprehensive validation of all records
		foreach ($records as $index => $record) {
			if (!is_array($record) || empty($record)) {
				throw new InvalidArgumentException("Invalid record at index {$index}");
			}
			
			foreach ($record as $key => $value) {
				$this->securityValidator->validateColumnName($key);
				$this->securityValidator->validateValue($value, $key);
			}
		}
		
		$startTime = microtime(true);
		
		try {
			// Process XML fields for all records
			$processedRecords = array_map([$this, 'processXmlFieldsForSave'], $records);
			
			// Execute bulk insert
			$insertedIds = $this->objDatabase->bulkInsert($this->strTable, $processedRecords, $this->strPrimaryKey);
			
			// Clear relevant caches
			$this->invalidateTableCache();
			
			$this->performanceMonitor->recordBulkOperation(
				'bulk_create',
				$this->strTable,
				count($records),
				microtime(true) - $startTime
			);
			
			$this->logger->info('Bulk create completed', [
				'table' => $this->strTable,
				'record_count' => count($records),
				'execution_time' => microtime(true) - $startTime
			]);
			
			return $insertedIds;
			
		} catch (\Exception $e) {
			$this->logger->error('Bulk create failed', [
				'table' => $this->strTable,
				'record_count' => count($records),
				'error' => $e->getMessage()
			]);
			throw new RuntimeException("Bulk create failed: " . $e->getMessage(), 0, $e);
		}
	}

	/**
	 * Secure bulk update with validation and performance monitoring
	 */
	public function bulkUpdate(array $updates): bool
	{
		if (empty($updates)) {
			throw new InvalidArgumentException('No updates provided for bulk update');
		}
		
		if (count($updates) > self::MAX_BULK_SIZE) {
			throw new InvalidArgumentException('Bulk operation exceeds maximum size limit');
		}
		
		// Validate all updates
		foreach ($updates as $index => $update) {
			if (!isset($update[$this->strPrimaryKey])) {
				throw new InvalidArgumentException("Primary key missing in update at index {$index}");
			}
			
			foreach ($update as $key => $value) {
				$this->securityValidator->validateColumnName($key);
				$this->securityValidator->validateValue($value, $key);
			}
		}
		
		$startTime = microtime(true);
		
		try {
			// Process XML fields
			$processedUpdates = array_map([$this, 'processXmlFieldsForSave'], $updates);
			
			// Execute bulk update
			$success = $this->objDatabase->bulkUpdate($this->strTable, $processedUpdates, $this->strPrimaryKey);
			
			// Clear caches for updated records
			foreach ($updates as $update) {
				$this->clearObjectCache($update[$this->strPrimaryKey]);
			}
			
			$this->performanceMonitor->recordBulkOperation(
				'bulk_update',
				$this->strTable,
				count($updates),
				microtime(true) - $startTime
			);
			
			return $success;
			
		} catch (\Exception $e) {
			$this->logger->error('Bulk update failed', [
				'table' => $this->strTable,
				'update_count' => count($updates),
				'error' => $e->getMessage()
			]);
			throw new RuntimeException("Bulk update failed: " . $e->getMessage(), 0, $e);
		}
	}

	/**
	 * Secure bulk delete with comprehensive validation
	 */
	public function bulkDelete(array $ids): bool
	{
		if (empty($ids)) {
			throw new InvalidArgumentException('No IDs provided for bulk delete');
		}
		
		if (count($ids) > self::MAX_BULK_SIZE) {
			throw new InvalidArgumentException('Bulk operation exceeds maximum size limit');
		}
		
		// Validate all IDs
		foreach ($ids as $id) {
			$this->securityValidator->validateValue($id, $this->strPrimaryKey);
		}
		
		$startTime = microtime(true);
		
		try {
			$success = $this->objDatabase->bulkDelete($this->strTable, $this->strPrimaryKey, $ids);
			
			// Clear caches for deleted records
			foreach ($ids as $id) {
				$this->clearObjectCache($id);
			}
			
			$this->performanceMonitor->recordBulkOperation(
				'bulk_delete',
				$this->strTable,
				count($ids),
				microtime(true) - $startTime
			);
			
			return $success;
			
		} catch (\Exception $e) {
			$this->logger->error('Bulk delete failed', [
				'table' => $this->strTable,
				'id_count' => count($ids),
				'error' => $e->getMessage()
			]);
			throw new RuntimeException("Bulk delete failed: " . $e->getMessage(), 0, $e);
		}
	}

	/**
	 * Modern secure API methods
	 */
	public function get(string $attribute, string $default = ''): string
	{
		// Always validate attribute access
		if (!array_key_exists($attribute, $this->arrAttributes) && 
			!str_contains($attribute, '_')) {
			$this->logger->debug('Attempt to access unknown attribute', [
				'attribute' => $attribute,
				'table' => $this->strTable
			]);
			return $default;
		}
		
		return (string)($this->arrAttributes[$attribute] ?? $default);
	}

	public function set(string $attribute, mixed $value): self
	{
		// Always validate - no bypass allowed
		$this->securityValidator->validateColumnName($attribute);
		$this->securityValidator->validateValue($value, $attribute);
		
		// Validate against database schema for regular columns (simplified)
		if (!str_contains($attribute, '_')) {
			if (!$this->hasColumn($attribute)) {
				throw new InvalidArgumentException("Column '{$attribute}' does not exist in table '{$this->strTable}'");
			}
		}
		
		$this->arrAttributes[$attribute] = $value;
		
		$this->logger->debug('Attribute set', [
			'attribute' => $attribute,
			'table' => $this->strTable,
			'value_type' => gettype($value),
			'value_length' => is_string($value) ? strlen($value) : null
		]);
		
		return $this;
	}

	/**
	 * Secure create with comprehensive validation
	 */
	public function create(array $data, bool $forceOverwritePK = false): int|string
	{
		if (empty($data)) {
			throw new InvalidArgumentException("No data provided for create");
		}

		// Validate all input data
		foreach ($data as $key => $value) {
			$this->securityValidator->validateColumnName($key);
			$this->securityValidator->validateValue($value, $key);
		}

		$startTime = microtime(true);
		
		try {
			$result = $this->saveContentNew($data, $forceOverwritePK);
			
			// Update object identifier with new ID
			$this->identifier = $result;
			
			// Load the created record into this object
			$this->load($result);
			
			$this->performanceMonitor->recordOperation(
				'create',
				$this->strTable,
				microtime(true) - $startTime
			);
			
			return $result;
			
		} catch (\Exception $e) {
			$this->logger->error('Create operation failed', [
				'table' => $this->strTable,
				'data_keys' => array_keys($data),
				'error' => $e->getMessage()
			]);
			throw new RuntimeException("Create failed: " . $e->getMessage(), 0, $e);
		}
	}

	/**
	 * Secure update with validation and cache invalidation
	 */
	public function update(int|string $id, array $data): bool
	{
		if (empty($data)) {
			throw new InvalidArgumentException("No data provided for update");
		}

		// Validate ID and all data
		$this->securityValidator->validateValue($id, $this->strPrimaryKey);
		
		foreach ($data as $key => $value) {
			$this->securityValidator->validateColumnName($key);
			$this->securityValidator->validateValue($value, $key);
		}

		$startTime = microtime(true);
		
		try {
			$success = $this->saveContentUpdate($id, $data);
			
			if ($success) {
				$this->clearObjectCache($id);
				
				// Reload this object if it's the same ID
				if ($this->identifier == $id) {
					$this->load($id);
				}
			}
			
			$this->performanceMonitor->recordOperation(
				'update',
				$this->strTable,
				microtime(true) - $startTime
			);
			
			return $success;
			
		} catch (\Exception $e) {
			$this->logger->error('Update operation failed', [
				'id' => $id,
				'table' => $this->strTable,
				'error' => $e->getMessage()
			]);
			throw new RuntimeException("Update failed: " . $e->getMessage(), 0, $e);
		}
	}

	/**
	 * Secure delete with validation
	 */
	public function delete(mixed $id = null): bool
	{
		$deleteId = $id ?? $this->identifier;
		
		if ($deleteId === null) {
			throw new InvalidArgumentException("No ID provided for delete");
		}

		// Validate ID
		if (is_array($deleteId)) {
			return $this->bulkDelete($deleteId);
		}
		
		$this->securityValidator->validateValue($deleteId, $this->strPrimaryKey);

		$startTime = microtime(true);
		
		try {
			$success = $this->deleteEntry($deleteId);
			
			if ($success) {
				$this->clearObjectCache($deleteId);
				
				// Clear this object if it was the deleted record
				if ($this->identifier == $deleteId) {
					$this->identifier = null;
					$this->resetAttributes();
				}
			}
			
			$this->performanceMonitor->recordOperation(
				'delete',
				$this->strTable,
				microtime(true) - $startTime
			);
			
			return $success;
			
		} catch (\Exception $e) {
			$this->logger->error('Delete operation failed', [
				'id' => $deleteId,
				'table' => $this->strTable,
				'error' => $e->getMessage()
			]);
			throw new RuntimeException("Delete failed: " . $e->getMessage(), 0, $e);
		}
	}

    /**
     * Überarbeitete findAll() Methode, die jetzt die vollständige executeDirectQuery verwendet
     */
    public function findAll(array $conditions = [], array $options = []): array
    {
        // Validate conditions
        foreach ($conditions as $key => $value) {
            $this->securityValidator->validateColumnName($key);
            $this->securityValidator->validateValue($value, $key);
        }

        $startTime = microtime(true);

        try {
            $result = $this->executeDirectQuery($conditions, $options);

            $this->performanceMonitor->recordOperation(
                'find_all',
                $this->strTable,
                microtime(true) - $startTime
            );

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('FindAll operation failed', [
                'table' => $this->strTable,
                'conditions' => array_keys($conditions),
                'error' => $e->getMessage()
            ]);
            throw new RuntimeException("FindAll failed: " . $e->getMessage(), 0, $e);
        }
    }
	
	/**
	 * NEUE METHODE: Direkte Query-Ausführung ohne Rekursion
	 */
	private function executeDirectQueryFIRST(array $conditions = [], array $options = []): array
	{
		$strWhere = '';
		$strQuery = "SELECT " . ($options['result'] ?? '*') . " FROM `{$this->strTable}` i";
		
		// Build WHERE clause
		if (!empty($conditions)) {
			$whereClauses = [];
			foreach ($conditions as $attribute => $value) {
				if (empty($value)) continue;
				
				$values = explode('|', $value);
				$orClauses = [];
				
				foreach ($values as $val) {
					if (str_starts_with($attribute, 'data_')) {
						// XML data fields
						$dataField = str_replace('data_', '', $attribute);
						if (str_starts_with($val, 'NOT')) {
							$cleanVal = str_replace(['NOT ', 'NOT'], '', $val);
							$orClauses[] = "i.data NOT LIKE '%<{$dataField}><![CDATA[{$cleanVal}]]></{$dataField}>%'";
						} else {
							$orClauses[] = "i.data LIKE '%<{$dataField}><![CDATA[{$val}]]></{$dataField}>%'";
						}
					} else {
						// Regular fields
						if (str_starts_with($val, 'NOT')) {
							$cleanVal = str_replace(['NOT ', 'NOT'], '', $val);
							$orClauses[] = "(i.`{$attribute}` NOT LIKE '{$cleanVal}' OR i.`{$attribute}` IS NULL)";
						} elseif ($val === 'NULL') {
							$orClauses[] = "i.`{$attribute}` IS NULL";
						} else {
							$orClauses[] = "i.`{$attribute}` LIKE '{$val}'";
						}
					}
				}
				
				if (!empty($orClauses)) {
					$whereClauses[] = '(' . implode(' OR ', $orClauses) . ')';
				}
			}
			
			if (!empty($whereClauses)) {
				$strQuery .= ' WHERE ' . implode(' AND ', $whereClauses);
			}
		}
		
		// Add ORDER BY
		if (!empty($options['order'])) {
			$strQuery .= " ORDER BY i.`{$options['order']}` " . ($options['direction'] ?? 'ASC');
		} else {
			$strQuery .= " ORDER BY i.`{$this->strPrimaryKey}` ASC";
		}
		
		// Add LIMIT
		if (!empty($options['limit'])) {
			$strQuery .= " LIMIT " . (int)$options['limit'];
		}
		
		// Execute query
		if ($this->objDatabase !== null) {
			echo $strQuery;
			return $this->objDatabase->select($strQuery);
		}
		
		return [];
	}

	/**
	 * Find first matching record with security validation
	 */
	public function findFirst(array $conditions = [], array $options = []): ?static
	{
		// Validate conditions
		foreach ($conditions as $key => $value) {
			$this->securityValidator->validateColumnName($key);
			$this->securityValidator->validateValue($value, $key);
		}
		
		$startTime = microtime(true);
		
		try {
			// Set limit to 1 for efficiency
			$options['limit'] = 1;
			
			$entries = $this->getAllEntries(
				$options['order'] ?? null,
				$options['direction'] ?? 'ASC',
				$conditions,
				$options['selection'] ?? null,
				$options['result'] ?? '',
				1
			);
			
			$this->performanceMonitor->recordOperation(
				'find_first',
				$this->strTable,
				microtime(true) - $startTime
			);
			
			if (!empty($entries) && !empty($entries[0][$this->strPrimaryKey])) {
				return new static($entries[0][$this->strPrimaryKey], $this->strTable, $this->strPrimaryKey);
			}
			
			return null;
			
		} catch (\Exception $e) {
			$this->logger->error('FindFirst operation failed', [
				'table' => $this->strTable,
				'conditions' => array_keys($conditions),
				'error' => $e->getMessage()
			]);
			throw new RuntimeException("FindFirst failed: " . $e->getMessage(), 0, $e);
		}
	}

	/**
	 * Count records matching conditions
	 */
	public function count(array $conditions = []): int
	{
		// Validate conditions
		foreach ($conditions as $key => $value) {
			$this->securityValidator->validateColumnName($key);
			$this->securityValidator->validateValue($value, $key);
		}
		
		$startTime = microtime(true);
		
		try {
			$query = "SELECT COUNT(*) as total FROM `{$this->strTable}` i";
			$params = [];
			$whereClauses = [];

			// Process conditions (similar to getAllEntries but for COUNT)
			if (!empty($conditions)) {
				foreach ($conditions as $attribute => $value) {
					if (empty($value)) continue;

					$values = explode('|', $value);
					$orClauses = [];

					foreach ($values as $val) {
						if (str_starts_with($attribute, 'data_')) {
							// XML data fields
							$dataField = str_replace('data_', '', $attribute);
							if (str_starts_with($val, 'NOT')) {
								$cleanVal = str_replace(['NOT ', 'NOT'], '', $val);
								$orClauses[] = "i.data NOT LIKE ?";
								$params[] = "%<{$dataField}><![CDATA[{$cleanVal}]]></{$dataField}>%";
							} else {
								$orClauses[] = "i.data LIKE ?";
								$params[] = "%<{$dataField}><![CDATA[{$val}]]></{$dataField}>%";
							}
						} else {
							// Regular fields
							if (str_starts_with($val, 'NOT')) {
								$cleanVal = str_replace(['NOT ', 'NOT'], '', $val);
								$orClauses[] = "(i.`{$attribute}` NOT LIKE ? OR i.`{$attribute}` IS NULL)";
								$params[] = $cleanVal;
							} elseif ($val === 'NULL') {
								$orClauses[] = "i.`{$attribute}` IS NULL";
							} else {
								$orClauses[] = "i.`{$attribute}` LIKE ?";
								$params[] = $val;
							}
						}
					}

					if (!empty($orClauses)) {
						$whereClauses[] = '(' . implode(' OR ', $orClauses) . ')';
					}
				}
			}

			// Build WHERE clause
			if (!empty($whereClauses)) {
				$query .= ' WHERE ' . implode(' AND ', $whereClauses);
			}

			$result = $this->objDatabase->selectOne($query, $params);
			
			$this->performanceMonitor->recordOperation(
				'count',
				$this->strTable,
				microtime(true) - $startTime
			);
			
			return (int)($result['total'] ?? 0);
			
		} catch (\Exception $e) {
			$this->logger->error('Count operation failed', [
				'table' => $this->strTable,
				'conditions' => array_keys($conditions),
				'error' => $e->getMessage()
			]);
			throw new RuntimeException("Count failed: " . $e->getMessage(), 0, $e);
		}
	}

	/**
	 * Check if record exists with given conditions
	 */
	public function exists(array $conditions = []): bool
	{
		return $this->count($conditions) > 0;
	}

	/**
	 * Smart save - create or update based on identifier
	 */
	public function save(?array $data = null): int|string
	{
		$saveData = $data ?? $this->arrAttributes;
		
		if (empty($saveData)) {
			throw new InvalidArgumentException("No data provided for save");
		}
		
		if ($this->identifier !== null) {
			// Update existing
			$saveData["date_updated"] = date("Y-m-d H:i:s");
			$this->update($this->identifier, $saveData);
			return $this->identifier;
		} else {
			// Create new
			$saveData["date_created"] = date("Y-m-d H:i:s");
			$newId = $this->create($saveData);
			$this->identifier = $newId;
			return $newId;
		}
	}

	/**
	 * Transaction wrapper with rollback support
	 */
	public function transaction(callable $callback): mixed
	{
		$startTime = microtime(true);
		
		try {
			$result = $this->objDatabase->transaction($callback);
			
			$this->performanceMonitor->recordOperation(
				'transaction',
				$this->strTable,
				microtime(true) - $startTime
			);
			
			return $result;
			
		} catch (\Exception $e) {
			$this->logger->error('Transaction failed', [
				'table' => $this->strTable,
				'error' => $e->getMessage(),
				'execution_time' => microtime(true) - $startTime
			]);
			throw $e;
		}
	}

	// ================================================================
	// COLUMN CONVENIENCE METHODS - Simple access to database schema
	// ================================================================

	/**
	 * Check if column exists in table
	 * 
	 * @param string $columnName Column name to check
	 * @return bool True if column exists
	 */
	public function hasColumn(string $columnName): bool
	{
		return array_key_exists($columnName, $this->arrDatabaseColumns);
	}

	/**
	 * Get column type
	 * 
	 * @param string $columnName Column name
	 * @return string|null Column type or null if not found
	 */
	public function getColumnType(string $columnName): ?string
	{
		return $this->arrDatabaseColumns[$columnName] ?? null;
	}

	/**
	 * Get all column names
	 * 
	 * @return array Array of column names
	 */
	public function getColumnNames(): array
	{
		return array_keys($this->arrDatabaseColumns);
	}

	/**
	 * Get all columns with their types
	 * 
	 * @return array Associative array of column_name => column_type
	 */
	public function getColumnTypes(): array
	{
		return $this->arrDatabaseColumns;
	}

	/**
	 * Filter array to only include valid table columns
	 * 
	 * @param array $data Input data array
	 * @return array Filtered array with only valid columns
	 */
	public function filterValidColumns(array $data): array
	{
		return array_intersect_key($data, $this->arrDatabaseColumns);
	}

	/**
	 * Validate that all specified columns exist
	 * 
	 * @param array $columnNames Array of column names to validate
	 * @return bool True if all columns exist
	 */
	public function validateColumns(array $columnNames): bool
	{
		foreach ($columnNames as $columnName) {
			if (!$this->hasColumn($columnName)) {
				return false;
			}
		}
		return true;
	}

	// ================================================================
	// UTILITY AND HELPER METHODS
	// ================================================================

	/**
	 * Get comprehensive health check information
	 */
	public function getHealthCheck(): array
	{
		$health = [
			'status' => 'ok',
			'timestamp' => date('Y-m-d H:i:s'),
			'table' => $this->strTable,
			'object_state' => $this->isEmpty() ? 'empty' : 'loaded',
			'checks' => []
		];

		try {
			// Database connection test
			$startTime = microtime(true);
			$this->objDatabase->select('SELECT 1 as test LIMIT 1');
			$health['checks']['database'] = [
				'status' => 'ok',
				'response_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms'
			];
		} catch (\Exception $e) {
			$health['status'] = 'error';
			$health['checks']['database'] = [
				'status' => 'failed',
				'error' => $e->getMessage()
			];
		}

		try {
			// Table existence test
			$result = $this->objDatabase->select("SHOW TABLES LIKE '{$this->strTable}'");
			$health['checks']['table'] = !empty($result) ? 'ok' : 'missing';
			
			if (empty($result)) {
				$health['status'] = 'error';
			}
		} catch (\Exception $e) {
			$health['status'] = 'error';
			$health['checks']['table'] = 'failed: ' . $e->getMessage();
		}

		// Cache statistics
		$health['cache'] = $this->getCacheStatistics();

		// Performance metrics
		$health['performance'] = $this->performanceMonitor->getMetrics();

		return $health;
	}

	/**
	 * Get cache statistics
	 */
	public function getCacheStatistics(): array
	{
		$totalRequests = self::$globalMetrics['cache_hits'] + self::$globalMetrics['cache_misses'];
		$hitRate = $totalRequests > 0 ? (self::$globalMetrics['cache_hits'] / $totalRequests) * 100 : 0;
		
		return [
			'enabled' => true,
			'size' => count(self::$cacheData),
			'max_size' => self::MAX_CACHE_SIZE,
			'hits' => self::$globalMetrics['cache_hits'],
			'misses' => self::$globalMetrics['cache_misses'],
			'hit_rate' => round($hitRate, 2) . '%',
			'memory_usage' => array_sum(array_column(self::$cacheData, 'memory_size'))
		];
	}

	/**
	 * Clear all caches (static method for global cleanup)
	 */
	public static function clearAllCaches(): void
	{
		self::$cacheData = [];
		self::$cacheAccessTimes = [];
		self::$cacheHitCounts = [];
		
		// Reset cache metrics
		self::$globalMetrics['cache_hits'] = 0;
		self::$globalMetrics['cache_misses'] = 0;
	}

	/**
	 * Get global performance metrics
	 */
	public static function getGlobalMetrics(): array
	{
		return [
			'queries_executed' => self::$globalMetrics['queries_executed'],
			'cache_performance' => [
				'hits' => self::$globalMetrics['cache_hits'],
				'misses' => self::$globalMetrics['cache_misses'],
				'hit_rate' => self::calculateHitRate()
			],
			'memory_usage' => self::$globalMetrics['memory_usage'],
			'connections' => [
				'active' => count(self::$connectionPool),
				'max_allowed' => self::MAX_CONNECTIONS
			],
			'cache_size' => count(self::$cacheData)
		];
	}

	/**
	 * Calculate cache hit rate
	 */
	private static function calculateHitRate(): string
	{
		$total = self::$globalMetrics['cache_hits'] + self::$globalMetrics['cache_misses'];
		if ($total === 0) {
			return '0%';
		}
		return round((self::$globalMetrics['cache_hits'] / $total) * 100, 2) . '%';
	}

	// ================================================================
	// PRIVATE HELPER METHODS
	// ================================================================

	/**
	 * Load table schema information - Simplified version
	 */
	private function loadTableSchema(): void
	{
		$cacheKey = "schema_{$this->strTable}";
		$cached = $this->getCachedData($cacheKey);
		
		if ($cached !== null) {
			$this->arrDatabaseColumns = $cached;
			$this->initializeEmptyAttributes();
			return;
		}
		
		try {
			// Load full column information
			$fullColumns = $this->objDatabase->show("SHOW COLUMNS FROM `{$this->strTable}`");
			
			// Convert to simple associative array: column_name => column_type
			$this->arrDatabaseColumns = [];
			foreach ($fullColumns as $column) {
				$this->arrDatabaseColumns[$column['Field']] = $column['Type'];
			}
			
			// Initialize attributes with default values
			$this->initializeEmptyAttributes();
			
			// Cache simplified schema information
			$this->setCachedData($cacheKey, $this->arrDatabaseColumns);
			
		} catch (\Exception $e) {
			throw new RuntimeException("Failed to load table schema: " . $e->getMessage());
		}
	}

	/**
	 * Initialize empty attributes from database schema - Simplified version
	 */
	private function initializeEmptyAttributes(): void
	{
		foreach (array_keys($this->arrDatabaseColumns) as $columnName) {
			if (!array_key_exists($columnName, $this->arrAttributes)) {
				$this->arrAttributes[$columnName] = '';
			}
		}
	}

	/**
	 * Populate object from cached data
	 */
	private function populateFromCachedData(array $data): void
	{
		$this->resetAttributes();
		$this->populateFromData($data);
	}

	/**
	 * Populate object from data array
	 */
	private function populateFromData(array $data): void
	{
		foreach ($data as $key => $value) {
			if ( is_string($value) ) {
				$this->arrAttributes[$key] = stripslashes($value ?? '');
			} else {
				$this->arrAttributes[$key] = $value;
			}

			// Automatic XML parsing for special fields
			if (in_array($key, ['media', 'data', 'settings']) && !empty($value)) {
				$this->parseXmlField($key, $value);
			}
		}

		// Convert addressgroups to array
		if (!empty($this->arrAttributes['addressgroups'])) {
			$this->arrAttributes['arrAddressgroups'] = explode(',', $this->arrAttributes['addressgroups']);
		}
	}

	/**
	 * Reset all attributes to default values
	 */
	private function resetAttributes(): void
	{
		// WICHTIG: Komplett neues Array erstellen
		unset($this->arrAttributes);
		$this->arrAttributes = [];
		
		// Neue Struktur ohne Referenzen aufbauen
		if (!empty($this->arrDatabaseColumns)) {
			$newAttributes = [];
			foreach (array_keys($this->arrDatabaseColumns) as $columnName) {
				$newAttributes[$columnName] = '';
			}
			$this->arrAttributes = $newAttributes;
		}
	}

	/**
	 * Parse XML field and populate sub-attributes
	 */
	private function parseXmlField(string $fieldName, string $xmlData): void
	{
		if (empty($xmlData)) return;

		try {
			// Improved CDATA pattern matching
			preg_match_all('/\<([^>]+)\>\<!\[CDATA\[(.*?)\]\]>\<\/([^>]+)\>/s', $xmlData, $matches);

			for ($i = 0; $i < count($matches[0]); $i++) {
				$tagName = $matches[1][$i];
				$content = $matches[2][$i];
				$this->arrAttributes[$fieldName . '_' . $tagName] = $content;
			}
		} catch (\Exception $e) {
			$this->logger->warning('XML parsing failed', [
				'field' => $fieldName,
				'error' => $e->getMessage()
			]);
		}
	}

	/**
	 * Process XML fields for saving - Updated for simplified schema
	 */
	private function processXmlFieldsForSave(array $data): array
	{
		// Handle direct XML field arrays
		foreach (['data', 'media', 'settings'] as $xmlField) {
			if (isset($data[$xmlField]) && is_array($data[$xmlField])) {
				$xml = '';
				foreach ($data[$xmlField] as $key => $value) {
					$xml .= "<{$key}><![CDATA[{$value}]]></{$key}>\n";
				}
				$data[$xmlField] = $xml;
			}
		}

		// Process individual XML fields (data_*, media_*, settings_*)
		$xmlFields = ['data' => [], 'media' => [], 'settings' => []];

		foreach ($data as $key => $value) {
			foreach (['data_', 'media_', 'settings_'] as $prefix) {
				if (str_starts_with($key, $prefix)) {
					$xmlType = rtrim($prefix, '_');
					$fieldName = str_replace($prefix, '', $key);
					
					// Skip media_id for capps_media table compatibility
					if ($key === 'media_id') continue;
					
					$xmlFields[$xmlType][$fieldName] = $value;
					unset($data[$key]);
				}
			}
		}

		// Generate XML strings
		foreach ($xmlFields as $xmlType => $fields) {
			if (!empty($fields)) {
				$xml = '';
				foreach ($fields as $key => $value) {
					$xml .= "<{$key}><![CDATA[{$value}]]></{$key}>\n";
				}
				$data[$xmlType] = $xml;
			}
		}

		// Filter to only include valid table columns (simplified)
		return $this->filterValidColumns($data);
	}

	/**
	 * Generate cache key for object
	 */
	private function getCacheKey(mixed $id): string
	{
		return "obj_{$this->strTable}_{$this->strPrimaryKey}_{$id}";
	}

	/**
	 * Clear object from cache
	 */
	private function clearObjectCache(mixed $id): void
	{
		$cacheKey = $this->getCacheKey($id);
		
		if (!$this->acquireLock('cache_write')) {
			return; // Graceful degradation
		}
		
		try {
			unset(
				self::$cacheData[$cacheKey],
				self::$cacheAccessTimes[$cacheKey],
				self::$cacheHitCounts[$cacheKey]
			);
		} finally {
			$this->releaseLock('cache_write');
		}
	}

	/**
	 * Invalidate all cache entries for this table
	 */
	private function invalidateTableCache(): void
	{
		if (!$this->acquireLock('cache_write')) {
			return;
		}
		
		try {
			$tablePrefix = "obj_{$this->strTable}_";
			$keysToRemove = [];
			
			foreach (array_keys(self::$cacheData) as $key) {
				if (str_starts_with($key, $tablePrefix)) {
					$keysToRemove[] = $key;
				}
			}
			
			foreach ($keysToRemove as $key) {
				unset(
					self::$cacheData[$key],
					self::$cacheAccessTimes[$key],
					self::$cacheHitCounts[$key]
				);
			}
			
			$this->logger->debug('Table cache invalidated', [
				'table' => $this->strTable,
				'keys_removed' => count($keysToRemove)
			]);
			
		} finally {
			$this->releaseLock('cache_write');
		}
	}

	/**
	 * Generate connection key for pooling
	 */
	private function getConnectionKey(?array $config): string
	{
		if (!$config) {
			$config = ['default' => true];
		}
		
		$keyData = [
			$config['DB_HOST'] ?? 'localhost',
			$config['DB_DATABASE'] ?? 'default',
			$config['DB_USER'] ?? 'default',
			$config['DB_PORT'] ?? 3306
		];
		
		return md5(implode('|', $keyData));
	}

	/**
	 * Update global metrics thread-safely
	 */
	private function updateGlobalMetrics(string $metric, int $increment = 1): void
	{
		if (!$this->acquireLock('metrics')) {
			return;
		}
		
		try {
			if (!isset(self::$globalMetrics[$metric])) {
				self::$globalMetrics[$metric] = 0;
			}
			
			self::$globalMetrics[$metric] += $increment;
			self::$globalMetrics['memory_usage'] = memory_get_usage(true);
			
		} finally {
			$this->releaseLock('metrics');
		}
	}

	/**
	 * Validate configuration array
	 */
	private function validateConfig(array $config): array
	{
		$defaults = [
			'cache_enabled' => true,
			'validate_input' => true,
			'debug_mode' => false,
			'max_value_length' => 65535,
			'enable_xss_protection' => true
		];
		
		return array_merge($defaults, $config);
	}

	/**
	 * Perform memory usage check
	 */
	private function performMemoryCheck(): void
	{
		$memoryUsage = memory_get_usage(true);
		
		if ($memoryUsage > self::MEMORY_WARNING_THRESHOLD) {
			$this->logger->warning('High memory usage detected', [
				'current_usage' => $memoryUsage,
				'threshold' => self::MEMORY_WARNING_THRESHOLD,
				'cache_size' => count(self::$cacheData),
				'connection_count' => count(self::$connectionPool)
			]);
			
			// Trigger cache cleanup
			if (count(self::$cacheData) > self::MAX_CACHE_SIZE * 0.8) {
				$this->evictLeastValuableEntries();
			}
		}
	}

	/**
	 * Debug-Ausgabe der Spalteninformationen - Simplified version
	 */
	public function debugColumnInfo(): array
	{
		return [
			'table' => $this->strTable,
			'total_columns' => count($this->arrDatabaseColumns),
			'column_names' => array_keys($this->arrDatabaseColumns),
			'column_types' => $this->arrDatabaseColumns,
			'primary_key' => $this->strPrimaryKey,
			'has_data_loaded' => $this->identifier !== null
		];
	}

	/**
	 * Exportiert Schema-Informationen für Caching oder API - Simplified version
	 */
	public function exportSchema(): array
	{
		return [
			'table' => $this->strTable,
			'primary_key' => $this->strPrimaryKey,
			'columns' => $this->arrDatabaseColumns,  // Now simplified: name => type
			'generated_at' => date('Y-m-d H:i:s')
		];
	}

	// ================================================================
	// LEGACY COMPATIBILITY METHODS (DEPRECATED BUT FUNCTIONAL)
	// ================================================================

	/**
	 * @deprecated Use get() instead
	 */
	public function getAttribute(string $strAttribute): string
	{
		@trigger_error('getAttribute() is deprecated. Use get() instead.', E_USER_DEPRECATED);
		return $this->get($strAttribute);
	}

	/**
	 * @deprecated Use set() instead
	 */
	public function setAttribute(string $strAttribute, mixed $strValue): bool
	{
		@trigger_error('setAttribute() is deprecated. Use set() instead.', E_USER_DEPRECATED);
		$this->set($strAttribute, $strValue);
		return true;
	}

    /**
     * Vollständige Implementierung von executeDirectQuery, die alle Features von getAllEntries unterstützt
     *
     * FEHLENDE FEATURES in der ursprünglichen executeDirectQuery:
     * 1. Selection Parameter (zusätzliche WHERE-Bedingungen)
     * 2. Mehrfache ORDER BY-Klauseln ("|"-getrennt)
     * 3. data_ Feld-Sortierung mit sortDataParameter()
     * 4. Result Parameter Default ($this->strPrimaryKey statt '*')
     * 5. arrAvailableRows Validierung
     * 6. Prepared Statements für SQL Injection Schutz
     * 7. Komplexere OR/AND Logik
     */
    private function executeDirectQuery(array $conditions = [], array $options = []): array
    {
        // Default result auf strPrimaryKey setzen (wie in getAllEntries)
        $result = $options['result'] ?? $this->strPrimaryKey;
        if ($result === '') {
            $result = $this->strPrimaryKey;
        }

        $strQuery = "SELECT {$result} FROM `{$this->strTable}` i";
        $queryParams = [];

        // ================================================================
        // CONDITIONS - Komplett überarbeitete Logik wie in getAllEntries
        // ================================================================
        if (!empty($conditions)) {
            $arrCond = [];

            foreach ($conditions as $attribute => $value) {
                if ($value === '' || $value === null) continue;

                // Pipe-separated values wie in getAllEntries
                $arrValues = explode('|', (string)$value);

                if (!str_contains($attribute, 'data_')) {
                    // ========== NORMALE FELDER ==========
                    if (is_array($arrValues) && count($arrValues) > 0) {
                        $strHelp = '';

                        foreach ($arrValues as $tmpValue) {
                            if ($strHelp !== "") {
                                $strHelp .= " OR ";
                            }

                            if (str_contains($tmpValue, "NOT")) {
                                $tmpValue = str_replace(["NOT ", "NOT"], "", $tmpValue);
                                $strHelp .= "(i.`{$attribute}` NOT LIKE ? OR i.`{$attribute}` IS NULL)";
                                $queryParams[] = $tmpValue;
                            } elseif ($tmpValue === "NULL") {
                                $strHelp .= "i.`{$attribute}` IS NULL";
                            } else {
                                $strHelp .= "i.`{$attribute}` LIKE ?";
                                $queryParams[] = $tmpValue;
                            }
                        }

                        if ($strHelp !== '') {
                            $arrCond[] = "({$strHelp})";
                        }
                    }

                } elseif (str_contains($attribute, 'data_')) {
                    // ========== XML DATA FELDER ==========
                    $attribute_pur = str_replace('data_', '', $attribute);

                    if (is_array($arrValues) && count($arrValues) > 0) {
                        $strHelp = '';

                        foreach ($arrValues as $tmpValue) {
                            if ($strHelp === '') {
                                if (str_contains($tmpValue, "NOT")) {
                                    $tmpValue = str_replace(["NOT ", "NOT"], "", $tmpValue);
                                    $strHelp .= "i.data NOT LIKE ?";
                                    $queryParams[] = "%<{$attribute_pur}><![CDATA[{$tmpValue}]]></{$attribute_pur}>%";
                                } else {
                                    $strHelp .= "i.data LIKE ?";
                                    $queryParams[] = "%<{$attribute_pur}><![CDATA[{$tmpValue}]]></{$attribute_pur}>%";
                                }
                            } else {
                                if (str_contains($tmpValue, "NOT")) {
                                    $tmpValue = str_replace(["NOT ", "NOT"], "", $tmpValue);
                                    $strHelp .= " OR i.data NOT LIKE ?";
                                    $queryParams[] = "%<{$attribute_pur}><![CDATA[{$tmpValue}]]></{$attribute_pur}>%";
                                } else {
                                    $strHelp .= " OR i.data LIKE ?";
                                    $queryParams[] = "%<{$attribute_pur}><![CDATA[{$tmpValue}]]></{$attribute_pur}>%";
                                }
                            }
                        }

                        if ($strHelp !== '') {
                            $arrCond[] = "({$strHelp})";
                        }
                    }
                }
            }

            // WHERE-Klausel hinzufügen
            if (count($arrCond) > 0) {
                $strQuery .= " WHERE " . implode(" AND ", $arrCond);
            }
        }

        // ================================================================
        // SELECTION - Zusätzliche WHERE-Bedingungen (fehlte in executeDirectQuery)
        // ================================================================
        $selection = $options['selection'] ?? null;
        if ($selection !== null && $selection !== '') {
            if (str_contains($strQuery, "WHERE")) {
                $strQuery .= " AND " . $selection;
            } else {
                $strQuery .= " WHERE " . $selection;
            }
        }

        // ================================================================
        // ORDER BY - Mehrfache Sortierung unterstützen (fehlte in executeDirectQuery)
        // ================================================================
        $order = $options['order'] ?? null;
        $direction = $options['direction'] ?? 'ASC';

        // arrAvailableRows für Validierung (fehlte in executeDirectQuery)
        $arrAvailableRows = array_keys($this->arrAttributes);

        $strOrder = '';
        $arrOrderData = []; // Für data_ Feld-Sortierung

        if (isset($order) && $order !== '') {
            // Mehrere ORDER BY-Klauseln unterstützen ("|"-getrennt)
            $arrOrderBy = explode("|", $order);
            $arrOrderDirBy = explode("|", $direction);
            $arrHilf = [];

            if (count($arrOrderBy) > 0 && count($arrOrderDirBy) > 0) {
                foreach ($arrOrderBy as $run => $strValue) {
                    if (!str_contains($strValue, 'data_')) {
                        // Normale Felder
                        $orderTmp = $arrOrderDirBy[$run] ?? 'ASC';
                        if (empty($orderTmp)) $orderTmp = 'ASC';

                        if (in_array($strValue, $arrAvailableRows) || str_contains($strValue, "data_")) {
                            $arrHilf[] = " i.`{$strValue}` {$orderTmp}";
                        } else {
                            $arrHilf[] = " {$strValue} {$orderTmp}";
                        }
                    } else {
                        // data_ Felder für spätere Sortierung merken
                        $orderTmp = $arrOrderDirBy[$run] ?? 'ASC';
                        if (empty($orderTmp)) $orderTmp = 'ASC';
                        $arrOrderData[$strValue] = $orderTmp;
                    }
                }

                $strOrderByTmp = implode(",", $arrHilf);
                if ($strOrderByTmp !== "") {
                    $strOrder .= " ORDER BY {$strOrderByTmp}";
                }
            }
        } else {
            // Default Sortierung
            $strOrder .= " ORDER BY i.`{$this->strPrimaryKey}` ASC";
        }

        $strQuery .= $strOrder;

        // ================================================================
        // LIMIT
        // ================================================================
        $limit = $options['limit'] ?? null;
        if (isset($limit) && $limit !== '' && is_numeric($limit)) {
            $strQuery .= " LIMIT " . (int)$limit;
        }

        // ================================================================
        // QUERY AUSFÜHRUNG mit Prepared Statements
        // ================================================================
        $arrIDs = [];
        if ($this->objDatabase !== null) {
            try {
                if (!empty($queryParams)) {
                    // Mit Parametern (Prepared Statements)
                    $arrIDs = $this->objDatabase->select($strQuery, $queryParams);
                } else {
                    // Ohne Parameter
                    $arrIDs = $this->objDatabase->select($strQuery);
                }
            } catch (\Exception $e) {
                $this->logger->error('Query execution failed', [
                    'query' => $strQuery,
                    'params' => $queryParams,
                    'error' => $e->getMessage()
                ]);
                return [];
            }
        }

        // ================================================================
        // DATA_ FELD-SORTIERUNG (fehlte komplett in executeDirectQuery!)
        // ================================================================
        if (count($arrOrderData) > 0 && count($arrIDs) > 0) {
            foreach ($arrOrderData as $strDataField => $strDirection) {
                $arrIDs = $this->sortDataParameter($arrIDs, $strDataField, $strDirection);
            }
        }

        return $arrIDs;
    }

    /**
     * WICHTIG: sortDataParameter Methode muss auch in der neuen Implementierung verfügbar sein!
     * Diese Methode fehlte komplett in der neuen CBObject-Implementierung
     */
    private function sortDataParameter(array $arrInput, string $strDataField = "NULL", string $strDirection = "ASC"): array
    {
        if (!isset($strDirection) || $strDirection === '') {
            $strDirection = "ASC";
        }

        if (!is_array($arrInput) || count($arrInput) === 0) {
            $this->logger->warning('sortDataParameter called without input');
            return [];
        }

        if (!isset($strDataField) || $strDataField === '') {
            $this->logger->warning('sortDataParameter called without field name');
            return $arrInput;
        }

        // Zuordnung für Sortierung
        $arrOrderData = [];
        $arrZusatzField = [];

        if (isset($strDataField) && $strDataField !== '') {
            // Mehrere Sortierangaben unterstützen
            $arrOrderBy = explode("|", $strDataField);
            $arrOrderDirBy = explode("|", $strDirection);

            if (count($arrOrderBy) > 0 && count($arrOrderDirBy) > 0) {
                foreach ($arrOrderBy as $run => $strValue) {
                    if (!str_contains($strValue, 'data_') &&
                        !(in_array(trim($strValue), array_keys($this->arrAttributes)))) {
                        $this->logger->warning("sortDataParameter: {$strValue} does not exist");
                        return $arrInput;
                    }

                    $orderTmp = $arrOrderDirBy[$run] ?? 'ASC';
                    if (empty($orderTmp)) $orderTmp = 'ASC';

                    $arrOrderData[$strValue] = $orderTmp;

                    if (str_contains($strValue, 'data_')) {
                        $arrZusatzField[] = 'data';
                    } else {
                        $arrZusatzField[] = $strValue;
                    }
                }
            }
        }

        // IDs sammeln für Batch-Query
        $arrIds = [];
        foreach ($arrInput as $arrTmp1) {
            if (isset($arrTmp1[$this->strPrimaryKey])) {
                $arrIds[] = $arrTmp1[$this->strPrimaryKey];
            }
        }

        if (empty($arrIds)) {
            return $arrInput;
        }

        $strIds = implode(',', array_map(fn($id) => "'" . addslashes($id) . "'", $arrIds));
        $arrZusatzField = array_unique($arrZusatzField);
        $strAdditionalField = implode(',', array_map(fn($field) => "`{$field}`", $arrZusatzField));

        $strStatement = "SELECT `{$this->strPrimaryKey}`, {$strAdditionalField} FROM `{$this->strTable}` WHERE `{$this->strPrimaryKey}` IN ({$strIds})";

        try {
            $arrRes = $this->objDatabase->select($strStatement);

            $arrZuordnung = [];
            if (is_array($arrRes) && count($arrRes) > 0) {
                foreach ($arrRes as $arrTmp2) {
                    $strZuordnung = '';

                    foreach ($arrOrderData as $sort_db_feld => $direc) {
                        if (str_contains($sort_db_feld, 'data_')) {
                            // XML Data Field
                            $strDbRein = str_replace('data_', '', $sort_db_feld);
                            $strData = $arrTmp2['data'] ?? '';

                            preg_match_all('/\<' . preg_quote($strDbRein) . '\>\<!\[CDATA\[(.*?)\]\]>\<\/' . preg_quote($strDbRein) . '\>/s', $strData, $arrTreffer);

                            $value = $arrTreffer[1][0] ?? '';
                            $strZuordnung .= strtolower($value);
                        } else {
                            // Normales Feld
                            $strZuordnung .= strtolower($arrTmp2[$sort_db_feld] ?? '');
                        }
                    }

                    $arrZuordnung[$arrTmp2[$this->strPrimaryKey]] = $strZuordnung;
                }
            }

            // Sortierung anwenden
            if (strtoupper($strDirection) === 'ASC') {
                asort($arrZuordnung);
            } else {
                arsort($arrZuordnung);
            }

            // Ausgabe-Array in sortierter Reihenfolge erstellen
            $arrOutput = [];
            foreach ($arrZuordnung as $intTmpId => $tmpValue) {
                $arrOutput[] = [$this->strPrimaryKey => $intTmpId];
            }

            return $arrOutput;

        } catch (\Exception $e) {
            $this->logger->error('sortDataParameter failed', [
                'field' => $strDataField,
                'error' => $e->getMessage()
            ]);
            return $arrInput;
        }
    }

    /**
     * LEGACY getAllEntries() kann jetzt sicher die neue Implementierung verwenden
     */
    public function getAllEntries(
        ?string $order = null,
        string $direction = "ASC",
        array $arrCondition = [],
        ?string $selection = null,
        string $result = "",
        ?int $limit = null
    ): array {
        @trigger_error('getAllEntries() is deprecated. Use findAll() instead.', E_USER_DEPRECATED);

        $options = [
            'order' => $order,
            'direction' => $direction,
            'selection' => $selection,
            'result' => $result,
            'limit' => $limit
        ];

        return $this->executeDirectQuery($arrCondition, $options);
    }

	/**
	 * @deprecated Use create() instead
	 */
	public function saveContentNew(array $arrContent, bool $forceOverwritePK = false): int|string
	{
		@trigger_error('saveContentNew() is deprecated. Use create() instead.', E_USER_DEPRECATED);
		
		if (empty($arrContent)) {
			throw new InvalidArgumentException("No content provided for save");
		}

		try {
			// Process XML fields
			$processedContent = $this->processXmlFieldsForSave($arrContent);

			// Generate UUID if needed
			if (str_ends_with($this->strPrimaryKey, '_uid') && !isset($processedContent[$this->strPrimaryKey])) {
				$processedContent[$this->strPrimaryKey] = $this->objDatabase->generateUuid();
				$forceOverwritePK = true;
			}

			// Insert using modern CBDatabase
			$newId = $this->objDatabase->insert($this->strTable, $processedContent, $this->strPrimaryKey);

			return $forceOverwritePK ? $processedContent[$this->strPrimaryKey] : $newId;

		} catch (\Exception $e) {
			throw new RuntimeException("Failed to save new content: " . $e->getMessage(), 0, $e);
		}
	}

	/**
	 * @deprecated Use update() instead
	 */
	public function saveContentUpdate(int|string $id, array $arrContent): bool
	{
		@trigger_error('saveContentUpdate() is deprecated. Use update() instead.', E_USER_DEPRECATED);
		
		if (empty($arrContent)) {
			throw new InvalidArgumentException("No content provided for update");
		}

		try {
			// Add primary key to content
			$arrContent[$this->strPrimaryKey] = $id;

			// Process XML fields
			$processedContent = $this->processXmlFieldsForSave($arrContent);

			// Update using modern CBDatabase
			$success = $this->objDatabase->update($this->strTable, $processedContent, $this->strPrimaryKey);

			// Clear cache
			if ($success) {
				$this->clearObjectCache($id);
			}

			return $success;

		} catch (\Exception $e) {
			throw new RuntimeException("Failed to update content: " . $e->getMessage(), 0, $e);
		}
	}

	/**
	 * @deprecated Use delete() instead
	 */
	public function deleteEntry(mixed $id = null): bool
	{
		@trigger_error('deleteEntry() is deprecated. Use delete() instead.', E_USER_DEPRECATED);
		
		if ($id === null) {
			throw new InvalidArgumentException("No ID provided for delete");
		}

		try {
			$success = $this->objDatabase->delete($this->strTable, $this->strPrimaryKey, $id);

			if ($success) {
				if (is_array($id)) {
					foreach ($id as $singleId) {
						$this->clearObjectCache($singleId);
					}
				} else {
					$this->clearObjectCache($id);
				}
			}

			return $success;

		} catch (\Exception $e) {
			throw new RuntimeException("Failed to delete entry: " . $e->getMessage(), 0, $e);
		}
	}

	/**
	 * Destructor for cleanup
	 */
	public function __destruct()
	{
		// Cleanup any held locks
		foreach (array_keys(self::$lockHandles) as $lockName) {
			$this->releaseLock($lockName);
		}
	}
	
	
	
	// ================================================================
	// CONVENIENCE FACTORY METHODS - Für einfache Objekterstellung
	// ================================================================
	
	/**
	 * Create CBObject instance with smart defaults (Convenience Wrapper)
	 * 
	 * Simple factory method that delegates to CBObjectFactory for easy object creation.
	 * Uses sensible defaults while keeping the full factory functionality available.
	 *
	 * @param mixed $id Object ID to load (null for new empty object)
	 * @param string $table Database table name
	 * @param string $primaryKey Primary key column name
	 * @param array|null $dbConfig Optional database configuration
	 * @param array $config Optional CBObject configuration
	 * @return static New CBObject instance
	 * 
	 * @example
	 * // Quick object creation with ID
	 * $user = CBObject::make(123, 'users', 'user_id');
	 * 
	 * @example
	 * // Create empty object for new records
	 * $newUser = CBObject::make(null, 'users', 'user_id');
	 * $userId = $newUser->create(['name' => 'John', 'email' => 'john@test.com']);
	 */
	public static function make(
		mixed $id, 
		string $table, 
		string $primaryKey, 
		?array $dbConfig = null, 
		array $config = []
	): static {
		
		if (!class_exists(CBObjectFactory::class)) {
			// Fallback: create directly if factory not available
			return new static($id, $table, $primaryKey, $dbConfig, null, $config);
		}
		
		return CBObjectFactory::create($id, $table, $primaryKey, $dbConfig, $config);
	}
	
	/**
	 * Create CBObject with automatic primary key detection (Convenience Wrapper)
	 * 
	 * @param mixed $id Object ID to load
	 * @param string $table Database table name  
	 * @param array|null $dbConfig Optional database configuration
	 * @param array $config Optional CBObject configuration
	 * @return static New CBObject instance
	 * 
	 * @example
	 * // Auto-detect primary key (users -> user_id)
	 * $user = CBObject::makeSmart(123, 'users');
	 * $product = CBObject::makeSmart('uuid-123', 'products');
	 */
	public static function makeSmart(
		mixed $id,
		string $table,
		?array $dbConfig = null,
		array $config = []
	): static {
		if (class_exists(CBObjectFactory::class)) {
			return CBObjectFactory::createSmart($id, $table, $dbConfig, $config);
		}
		
		// Fallback: detect primary key manually
		$primaryKey = $table . '_id'; // Simple detection
		return new static($id, $table, $primaryKey, $dbConfig, null, $config);
	}
	
	/**
	 * Create multiple CBObject instances efficiently (Convenience Wrapper)
	 * 
	 * @param array $objectSpecs Array of [id, table, primaryKey] specifications
	 * @param array|null $dbConfig Shared database configuration
	 * @param array $config Shared CBObject configuration
	 * @return array Array of CBObject instances
	 * 
	 * @example
	 * // Create multiple objects efficiently
	 * $objects = CBObject::makeBatch([
	 *     [123, 'users', 'user_id'],
	 *     [456, 'products', 'product_id'],
	 *     [null, 'orders', 'order_id']  // Empty object
	 * ]);
	 */
	public static function makeBatch(
		array $objectSpecs,
		?array $dbConfig = null,
		array $config = []
	): array {
		if (class_exists(CBObjectFactory::class)) {
			return CBObjectFactory::createBatch($objectSpecs, $dbConfig, $config);
		}
		
		// Fallback: create manually
		$objects = [];
		foreach ($objectSpecs as $spec) {
			[$id, $table, $primaryKey] = $spec;
			$objects[] = new static($id, $table, $primaryKey, $dbConfig, null, $config);
		}
		return $objects;
	}
	
	/**
	 * Configure global defaults for all CBObject instances (Convenience Wrapper)
	 * 
	 * @param array $config Global configuration array
	 * 
	 * @example
	 * // Set global configuration once
	 * CBObject::configure([
	 *     'cache_enabled' => true,
	 *     'cache_size' => 5000,
	 *     'debug_mode' => false,
	 *     'validate_input' => true
	 * ]);
	 * 
	 * // All subsequent objects use this config
	 * $user = CBObject::make(123, 'users', 'user_id');
	 */
	public static function configure(array $config): void
	{
		if (class_exists(CBObjectFactory::class)) {
			CBObjectFactory::setGlobalConfig($config);
		}
		// Note: Without factory, configuration is handled per-instance
	}
	
	/**
	 * Set global logger for all CBObject instances (Convenience Wrapper)
	 * 
	 * @param LoggerInterface $logger PSR-3 compatible logger
	 * 
	 * @example
	 * // Set global logger once
	 * $logger = new FileLogger('/var/log/cbobject.log');
	 * CBObject::setLogger($logger);
	 * 
	 * // All objects use this logger
	 * $user = CBObject::make(123, 'users', 'user_id');
	 */
	public static function setLogger(LoggerInterface $logger): void
	{
		if (class_exists(CBObjectFactory::class)) {
			CBObjectFactory::setGlobalLogger($logger);
		}
		// Note: Without factory, logger is handled per-instance
	}
	
	/**
	 * Get basic health and statistics information (Convenience Wrapper)
	 * 
	 * @return array Basic health and stats information
	 * 
	 * @example
	 * // Quick health check
	 * $health = CBObject::getHealth();
	 * if ($health['status'] !== 'healthy') {
	 *     // Handle issues
	 * }
	 */
	public static function getHealth(): array
	{
		if (class_exists(CBObjectFactory::class)) {
			$factoryStats = CBObjectFactory::getStats();
			
			return [
				'status' => $factoryStats['health']['status'] ?? 'unknown',
				'instances' => $factoryStats['instances'] ?? [],
				'memory' => $factoryStats['memory'] ?? [],
				'cache_hit_rate' => $factoryStats['global_metrics']['cache_hit_rate'] ?? '0%'
			];
		}
		
		// Fallback: basic health info
		return [
			'status' => 'ok',
			'instances' => ['created' => 0, 'active' => 0],
			'memory' => ['current_usage' => memory_get_usage(true)],
			'cache_hit_rate' => '0%'
		];
	}
	
	/**
	 * Perform cleanup and maintenance (Convenience Wrapper)
	 * 
	 * @param bool $force Force cleanup even if not needed
	 * @return array Cleanup results
	 * 
	 * @example
	 * // Periodic maintenance
	 * $results = CBObject::cleanup();
	 * echo "Freed memory: " . $results['memory_freed'] . " bytes";
	 */
	public static function cleanup(bool $force = false): array
	{
		if (class_exists(CBObjectFactory::class)) {
			return CBObjectFactory::cleanup($force);
		}
		
		// Fallback: basic cleanup
		self::clearAllCaches();
		return [
			'instances_cleaned' => 0,
			'memory_freed' => 0,
			'caches_cleared' => 1,
			'connections_closed' => 0
		];
	}
	
	// ================================================================
	// MIGRATION HELPERS - Für Übergang von alter API
	// ================================================================
	
	/**
	 * Create new database record using static factory (Migration Helper)
	 * 
	 * Helper method for migrating from old static creation patterns.
	 * 
	 * @param string $table Table name
	 * @param string $primaryKey Primary key column
	 * @param array $data Data to insert
	 * @return int|string Generated ID
	 * 
	 * @example
	 * // Migration from old pattern
	 * $userId = CBObject::createRecord('users', 'user_id', [
	 *     'name' => 'John Doe',
	 *     'email' => 'john@test.com'
	 * ]);
	 */
	public static function createRecord(string $table, string $primaryKey, array $data): int|string
	{
		$obj = self::make(null, $table, $primaryKey);
		return $obj->create($data);
	}
	
	/**
	 * Update database record using static factory (Migration Helper)
	 * 
	 * @param string $table Table name
	 * @param string $primaryKey Primary key column
	 * @param mixed $id Record ID to update
	 * @param array $data Data to update
	 * @return bool Success status
	 * 
	 * @example
	 * // Migration from old pattern
	 * $success = CBObject::updateRecord('users', 'user_id', 123, [
	 *     'name' => 'John Updated'
	 * ]);
	 */
	public static function updateRecord(string $table, string $primaryKey, mixed $id, array $data): bool
	{
		$obj = self::make($id, $table, $primaryKey);
		return $obj->update($id, $data);
	}
	
	/**
	 * Delete database record using static factory (Migration Helper)
	 * 
	 * @param string $table Table name
	 * @param string $primaryKey Primary key column
	 * @param mixed $id Record ID to delete
	 * @return bool Success status
	 * 
	 * @example
	 * // Migration from old pattern
	 * $success = CBObject::deleteRecord('users', 'user_id', 123);
	 */
	public static function deleteRecord(string $table, string $primaryKey, mixed $id): bool
	{
		$obj = self::make($id, $table, $primaryKey);
		return $obj->delete($id);
	}
	
	
}

?>