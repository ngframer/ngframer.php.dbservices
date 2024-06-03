<?php

namespace NGFramer\NGFramerPHPDbService;

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
            $this->connection = new PDO(DB_DSN, DB_USER, DB_PASS, $pdoAttributes);
        } catch (Throwable $th) {
            $this->throwError("Connection to the database was not established. Error Message: " . $th->getMessage());
        }
    }


    /**
     * @throws Exception
     */
    private function validateConfigFile(): void
    {
        $configFile = ROOT . '/config/database.php';
        if (!file_exists($configFile)) {
            $this->throwError("Missing Connection Configuration File. Please check /config/database.php.");
        } else require_once $configFile;
    }


    private function throwError(string $message): void
    {
        $this->logError($message);
        throw new PDOException($message);
    }


    private function logError(string $message): void
    {
        $logFile = ROOT . '/logs/database_errors.log';
        error_log(date("[Y-m-d H:i:s]") . " " . $message . PHP_EOL, 3, $logFile);
    }


    public function prepare(string $queryStatement = null, $options = []): ?static
    {
        if (empty($queryStatement)) $this->throwError("No / empty query to prepare.");
        else $this->queryStatement = $this->connection->prepare($queryStatement, $options);
        return $this;
    }


    public function bindParams(array &$args): ?static
    {
        // If queryStatement is false, then throw an exception.
        if (!$this->queryStatement) {
            $this->throwError("No queryStatement to bind parameters to.");
        }
        // If there are no parameters to bind to the statement.
        if (count($args) === 0) {
            $this->throwError("No parameters passed to bind to queryStatement.");
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
            $this->throwError("No queryStatement to bind parameters to.");
        }
        // If there are no parameters to bind to the statement.
        if (count($args) === 0) {
            $this->throwError("No parameters passed to bind to queryStatement.");
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
        } // If queryStatement is true, then execute the queryStatement.
        else if ($queryStatement === null && $this->queryStatement !== null) {
            $this->queryExecutionStatus = $this->queryStatement->execute();
        } // If queryStatement is false, throw an error.
        else $this->throwError("Invalid or no queryStatement to execute.");

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
        else $this->throwError("No executed statement to fetch results");
    }


    public function fetchAll($fetchStyle = PDO::FETCH_ASSOC): bool|array
    {
        if ($this->queryExecutionStatus) return $this->queryStatement->fetchAll($fetchStyle);
        else $this->throwError("No executed statement to fetch results");
    }

    public function close(): void
    {
        if ($this->connection) $this->connection = null;
        $this->queryExecutionStatus = false;
    }
}
