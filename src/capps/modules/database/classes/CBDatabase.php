<?php

declare(strict_types=1);

namespace Capps\Modules\Database\Classes;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * CBDatabase - Simple PDO Wrapper
 *
 * Simplified version without threading, pooling, caching
 * Features:
 * - Transaction support
 * - Connection retry logic
 * - UUID generation
 * - Prepared statements
 * - Optional query hook (see $onQuery), no other dependencies
 */
class CBDatabase
{
    /**
     * Optional query hook: callable(string $query, float $startTime).
     * Null = no overhead. Set from outside, e.g. CBDatabase::$onQuery = [CBDebugger::class, 'query'];
     * @var callable|null
     */
    public static $onQuery = null;

    private ?PDO $connection = null;
    private string $database;
    private ?string $lastError = null;
    private ?string $lastQuery = null;
    private array $lastParams = [];

    /** @var array<string, self> Shared instances, one per config (see shared()) */
    private static array $shared = [];

    /**
     * One shared connection per config (null = default config).
     * Use instead of "new" when many objects should reuse the same connection.
     * Note: transactions and lastError are then shared as well.
     */
    public static function shared(?array $config = null): self
    {
        $key = $config === null ? 'default' : md5(serialize($config));
        return self::$shared[$key] ??= new self($config);
    }

    public function __construct(?array $config = null)
    {
        $config = $config ?? $this->getDefaultConfig();
        $this->connect($config);
        $this->database = $config['DB_DATABASE'];
    }

    /** True if the last query failed. */
    public function hasError(): bool
    {
        return $this->lastError !== null;
    }

    /**
     * Set an error from outside (e.g. CBObject safety checks).
     */
    public function setError(string $message): void
    {
        $this->lastError = $message;
        $this->lastQuery = null;
        $this->lastParams = [];
    }

    /**
     * Last error message, including query and params (for logs only).
     * SECURITY: never output this to users, params may contain sensitive data.
     */
    public function getLastError(): ?string
    {
        if ($this->lastError && $this->lastQuery) {
            return $this->lastError . " | Query: " . $this->lastQuery . " | Params: " . json_encode($this->lastParams);
        }
        return $this->lastError;
    }

    /**
     * Establish database connection with retry logic
     */
    private function connect(array $config): void
    {
        $host = $config['DB_HOST'];
        $port = $config['DB_PORT'] ?? 3306;
        $database = $config['DB_DATABASE'];
        $username = $config['DB_USER'];
        $password = $config['DB_PASSWORD'];
        $charset = $config['DB_CHARSET'] ?? 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}",
        ];

        // Retry logic: 3 attempts with 1 second delay
        $maxRetries = 3;
        $retryDelay = 1; // seconds
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $t = self::$onQuery ? microtime(true) : 0;
                $this->connection = new PDO($dsn, $username, $password, $options);
                if ($t) (self::$onQuery)('PDO CONNECT', $t);
                return; // Successfully connected
            } catch (PDOException $e) {
                $lastException = $e;

                if ($attempt < $maxRetries) {
                    // Pause before next attempt
                    sleep($retryDelay);
                }
            }
        }

        // All attempts failed
        throw new RuntimeException(
            "Database connection failed after {$maxRetries} attempts: " . $lastException->getMessage()
        );
    }

    /**
     * Load default configuration
     */
    private function getDefaultConfig(): array
    {
        // Use DATABASE constant array with fallback to environment variables
        $dbConfig = defined('DATABASE') && is_array(DATABASE) ? DATABASE : [];

        return [
            'DB_HOST' => $dbConfig['DB_HOST'] ?? ($_ENV['DB_HOST'] ?? 'localhost'),
            'DB_PORT' => $dbConfig['DB_PORT'] ?? ($_ENV['DB_PORT'] ?? 3306),
            'DB_DATABASE' => $dbConfig['DB_DATABASE'] ?? ($_ENV['DB_DATABASE'] ?? ''),
            'DB_USER' => $dbConfig['DB_USER'] ?? ($_ENV['DB_USER'] ?? ''),
            'DB_PASSWORD' => $dbConfig['DB_PASSWORD'] ?? ($_ENV['DB_PASSWORD'] ?? ''),
            'DB_CHARSET' => $dbConfig['DB_CHARSET'] ?? ($_ENV['DB_CHARSET'] ?? 'utf8mb4')
        ];
    }

    /**
     * Single place for prepare/execute, error handling and the query hook.
     * Returns the executed statement, or null on failure (see getLastError()).
     */
    private function run(string $query, array $params = []): ?PDOStatement
    {
        $this->lastError = null;
        $this->lastQuery = $query;
        $this->lastParams = $params;
        $t = self::$onQuery ? microtime(true) : 0;

        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            return null;
        } finally {
            if ($t) (self::$onQuery)($query, $t);
        }
    }

    /**
     * Table/column names cannot be bound as parameters, so allow only plain
     * identifiers (letters, digits, _), optionally "table.column".
     * Sets lastError and returns false on the first invalid name.
     */
    public function validNames(mixed ...$names): bool
    {
        foreach ($names as $name) {
            if (!(is_string($name) || is_int($name)) || !preg_match('/^[A-Za-z0-9_]+(\.[A-Za-z0-9_]+)?$/', (string)$name)) {
                $this->setError("Invalid identifier: " . (is_scalar($name) ? $name : gettype($name)));
                return false;
            }
        }
        return true;
    }

    /**
     * SELECT - returns all rows.
     * Empty array on "no rows" AND on error: use hasError() if the difference matters.
     *
     * SECURITY: never concatenate user input into $query, pass values via $params (?).
     *
     * @param string $query SECURITY: SQL with ? placeholders, values only via $params
     */
    public function get(string $query, array $params = []): array
    {
        $stmt = $this->run($query, $params);
        return $stmt ? $stmt->fetchAll() : [];
    }

    /**
     * SELECT - returns the first row.
     * Null on "no row" AND on error: use hasError() if the difference matters.
     *
     * SECURITY: never concatenate user input into $query, pass values via $params (?).
     *
     * @param string $query SECURITY: SQL with ? placeholders, values only via $params
     */
    public function selectOne(string $query, array $params = []): ?array
    {
        $stmt = $this->run($query, $params);
        return $stmt ? ($stmt->fetch() ?: null) : null;
    }

    /** SHOW queries (schema information). */
    public function show(string $query): array
    {
        return $this->get($query);
    }

    /**
     * INSERT - returns $data[$primaryKey] if given (e.g. UUID), else lastInsertId.
     * False on error.
     */
    public function insert(string $table, array $data, ?string $primaryKey = null): int|string|false
    {
        if (empty($data)) {
            $this->setError("No data provided for insert");
            return false;
        }
        if (!$this->validNames($table, ...array_keys($data))) {
            return false;
        }

        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $query = "INSERT INTO `{$table}` (`" . implode('`, `', array_keys($data)) . "`) VALUES ({$placeholders})";

        if (!$this->run($query, array_values($data))) {
            return false;
        }

        if ($primaryKey && isset($data[$primaryKey])) {
            return $data[$primaryKey];
        }
        return (int)$this->connection->lastInsertId();
    }

    /**
     * UPDATE - $where is raw SQL: use ? placeholders and pass values in
     * $whereParams, never concatenate user input.
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): bool
    {
        if (empty($data)) {
            $this->setError("No data provided for update");
            return false;
        }
        if (!$this->validNames($table, ...array_keys($data))) {
            return false;
        }

        $setParts = array_map(fn($col) => "`{$col}` = ?", array_keys($data));
        $query = "UPDATE `{$table}` SET " . implode(', ', $setParts) . " WHERE {$where}";

        return $this->run($query, array_merge(array_values($data), $whereParams)) !== null;
    }

    /** DELETE - $where is raw SQL, same rules as update(). */
    public function delete(string $table, string $where, array $whereParams = []): bool
    {
        if (!$this->validNames($table)) {
            return false;
        }

        return $this->run("DELETE FROM `{$table}` WHERE {$where}", $whereParams) !== null;
    }

    /**
     * Raw query (legacy): rows for SELECT/SHOW, lastInsertId for INSERT,
     * affected rows otherwise. False on error.
     *
     * SECURITY: never concatenate user input into $query, pass values via $params (?).
     *
     * @param string $query SECURITY: SQL with ? placeholders, values only via $params
     */
    public function query(string $query, array $params = []): mixed
    {
        $stmt = $this->run($query, $params);
        if (!$stmt) {
            return false;
        }

        // Result columns present = read query (SELECT, SHOW, WITH, DESCRIBE, EXPLAIN)
        if ($stmt->columnCount() > 0) {
            return $stmt->fetchAll();
        }
        if (stripos(ltrim($query), 'INSERT') === 0) {
            return $this->connection->lastInsertId();
        }
        return $stmt->rowCount();
    }

    /**
     * Generate UUID (MySQL-compatible)
     */
    public function generateUuid(): string
    {
        try {
            $result = $this->connection->query("SELECT UUID() as uuid")->fetch();
            return $result['uuid'];
        } catch (PDOException $e) {
            // Fallback: PHP UUID v4 (same format as MySQL UUID())
            $b = random_bytes(16);
            $b[6] = chr(ord($b[6]) & 0x0f | 0x40); // version 4
            $b[8] = chr(ord($b[8]) & 0x3f | 0x80); // variant
            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
        }
    }

    /**
     * Get PDO connection for advanced operations
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * Get current database name
     */
    public function getDatabase(): string
    {
        return $this->database;
    }

    // ================================================================
    // TRANSACTION SUPPORT
    // ================================================================

    /**
     * Begin transaction
     */
    public function beginTransaction(): bool
    {
        $this->lastError = null;
        try {
            return $this->connection->beginTransaction();
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        $this->lastError = null;
        try {
            return $this->connection->commit();
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Rollback transaction
     */
    public function rollback(): bool
    {
        // lastError is kept on purpose: a rollback usually follows the error that caused it
        try {
            return $this->connection->rollBack();
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Check if currently in transaction
     */
    public function inTransaction(): bool
    {
        return $this->connection->inTransaction();
    }
}