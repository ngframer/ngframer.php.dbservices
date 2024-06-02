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
    private ?string $query = null;
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
            $this->throwError("NGFramerPHPDbService/Database.php/connect() :: Connection to the database was not established. Error Message: " . $th->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    private function validateConfigFile(): void
    {
        $configFile = ROOT . '/config/database.php';
        if (!file_exists($configFile)) {
            $this->throwError("NGFramerPHPDbService/Database.php/validateConfigFile() :: Missing Connection Configuration File. Please check /config/database.php.");
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
        if (!$this->connection) $this->throwError("ngframerphp/core/Database.php/prepare() :: No database connection to prepare the statement.");
        elseif (empty($queryStatement)) $this->throwError("ngframerphp/core/Database.php/prepare() :: No query to prepare.");
        else $this->queryStatement = $this->connection->prepare($queryStatement, $options);
        return $this;
    }

    public function bindParams(array &$args): ?static
    {
        // If queryStatement is false, then throw an exception.
        if (!$this->queryStatement) {
            $this->throwError("ngframerphp/core/Database.php/bindParams() :: No queryStatement to bind parameters.");
        }
        // If there are no parameters to bind to the statement.
        if (count($args) === 0) {
            $this->throwError("ngframerphp/core/Database.php/bindParams() :: No parameters to bind to queryStatement.");
        }
        // If all elements of the array are arrays, then run bindParam function with arg.
        if ($this->areAllElementsArray($args)) {
            foreach ($args as $arg) {
                $name = $args['name'] ?? $args[0];
                $value = $args['value'] ?? $args[1];
                $type = $args['type'] ?? $args[2] ?? PDO::PARAM_STR;
                $this->bindParam($name, $value, $type);
            }
        }
        // If all the elements of thr array ($args) are not array/s.
        else {
            $name = $args['name'] ?? $args[0];
            $value = $args['value'] ?? $args[1];
            $type = $args['type'] ?? $args[2] ?? PDO::PARAM_STR;
            $this->queryStatement->bindParam($name, $value, $type);
        }
        // Return for function chaining.
        return $this;
    }

    public function bindParam($name, &$value, $type = PDO::PARAM_STR): static
    {
        if (!str_starts_with($name, ":")) $name = ":" . $name;
        $this->queryStatement->bindParam($name, $value, $type);
        return $this;
    }

    public function bindValues(array &$args): ?static
    {
        // If queryStatement is false, then throw an exception.
        if (!$this->queryStatement) {
            $this->throwError("ngframerphp/core/Database.php/bindParams() :: No queryStatement to bind parameters.");
        }
        // If there are no parameters to bind to the statement.
        if (count($args) === 0) {
            $this->throwError("ngframerphp/core/Database.php/bindParams() :: No parameters to bind to queryStatement.");
        }
        // If all elements of the array are arrays, then run bindParam function with arg.
        if ($this->areAllElementsArray($args)) {
            foreach ($args as $arg) {
                $name = $args['name'] ?? $args[0];
                $value = $args['value'] ?? $args[1];
                $type = $args['type'] ?? $args[2] ?? PDO::PARAM_STR;
                $this->bindValue($name, $value, $type);
            }
        }
        // If all the elements of thr array ($args) are not array/s.
        else {
            $name = $args['name'] ?? $args[0];
            $value = $args['value'] ?? $args[1];
            $type = $args['type'] ?? $args[2] ?? PDO::PARAM_STR;
            $this->queryStatement->bindValue($name, $value, $type);
        }
        // Return for function chaining.
        return $this;
    }

    public function bindValue($name, $value, $type = PDO::PARAM_STR): static
    {
        if (!str_starts_with($name, ":")) $name = ":" . $name;
        $this->queryStatement->bindParam($name, $value, $type);
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
            $this->queryExecutionStatus = ($this->queryStatement !== false) ? true : false;
        } // If queryStatement is true, then execute the queryStatement.
        else if ($queryStatement === null && $this->queryStatement !== null) {
            $this->queryExecutionStatus = $this->queryStatement->execute();
        } // If queryStatement is false, throw an error.
        else $this->throwError("ngframerphp/core/Database.php/bindParam() :: Invalid or no queryStatement to execute.");
        return $this;
    }


    public function beginTransaction(): bool
    {
        if (!$this->connection) $this->throwError("ngframerphp/core/Database.php/beginTransaction() :: No database connection");
        return $this->connection->beginTransaction();
    }


    public function commit(): bool
    {
        if (!$this->connection) $this->throwError("ngframerphp/core/Database.php/commitTransaction() :: No database connection");
        return $this->connection->commit();
    }


    public function rollback(): bool
    {
        if (!$this->connection) $this->throwError("ngframerphp/core/Database.php/rollBackTransaction() :: No database connection");
        return $this->connection->rollBack();
    }


    public function lastInsertId(): string
    {
        if (!$this->connection) $this->throwError("ngframerphp/core/Database.php/lastInsertId() :: No database connection");
        return $this->connection->lastInsertId();
    }


    public function fetch($fetchStyle = PDO::FETCH_ASSOC)
    {
        if ($this->queryExecutionStatus) return $this->queryStatement->fetch($fetchStyle);
        else $this->throwError("ngframerphp/core/Database.php/fetch() :: No executed statement to fetch results");
    }


    public function fetchAll($fetchStyle = PDO::FETCH_ASSOC): bool|array
    {
        if ($this->queryExecutionStatus) return $this->queryStatement->fetchAll($fetchStyle);
        else $this->throwError("ngframerphp/core/Database.php/fetchAll() :: No executed statement to fetch results");
    }

    public function close(): void
    {
        if ($this->connection) $this->connection = null;
        $this->queryExecutionStatus = false;
    }
}
