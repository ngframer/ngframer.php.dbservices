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


    private function throwError(string $message): void
    {
        $this->logError($message);
        Throw new PDOException($message);
    }


    private function logError(string $message): void
    {
        $logFile = ROOT . '/logs/database_errors.log';
        error_log(date("[Y-m-d H:i:s]") . " " . $message . PHP_EOL, 3, $logFile);
    }


    /**
     * @throws Exception
     */
    private function validateConfigFile(): void
    {
        $configFile = ROOT . '/config/database.php';
        if (!file_exists($configFile)) {
            $this->throwError("NGFramerPHPDbService/Database.php/checkConfigFile() :: Missing Connection Configuration File. Please check /config/database.php.");
        }
        else require_once $configFile;
    }


    public function prepare(string $query, $options = []): ?static
    {
        if (!$this->connection) $this->throwError("ngframerphp/core/Database.php/prepare() :: No database connection to prepare the statement.");
        else $this->queryStatement = $this->connection->prepare($query, $options); return $this;
    }


    public function bindParam(string|array ...$args): ?static
    {
        // If queryStatement is null or if queryStatement is false, then throw an exception.
        if ($this->queryStatement == null || !$this->queryStatement) {
            $this->throwError("ngframerphp/core/Database.php/bindParam() :: No prepared statement (queryStatement) to bind parameters.");
        }

        // If there are no parameters to bind to the statement.
        if (count($args) === 0) {
            $this->throwError("ngframerphp/core/Database.php/bindParam() :: No parameters to bind to the statement (queryStatement).");
        }

        // If some parameters are sent to bind to the statement.
        else{
            foreach ($args as $arg) {
                // For string based conditions.
                if ((count($args) === 2 || count($args) === 3 ) && (!is_array($args[0]) && !is_array($args[1]) && !is_array($args[2]))) $this->bindValues($args);
                // For array based conditions.
                else if (is_array($arg)) $this->bindValues($arg);
                // If parameters are not in format.
                else $this->throwError("ngframerphp/core/Database.php/bindParam() :: Invalid parameter format for bindings.");
            }
            return $this;
        }
    }


    private function bindValues(array $args): void
    {
        if (is_string($args[0]) && !is_array($args[1]) && ((count($args) === 3)) || (count($args) === 2)) {
            // $args[0] is the name of the parameter.
            // $args[1] is the value of the parameter.
            // $args[2] is the data type of the parameter.
            $this->queryStatement->bindParam(":".$args[0], $args[1], $args[2] ?? PDO::PARAM_STR);
        } else $this->throwError("ngframerphp/core/Database.php/bindValues() :: Invalid parameter format for binding.");
    }


    public function execute(): static
    {
        if (!$this->queryStatement) $this->throwError("ngframerphp/core/Database.php/bindParam() :: No prepared statement to execute.");
        $this->queryExecutionStatus = $this->queryStatement->execute();
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
        if (!$this->queryExecutionStatus) $this->throwError("ngframerphp/core/Database.php/fetch() :: No executed statement to fetch results");
        return $this->queryStatement->fetch($fetchStyle);
    }


    public function fetchAll($fetchStyle = PDO::FETCH_ASSOC): bool|array
    {
        if (!$this->queryExecutionStatus) $this->throwError("ngframerphp/core/Database.php/fetchAll() :: No executed statement to fetch results");
        return $this->queryStatement->fetchAll($fetchStyle);
    }

    public function close(): void
    {
        if ($this->connection) $this->connection = null;
        $this->queryExecutionStatus = false;
    }
}
