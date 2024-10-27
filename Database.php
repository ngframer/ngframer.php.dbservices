<?php

namespace NGFramer\NGFramerPHPDbServices;

use NGFramer\NGFramerPHPExceptions\exceptions\BaseException;
use PDO;
use Exception;
use PDOException;
use PDOStatement;
use app\config\DatabaseConfig;
use app\config\ApplicationConfig;
use NGFramer\NGFramerPHPDbServices\Exceptions\DbServicesException;


class Database
{
    /**
     * Singleton instance of the Database class.
     * @var Database|null
     */
    private static ?Database $instance = null;

    /**
     * PDO connection to the database.
     * @var PDO|null
     */
    private static ?PDO $connection = null;

    /**
     * Query statement to execute.
     * @var bool|PDOStatement|null
     */
    private null|bool|PDOStatement $queryStatement = null;
    /**
     * Query execution status.
     * @var bool
     */
    private bool $queryExecutionStatus = false;


    /**
     * Function checks if the instance is already created or not, if yes, returns that instance, else returns by creating.
     *
     * @return Database. Returns the singleton instance.
     * @throws DbServicesException
     */
    public static function getInstance(): Database
    {
        // This will create an instance and instance will initialize the constructor.
        if (empty(self::$instance)) {
            self::$instance = new self();
        }

        // Only when an instance is available but the connection is destroyed.
        if (empty(self::$connection)) {
            self::$instance->connect();
        }

        // Finally, the instance will always have a PDO connection to operate on, return it.
        return self::$instance;
    }


    /**
     * Private constructor to make sure no more of it's instance can be created.
     *
     * @return void
     * @throws DbServicesException
     */
    private function __construct()
    {
        $this->connect();
    }


    /**
     * Function connects to the database using PDO.
     * Connects to the database only if the connection is not already created.
     *
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
            if (!class_exists('app\config\DatabaseConfig')) {
                throw new DbServicesException("The class app/config/DatabaseConfig doesn't exist.", 4001001, 'dbservices.databaseConfigClassNotFound');
            }

            // Check if the get method exists or not.
            if (!method_exists('app\config\DatabaseConfig', 'get')) {
                throw new DbServicesException("Requested methods are not availabe on the class.", 4001002, 'dbservices.databaseConfigMethodNotFound');
            }

            // Define the DB_DSN, DB_USER, and DB_PASS in the /config/database.php file of the project/root directory.
            try {
                // Try to get the DatabaseConfig.
                try {
                    $db_dsn = DatabaseConfig::get('db_dsn');
                    $db_user = DatabaseConfig::get('db_user');
                    $db_pass = DatabaseConfig::get('db_pass');
                } catch (BaseException $exception) {
                    throw new DbServicesException("All the requested variables do not exist in ApplicationConfig.", 4002001, 'dbservices.databaseConfigVariablesNotSet');
                }
                // If all the variables exist in the DatabaseConfig.
                self::$connection = new PDO($db_dsn, $db_user, $db_pass, $pdoAttributes);
            } catch (PDOException $exception) {
                // Check the code of the exception.
                if ($exception->getCode() == 1045) {
                    throw new DbServicesException("Invalid username or password.", 4004001, 'dbservices.invalidUsernameOrPassword');
                } elseif ($exception->getCode() == 1049) {
                    throw new DbServicesException("Database doesn't exist.", 4004002, 'dbservices.databaseDoesNotExist');
                } elseif ($exception->getCode() == 2002) {
                    throw new DbServicesException("Connection refused to database server.", 4004003, 'dbservices.connectionRefused');
                } elseif ($exception->getCode() == '08001') {
                    throw new DbServicesException("SQL Client unable to establish a connection.", 4005001, 'dbservices.unableToEstablishConnection');
                } elseif ($exception->getCode() == '08003') {
                    throw new DbServicesException("The connection does not exists. Visit error_log for details.", 4005002, 'dbservices.connectionDoesNotExist');
                } elseif ($exception->getCode() == '08004') {
                    throw new DbServicesException("The connection has been failed. Visit error_log for details.", 4005003, 'dbservices.connectionFailed');
                } else {
                    error_log("The exception caught is " . json_encode($exception) . ". New Code: 4004004 (4M4K4)");
                    throw new DbServicesException("Database connection failed of unknown reason.", 4004004, 'dbservices.unknownDatabaseConnectionError');
                }
            }
        }
    }


    /**
     * Function to check if the connection exists.
     *
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
     *
     * @param string|null $queryStatement . Query to prepare for the execution.
     * @param array $options . Optional parameter to pass options to the prepare function.
     * @return Database|null
     * @throws DbServicesException
     */
    public function prepare(string $queryStatement = null, array $options = []): ?static
    {
        if (empty($queryStatement)) {
            throw new DbServicesException("No query to prepare.", 4007001, 'dbservices.noQueryToPrepare');
        }

        // Check for any possible error handling.
        try {
            $this->queryStatement = self::$connection->prepare($queryStatement, $options);
        } catch (PDOException $exception) {
            if ($exception->getCode() == 42000) {
                throw new DbServicesException("Syntax error or Access violation.", 4022001, 'dbservices.syntaxErrorOrAccessViolation');
            } elseif ($exception->getCode() == '42S02') {
                throw new DbServicesException("Base table or view not found.", 4023001, 'dbservices.baseTableOrViewNotFound');
            } else {
                error_log("The exception caught is " . json_encode($exception) . ". New Code: 4024001 (4M24K1)");
                throw new DbServicesException("Something went wrong. Visit error_log for details.", 4024001, 'dbservices.unknownError');
            }
        }

        // Return for function chaining.
        return $this;
    }


    /**
     * Function to bind multiple parameters to the queryStatement.
     *  Uses referenced variable names to bind.
     * @param array $args . Array of parameters to bind.
     *
     * @throws DbServicesException
     */
    public function bindParams(array &$args): ?static
    {
        // If queryStatement is false, then throw an exception.
        if (!$this->queryStatement) {
            throw new DbServicesException("No queryStatement to bind parameters to.", 4008001, 'dbservices.noQueryStatementToBind');
        }

        // If there are no parameters to bind to the statement.
        if (count($args) === 0) {
            throw new DbServicesException("No parameters passed to bind to queryStatement.", 4009001, 'dbservices.noParametersToBind');
        }

        // Check if all elements are arrays.
        if (!$this->areAllElementsArray($args)) {
            throw new DbServicesException("Invalid format of data passed to bind.", 4009002, 'dbservices.invalidDataFormat');
        }

        // Loop through the args array to bind the parameters.
        foreach ($args as $arg) {
            // Get values for the column, value, and type.
            $column = $arg['name'] ?? $arg[0];
            $value = $arg['value'] ?? $arg[1];
            $type = $arg['type'] ?? $arg[2] ?? PDO::PARAM_STR;
            $type = $this->resolveType($type);

            // Now bind the parameters.
            $this->bindParam($column, $value, $type);
        }

        // Return for function chaining.
        return $this;
    }


    /**
     * Function to bind the parameters to the queryStatement.
     * To bind multiple parameters at once, use bindValues/bindParams.
     * Uses referenced variable names to bind.
     *
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
    public function bindValues(array $args): ?static
    {
        // If queryStatement is false, then throw an exception.
        if (!$this->queryStatement) {
            throw new DbServicesException("No queryStatement to bind parameters to.", 4010001, 'dbservices.noQueryStatementToBind');
        }

        // If there are no parameters to bind to the statement.
        if (count($args) === 0) {
            throw new DbServicesException("No parameters passed to bind to queryStatement.", 4011001, 'dbservices.noParametersToBind');
        }

        // Check if all elements are arrays.
        if (!$this->areAllElementsArray($args)) {
            throw new DbServicesException("Invalid format of data passed to bind.", 4011002, 'dbservices.invalidDataFormat');
        }

        // Loop through the args array to bind the values.
        foreach ($args as $arg) {
            // Get values for the column, value, and type.
            $column = $arg['name'] ?? $arg[0];
            $value = $arg['value'] ?? $arg[1];
            $type = $arg['type'] ?? $arg[2] ?? PDO::PARAM_STR;
            $type = $this->resolveType($type);

            // Now bind the values.
            $this->bindValue($column, $value, $type);
        }

        // Return for function chaining.
        return $this;
    }


    /**
     * Resolves the data type for binding to the prepared statement.
     *
     * @param mixed $type.
     * @return int Returns the corresponding PDO::PARAM_* constant for the given type
     * @throws DbServicesException
     */
    private function resolveType($type): int
    {
        // Check the type of the value and return the corresponding PDO constant.
        if ($type === 'string' || $type === PDO::PARAM_STR) {
            return PDO::PARAM_STR;
        } elseif ($type === 'integer' || $type === 'int' || $type === PDO::PARAM_INT) {
            return PDO::PARAM_INT;
        } elseif ($type === 'boolean' || $type === 'bool' || $type === PDO::PARAM_BOOL) {
            return PDO::PARAM_BOOL;
        } elseif ($type === 'null' || $type === PDO::PARAM_NULL) {
            return PDO::PARAM_NULL;
        } else {
            throw new DbServicesException("Invalid value type to bind.", 4012001, 'dbservices.invalidValueType');
        }
    }


    /**
     * Function to bind a single value to the queryStatement.
     * Can bind only a single value at once.
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
     * Function to handle the exception from the bindParam and bindValue functions.
     *
     * @param PDOException $exception
     * @return void
     * @throws DbServicesException
     */
    private function handleBind(PDOException $exception): void
    {
        if ($exception->getCode() == 22001) {
            throw new DbServicesException("Data too long to insert or update.", 4015001, 'dbservices.dataTooLong');
        } elseif ($exception->getCode() == 22007) {
            throw new DbServicesException("Invalid datetime/timestamp value is invalid.", 4016001, 'dbservices.invalidDateTimeValue');
        } elseif ($exception->getCode() == 'HY093') {
            throw new DbServicesException("Invalid parameter number.", 4026001, 'dbservices.invalidParameterNumber');
        } else {
            error_log("The exception caught is " . json_encode($exception) . ". New Code: 4017001 (4M17K1)");
            throw new DbServicesException("Something went wrong while binding. Visit error_log for details.", 4017001, 'dbservices.unknownError');
        }
    }


    /**
     * Function checks if elements of the array are arrays or something else.
     *
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
     *
     * @param string|null $queryStatement Query to prepare for the execution, optional. Only in the case of a direct execution type.
     * @return Database
     * @throws DbServicesException
     */
    public function execute(string $queryStatement = null): static
    {
        // Check if a direct query statement is provided and no existing prepared statement exists.
        if ($queryStatement !== null && $this->queryStatement === null) {
            $this->executeDirectQuery($queryStatement);
        } elseif ($queryStatement === null && $this->queryStatement !== null) {
            $this->executePreparedStatement();
        } else {
            throw new DbServicesException("Invalid or no queryStatement to execute.", 4012001, 'dbservices.invalidQueryStatement');
        }

        // Return the object for function chaining.
        return $this;
    }


    /**
     * Execute a direct query statement.
     *
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
     *
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
     *
     * @param Exception $exception
     * @return void
     * @throws DbServicesException
     */
    private function handleExecute(Exception $exception): void
    {
        if ($exception->getCode() == 2006) {
            throw new DbServicesException("Lost connection to server during query. Visit error_log for details.", 4030001, 'dbservices.lostConnection');
        } elseif ($exception->getCode() == 2013) {
            throw new DbServicesException("Lost connection to server at query end. Visit error_log for details.", 4031001, 'dbservices.lostConnectionAtEnd');
        } elseif ($exception->getCode() == 22000) {
            throw new DbServicesException("General data error. Visit error_log for details.", 4036001, 'dbservices.generalDataError');
        } elseif ($exception->getCode() == 22001) {
            throw new DbServicesException("Data too long to insert or update.", 4025001, 'dbservices.dataTooLong');
        } elseif ($exception->getCode() == 22002) {
            throw new DbServicesException("Indicator variable required not supplied.", 4032001, 'dbservices.indicatorVariableRequired');
        } elseif ($exception->getCode() == 22003) {
            throw new DbServicesException("Data (numeric) value out of range.", 4027001, 'dbservices.dataValueOutOfRange');
        } elseif ($exception->getCode() == 22004) {
            throw new DbServicesException("Null value not allowed in the column.", 4033001, 'dbservices.nullValueNotAllowed');
        } elseif ($exception->getCode() == 22005) {
            throw new DbServicesException("Error in assignment of value to parameter.", 4037001, 'dbservices.errorInAssignment');
        } elseif ($exception->getCode() == 22007) {
            throw new DbServicesException("Invalid date or time format.", 4038001, 'dbservices.invalidDateTimeFormat');
        } elseif ($exception->getCode() == 22008) {
            throw new DbServicesException("Date time field value out of range.", 4039001, 'dbservices.dateTimeFieldOutOfRange');
        } elseif ($exception->getCode() == 23000) {
            throw new DbServicesException("Integrity constraint violation.", 4019001, 'dbservices.integrityConstraintViolation');
        } elseif ($exception->getCode() == 23502) {
            throw new DbServicesException("Not Null Violation.", 4040001, 'dbservices.notNullViolation');
        } elseif ($exception->getCode() == 23503) {
            throw new DbServicesException("Foreign Key Violation.", 4041001, 'dbservices.foreignKeyViolation');
        } elseif ($exception->getCode() == 23505) {
            throw new DbServicesException("Unique Key Violation.", 4042001, 'dbservices.uniqueKeyViolation');
        } elseif ($exception->getCode() == 40001) {
            throw new DbServicesException("Deadlock condition found. Visit error_log for details.", 4028001, 'dbservices.deadlockCondition');
        } elseif ($exception->getCode() == 42000) {
            throw new DbServicesException("Syntax error or Access violation.", 4018001, 'dbservices.syntaxErrorOrAccessViolation');
        } elseif ($exception->getCode() == 42501) {
            throw new DbServicesException("Insufficient privilege. Visit error_log for details.", 4026001, 'dbservices.insufficientPrivilege');
        } elseif ($exception->getCode() == 42601) {
            throw new DbServicesException("Syntax error. Visit error_log for details.", 4022001, 'dbservices.syntaxError');
        } elseif ($exception->getCode() == 42602) {
            throw new DbServicesException("Invalid cursor name. Visit error_log for details.", 4043001, 'dbservices.invalidCursorName');
        } elseif ($exception->getCode() == 42622) {
            throw new DbServicesException("Too long identifier name.", 4044001, 'dbservices.tooLongIdentifierName');
        } elseif ($exception->getCode() == 42701) {
            throw new DbServicesException("The column name already exists.", 4045001, 'dbservices.columnNameAlreadyExists');
        } elseif ($exception->getCode() == 42703) {
            throw new DbServicesException("Undefined column.", 4046001, 'dbservices.undefinedColumn');
        } elseif ($exception->getCode() == '42P01') {
            throw new DbServicesException("Table or view not found.", 4047001, 'dbservices.tableOrViewNotFound');
        } elseif ($exception->getCode() == '42P02') {
            throw new DbServicesException("Undefined parameter.", 4048001, 'dbservices.undefinedParameter');
        } elseif ($exception->getCode() == '42P03') {
            throw new DbServicesException("Duplicate cursor.", 4049001, 'dbservices.duplicateCursor');
        } elseif ($exception->getCode() == '42P04') {
            throw new DbServicesException("Duplicate database.", 4050001, 'dbservices.duplicateDatabase');
        } elseif ($exception->getCode() == 'HY000') {
            throw new DbServicesException("Unknown general error. Visit error_log for details.", 4020001, 'dbservices.unknownGeneralError');
        } elseif ($exception->getCode() == 'HY009') {
            throw new DbServicesException("Error in number of data to bind. Visit error_log for details.", 4029001, 'dbservices.errorInNumberOfDataToBind');
        } elseif ($exception->getCode() == 'HY013') {
            throw new DbServicesException("Memory management error. Visit error_log for details.", 4034001, 'dbservices.memoryManagementError');
        } elseif ($exception->getCode() == 'HY014') {
            throw new DbServicesException("Limit on the number of handles exceeded.", 4035001, 'dbservices.limitOnNumberOfHandlesExceeded');
        } else {
            error_log("The exception caught is " . json_encode($exception) . ". New Code: 4021001 (4M21K1)");
            throw new DbServicesException("Something went wrong while executing query. Visit error_log for details.", 4021001, 'dbservices.unknownError');
        }
    }


    /**
     * Function to start the transaction.
     *
     * @return bool
     * @throws DbServicesException
     */
    public function beginTransaction(): bool
    {
        if (empty(self::$connection)) {
            throw new DbServicesException("Connection not established.", 4002001, 'dbservices.connectionNotEstablished');
        }
        if ($this->hasActiveTransactions()) {
            throw new DbServicesException("Transaction already in progress.", 4003001, 'dbservices.transactionAlreadyInProgress');
        }
        return self::$connection->beginTransaction();
    }


    /**
     * Function to end the transaction and save the records.
     *
     * @return bool
     * @throws DbServicesException
     */
    public function commit(): bool
    {
        if (!$this->checkConnection()) {
            throw new DbServicesException("Connection not established.", 4002001, 'dbservices.connectionNotEstablished');
        }
        if (!self::$connection->inTransaction()) {
            throw new DbServicesException("Transaction not in progress.", 4004001, 'dbservices.transactionNotInProgress');
        }
        return self::$connection->commit();
    }


    /**
     * Function to end the transaction and remove the records.
     *
     * @return bool
     * @throws DbServicesException
     */
    public function rollback(): bool
    {
        if (!$this->checkConnection()) {
            throw new DbServicesException("Connection not established.", 4002001, 'dbservices.connectionNotEstablished');
        }
        if (!self::$connection->inTransaction()) {
            throw new DbServicesException("Transaction not in progress.", 4004001, 'dbservices.transactionNotInProgress');
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
            throw new DbServicesException("Connection not established.", 4002001, 'dbservices.connectionNotEstablished');
        }
        return self::$connection->inTransaction();
    }


    /**
     * Function to get the last inserted id.
     *
     * @return string
     * @throws DbServicesException
     */
    public function lastInsertId(): string
    {
        if (!$this->checkConnection()) {
            throw new DbServicesException("Connection not established.", 4002001, 'dbservices.connectionNotEstablished');
        }
        return self::$connection->lastInsertId();
    }


    /**
     * Function to get the number of rows affected by the query.
     *
     * @return int
     * @throws DbServicesException
     */
    public function rowCount(): int
    {
        if ($this->queryExecutionStatus) {
            return $this->queryStatement->rowCount();
        }
        throw new DbServicesException("No executed statement to fetch results", 4013001, 'dbservices.noExecutedStatement');
    }


    /**
     * Clone of function rowCount().
     * Function to get the number of rows affected by the query.
     *
     * @return int
     * @throws DbServicesException
     */
    public function affectedRowCount(): int
    {
        return $this->rowCount();
    }


    /**
     * Function to fetch the results.
     *
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
        throw new DbServicesException("No executed statement to fetch results", 4013001, 'dbservices.noExecutedStatement');
    }


    /**
     * Function to fetch all the results of the query.
     *
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
        throw new DbServicesException("No executed statement to fetch results", 4014001, 'dbservices.noExecutedStatement');
    }


    /**
     * Function to handle the PDO Exception while fetching and create new DbServicesException.
     *
     * @throws DbServicesException
     */
    private function handleFetch(PDOException $exception): void
    {
        if ($exception->getCode() == 22001) {
            throw new DbServicesException("Data too long fetch.", 4015001, 'dbservices.dataTooLong');
        } elseif ($exception->getCode() == 22002) {
            throw new DbServicesException(" Indicator variable required but not supplied.", 4016001, 'dbservices.indicatorVariableRequired');
        } elseif ($exception->getCode() == 23003) {
            throw new DbServicesException("Numeric value out of range.", 4017001, 'dbservices.numericValueOutOfRange');
        } elseif ($exception->getCode() == 23004) {
            throw new DbServicesException("Null value not allowed.", 4018001, 'dbservices.nullValueNotAllowed');
        } elseif ($exception->getCode() == 23005) {
            throw new DbServicesException("Error in assignment.", 4019001, 'dbservices.errorInAssignment');
        } elseif ($exception->getCode() == 23007) {
            throw new DbServicesException("Invalid datetime format.", 4020001, 'dbservices.invalidDateTimeFormat');
        } elseif ($exception->getCode() == 22008) {
            throw new DbServicesException("Datetime field overflow", 4021001, 'dbservices.datetimeFieldOverflow');
        } elseif ($exception->getCode() == 22012) {
            throw new DbServicesException("Divisible by zero.", 4022001, 'dbservices.divisibleByZero');
        } elseif ($exception->getCode() == 22018) {
            throw new DbServicesException("Invalid character value for cast.", 4023001, 'dbservices.invalidCharacterValue');
        } else {
            error_log("The exception caught is " . json_encode($exception) . ". New Code: 4042001 (4M42K1)");
            throw new DbServicesException("Unknown error occurred during fetching results. Visit error_log for details.", 4042001, 'dbservices.unknownError');
        }
    }


    /**
     * Use this with caution. Can make the application unstable if used incorrectly.
     * ==========================>
     * Use this only when the previous connection can't be used for another transaction.
     * Creates a new connection and assigns it to the $connection variable.
     * Closing the connection is not possible.
     *
     * @return void
     * @throws DbServicesException
     */
    public function close(): void
    {
        if ($this->checkConnection()) {
            self::$connection = null;
            $this->queryExecutionStatus = false;
        } else {
            throw new DbServicesException("Connection not established.", 4002001, 'dbservices.connectionNotEstablished');
        }
    }
}
