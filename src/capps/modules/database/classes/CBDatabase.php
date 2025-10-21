<?php

declare(strict_types=1);

namespace Capps\Modules\Database\Classes;

use PDO;
use PDOException;
use InvalidArgumentException;
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
 */
class CBDatabase
{
    private ?PDO $connection = null;
    private string $database;

    public function __construct(?array $config = null)
    {
        $config = $config ?? $this->getDefaultConfig();
        $this->connect($config);
        $this->database = $config['DB_DATABASE'];
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
                $this->connection = new PDO($dsn, $username, $password, $options);
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
        // Try loading from various sources
        $configPaths = [
            $_SERVER['DOCUMENT_ROOT'] . '/../src/capps/inc.localconf.php',
            dirname(__DIR__, 3) . '/inc.localconf.php'
        ];

        foreach ($configPaths as $path) {
            if (file_exists($path)) {
                include $path;
                break;
            }
        }

        // Fallback to environment variables or constants
        return [
            'DB_HOST' => defined('DB_HOST') ? DB_HOST : ($_ENV['DB_HOST'] ?? 'localhost'),
            'DB_PORT' => defined('DB_PORT') ? DB_PORT : ($_ENV['DB_PORT'] ?? 3306),
            'DB_DATABASE' => defined('DB_DATABASE') ? DB_DATABASE : ($_ENV['DB_DATABASE'] ?? ''),
            'DB_USER' => defined('DB_USER') ? DB_USER : ($_ENV['DB_USER'] ?? ''),
            'DB_PASSWORD' => defined('DB_PASSWORD') ? DB_PASSWORD : ($_ENV['DB_PASSWORD'] ?? ''),
            'DB_CHARSET' => defined('DB_CHARSET') ? DB_CHARSET : ($_ENV['DB_CHARSET'] ?? 'utf8mb4')
        ];
    }

    /**
     * Execute SELECT query - returns array
     */
    public function get(string $query, array $params = []): array
    {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new RuntimeException("Query failed: " . $e->getMessage());
        }
    }

    /**
     * SELECT query - single result
     */
    public function selectOne(string $query, array $params = []): ?array
    {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (PDOException $e) {
            throw new RuntimeException("Query failed: " . $e->getMessage());
        }
    }

    /**
     * SHOW queries (for schema information)
     */
    public function show(string $query): array
    {
        return $this->get($query);
    }

    /**
     * Execute INSERT - returns lastInsertId or primary key value
     */
    public function insert(string $table, array $data, ?string $primaryKey = null): int|string
    {
        if (empty($data)) {
            throw new InvalidArgumentException("No data provided for insert");
        }

        $columns = array_keys($data);
        $placeholders = array_fill(0, count($data), '?');

        $query = "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $placeholders) . ")";

        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute(array_values($data));

            // If primary key exists and value in data, return it (UUID)
            if ($primaryKey && isset($data[$primaryKey])) {
                return $data[$primaryKey];
            }

            // Otherwise return lastInsertId for auto_increment (int)
            return (int)$this->connection->lastInsertId();
        } catch (PDOException $e) {
            throw new RuntimeException("Insert failed: " . $e->getMessage());
        }
    }

    /**
     * Execute UPDATE
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): bool
    {
        if (empty($data)) {
            throw new InvalidArgumentException("No data provided for update");
        }

        $setParts = array_map(fn($col) => "`{$col}` = ?", array_keys($data));
        $query = "UPDATE `{$table}` SET " . implode(', ', $setParts) . " WHERE {$where}";

        try {
            $stmt = $this->connection->prepare($query);
            $params = array_merge(array_values($data), $whereParams);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            throw new RuntimeException("Update failed: " . $e->getMessage());
        }
    }

    /**
     * Execute DELETE
     */
    public function delete(string $table, string $where, array $whereParams = []): bool
    {
        $query = "DELETE FROM `{$table}` WHERE {$where}";

        try {
            $stmt = $this->connection->prepare($query);
            return $stmt->execute($whereParams);
        } catch (PDOException $e) {
            throw new RuntimeException("Delete failed: " . $e->getMessage());
        }
    }

    /**
     * Execute raw query (for legacy compatibility)
     */
    public function query(string $query, array $params = []): mixed
    {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);

            // Return appropriate result based on query type
            if (stripos($query, 'SELECT') === 0 || stripos($query, 'SHOW') === 0) {
                return $stmt->fetchAll();
            } elseif (stripos($query, 'INSERT') === 0) {
                return $this->connection->lastInsertId();
            } else {
                return $stmt->rowCount();
            }
        } catch (PDOException $e) {
            throw new RuntimeException("Query failed: " . $e->getMessage());
        }
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
            // Fallback: PHP UUID v4
            return sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
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
        try {
            return $this->connection->beginTransaction();
        } catch (PDOException $e) {
            throw new RuntimeException("Failed to begin transaction: " . $e->getMessage());
        }
    }

    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        try {
            return $this->connection->commit();
        } catch (PDOException $e) {
            throw new RuntimeException("Failed to commit transaction: " . $e->getMessage());
        }
    }

    /**
     * Rollback transaction
     */
    public function rollback(): bool
    {
        try {
            return $this->connection->rollBack();
        } catch (PDOException $e) {
            throw new RuntimeException("Failed to rollback transaction: " . $e->getMessage());
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