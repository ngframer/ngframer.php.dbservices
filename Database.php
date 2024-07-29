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
                throw new DbServicesException("The class app/config/DatabaseConfig doesn't exist.", 4001001);
            }

            if (!ApplicationConfig::exists('db_dsn') || !ApplicationConfig::exists('db_user') || !ApplicationConfig::exists('db_pass')) {
                throw new DbServicesException("Requested variable are not set.", 4002001);
            }

            try {
                // Define the DB_DSN, DB_USER, and DB_PASS in the /config/database.php file of the project/root directory.
                $db_dsn = DatabaseConfig::get('db_dsn');
                $db_user = DatabaseConfig::get('db_user');
                $db_pass = DatabaseConfig::get('db_pass');
                self::$connection = new PDO($db_dsn, $db_user, $db_pass, $pdoAttributes);
            } catch (PDOException $exception) {
                // Check the code of the exception.
                if ($exception->getCode() == 1045) {
                    throw new DbServicesException("Invalid username or password.", 4004001);
                } elseif ($exception->getCode() == 1049) {
                    throw new DbServicesException("Database doesn't exist.", 4004002);
                } elseif ($exception->getCode() == 2002) {
                    throw new DbServicesException("Connection refused to database server.", 4004003);
                } elseif ($exception->getCode() == '08001') {
                    throw new DbServicesException("SQL Client unable to establish a connection.", 4005001);
                } elseif ($exception->getCode() == '08003') {
                    throw new DbServicesException("The connection does not exists. Visit error_log for details.", 4005002);
                } elseif ($exception->getCode() == '08004') {
                    throw new DbServicesException("The connection has been failed. Visit error_log for details.", 4005003);
                } else {
                    error_log("The exception caught is " . json_encode($exception) . ". New Code: 4004004 (4M4004)");
                    throw new DbServicesException("Database connection failed of unknown reason.", 4004004);
                }
            }
        }
    }


    /**
     * Function to check if the connection exists.
     * @return bool
     */
    private function checkConnection(): bool
    {
        if (self::$connection instanceof PDO && !empty(self::$connection)) {
            return true;
        }
        return false;
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
        if (empty($queryStatement)) {
            throw new DbServicesException("No query to prepare.", 4007001);
        }

        // Check for any possible error handling.
        try {
            $this->queryStatement = self::$connection->prepare($queryStatement, $options);
        } catch (PDOException $exception) {
            if ($exception->getCode() == 42000) {
                throw new DbServicesException("Syntax error or Access violation.", 4022001);
            } elseif ($exception->getCode() == '42S02') {
                throw new DbServicesException("Base table or view not found.", 4023001);
            } else {
                error_log("The exception caught is " . json_encode($exception) . ". New Code: 4024001 (4M24001)");
                throw new DbServicesException("Something went wrong. Visit error_log for details.", 4024001);
            }
        }

        // Return for function chaining.
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
            throw new DbServicesException("No queryStatement to bind parameters to.", 4008001);
        }
        // If there are no parameters to bind to the statement.
        if (count($args) === 0) {
            throw new DbServicesException("No parameters passed to bind to queryStatement.", 4009001);
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
     * @throws DbServicesException
     */
    public function bindParam(string $column, &$value, int $type = PDO::PARAM_STR): static
    {
        if (!str_starts_with($column, ":")) {
            $column = ":" . $column;
        }

        // Now using the main function to bind.
        try {
            $this->queryStatement->bindParam($column, $value, $type);
        } catch (PDOException $exception) {
            $this->handleBind($exception);
        }

        // Return for function chaining.
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
            throw new DbServicesException("No queryStatement to bind parameters to.", 4010001);
        }
        // If there are no parameters to bind to the statement.
        if (count($args) === 0) {
            throw new DbServicesException("No parameters passed to bind to queryStatement.", 4011001);
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
     * @throws DbServicesException
     */
    public function bindValue(string $column, $value, int $type = PDO::PARAM_STR): static
    {
        if (!str_starts_with($column, ":")) {
            $column = ":" . $column;
        }

        // Now using the main function to bind.
        try {
            $this->queryStatement->bindValue($column, $value, $type);
        } catch (PDOException $exception) {
            $this->handleBind($exception);
        }

        // Return for function chaining.
        return $this;
    }


    /**
     * @param PDOException $exception
     * @return void
     * @throws DbServicesException
     */
    private function handleBind(PDOException $exception): void
    {
        if ($exception->getCode() == 22001) {
            throw new DbServicesException("Data too long to insert or update.", 4015001);
        } elseif ($exception->getCode() == 22007) {
            throw new DbServicesException("Invalid datetime/timestamp value is invalid.", 4016001);
        } elseif ($exception->getCode() == 'HY093') {
            throw new DbServicesException("Invalid parameter number.", 4026001);
        } else {
            error_log("The exception caught is " . json_encode($exception) . ". New Code: 4017001 (4M17001)");
            throw new DbServicesException("Something went wrong while binding. Visit error_log for details.", 4017001);
        }
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
     * @param string|null $queryStatement Query to prepare for the execution, optional. Only in case of direct execution type.
     * @throws DbServicesException
     * @return Database
     */
    public function execute(string $queryStatement = null): static
    {
        // Check if a direct query statement is provided and no existing prepared statement exists.
        if ($queryStatement !== null && $this->queryStatement === null) {
            $this->executeDirectQuery($queryStatement);
        } elseif ($queryStatement === null && $this->queryStatement !== null) {
            $this->executePreparedStatement();
        } else {
            throw new DbServicesException("Invalid or no queryStatement to execute.", 4012001);
        }

        // Return the object for function chaining.
        return $this;
    }


    /**
     * Execute a direct query statement.
     * @param string $queryStatement
     * @throws DbServicesException
     */
    private function executeDirectQuery(string $queryStatement): void
    {
        try {
            $this->queryStatement = self::$connection->query($queryStatement);
            $this->queryExecutionStatus = $this->queryStatement !== false;
        } catch (PDOException $exception) {
            $this->handleExecute($exception);
        }
    }


    /**
     * Execute a prepared statement.
     * @throws DbServicesException
     */
    private function executePreparedStatement(): void
    {
        try {
            $this->queryExecutionStatus = $this->queryStatement->execute();
        } catch (PDOException $exception) {
            $this->handleExecute($exception);
        }
    }


    /**
     * This function will handle the exception from the executeDirectQuery and executePreparedStatement functions.
     * @param Exception $exception
     * @return void
     * @throws DbServicesException
     */
    private function handleExecute(Exception $exception): void
    {
        if ($exception->getCode() == 2006) {
            throw new DbServicesException("Lost connection to server during query. Visit error_log for details.", 4030001);
        } elseif ($exception->getCode() == 2013) {
            throw new DbServicesException("Lost connection to server at query end. Visit error_log for details.", 4031001);
        } elseif ($exception->getCode() == 22000) {
            throw new DbServicesException("General data error. Visit error_log for details.", 4036001);
        } elseif ($exception->getCode() == 22001) {
            throw new DbServicesException("Data too long to insert or update.", 4025001);
        } elseif ($exception->getCode() == 22002) {
            throw new DbServicesException("Indicator variable required not supplied.", 4032001);
        } elseif ($exception->getCode() == 22003) {
            throw new DbServicesException("Data (numeric) value out of range.", 4027001);
        } elseif ($exception->getCode() == 22004) {
            throw new DbServicesException("Null value not allowed in the column.", 4033001);
        } elseif ($exception->getCode() == 22005) {
            throw new DbServicesException("Error in assignment of value to parameter.", 4037001);
        } elseif ($exception->getCode() == 22007) {
            throw new DbServicesException("Invalid date or time format.", 4038001);
        } elseif ($exception->getCode() == 22008) {
            throw new DbServicesException("Date time field value out of range.", 4039001);
        } elseif ($exception->getCode() == 23000) {
            throw new DbServicesException("Integrity constraint violation.", 4019001);
        } elseif ($exception->getCode() == 23502) {
            throw new DbServicesException("Not Null Violation.", 4040001);
        } elseif ($exception->getCode() == 23503) {
            throw new DbServicesException("Foreign Key Violation.", 4041001);
        } elseif ($exception->getCode() == 23505) {
            throw new DbServicesException("Unique Key Violation.", 4042001);
        } elseif ($exception->getCode() == 40001) {
            throw new DbServicesException("Deadlock condition found. Visit error_log for details.", 4028001);
        } elseif ($exception->getCode() == 42000) {
            throw new DbServicesException("Syntax error or Access violation.", 4018001);
        } elseif ($exception->getCode() == 42501) {
            throw new DbServicesException("Insufficient privilege. Visit error_log for details.", 4026001);
        } elseif ($exception->getCode() == 42601) {
            throw new DbServicesException("Syntax error. Visit error_log for details.", 4022001);
        } elseif ($exception->getCode() == 42602) {
            throw new DbServicesException("Invalid cursor name. Visit error_log for details.", 4043001);
        } elseif ($exception->getCode() == 42622) {
            throw new DbServicesException("Too long identifier name.", 4044001);
        } elseif ($exception->getCode() == 42701) {
            throw new DbServicesException("The column name already exists.", 4045001);
        } elseif ($exception->getCode() == 42703) {
            throw new DbServicesException("Undefined column.", 4046001);
        } elseif ($exception->getCode() == '42P01') {
            throw new DbServicesException("Table or view not found.", 4047001);
        } elseif ($exception->getCode() == '42P02') {
            throw new DbServicesException("Undefined parameter.", 4048001);
        } elseif ($exception->getCode() == '42P03') {
            throw new DbServicesException("Duplicate cursor.", 4049001);
        } elseif ($exception->getCode() == '42P04') {
            throw new DbServicesException("Duplicate database.", 4050001);
        } elseif ($exception->getCode() == 'HY000') {
            throw new DbServicesException("Unknown general error. Visit error_log for details.", 4020001);
        } elseif ($exception->getCode() == 'HY009') {
            throw new DbServicesException("Error in number of data to bind. Visit error_log for details.", 4029001);
        } elseif ($exception->getCode() == 'HY013') {
            throw new DbServicesException("Memory management error. Visit error_log for details.", 4034001);
        } elseif ($exception->getCode() == 'HY014') {
            throw new DbServicesException("Limit on the number of handles exceeded.", 4035001);
        } else {
            error_log("The exception caught is " . json_encode($exception) . ". New Code: 4021001 (4M21001)");
            throw new DbServicesException("Something went wrong while executing query. Visit error_log for details.", 4021001);
        }
    }


    /**
     * Function to start the transaction.
     * @return bool
     * @throws DbServicesException
     */
    public function beginTransaction(): bool
    {
        if (empty(self::$connection)) {
            throw new DbServicesException("Connection not established.", 4002001);
        }
        if ($this->hasActiveTransactions()) {
            throw new DbServicesException("Transaction already in progress.", 4003001);
        }
        return self::$connection->beginTransaction();
    }


    /**
     * Function to end the transaction and save the records.
     * @return bool
     * @throws DbServicesException
     */
    public function commit(): bool
    {
        if (!$this->checkConnection()) {
            throw new DbServicesException("Connection not established.", 4002001);
        }
        if (!self::$connection->inTransaction()) {
            throw new DbServicesException("Transaction not in progress.", 4004001);
        }
        return self::$connection->commit();
    }


    /**
     * Function to end the transaction and remove the records.
     * @return bool
     * @throws DbServicesException
     */
    public function rollback(): bool
    {
        if (!$this->checkConnection()) {
            throw new DbServicesException("Connection not established.", 4002001);
        }
        if (!self::$connection->inTransaction()) {
            throw new DbServicesException("Transaction not in progress.", 4004001);
        }
        return self::$connection->rollBack();
    }


    /**
     * Function to check if there are any active transactions.
     * @return bool
     * @throws DbServicesException
     */
    public function hasActiveTransactions(): bool
    {
        if (!$this->checkConnection()) {
            throw new DbServicesException("Connection not established.", 4002001);
        }
        return self::$connection->inTransaction();
    }


    /**
     * Function to get the last inserted id.
     * @return string
     * @throws DbServicesException
     */
    public function lastInsertId(): string
    {
        if (!$this->checkConnection()) {
            throw new DbServicesException("Connection not established.", 4002001);
        }
        return self::$connection->lastInsertId();
    }


    /**
     * Function to get the number of rows affected by the query.
     * @return int
     * @throws DbServicesException
     */
    public function rowCount(): int
    {
        if ($this->queryExecutionStatus) {
            return $this->queryStatement->rowCount();
        }
        throw new DbServicesException("No executed statement to fetch results", 4013001);
    }


    /**
     * Clone of function rowCount().
     * Function to get the number of rows affected by the query.
     * @return int
     * @throws DbServicesException
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
            try {
                return $this->queryStatement->fetch($fetchStyle);
            } catch (PDOException $exception) {
                $this->handleFetch($exception);
            }
        }
        throw new DbServicesException("No executed statement to fetch results", 4013001);
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
            try {
                return $this->queryStatement->fetchAll($fetchStyle);
            } catch (PDOException $exception) {
                $this->handleFetch($exception);
            }
        }
        throw new DbServicesException("No executed statement to fetch results", 4014001);
    }


    /**
     * Function to handle the PDO Exception while fetching and create new DbServicesException.
     * @throws DbServicesException
     */
    private function handleFetch(PDOException $exception): void
    {
        if ($exception->getCode() == 22001) {
            throw new DbServicesException("Data too long fetch.", 4015001);
        } elseif ($exception->getCode() == 22002) {
            throw new DbServicesException(" Indicator variable required but not supplied.", 4016001);
        } elseif ($exception->getCode() == 23003) {
            throw new DbServicesException("Numeric value out of range.", 4017001);
        } elseif ($exception->getCode() == 23004) {
            throw new DbServicesException("Null value not allowed.", 4018001);
        } elseif ($exception->getCode() == 23005) {
            throw new DbServicesException("Error in assignment.", 4019001);
        } elseif ($exception->getCode() == 23007) {
            throw new DbServicesException("Invalid datetime format.", 4020001);
        } elseif ($exception->getCode() == 22008) {
            throw new DbServicesException("Datetime field overflow", 4021001);
        } elseif ($exception->getCode() == 22012) {
            throw new DbServicesException("Divisible by zero.", 4022001);
        } elseif ($exception->getCode() == 22018) {
            throw new DbServicesException("Invalid character value for cast.", 4023001);
        } else {
            error_log("The exception caught is " . json_encode($exception) . ". New Code: 4042001 (4M42001)");
            throw new DbServicesException("Unknown error occurred during fetching results. Visit error_log for details.", 4042001);
        }
    }


    /**
     * Use this with caution. Can make the application unstable if used incorrectly.
     * ==========================>
     * Use this only when previous connection can't be used for another transaction.
     * Creates a new connection and assigns it to $connection variable.
     * Closing the connection is not possible.
     * @return void
     * @throws DbServicesException
     */
    public function close(): void
    {
        if ($this->checkConnection()) {
            self::$connection = null;
            $this->queryExecutionStatus = false;
        } else {
            throw new DbServicesException("Connection not established.", 4002001);
        }
    }
}