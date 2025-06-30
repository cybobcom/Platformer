<?php

declare(strict_types=1);

namespace Capps\Modules\Database\Classes;

use PDO;
use PDOException;
use PDOStatement;
use InvalidArgumentException;
use RuntimeException;

//use Capps\Modules\Database\Classes\SecurityValidator;


/**
 * Production-Ready Database Handler
 * 
 * Thread-safe, secure, and high-performance database operations with:
 * - Comprehensive input validation (always-on)
 * - File-based locking for thread safety (no PECL dependencies)
 * - Resource monitoring and health checks
 * - Bulk operations for performance
 * - Circuit breaker pattern for resilience
 * - KEINE dauerhafte Credential-Speicherung (SICHERHEIT!)
 * 
 * @version 3.1 Production-Ready (No Dependencies)
 */
class CBDatabase
{
	private ?PDO $connection = null;
	private string $database;
	private string $mainDatabase;
	
	// SICHERHEIT: Nur nicht-sensitive Verbindungsinformationen speichern
	private array $connectionInfo = [];
	
	// Thread-safe caches with file-based locking
	private static array $statementCache = [];
	private static array $tableMetaCache = [];
	private static array $lockHandles = [];
	
	// Connection pool with health monitoring
	private static array $connectionPool = [];
	private static array $connectionHealth = [];
	
	// Circuit breaker for resilience
	private static array $circuitBreakers = [];
	
	// Security and validation
	private SecurityValidator $securityValidator;
	private const MAX_QUERY_LENGTH = 1000000; // 1MB
	private const MAX_PARAM_COUNT = 1000;
	
	// Resource limits
	private const MAX_CONNECTIONS = 20;
	private const MAX_CACHE_SIZE = 5000;
	private const CONNECTION_TIMEOUT = 30;
	private const HEALTH_CHECK_INTERVAL = 300; // 5 minutes
	private const LOCK_TIMEOUT = 5.0; // 5 seconds

	public function __construct(?array $config = null)
	{
		$validatedConfig = $this->validateAndNormalizeConfig($config ?? $this->getDefaultConfig());
		$this->securityValidator = new \capps\modules\database\classes\SecurityValidator(['validate_input' => true]);
		
		// Sichere Verbindung herstellen
		$this->connectSecurely($validatedConfig);
		
		// NUR nicht-sensitive Daten speichern
		$this->storeNonSensitiveInfo($validatedConfig);
		
		// Sensitive Daten sofort aus dem Speicher entfernen
		$this->clearSensitiveData($validatedConfig);
	}

	/**
	 * File-based locking for thread safety (replaces SyncMutex)
	 */
	private function acquireLock(string $lockName): bool
	{
		$lockFile = sys_get_temp_dir() . '/cbdatabase_' . md5($lockName) . '.lock';
		
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
	 * Sichere Verbindung ohne dauerhafte Credential-Speicherung
	 */
	private function connectSecurely(array $config): void
	{
		$connectionKey = $this->getConnectionKey($config);
		
		if (!$this->acquireLock('connection_pool')) {
			// Fallback: create new connection if can't acquire lock
			$this->connection = $this->createSecureConnection($config);
			return;
		}
		
		try {
			// Check if healthy connection exists
			if (isset(self::$connectionPool[$connectionKey])) {
				$connection = self::$connectionPool[$connectionKey];
				if ($this->isConnectionHealthy($connection)) {
					$this->connection = $connection;
					self::$connectionHealth[$connectionKey] = time();
					$this->database = $config['DB_DATABASE'];
					$this->mainDatabase = $config['DB_DATABASE'];
					return;
				}
				// Remove unhealthy connection
				unset(self::$connectionPool[$connectionKey], self::$connectionHealth[$connectionKey]);
			}
			
			// Enforce connection limits
			if (count(self::$connectionPool) >= self::MAX_CONNECTIONS) {
				$this->evictOldestConnection();
			}
			
			// Create new secure connection
			$connection = $this->createSecureConnection($config);
			
			// Add to pool
			self::$connectionPool[$connectionKey] = $connection;
			self::$connectionHealth[$connectionKey] = time();
			$this->connection = $connection;
			$this->database = $config['DB_DATABASE'];
			$this->mainDatabase = $config['DB_DATABASE'];
			
		} finally {
			$this->releaseLock('connection_pool');
		}
	}

	/**
	 * Create secure PDO connection with automatic credential cleanup
	 */
	private function createSecureConnection(array $config): PDO
	{
		// Temporäre Variablen für Verbindung (werden nach Verwendung gelöscht)
		$host = $this->securityValidator->validateHost($config['DB_HOST']);
		$port = $this->securityValidator->validatePort($config['DB_PORT'] ?? 3306);
		$database = $this->securityValidator->validateDatabaseName($config['DB_DATABASE']);
		$username = $this->securityValidator->validateUsername($config['DB_USER']);
		$password = $config['DB_PASSWORD']; // Wird nur temporär verwendet
		$charset = $this->securityValidator->validateCharset($config['DB_CHARSET'] ?? 'utf8mb4');

		$dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

		try {
			$options = [
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
				PDO::ATTR_EMULATE_PREPARES => false,
				PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}",
				PDO::ATTR_TIMEOUT => self::CONNECTION_TIMEOUT,
				// Security hardening
				PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
				PDO::MYSQL_ATTR_MULTI_STATEMENTS => false, // Prevent SQL injection via multiple statements
			];

			$connection = new PDO($dsn, $username, $password, $options);
			
			// Additional security settings
			$connection->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'");
			$connection->exec("SET SESSION autocommit = 1");
			
			// SICHERHEIT: Lokale Variablen mit Credentials sofort überschreiben
			$password = str_repeat('X', strlen($password ?? ''));
			$username = str_repeat('X', strlen($username ?? ''));
			$password = null;
			$username = null;
			unset($password, $username);
			
			return $connection;
			
		} catch (PDOException $e) {
			// Sanitize error message to prevent information disclosure
			$sanitizedMessage = $this->sanitizeErrorMessage($e->getMessage());
			throw new RuntimeException('Database connection failed: ' . $sanitizedMessage);
		}
	}

	/**
	 * Nur nicht-sensitive Verbindungsinformationen speichern
	 */
	private function storeNonSensitiveInfo(array $config): void
	{
		$this->connectionInfo = [
			'host' => $config['DB_HOST'],
			'database' => $config['DB_DATABASE'],
			'charset' => $config['DB_CHARSET'] ?? 'utf8mb4',
			'port' => $config['DB_PORT'] ?? 3306,
			'connected_at' => date('Y-m-d H:i:s'),
			'connection_id' => uniqid('conn_', true)
		];
		
		// WICHTIG: Keine Benutzerdaten oder Passwörter!
	}
	
	/**
	 * Sensitive Daten sicher aus dem Speicher entfernen
	 */
	private function clearSensitiveData(array &$config): void
	{
		// Überschreiben mit Random-Daten (gegen Memory-Forensik)
		if (isset($config['DB_PASSWORD'])) {
			$config['DB_PASSWORD'] = bin2hex(random_bytes(32));
			unset($config['DB_PASSWORD']);
		}
		
		if (isset($config['DB_USER'])) {
			$config['DB_USER'] = bin2hex(random_bytes(16));
			unset($config['DB_USER']);
		}
		
		// Array komplett leeren
		$config = [];
		unset($config);
		
		// Garbage Collection erzwingen
		if (function_exists('gc_collect_cycles')) {
			gc_collect_cycles();
		}
	}

	/**
	 * Thread-safe SELECT operations with comprehensive security
	 */
	public function select(string $query, array $params = []): array
	{
		// Comprehensive input validation
		$this->securityValidator->validateQuery($query, 'SELECT');
		$this->securityValidator->validateParameters($params);
		
		// Circuit breaker check
		$this->checkCircuitBreaker('select');
		
		try {
			$stmt = $this->prepareSecureStatement($query);
			$this->executeWithMetrics($stmt, $params, 'SELECT');
			
			$result = $stmt->fetchAll();
			$this->recordCircuitBreakerSuccess('select');
			
			return $result;
			
		} catch (\Exception $e) {
			$this->recordCircuitBreakerFailure('select');
			throw new RuntimeException('Select query failed: ' . $this->sanitizeErrorMessage($e->getMessage()));
		}
	}
	
	/**
	 * Thread-safe SHOW operations with comprehensive security
	 */
	public function show(string $query, array $params = []): array
	{
		// Comprehensive input validation
		$this->securityValidator->validateQuery($query, 'SHOW');
		$this->securityValidator->validateParameters($params);
		
		// Circuit breaker check
		$this->checkCircuitBreaker('show');
		
		try {
			$stmt = $this->prepareSecureStatement($query);
			$this->executeWithMetrics($stmt, $params, 'SHOW');
			
			$result = $stmt->fetchAll();
			$this->recordCircuitBreakerSuccess('show');
			
			return $result;
			
		} catch (\Exception $e) {
			$this->recordCircuitBreakerFailure('show');
			throw new RuntimeException('Select query failed: ' . $this->sanitizeErrorMessage($e->getMessage()));
		}
	}

	/**
	 * Secure single row SELECT
	 */
	public function selectOne(string $query, array $params = []): ?array
	{
		$result = $this->select($query, $params);
		return $result[0] ?? null;
	}

	/**
	 * Secure bulk insert operation for high performance
	 */
	public function bulkInsert(string $table, array $records, string $primaryKey = 'id'): array
	{
		if (empty($records)) {
			throw new InvalidArgumentException('No records provided for bulk insert');
		}
		
		$this->securityValidator->validateTableName($table);
		
		// Validate all records have same structure
		$firstRecord = reset($records);
		$columns = array_keys($firstRecord);
		
		foreach ($records as $index => $record) {
			if (array_keys($record) !== $columns) {
				throw new InvalidArgumentException("Record at index {$index} has different structure");
			}
			// Validate each record
			foreach ($record as $key => $value) {
				$this->securityValidator->validateColumnName($key);
				$this->securityValidator->validateValue($value, $key);
			}
		}
		
		// Get table metadata for validation
		$tableColumns = $this->getTableColumns($table);
		$validColumns = array_column($tableColumns, 'Field');
		
		// Filter only valid columns
		$validRecordColumns = array_intersect($columns, $validColumns);
		if (empty($validRecordColumns)) {
			throw new InvalidArgumentException('No valid columns found for bulk insert');
		}
		
		// Generate UUIDs for _uid primary keys
		if (str_ends_with($primaryKey, '_uid')) {
			foreach ($records as &$record) {
				if (!isset($record[$primaryKey])) {
					$record[$primaryKey] = $this->generateUuid();
				}
			}
		}
		
		return $this->executeBulkInsert($table, $records, $validRecordColumns, $primaryKey);
	}

	/**
	 * Execute bulk insert with batching for memory efficiency
	 */
	private function executeBulkInsert(string $table, array $records, array $columns, string $primaryKey): array
	{
		$batchSize = 1000; // Process in batches to prevent memory issues
		$insertedIds = [];
		
		$this->beginTransaction();
		
		try {
			$batches = array_chunk($records, $batchSize);
			
			foreach ($batches as $batch) {
				$placeholders = [];
				$params = [];
				
				foreach ($batch as $record) {
					$recordPlaceholders = [];
					foreach ($columns as $column) {
						$recordPlaceholders[] = '?';
						$params[] = $record[$column] ?? null;
					}
					$placeholders[] = '(' . implode(',', $recordPlaceholders) . ')';
				}
				
				$sql = sprintf(
					'INSERT INTO `%s` (`%s`) VALUES %s',
					$table,
					implode('`, `', $columns),
					implode(', ', $placeholders)
				);
				
				$stmt = $this->connection->prepare($sql);
				$stmt->execute($params);
				
				// Collect inserted IDs
				if (str_ends_with($primaryKey, '_uid')) {
					// For UUIDs, return the generated ones
					foreach ($batch as $record) {
						$insertedIds[] = $record[$primaryKey];
					}
				} else {
					// For auto-increment, calculate range
					$firstId = $this->connection->lastInsertId();
					for ($i = 0; $i < count($batch); $i++) {
						$insertedIds[] = $firstId + $i;
					}
				}
			}
			
			$this->commit();
			return $insertedIds;
			
		} catch (\Exception $e) {
			$this->rollback();
			throw new RuntimeException('Bulk insert failed: ' . $this->sanitizeErrorMessage($e->getMessage()));
		}
	}

	/**
	 * Secure bulk update operation
	 */
	public function bulkUpdate(string $table, array $updates, string $primaryKey = 'id'): bool
	{
		if (empty($updates)) {
			throw new InvalidArgumentException('No updates provided for bulk update');
		}
		
		$this->securityValidator->validateTableName($table);
		$this->securityValidator->validateColumnName($primaryKey);
		
		// Validate all updates
		foreach ($updates as $update) {
			if (!isset($update[$primaryKey])) {
				throw new InvalidArgumentException('Primary key missing in update data');
			}
			foreach ($update as $key => $value) {
				$this->securityValidator->validateColumnName($key);
				$this->securityValidator->validateValue($value, $key);
			}
		}
		
		$this->beginTransaction();
		
		try {
			foreach ($updates as $update) {
				$id = $update[$primaryKey];
				unset($update[$primaryKey]);
				
				if (!empty($update)) {
					$this->update($table, array_merge($update, [$primaryKey => $id]), $primaryKey);
				}
			}
			
			$this->commit();
			return true;
			
		} catch (\Exception $e) {
			$this->rollback();
			throw new RuntimeException('Bulk update failed: ' . $this->sanitizeErrorMessage($e->getMessage()));
		}
	}

	/**
	 * Secure bulk delete operation
	 */
	public function bulkDelete(string $table, string $primaryKey, array $ids): bool
	{
		if (empty($ids)) {
			throw new InvalidArgumentException('No IDs provided for bulk delete');
		}
		
		$this->securityValidator->validateTableName($table);
		$this->securityValidator->validateColumnName($primaryKey);
		
		// Validate all IDs
		foreach ($ids as $id) {
			$this->securityValidator->validateValue($id, $primaryKey);
		}
		
		// Batch deletes to prevent memory issues
		$batchSize = 1000;
		$batches = array_chunk($ids, $batchSize);
		
		$this->beginTransaction();
		
		try {
			foreach ($batches as $batch) {
				$placeholders = str_repeat('?,', count($batch) - 1) . '?';
				$sql = "DELETE FROM `{$table}` WHERE `{$primaryKey}` IN ({$placeholders})";
				
				$stmt = $this->connection->prepare($sql);
				$stmt->execute($batch);
			}
			
			$this->commit();
			return true;
			
		} catch (\Exception $e) {
			$this->rollback();
			throw new RuntimeException('Bulk delete failed: ' . $this->sanitizeErrorMessage($e->getMessage()));
		}
	}

	/**
	 * Thread-safe statement preparation with caching
	 */
	private function prepareSecureStatement(string $query): PDOStatement
	{
		$hash = hash('sha256', $query);
		
		if (!$this->acquireLock('statement_cache')) {
			// Fallback: prepare without caching
			return $this->connection->prepare($query);
		}
		
		try {
			// Check cache size and evict if necessary
			if (count(self::$statementCache) >= self::MAX_CACHE_SIZE) {
				$this->evictLeastUsedStatements();
			}
			
			if (!isset(self::$statementCache[$hash])) {
				self::$statementCache[$hash] = [
					'statement' => $this->connection->prepare($query),
					'last_used' => time(),
					'use_count' => 0
				];
			}
			
			self::$statementCache[$hash]['last_used'] = time();
			self::$statementCache[$hash]['use_count']++;
			
			return self::$statementCache[$hash]['statement'];
			
		} finally {
			$this->releaseLock('statement_cache');
		}
	}

	/**
	 * Execute statement with performance metrics
	 */
	private function executeWithMetrics(PDOStatement $stmt, array $params, string $operation): void
	{
		$startTime = microtime(true);
		
		try {
			$stmt->execute($params);
			
			$executionTime = microtime(true) - $startTime;
			$this->recordMetrics($operation, $executionTime, true);
			
		} catch (\Exception $e) {
			$executionTime = microtime(true) - $startTime;
			$this->recordMetrics($operation, $executionTime, false);
			throw $e;
		}
	}

	/**
	 * Check connection health
	 */
	private function isConnectionHealthy(PDO $connection): bool
	{
		try {
			$stmt = $connection->query('SELECT 1');
			return $stmt !== false;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Evict oldest connection from pool
	 */
	private function evictOldestConnection(): void
	{
		if (empty(self::$connectionHealth)) {
			return;
		}
		
		$oldestKey = array_keys(self::$connectionHealth, min(self::$connectionHealth))[0];
		unset(self::$connectionPool[$oldestKey], self::$connectionHealth[$oldestKey]);
	}

	/**
	 * Evict least used statements from cache
	 */
	private function evictLeastUsedStatements(): void
	{
		// Sort by use count and last used time
		uasort(self::$statementCache, function($a, $b) {
			if ($a['use_count'] === $b['use_count']) {
				return $a['last_used'] <=> $b['last_used'];
			}
			return $a['use_count'] <=> $b['use_count'];
		});
		
		// Remove 20% of entries
		$removeCount = (int)(count(self::$statementCache) * 0.2);
		$keys = array_keys(self::$statementCache);
		
		for ($i = 0; $i < $removeCount; $i++) {
			unset(self::$statementCache[$keys[$i]]);
		}
	}

	/**
	 * Circuit breaker implementation
	 */
	private function checkCircuitBreaker(string $operation): void
	{
		if (!isset(self::$circuitBreakers[$operation])) {
			self::$circuitBreakers[$operation] = [
				'failures' => 0,
				'last_failure' => 0,
				'state' => 'closed' // closed, open, half-open
			];
		}
		
		$breaker = &self::$circuitBreakers[$operation];
		
		if ($breaker['state'] === 'open') {
			// Check if enough time has passed to try again
			if (time() - $breaker['last_failure'] > 60) { // 1 minute
				$breaker['state'] = 'half-open';
			} else {
				throw new RuntimeException("Circuit breaker open for operation: {$operation}");
			}
		}
	}

	private function recordCircuitBreakerSuccess(string $operation): void
	{
		if (isset(self::$circuitBreakers[$operation])) {
			self::$circuitBreakers[$operation]['failures'] = 0;
			self::$circuitBreakers[$operation]['state'] = 'closed';
		}
	}

	private function recordCircuitBreakerFailure(string $operation): void
	{
		if (!isset(self::$circuitBreakers[$operation])) {
			self::$circuitBreakers[$operation] = ['failures' => 0, 'last_failure' => 0, 'state' => 'closed'];
		}
		
		$breaker = &self::$circuitBreakers[$operation];
		$breaker['failures']++;
		$breaker['last_failure'] = time();
		
		if ($breaker['failures'] >= 5) { // Open after 5 failures
			$breaker['state'] = 'open';
		}
	}

	/**
	 * Record performance metrics
	 */
	private function recordMetrics(string $operation, float $executionTime, bool $success): void
	{
		// Implementation would integrate with monitoring system
		// (Prometheus, StatsD, etc.)
	}

	/**
	 * Sanitize error messages to prevent credential disclosure
	 */
	private function sanitizeErrorMessage(string $message): string
	{
		// Remove sensitive information from error messages
		$patterns = [
			'/password[\'\"]*\s*[:=]\s*[\'\"]*[^\s\'"]+/i',
			'/user[\'\"]*\s*[:=]\s*[\'\"]*[^\s\'"]+/i',
			'/SQLSTATE\[[^\]]+\]/',
			'/Connection refused/',
			'/Access denied for user [\'"]([^\'"]*)[\'"]/i',
			'/Unknown database [\'"]([^\'"]*)[\'"]/i',
			'/Can\'t connect to .* server/i',
		];
		
		$replacements = [
			'password: [REDACTED]',
			'user: [REDACTED]',
			'Database error',
			'Connection error', 
			'Access denied for user [REDACTED]',
			'Unknown database [REDACTED]',
			'Connection error to database server',
		];
		
		return preg_replace($patterns, $replacements, $message);
	}

	/**
	 * Validate and normalize configuration
	 */
	private function validateAndNormalizeConfig(?array $config): array
	{
		if (!$config || !is_array($config)) {
			throw new InvalidArgumentException('Invalid database configuration');
		}
		
		$required = ['DB_HOST', 'DB_DATABASE', 'DB_USER', 'DB_PASSWORD'];
		foreach ($required as $key) {
			if (!isset($config[$key]) || !is_string($config[$key]) || trim($config[$key]) === '') {
				throw new InvalidArgumentException("Missing or invalid configuration: {$key}");
			}
		}
		
		// Set defaults for optional parameters
		$config['DB_PORT'] = $config['DB_PORT'] ?? 3306;
		$config['DB_CHARSET'] = $config['DB_CHARSET'] ?? 'utf8mb4';
		
		return $config;
	}

	/**
	 * Get connection key for pooling (ohne sensitive Daten)
	 */
	private function getConnectionKey(array $config): string
	{
		return hash('sha256', implode('|', [
			$config['DB_HOST'],
			$config['DB_PORT'] ?? 3306,
			$config['DB_DATABASE'],
			// NICHT: DB_USER oder DB_PASSWORD!
		]));
	}

	/**
	 * Thread-safe table metadata with caching
	 */
	private function getTableColumns(string $table): array
	{
		if (!$this->acquireLock('table_meta')) {
			// Fallback: query without caching
			$query = "SHOW COLUMNS FROM `{$table}`";
			$stmt = $this->connection->prepare($query);
			$stmt->execute();
			return $stmt->fetchAll();
		}
		
		try {
			if (!isset(self::$tableMetaCache[$table])) {
				$query = "SHOW COLUMNS FROM `{$table}`";
				$stmt = $this->connection->prepare($query);
				$stmt->execute();
				self::$tableMetaCache[$table] = $stmt->fetchAll();
			}
			
			return self::$tableMetaCache[$table];
		} finally {
			$this->releaseLock('table_meta');
		}
	}

	/**
	 * Generate secure UUID v4
	 */
	public function generateUuid(): string
	{
		if (function_exists('random_bytes')) {
			$data = random_bytes(16);
		} else {
			throw new RuntimeException('Secure random number generation not available');
		}
		
		$data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Version 4
		$data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variant bits
		
		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}

	// Standard CRUD operations with security
	public function insert(string $table, array $data, string $primaryKey = 'id'): string|int
	{
		$this->securityValidator->validateTableName($table);
		
		if (empty($data)) {
			throw new InvalidArgumentException('No data provided for insert');
		}

		// Validate all data
		foreach ($data as $key => $value) {
			$this->securityValidator->validateColumnName($key);
			$this->securityValidator->validateValue($value, $key);
		}

		// Generate UUID if needed
		if (str_ends_with($primaryKey, '_uid') && !isset($data[$primaryKey])) {
			$data[$primaryKey] = $this->generateUuid();
		}

		// Get table metadata
		$tableColumns = $this->getTableColumns($table);
		$validColumns = array_column($tableColumns, 'Field');
		
		// Filter only valid columns
		$filteredData = array_intersect_key($data, array_flip($validColumns));
		
		if (empty($filteredData)) {
			throw new InvalidArgumentException('No valid columns found');
		}

		$columns = array_keys($filteredData);
		$placeholders = array_map(fn($col) => ":{$col}", $columns);
		
		$query = sprintf(
			'INSERT INTO `%s` (`%s`) VALUES (%s)',
			$table,
			implode('`, `', $columns),
			implode(', ', $placeholders)
		);

		try {
			$stmt = $this->prepareSecureStatement($query);
			$this->executeWithMetrics($stmt, $filteredData, 'INSERT');
			
			return isset($data[$primaryKey]) ? $data[$primaryKey] : $this->connection->lastInsertId();
			
		} catch (\Exception $e) {
			throw new RuntimeException('Insert failed: ' . $this->sanitizeErrorMessage($e->getMessage()));
		}
	}

	public function update(string $table, array $data, string $primaryKey = 'id'): bool
	{
		$this->securityValidator->validateTableName($table);
		
		if (empty($data) || !isset($data[$primaryKey])) {
			throw new InvalidArgumentException('Invalid update data or missing primary key');
		}

		// Validate all data
		foreach ($data as $key => $value) {
			$this->securityValidator->validateColumnName($key);
			$this->securityValidator->validateValue($value, $key);
		}

		$primaryKeyValue = $data[$primaryKey];
		unset($data[$primaryKey]);

		// Get table metadata
		$tableColumns = $this->getTableColumns($table);
		$validColumns = array_column($tableColumns, 'Field');
		
		// Filter only valid columns
		$filteredData = array_intersect_key($data, array_flip($validColumns));
		
		if (empty($filteredData)) {
			throw new InvalidArgumentException('No valid columns found for update');
		}

		$setParts = array_map(fn($col) => "`{$col}` = :{$col}", array_keys($filteredData));
		
		$query = sprintf(
			'UPDATE `%s` SET %s WHERE `%s` = :pk_value',
			$table,
			implode(', ', $setParts),
			$primaryKey
		);

		$filteredData['pk_value'] = $primaryKeyValue;

		try {
			$stmt = $this->prepareSecureStatement($query);
			$this->executeWithMetrics($stmt, $filteredData, 'UPDATE');
			return true;
		} catch (\Exception $e) {
			throw new RuntimeException('Update failed: ' . $this->sanitizeErrorMessage($e->getMessage()));
		}
	}

	public function delete(string $table, string $primaryKey, string|int|array $ids): bool
	{
		$this->securityValidator->validateTableName($table);
		$this->securityValidator->validateColumnName($primaryKey);
		
		if (empty($ids)) {
			throw new InvalidArgumentException('No IDs provided for delete');
		}

		if (is_array($ids)) {
			return $this->bulkDelete($table, $primaryKey, $ids);
		}

		// Validate single ID
		$this->securityValidator->validateValue($ids, $primaryKey);

		$query = "DELETE FROM `{$table}` WHERE `{$primaryKey}` = ?";

		try {
			$stmt = $this->connection->prepare($query);
			$this->executeWithMetrics($stmt, [$ids], 'DELETE');
			return true;
		} catch (\Exception $e) {
			throw new RuntimeException('Delete failed: ' . $this->sanitizeErrorMessage($e->getMessage()));
		}
	}

	// Transaction methods
	public function beginTransaction(): bool
	{
		return $this->connection->beginTransaction();
	}

	public function commit(): bool
	{
		return $this->connection->commit();
	}

	public function rollback(): bool
	{
		return $this->connection->rollback();
	}

	public function transaction(callable $callback): mixed
	{
		$this->beginTransaction();
		
		try {
			$result = $callback($this);
			$this->commit();
			return $result;
		} catch (\Throwable $e) {
			$this->rollback();
			throw $e;
		}
	}

	/**
	 * Get connection information without sensitive data
	 */
	public function getConnectionInfo(): array
	{
		return [
			'info' => $this->connectionInfo,
			'is_connected' => $this->connection !== null,
			'health_status' => $this->connection ? $this->isConnectionHealthy($this->connection) : false,
			'pool_size' => count(self::$connectionPool),
			'memory_usage' => memory_get_usage(true),
			// KEINE Credentials oder sensitive Daten!
		];
	}

	// Health check and diagnostics
	public function healthCheck(): array
	{
		$health = [
			'status' => 'ok',
			'timestamp' => date('Y-m-d H:i:s'),
			'connection_info' => $this->connectionInfo,
			'connections' => count(self::$connectionPool),
			'cache_size' => count(self::$statementCache),
			'circuit_breakers' => self::$circuitBreakers,
			'tests' => []
		];

		try {
			// Basis-Verbindungstest
			if (!$this->connection) {
				throw new RuntimeException('No database connection');
			}
			
			// Query-Test
			$this->connection->query('SELECT 1');
			$health['tests']['query'] = 'passed';
			
			// Zugriffs-Test
			$this->connection->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '{$this->database}'");
			$health['tests']['database_access'] = 'passed';
			
		} catch (\Exception $e) {
			$health['status'] = 'error';
			$health['error'] = $this->sanitizeErrorMessage($e->getMessage());
			$health['tests']['connection'] = 'failed';
		}

		return $health;
	}

	/**
	 * Sichere Reconnection (falls nötig) - lädt Credentials aus externen Quellen
	 */
	public function reconnect(): bool
	{
		// Problem: Wir haben keine Credentials mehr!
		// Lösung: Credentials aus sicherem External Store laden
		
		try {
			// Option 1: Aus Environment Variables
			$config = $this->loadCredentialsFromEnvironment();
			
			// Option 2: Aus verschlüsseltem Credential Store (falls verfügbar)
			if (!$config && class_exists('Capps\Modules\Database\Classes\SecureCredentialManager')) {
				$connectionId = $this->connectionInfo['connection_id'] ?? 'default';
				$config = \Capps\Modules\Database\Classes\SecureCredentialManager::loadCredentials($connectionId);
			}
			
			if ($config) {
				$this->connectSecurely($config);
				$this->clearSensitiveData($config);
				return true;
			}
			
			return false;
			
		} catch (\Exception $e) {
			error_log("Database reconnection failed: " . $this->sanitizeErrorMessage($e->getMessage()));
			return false;
		}
	}
	
	/**
	 * Credentials aus Environment laden (sicherer als im Objekt speichern)
	 */
	private function loadCredentialsFromEnvironment(): ?array
	{
		$requiredVars = ['DB_HOST', 'DB_DATABASE', 'DB_USER', 'DB_PASSWORD'];
		$config = [];
		
		foreach ($requiredVars as $var) {
			$value = getenv($var) ?: $_ENV[$var] ?? null;
			if (!$value) {
				return null; // Credentials nicht verfügbar
			}
			$config[$var] = $value;
		}
		
		$config['DB_PORT'] = getenv('DB_PORT') ?: $_ENV['DB_PORT'] ?? 3306;
		$config['DB_CHARSET'] = getenv('DB_CHARSET') ?: $_ENV['DB_CHARSET'] ?? 'utf8mb4';
		
		return $config;
	}

	private function getDefaultConfig(): array
	{
		return defined('DATABASE') ? DATABASE : [];
	}

	public function getConnection(): PDO
	{
		return $this->connection;
	}

	public function __destruct()
	{
		// Clean up file locks
		foreach (self::$lockHandles as $lockName => $handle) {
			flock($handle, LOCK_UN);
			fclose($handle);
		}
		
		// Sicherstellen, dass keine Credentials im Speicher bleiben
		$this->connectionInfo = [];
		
		// Connections remain in pool for reuse
		$this->connection = null;
	}
}

?>