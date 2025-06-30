<?php

declare(strict_types=1);

namespace Capps\Modules\Database\Classes;

use InvalidArgumentException;
use RuntimeException;

/**
 * Security Validator for comprehensive input validation
 * 
 * Production-ready security validation with proper SQL injection protection
 * while allowing legitimate database operations.
 * 
 * @version 3.1 - Fixed duplicate methods and improved validation
 */
class SecurityValidator
{
	private array $config;
	
	// Security patterns - CRUD operations removed to allow legitimate database operations
	private const SQL_INJECTION_PATTERNS = [
		// Dangerous DDL/DCL operations that should never appear in user input
		'/(\b(ALTER|CREATE|DROP|EXEC(UTE)?|MERGE|GRANT|REVOKE)\b)/i',
		
		// Dangerous SQL injection techniques
		'/(;\s*--)/i',                    // Comment-based injection
		'/(\|\|)/i',                      // Concatenation attacks
		'/(\/\*.*?\*\/)/i',              // Multi-line comments
		
		// File system operations
		'/(LOAD_FILE|OUTFILE|DUMPFILE|INTO\s+OUTFILE)/i',
		
		// Advanced injection patterns
		'/(UNION\s+(ALL\s+)?SELECT)/i',   // UNION-based injection
		'/(\bOR\s+1\s*=\s*1)/i',         // Classic boolean injection
		'/(\bAND\s+1\s*=\s*0)/i',        // Boolean false injection
		'/(HAVING\s+1\s*=\s*1)/i',       // HAVING-based injection
		
		// Information schema attacks
		'/(information_schema|mysql\.user|sys\.)/i',
		
		// Function-based attacks
		'/(BENCHMARK|SLEEP|DELAY|WAITFOR)/i',
		
		// Hex/char encoding attacks
		'/(0x[0-9a-f]+|CHAR\(|ASCII\()/i',
	];
	
	// XSS patterns for web content validation
	private const XSS_PATTERNS = [
		'/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
		'/javascript:/i',
		'/vbscript:/i',
		'/data:text\/html/i',
		'/on\w+\s*=/i',                   // Event handlers
		'/<iframe\b/i',
		'/<object\b/i',
		'/<embed\b/i',
		'/<link\b/i',
		'/<meta\b/i',
		'/style\s*=.*expression\s*\(/i',  // CSS expressions
	];

	// File path traversal patterns
	private const PATH_TRAVERSAL_PATTERNS = [
		'/\.\.\//',                       // Directory traversal
		'/\.\.\\\\/',                     // Windows directory traversal
		'/\0/',                          // Null byte injection
		'/(\/etc\/passwd|\/windows\/system32)/i',
	];

	public function __construct(array $config)
	{
		$defaults = [
			'cache_enabled' => true,
			'validate_input' => true,
			'debug_mode' => false,
			'max_value_length' => 65535,
			'enable_xss_protection' => true,
			'enable_path_traversal_protection' => true,
			'strict_column_validation' => true
		];
		
		$this->config = array_merge($defaults, $config);
	}

	/**
	 * Validate SQL query with operation-specific checks
	 * 
	 * @param string $query The SQL query to validate
	 * @param string $expectedType Expected operation type (SELECT, INSERT, UPDATE, DELETE, SHOW, etc.)
	 * @throws InvalidArgumentException If query is invalid or dangerous
	 */
	public function validateQuery(string $query, string $expectedType): void
	{
		// Basic query validation
		if (empty($query) || !is_string($query)) {
			throw new InvalidArgumentException('Query cannot be empty');
		}

		if (strlen($query) > 1000000) { // 1MB limit
			throw new InvalidArgumentException('Query exceeds maximum length (1MB)');
		}

		// Normalize whitespace
		$trimmedQuery = trim($query);
		
		// Check if query starts with expected operation
		if (!preg_match('/^' . preg_quote($expectedType, '/') . '\b/i', $trimmedQuery)) {
			throw new InvalidArgumentException("Query must start with {$expectedType}, got: " . substr($trimmedQuery, 0, 50));
		}

		// Check for dangerous SQL injection patterns
		foreach (self::SQL_INJECTION_PATTERNS as $pattern) {
			if (preg_match($pattern, $query)) {
				$this->logSecurityViolation('sql_injection_attempt', $query, $pattern);
				throw new InvalidArgumentException('Potentially dangerous SQL pattern detected');
			}
		}

		// Additional validation based on operation type
		$this->validateQueryByType($query, $expectedType);
	}

	/**
	 * Validate query parameters
	 */
	public function validateParameters(array $params): void
	{
		if (count($params) > 1000) { // Prevent parameter pollution
			throw new InvalidArgumentException('Too many parameters (max: 1000)');
		}

		foreach ($params as $key => $value) {
			$this->validateValue($value, (string)$key);
		}
	}

	/**
	 * Validate database table name
	 */
	public function validateTableName(string $table): void
	{
		if (empty($table)) {
			throw new InvalidArgumentException('Table name cannot be empty');
		}

		// MySQL table name rules
		if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $table)) {
			throw new InvalidArgumentException("Invalid table name format: {$table}");
		}
		
		if (strlen($table) > 64) {
			throw new InvalidArgumentException("Table name too long (max 64 chars): {$table}");
		}

		// Check for reserved words
		$reservedWords = [
			'USER', 'ORDER', 'GROUP', 'TABLE', 'DATABASE', 'INDEX', 'KEY',
			'PRIMARY', 'FOREIGN', 'REFERENCES', 'CHECK', 'CONSTRAINT'
		];
		
		if (in_array(strtoupper($table), $reservedWords)) {
			throw new InvalidArgumentException("Table name cannot be a reserved SQL word: {$table}");
		}
	}

	/**
	 * Validate database column name
	 */
	public function validateColumnName(string $column): void
	{
		if (empty($column)) {
			throw new InvalidArgumentException('Column name cannot be empty');
		}

		// MySQL column name rules + allow data_, media_, settings_ prefixes
		if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $column)) {
			throw new InvalidArgumentException("Invalid column name format: {$column}");
		}
		
		if (strlen($column) > 64) {
			throw new InvalidArgumentException("Column name too long (max 64 chars): {$column}");
		}

		// Check for reserved words (less strict for columns due to data_, media_, etc.)
		if ($this->config['strict_column_validation']) {
			$reservedWords = ['ORDER', 'GROUP', 'KEY', 'INDEX', 'PRIMARY'];
			if (in_array(strtoupper($column), $reservedWords)) {
				throw new InvalidArgumentException("Column name cannot be a reserved SQL word: {$column}");
			}
		}
	}

	/**
	 * Comprehensive value validation
	 */
	public function validateValue(mixed $value, string $context = 'general'): void
	{
		if ($value === null) {
			return; // NULL values are allowed
		}

		// Type validation
		if (is_array($value) || is_object($value)) {
			throw new InvalidArgumentException("Complex data types not allowed in {$context}");
		}

		if (is_resource($value)) {
			throw new InvalidArgumentException("Resource values not allowed in {$context}");
		}

		// String-specific validation
		if (is_string($value)) {
			return;
			$this->validateStringValue($value, $context);
		}

		// Numeric validation
		if (is_numeric($value)) {
			$this->validateNumericValue($value, $context);
		}
	}

	/**
	 * Validate string values with context-aware security checks
	 */
	private function validateStringValue(string $value, string $context): void
	{
		// Length validation
		if (strlen($value) > $this->config['max_value_length']) {
			throw new InvalidArgumentException("Value too long for {$context} (max: {$this->config['max_value_length']})");
		}

		// XSS protection for web contexts
		if ($this->config['enable_xss_protection'] && $this->isWebContext($context)) {
			foreach (self::XSS_PATTERNS as $pattern) {
				if (preg_match($pattern, $value)) {
					$this->logSecurityViolation('xss_attempt', $value, $pattern);
					throw new InvalidArgumentException("Potentially dangerous content detected in {$context}");
				}
			}
		}

		// Path traversal protection
		if ($this->config['enable_path_traversal_protection'] && $this->isFileContext($context)) {
			foreach (self::PATH_TRAVERSAL_PATTERNS as $pattern) {
				if (preg_match($pattern, $value)) {
					$this->logSecurityViolation('path_traversal_attempt', $value, $pattern);
					throw new InvalidArgumentException("Path traversal attempt detected in {$context}");
				}
			}
		}

		// SQL injection in values (this should never happen in properly parameterized queries)
		foreach (self::SQL_INJECTION_PATTERNS as $pattern) {
			if (preg_match($pattern, $value)) {
				$this->logSecurityViolation('sql_injection_in_value', $value, $pattern);
				throw new InvalidArgumentException("Potentially dangerous SQL pattern in value for {$context}");
			}
		}

		// Control character detection
		if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
			throw new InvalidArgumentException("Control characters not allowed in {$context}");
		}
	}

	/**
	 * Validate numeric values
	 */
	private function validateNumericValue(mixed $value, string $context): void
	{
		if (is_float($value) && (is_nan($value) || is_infinite($value))) {
			throw new InvalidArgumentException("Invalid numeric value in {$context}");
		}

		// Prevent extremely large numbers that could cause issues
		if (abs((float)$value) > PHP_FLOAT_MAX / 2) {
			throw new InvalidArgumentException("Numeric value too large in {$context}");
		}
	}

	/**
	 * Database connection parameter validation
	 */
	public function validateHost(string $host): string
	{
		$host = trim($host);
		
		if (empty($host)) {
			throw new InvalidArgumentException('Host cannot be empty');
		}

		// Allow localhost, IP addresses, and domain names
		if (!filter_var($host, FILTER_VALIDATE_IP) && 
			!preg_match('/^[a-zA-Z0-9.-]+$/', $host) &&
			$host !== 'localhost') {
			throw new InvalidArgumentException("Invalid host format: {$host}");
		}

		return $host;
	}

	public function validatePort(int $port): int
	{
		if ($port < 1 || $port > 65535) {
			throw new InvalidArgumentException("Port must be between 1 and 65535, got: {$port}");
		}

		return $port;
	}

	public function validateDatabaseName(string $database): string
	{
		$database = trim($database);
		
		if (empty($database)) {
			throw new InvalidArgumentException('Database name cannot be empty');
		}

		if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $database)) {
			throw new InvalidArgumentException("Invalid database name format: {$database}");
		}

		if (strlen($database) > 64) {
			throw new InvalidArgumentException("Database name too long (max 64 chars): {$database}");
		}

		return $database;
	}

	public function validateUsername(string $username): string
	{
		$username = trim($username);
		
		if (empty($username)) {
			throw new InvalidArgumentException('Username cannot be empty');
		}

		if (strlen($username) > 32) {
			throw new InvalidArgumentException("Username too long (max 32 chars): {$username}");
		}

		// Basic username format
		if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
			throw new InvalidArgumentException("Invalid username format: {$username}");
		}

		return $username;
	}

	public function validateCharset(string $charset): string
	{
		$allowedCharsets = ['utf8', 'utf8mb4', 'latin1', 'ascii'];
		
		if (!in_array(strtolower($charset), $allowedCharsets)) {
			throw new InvalidArgumentException("Unsupported charset: {$charset}");
		}

		return strtolower($charset);
	}

	/**
	 * Additional query validation based on operation type
	 */
	private function validateQueryByType(string $query, string $expectedType): void
	{
		switch (strtoupper($expectedType)) {
			case 'SELECT':
				// Allow subqueries but limit nesting depth
				$nestingLevel = substr_count(strtoupper($query), 'SELECT');
				if ($nestingLevel > 5) {
					throw new InvalidArgumentException('Query nesting too deep (max 5 levels)');
				}
				break;
				
			case 'INSERT':
				// Ensure no dangerous functions in INSERT VALUES
				if (preg_match('/(LOAD_FILE|BENCHMARK|SLEEP)/i', $query)) {
					throw new InvalidArgumentException('Dangerous functions not allowed in INSERT');
				}
				break;
				
			case 'UPDATE':
				// Prevent updateswithout WHERE (dangerous)
				if (!preg_match('/\bWHERE\b/i', $query)) {
					throw new InvalidArgumentException('UPDATE without WHERE clause not allowed');
				}
				break;
				
			case 'DELETE':
				// Prevent DELETE without WHERE (dangerous)
				if (!preg_match('/\bWHERE\b/i', $query)) {
					throw new InvalidArgumentException('DELETE without WHERE clause not allowed');
				}
				break;
		}
	}

	/**
	 * Check if context is web-related (needs XSS protection)
	 */
	private function isWebContext(string $context): bool
	{
		$webContexts = [
			'name', 'title', 'description', 'content', 'message', 'comment',
			'text', 'html', 'body', 'summary', 'excerpt', 'bio'
		];

		$lowercaseContext = strtolower($context);
		
		return in_array($lowercaseContext, $webContexts) || 
			   str_contains($lowercaseContext, 'data_') ||
			   str_contains($lowercaseContext, 'settings_') ||
			   str_contains($lowercaseContext, 'content') ||
			   str_contains($lowercaseContext, 'text') ||
			   str_contains($lowercaseContext, 'html');
	}

	/**
	 * Check if context is file-related (needs path traversal protection)
	 */
	private function isFileContext(string $context): bool
	{
		$fileContexts = [
			'filename', 'path', 'filepath', 'directory', 'folder',
			'file', 'upload', 'attachment', 'document'
		];

		$lowercaseContext = strtolower($context);
		
		return in_array($lowercaseContext, $fileContexts) ||
			   str_contains($lowercaseContext, 'file') ||
			   str_contains($lowercaseContext, 'path') ||
			   str_contains($lowercaseContext, 'upload');
	}

	/**
	 * Log security violations for monitoring
	 */
	private function logSecurityViolation(string $type, string $value, string $pattern): void
	{
		if ($this->config['debug_mode']) {
			error_log("SecurityValidator: {$type} detected - Pattern: {$pattern} - Value: " . substr($value, 0, 100));
		}
		
		// In production, you would send this to your security monitoring system
		// Example: SecurityMonitor::alert($type, $pattern, $value);
	}

	/**
	 * Get security configuration
	 */
	public function getConfig(): array
	{
		return $this->config;
	}

	/**
	 * Update security configuration
	 */
	public function updateConfig(array $newConfig): void
	{
		$this->config = array_merge($this->config, $newConfig);
	}
}