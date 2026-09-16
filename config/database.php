<?php

declare(strict_types=1);

namespace PrakruthiSiri\Config;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Singleton Database Connection Manager for Prakruthi Siri platform.
 * 
 * Provides a high-performance, strictly typed PDO wrapper configured for
 * MySQL 8.0+ and PHP 8.2+.
 */
final class Database
{
    private static ?Database $instance = null;
    private PDO $connection;
    private int $transactionLevel = 0;

    /**
     * Protected constructor to prevent direct instantiation.
     */
    private function __construct(array $customConfig = [])
    {
        // Load environment variables from .env if present
        $envFile = dirname(__DIR__) . '/.env';
        if (file_exists($envFile)) {
            $parsedEnv = parse_ini_file($envFile);
            if (is_array($parsedEnv)) {
                foreach ($parsedEnv as $key => $val) {
                    if (!isset($_ENV[$key])) {
                        $_ENV[$key] = (string)$val;
                    }
                }
            }
        }

        $fileConfig = [];
        $dbConfigFile = __DIR__ . '/db_config.php';
        if (file_exists($dbConfigFile)) {
            $loaded = require $dbConfigFile;
            if (is_array($loaded)) {
                $fileConfig = $loaded;
            }
        }

        $host     = $customConfig['host'] ?? $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: ($fileConfig['host'] ?? '127.0.0.1');
        $port     = (int) ($customConfig['port'] ?? $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: ($fileConfig['port'] ?? 3306));
        $database = $customConfig['database'] ?? $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: ($fileConfig['database'] ?? 'prakruthi_siri');
        $username = $customConfig['username'] ?? $_ENV['DB_USER'] ?? getenv('DB_USER') ?: ($fileConfig['username'] ?? 'root');
        $password = $customConfig['password'] ?? $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: ($fileConfig['password'] ?? '');
        $charset  = $customConfig['charset'] ?? $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: ($fileConfig['charset'] ?? 'utf8mb4');

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci",
        ];

        try {
            $this->connection = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            throw new RuntimeException(
                sprintf('Database connection failed: %s', $e->getMessage()),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Prevent object cloning.
     */
    private function __clone()
    {
    }

    /**
     * Prevent object unserialization.
     */
    public function __wakeup(): void
    {
        throw new RuntimeException('Cannot unserialize a singleton Database instance.');
    }

    /**
     * Retrieve the singleton instance of Database.
     *
     * @param array<string, mixed> $config Optional configuration override on initial boot
     */
    public static function getInstance(array $config = []): self
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    /**
     * Retrieve the underlying PDO instance.
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * Static shortcut to get underlying PDO instance.
     */
    public static function getPdo(): PDO
    {
        return self::getInstance()->getConnection();
    }

    /**
     * Initiates a database transaction (supports nested transactions via MySQL SAVEPOINTs).
     */
    public function beginTransaction(): bool
    {
        if ($this->transactionLevel === 0) {
            $success = $this->connection->beginTransaction();
            if ($success) {
                $this->transactionLevel = 1;
            }
            return $success;
        }

        $this->transactionLevel++;
        $this->connection->exec('SAVEPOINT LEVEL' . $this->transactionLevel);
        return true;
    }

    /**
     * Commits the current active database transaction or releases the inner savepoint.
     */
    public function commit(): bool
    {
        if ($this->transactionLevel === 0) {
            return false;
        }

        if ($this->transactionLevel === 1) {
            $this->transactionLevel = 0;
            return $this->connection->commit();
        }

        $this->connection->exec('RELEASE SAVEPOINT LEVEL' . $this->transactionLevel);
        $this->transactionLevel--;
        return true;
    }

    /**
     * Rolls back the current active database transaction or rolls back to the inner savepoint.
     */
    public function rollBack(): bool
    {
        if ($this->transactionLevel === 0) {
            return false;
        }

        if ($this->transactionLevel === 1) {
            $this->transactionLevel = 0;
            return $this->connection->rollBack();
        }

        $this->connection->exec('ROLLBACK TO SAVEPOINT LEVEL' . $this->transactionLevel);
        $this->transactionLevel--;
        return true;
    }

    /**
     * Checks if a transaction is currently active within the driver.
     */
    public function inTransaction(): bool
    {
        return $this->transactionLevel > 0 || $this->connection->inTransaction();
    }

    /**
     * Executes an operation atomically within a transaction.
     * Automatically commits on success or rolls back and rethrows on failure.
     *
     * @template T
     * @param callable(PDO): T $callback
     * @return T
     * @throws \Throwable
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this->connection);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->inTransaction()) {
                $this->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Resets singleton instance (useful for testing or reconnecting with new config).
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }
}
