<?php

namespace NGFramer\NGFramerPHPDbService;

use app\config\ApplicationConfig;
use app\config\DatabaseConfig;
use PDO;
use PDOStatement;
use Throwable;
use Exception;
use PDOException;


class Database
{
    public ?PDO $connection = null;
    private null|bool|PDOStatement $queryStatement = null;
    private bool $queryExecutionStatus = false;


    /**
     * @throws Exception
     */
    public function __construct()
    {
        $this->connect();
    }


    /**
     * @throws Exception
     */
    private function connect(): void
    {
        // Check the configuration file for the database connection.
        $this->validateConfigFile();

        // Now the main connection.
        $pdoAttributes = [
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];

        try {
            // Define the DB_DSN, DB_USER, and DB_PASS in the /config/database.php file of the project/root directory.
            $db_dsn = DatabaseConfig::get('db_dsn');
            $db_user = DatabaseConfig::get('db_user');
            $db_pass = DatabaseConfig::get('db_pass');
            $this->connection = new PDO($db_dsn, $db_user, $db_pass, $pdoAttributes);
        } catch (Throwable $th) {
            throw new PDOException("Connection to the database was not established. Error Message: " . $th->getMessage());
        }
    }


    /**
     * @throws Exception
     */
    private function validateConfigFile(): void
    {
        $configFile = ApplicationConfig::get('root') . '/config/DatabaseConfig.php';
        if (!file_exists($configFile)) {
            throw new PDOException("Missing Connection Configuration File. Please check /config/DatabaseConfig.php.");
        } else require_once $configFile;
    }


    public function prepare(string $queryStatement = null, $options = []): ?static
    {
        if (empty($queryStatement)) throw new PDOException("No / empty query to prepare.");
        else $this->queryStatement = $this->connection->prepare($queryStatement, $options);
        return $this;
    }


    public function bindParams(array &$args): ?static
    {
        // If queryStatement is false, then throw an exception.
        if (!$this->queryStatement) {
            throw new PDOException("No queryStatement to bind parameters to.");
        }
        // If there are no parameters to bind to the statement.
        if (count($args) === 0) {
            throw new PDOException("No parameters passed to bind to queryStatement.");
        }
        // If all elements of the array are arrays, then run bindParam function with arg.
        if ($this->areAllElementsArray($args)) {
            foreach ($args as $arg) {
                $column = $arg['column'] ?? $arg[0];
                $value = $arg['value'] ?? $arg[1];
                $type = $arg['type'] ?? $arg[2] ?? PDO::PARAM_STR;
                $this->bindParam($column, $value, $type);
            }
        } // If all the elements of thr array ($args) are not array/s.
        else {
            $column = $args['column'] ?? $args[0];
            $value = $args['value'] ?? $args[1];
            $type = $args['type'] ?? $args[2] ?? PDO::PARAM_STR;
            $this->queryStatement->bindParam($column, $value, $type);
        }
        // Return for function chaining.
        return $this;
    }


    public function bindParam($column, &$value, $type = PDO::PARAM_STR): static
    {
        if (!str_starts_with($column, ":")) $column = ":" . $column;
        $this->queryStatement->bindParam($column, $value, $type);
        return $this;
    }


    public function bindValues(array &$args): ?static
    {
        // If queryStatement is false, then throw an exception.
        if (!$this->queryStatement) {
            throw new PDOException("No queryStatement to bind parameters to.");
        }
        // If there are no parameters to bind to the statement.
        if (count($args) === 0) {
            throw new PDOException("No parameters passed to bind to queryStatement.");
        }
        // If all elements of the array are arrays, then run bindParam function with arg.
        if ($this->areAllElementsArray($args)) {
            foreach ($args as $arg) {
                $column = $arg['column'] ?? $arg[0];
                $value = $arg['value'] ?? $arg[1];
                $type = $arg['type'] ?? $arg[2] ?? PDO::PARAM_STR;
                $this->bindValue($column, $value, $type);
            }
        } // If all the elements of thr array ($args) are not array/s.
        else {
            $column = $args['column'] ?? $args[0];
            $value = $args['value'] ?? $args[1];
            $type = $args['type'] ?? $args[2] ?? PDO::PARAM_STR;
            $this->queryStatement->bindValue($column, $value, $type);
        }
        // Return for function chaining.
        return $this;
    }


    public function bindValue($column, $value, $type = PDO::PARAM_STR): static
    {
        if (!str_starts_with($column, ":")) $column = ":" . $column;
        $this->queryStatement->bindParam($column, $value, $type);
        return $this;
    }


    private function areAllElementsArray(array $args): bool
    {
        foreach ($args as $arg) {
            if (!is_array($arg)) return false;
        }
        return true;
    }


    public function execute(string $queryStatement = null): static
    {
        // If queryStatement is not null, then execute the queryStatement.
        if ($queryStatement !== null && $this->queryStatement === null) {
            // query() function prepares and executes the queryStatement, and returns pdoStatement|false.
            $this->queryStatement = $this->connection->query($queryStatement);
            $this->queryExecutionStatus = $this->queryStatement !== false;
        }

        // If queryStatement is true, then execute the queryStatement.
        else if ($queryStatement === null && $this->queryStatement !== null) {
            $this->queryExecutionStatus = $this->queryStatement->execute();
        }

        // If queryStatement is false, throw an error.
        else throw new PDOException("Invalid or no queryStatement to execute.");
        // Return the object for function chaining.
        return $this;
    }


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
        return $this->connection->rollBack();
    }


    public function lastInsertId(): string
    {
        return $this->connection->lastInsertId();
    }


    public function rowCount(): int
    {
        return $this->queryStatement->rowCount();
    }


    public function affectedRowCount(): int
    {
        return $this->rowCount();
    }


    public function fetch($fetchStyle = PDO::FETCH_ASSOC)
    {
        if ($this->queryExecutionStatus) return $this->queryStatement->fetch($fetchStyle);
        else throw new PDOException("No executed statement to fetch results");
    }


    public function fetchAll($fetchStyle = PDO::FETCH_ASSOC): bool|array
    {
        if ($this->queryExecutionStatus) return $this->queryStatement->fetchAll($fetchStyle);
        else throw new PDOException("No executed statement to fetch results");
    }


    public function close(): void
    {
        if ($this->connection) $this->connection = null;
        $this->queryExecutionStatus = false;
    }
}
