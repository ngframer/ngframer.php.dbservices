<?php

namespace NGFramer\NGFramerPHPDbServices;

use PDO;
use Exception;
use PDOStatement;
use PDOException;
use app\config\ApplicationConfig;
use app\config\DatabaseConfig;
use NGFramer\NGFramerPHPExceptions\exceptions\DbServicesException;


class Database
{
    // Singleton instance variable of the class.
    private static ?Database $instance = null;

    // Using static to hold on the single instance of PDO.
    private static ?PDO $connection = null;

    // PDO connection related variable.
    private null|bool|PDOStatement $queryStatement = null;
    private bool $queryExecutionStatus = false;


    /**
     * Function checks if the instance is already created or not, if yes, returns that instance, else returns by creating.
     * @return Database. Returns the singleton instance.
     * @throws DbServicesException
     */
    public static function getInstance(): Database
    {
        // This will create an instance and instance will initialize the constructor.
        if (empty(self::$instance)) {
            self::$instance = new self();
        }

        // Only when instance is available but connection is destroyed.
        if (empty(self::$connection)) {
            self::$instance->connect();
        }

        // Finally the instance will always have a PDO connection to operate on, return it.
        return self::$instance;
    }


    /**
     * Private constructor to make sure, no more of it's instance can be created.
     * @throws DbServicesException
     * @return void
     */
    private function __construct()
    {
        $this->connect();
    }


    /**
     * Function connects to the database using PDO.
     * Connects to the database only if the connection is not already created.
     * @return void
     * @throws DbServicesException
     */
    private function connect(): void
    {
        // Only create an instance if the connection is not already created.
        if (empty(self::$connection)) {

            // The main connection.
            $pdoAttributes = [
                PDO::ATTR_PERSISTENT => true,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ];

            // Check if the DatabaseConfig class exists or not.
            if (!class_exists('app/config/DatabaseConfig')) {
                throw new DbServicesException("The class app/config/DatabaseConfig doesn't exist.", 4000001);
            }

            if (!ApplicationConfig::exists('db_dsn') || !ApplicationConfig::exists('db_user') || !ApplicationConfig::exists('db_pass')) {
                throw new DbServicesException("Requested variable are not set.", 4000002);
            }

            try {
                // Define the DB_DSN, DB_USER, and DB_PASS in the /config/database.php file of the project/root directory.
                $db_dsn = DatabaseConfig::get('db_dsn');
                $db_user = DatabaseConfig::get('db_user');
                $db_pass = DatabaseConfig::get('db_pass');
                self::$connection = new PDO($db_dsn, $db_user, $db_pass, $pdoAttributes);
            } catch (PDOException $exception) {
                // Check the code of the exception.
                if ($exception->getCode() == 2002) {
                    throw new DbServicesException("Connection refused to database server.", 4000003);
                } else if ($exception->getCode() == 1045) {
                    throw new DbServicesException("Invalid username or password.", 4000004);
                } else if ($exception->getCode() == 1049) {
                    throw new DbServicesException("Database doesn't exist.", 4000005);
                } else {
                    error_log("The exception caught is " . json_encode($exception) . ". New Code: 4000006 (4M6)");
                    throw new DbServicesException("Database connection failed of unknown reason.", 4000006);
                }
            }
        }
    }


    /**
     * Function to prepare the queryStatement.
     * @param string|null $queryStatement . Query to prepare for the execution.
     * @param array $options . Optional parameter to pass options to the prepare function.
     * @return Database|null
     * @throws DbServicesException
     */
    public function prepare(string $queryStatement = null, array $options = []): ?static
    {
        if (empty($queryStatement)) throw new DbServicesException("No query to prepare.", 4000007);
        else $this->queryStatement = self::$connection->prepare($queryStatement, $options);
        return $this;
    }


    /**
     * Function to bind multiple parameters to the queryStatement.
     *  Uses referenced variable names to bind.
     *  @param array $args . Array of parameters to bind.
     * @throws DbServicesException
     */
    public function bindParams(array &$args): ?static
    {
        // If queryStatement is false, then throw an exception.
        if (!$this->queryStatement) {
            throw new DbServicesException("No queryStatement to bind parameters to.", 4000008);
        }
        // If there are no parameters to bind to the statement.
        if (count($args) === 0) {
            throw new DbServicesException("No parameters passed to bind to queryStatement.", 4000009);
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


    /**
     * Function to bind the parameters to the queryStatement.
     * To bind multiple parameters at once, use bindValues/bindParams.
     * Uses referenced variable names to bind.
     * @param string $column
     * @param $value
     * @param int $type
     * @return Database
     */
    public function bindParam(string $column, &$value, int $type = PDO::PARAM_STR): static
    {
        if (!str_starts_with($column, ":")) $column = ":" . $column;
        $this->queryStatement->bindParam($column, $value, $type);
        return $this;
    }


    /**
     * Function to bind multiple values to the queryStatement.
     * @param array $args . Array of parameters to bind.
     * @throws DbServicesException
     */
    public function bindValues(array &$args): ?static
    {
        // If queryStatement is false, then throw an exception.
        if (!$this->queryStatement) {
            throw new DbServicesException("No queryStatement to bind parameters to.", 4000010);
        }
        // If there are no parameters to bind to the statement.
        if (count($args) === 0) {
            throw new DbServicesException("No parameters passed to bind to queryStatement.", 4000011);
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


    /**
     * Function to bind single value to the queryStatement.
     * Can bind only single value at once.
     * @param string $column
     * @param $value
     * @param int $type
     * @return Database
     */
    public function bindValue(string $column, $value, int $type = PDO::PARAM_STR): static
    {
        if (!str_starts_with($column, ":")) $column = ":" . $column;
        $this->queryStatement->bindParam($column, $value, $type);
        return $this;
    }


    /**
     * Function checks if the arguments of all the elements of the array are arrays or something else.
     * @param array $args
     * @return bool
     */
    private function areAllElementsArray(array $args): bool
    {
        foreach ($args as $arg) {
            if (!is_array($arg)) return false;
        }
        return true;
    }


    /**
     * Function to execute the query or the query statement.
     * Allows the execution of both the prepared execution type and direct execution type.
     * @param string|null $queryStatement . Query to prepare for the execution, optional. Only in case of direct execution type.
     * @throws DbServicesException
     */
    public function execute(string $queryStatement = null): static
    {
        // If queryStatement is not null, then execute the queryStatement.
        if ($queryStatement !== null && $this->queryStatement === null) {
            // query() function prepares and executes the queryStatement, and returns pdoStatement|false.
            $this->queryStatement = self::$connection->query($queryStatement);
            $this->queryExecutionStatus = $this->queryStatement !== false;
        } // If queryStatement is true, then execute the queryStatement.
        else if ($queryStatement === null && $this->queryStatement !== null) {
            $this->queryExecutionStatus = $this->queryStatement->execute();
        } // If queryStatement is false, throw an error.
        else throw new DbServicesException("Invalid or no queryStatement to execute.", 4000012);
        // Return the object for function chaining.
        return $this;
    }


    /**
     * Function to start the transaction.
     * @return bool
     */
    public function beginTransaction(): bool
    {
        return self::$connection->beginTransaction();
    }

    /**
     * Function to end the transaction and save the records.
     * @return bool
     */
    public function commit(): bool
    {
        return self::$connection->commit();
    }


    /**
     * Function to end the transaction and remove the records.
     * @return bool
     */
    public function rollback(): bool
    {
        return self::$connection->rollBack();
    }


    /**
     * Function to check if there are any active transactions.
     * @return bool
     */
    public function hasActiveTransactions(): bool
    {
        return self::$connection->inTransaction();
    }


    /**
     * Function to get the last inserted id.
     * @return string
     */
    public function lastInsertId(): string
    {
        return self::$connection->lastInsertId();
    }


    /**
     * Function to get the number of rows affected by the query.
     * @return int
     */
    public function rowCount(): int
    {
        return $this->queryStatement->rowCount();
    }


    /**
     * Clone of function rowCount().
     * Function to get the number of rows affected by the query.
     * @return int
     */
    public function affectedRowCount(): int
    {
        return $this->rowCount();
    }


    /**
     * Function to fetch the results.
     * @param int $fetchStyle
     * @return array . Returns all the results of the query.
     * @throws DbServicesException
     */
    public function fetch(int $fetchStyle = PDO::FETCH_ASSOC): array
    {
        if ($this->queryExecutionStatus) {
            return $this->queryStatement->fetch($fetchStyle);
        }
        throw new DbServicesException("No executed statement to fetch results", 4000013);
    }


    /**
     * Function to fetch all the results of the query.
     * @param int $fetchStyle
     * @return array . Returns all the results of the query.
     * @throws DbServicesException
     */
    public function fetchAll(int $fetchStyle = PDO::FETCH_ASSOC): array
    {
        if ($this->queryExecutionStatus) {
            return $this->queryStatement->fetchAll($fetchStyle);
        }
        throw new DbServicesException("No executed statement to fetch results", 4000014);
    }


    /**
     * Use this with caution. Can make the application unstable if used incorrectly.
     * ==========================>
     * Use this only when previous connection can't be used for another transaction.
     * Creates a new connection and assigns it to $connection variable.
     * Closing the connection is not possible.
     * @return void
     */
    public function close(): void
    {
        if (self::$connection) self::$connection = null;
        $this->queryExecutionStatus = false;
    }
}
