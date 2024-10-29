<?php

namespace NGFramer\NGFramerPHPDbServices;

use NGFramer\NGFramerPHPExceptions\Exceptions\BaseException;
use PDO;
use Exception;
use PDOException;
use PDOStatement;
use App\Config\DatabaseConfig;
use App\Config\ApplicationConfig;
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
            if (!class_exists('App\Config\DatabaseConfig')) {
                throw new DbServicesException("The class app/config/DatabaseConfig doesn't exist.", 4001001, 'dbservices.config.classNotFound');
            }

            // Check if the get method exists or not.
            if (!method_exists('App\Config\DatabaseConfig', 'get')) {
                throw new DbServicesException("Requested methods are not available on the class.", 4001002, 'dbservices.config.methodNotFound');
            }

            // Define the DB_DSN, DB_USER, and DB_PASS in the /config/database.php file of the project/root directory.
            try {
                // Try to get the DatabaseConfig.
                try {
                    $db_dsn = DatabaseConfig::get('db_dsn');
                    $db_user = DatabaseConfig::get('db_user');
                    $db_pass = DatabaseConfig::get('db_pass');
                } catch (BaseException) {
                    throw new DbServicesException("All the requested variables do not exist in ApplicationConfig.", 4002001, 'dbservices.config.variablesNotSet');
                }
                // If all the variables exist in the DatabaseConfig.
                self::$connection = new PDO($db_dsn, $db_user, $db_pass, $pdoAttributes);
            } catch (PDOException $exception) {
                // Check the code of the exception.
                switch ($exception->getCode()) {
                    case 1045:
                        throw new DbServicesException("Invalid username or password.", 4004001, 'dbservices.connection.invalidCredentials');
                    case 1049:
                        throw new DbServicesException("Database doesn't exist.", 4047001, 'dbservices.connection.databaseNotFound');
                    case 2002:
                        throw new DbServicesException("Connection refused to database server.", 4004002, 'dbservices.connection.connectionRefused');
                    case '08001':
                        throw new DbServicesException("SQL Client unable to establish a connection.", 4005001, 'dbservices.connection.unableToEstablish');
                    case '08003':
                        throw new DbServicesException("The connection does not exist. Visit error_log for details.", 4005002, 'dbservices.connection.connectionNotExists');
                    case '08004':
                        throw new DbServicesException("The connection has been failed. Visit error_log for details.", 4005003, 'dbservices.connection.connectionFailed');
                    default:
                        error_log("PDOException: " . $exception->getMessage() . " - Code: " . $exception->getCode());
                        throw new DbServicesException("Database connection failed for an unknown reason.", 4020001, 'dbservices.connection.unknownError');
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
        return self::$connection instanceof PDO && !empty(self::$connection);
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
            throw new DbServicesException("No query to prepare.", 4007001, 'dbservices.query.noQueryToPrepare');
        }

        try {
            $this->queryStatement = self::$connection->prepare($queryStatement, $options);
        } catch (PDOException $exception) {
            switch ($exception->getCode()) {
                case 42000:
                    throw new DbServicesException("Syntax error or Access violation.", 4022001, 'dbservices.query.syntaxErrorOrAccessViolation');
                case '42S02':
                    throw new DbServicesException("Base table or view not found.", 4023001, 'dbservices.query.baseTableOrViewNotFound');
                default:
                    error_log("PDOException in prepare: " . $exception->getMessage() . " - Code: " . $exception->getCode() . " - Query: " . $queryStatement);
                    throw new DbServicesException("Something went wrong during query preparation. Visit error_log for details.", 4007002, 'dbservices.query.unknownError');
            }
        }
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
            throw new DbServicesException("No parameters passed to bind to queryStatement.", 4008002, 'dbservices.noParametersToBind');
        }

        // Check if all elements are arrays.
        if (!$this->areAllElementsArray($args)) {
            throw new DbServicesException("Invalid format of data passed to bind.", 4015001, 'dbservices.invalidDataFormat');
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
            throw new DbServicesException("No queryStatement to bind parameters to.", 4008003, 'dbservices.noQueryStatementToBind.2');
        }

        // If there are no parameters to bind to the statement.
        if (count($args) === 0) {
            throw new DbServicesException("No parameters passed to bind to queryStatement.", 4008004, 'dbservices.noParametersToBind.2');
        }

        // Check if all elements are arrays.
        if (!$this->areAllElementsArray($args)) {
            throw new DbServicesException("Invalid format of data passed to bind.", 4015002, 'dbservices.invalidDataFormat.2');
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
     * Resolves the data type for binding to the prepared statement.
     *
     * @param mixed $type .
     * @return int Returns the corresponding PDO::PARAM_* constant for the given type
     * @throws DbServicesException
     */
    private function resolveType(mixed $type): int
    {
        return match ($type) {
            'string', PDO::PARAM_STR => PDO::PARAM_STR,
            'integer', 'int', PDO::PARAM_INT => PDO::PARAM_INT,
            'boolean', 'bool', PDO::PARAM_BOOL => PDO::PARAM_BOOL,
            'null', PDO::PARAM_NULL => PDO::PARAM_NULL,
            default => throw new DbServicesException("Invalid value type to bind.", 4016001, 'dbservices.bind.invalidValueType'),
        };
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
        switch ($exception->getCode()) {
            case 22001:
                throw new DbServicesException("Data too long to insert or update.", 4025001, 'dbservices.bind.dataTooLong');
            case 22007:
                throw new DbServicesException("Invalid datetime/timestamp value is invalid.", 4016002, 'dbservices.bind.invalidDateTime');
            case 'HY093':
                throw new DbServicesException("Invalid parameter number.", 4048001, 'dbservices.bind.invalidParameterNumber');
            default:
                error_log("PDOException in bind: " . $exception->getMessage() . " - Code: " . $exception->getCode());
                throw new DbServicesException("Something went wrong while binding. Visit error_log for details.", 4008005, 'dbservices.bind.unknownError');
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
            throw new DbServicesException("Invalid or no queryStatement to execute.", 4012001, 'dbservices.execute.invalidQueryStatement');
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
            $this->handleExecute($exception, $queryStatement);
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
            $this->handleExecute($exception, $this->queryStatement->queryString);
        }
    }


    /**
     * This function will handle the exception from the executeDirectQuery and executePreparedStatement functions.
     *
     * @param Exception $exception
     * @param string|null $queryStatement The query that caused the exception (for logging).
     * @return void
     * @throws DbServicesException
     */
    private function handleExecute(Exception $exception, ?string $queryStatement = null): void
    {

        switch ($exception->getCode()) {
            case 2006:
                throw new DbServicesException("Lost connection to server during query. Visit error_log for details.", 4012002, 'dbservices.execute.lostConnection');
            case 2013:
                throw new DbServicesException("Lost connection to server at query end. Visit error_log for details.", 4012003, 'dbservices.execute.lostConnectionAtEnd');
            case 22000:
                throw new DbServicesException("General data error. Visit error_log for details.", 4015003, 'dbservices.execute.generalDataError');
            case 22001:
                throw new DbServicesException("Data too long to insert or update.", 4025002, 'dbservices.execute.dataTooLong');
            case 22002:
                throw new DbServicesException("Indicator variable required not supplied.", 4015004, 'dbservices.execute.indicatorVariableRequired');
            case 22003:
                throw new DbServicesException("Data (numeric) value out of range.", 4016003, 'dbservices.execute.dataValueOutOfRange');
            case 22004:
                throw new DbServicesException("Null value not allowed in the column.", 4019001, 'dbservices.execute.nullValueNotAllowed');
            case 22005:
                throw new DbServicesException("Error in assignment of value to parameter.", 4015005, 'dbservices.execute.errorInAssignment');
            case 22007:
                throw new DbServicesException("Invalid date or time format.", 4016004, 'dbservices.execute.invalidDateTimeFormat');
            case 22008:
                throw new DbServicesException("Date time field value out of range.", 4016005, 'dbservices.execute.dateTimeFieldOutOfRange');
            case 23000:
                throw new DbServicesException("Integrity constraint violation.", 4019002, 'dbservices.execute.integrityConstraintViolation');
            case 23502:
                throw new DbServicesException("Not Null Violation.", 4019003, 'dbservices.execute.notNullViolation');
            case 23503:
                throw new DbServicesException("Foreign Key Violation.", 4019004, 'dbservices.execute.foreignKeyViolation');
            case 23505:
                throw new DbServicesException("Unique Key Violation.", 4019005, 'dbservices.execute.uniqueKeyViolation');
            case 40001:
                throw new DbServicesException("Deadlock condition found. Visit error_log for details.", 4012004, 'dbservices.execute.deadlockCondition');
            case 42000:
                throw new DbServicesException("Syntax error or Access violation.", 4022002, 'dbservices.execute.syntaxErrorOrAccessViolation.2');
            case 42501:
                throw new DbServicesException("Insufficient privilege. Visit error_log for details.", 4026001, 'dbservices.execute.insufficientPrivilege');
            case 42601:
                throw new DbServicesException("Syntax error. Visit error_log for details.", 4022003, 'dbservices.execute.syntaxError');
            case 42602:
                throw new DbServicesException("Invalid cursor name. Visit error_log for details.", 4043001, 'dbservices.execute.invalidCursorName');
            case 42622:
                throw new DbServicesException("Too long identifier name.", 4044001, 'dbservices.execute.tooLongIdentifierName');
            case 42701:
                throw new DbServicesException("The column name already exists.", 4045001, 'dbservices.execute.columnNameAlreadyExists');
            case 42703:
                throw new DbServicesException("Undefined column.", 4045002, 'dbservices.execute.undefinedColumn');
            case '42P01':
                throw new DbServicesException("Table or view not found.", 4047002, 'dbservices.execute.tableOrViewNotFound');
            case '42P02':
                throw new DbServicesException("Undefined parameter.", 4048002, 'dbservices.execute.undefinedParameter');
            case '42P03':
                throw new DbServicesException("Duplicate cursor.", 4043002, 'dbservices.execute.duplicateCursor');
            case '42P04':
                throw new DbServicesException("Duplicate database.", 4047003, 'dbservices.execute.duplicateDatabase');
            case 'HY000':
                throw new DbServicesException("Unknown general error. Visit error_log for details.", 4020002, 'dbservices.execute.unknownGeneralError');
            case 'HY009':
                throw new DbServicesException("Error in number of data to bind. Visit error_log for details.", 4008006, 'dbservices.execute.errorInNumberOfDataToBind');
            case 'HY013':
                throw new DbServicesException("Memory management error. Visit error_log for details.", 4034001, 'dbservices.execute.memoryManagementError');
            case 'HY014':
                throw new DbServicesException("Limit on the number of handles exceeded.", 4035001, 'dbservices.execute.limitOnNumberOfHandlesExceeded');
            default:
                error_log("PDOException in execute: " . $exception->getMessage());
                error_log("The query statement was " . $queryStatement ? " - Query: " . $queryStatement : "");
                throw new DbServicesException("Something went wrong while executing query. Visit error_log for details.", 4012005, 'dbservices.execute.unknownError');
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
            throw new DbServicesException("Connection not established.", 4003001, 'dbservices.transaction.connectionNotEstablished');
        }
        if ($this->hasActiveTransactions()) {
            throw new DbServicesException("Transaction already in progress.", 4003002, 'dbservices.transaction.alreadyInProgress');
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
            throw new DbServicesException("Connection not established.", 4003003, 'dbservices.transaction.connectionNotEstablished.2');
        }
        if (!self::$connection->inTransaction()) {
            throw new DbServicesException("Transaction not in progress.", 4003004, 'dbservices.transaction.notInProgress');
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
            throw new DbServicesException("Connection not established.", 4003005, 'dbservices.transaction.connectionNotEstablished.3');
        }
        if (!self::$connection->inTransaction()) {
            throw new DbServicesException("Transaction not in progress.", 4003006, 'dbservices.transaction.notInProgress.2');
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
            throw new DbServicesException("Connection not established.", 4003007, 'dbservices.transaction.connectionNotEstablished.4');
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
            throw new DbServicesException("Connection not established.", 4003008, 'dbservices.transaction.connectionNotEstablished.5');
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
        throw new DbServicesException("No executed statement to fetch results", 4013001, 'dbservices.fetch.noExecutedStatement');
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
        throw new DbServicesException("No executed statement to fetch results", 4013002, 'dbservices.fetch.noExecutedStatement.2');
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
        throw new DbServicesException("No executed statement to fetch results", 4013003, 'dbservices.fetch.noExecutedStatement.3');
    }


    /**
     * Function to handle the PDO Exception while fetching and create new DbServicesException.
     *
     * @throws DbServicesException
     */
    private function handleFetch(PDOException $exception): void
    {

        switch ($exception->getCode()) {
            case 22001:
                throw new DbServicesException("Data too long fetch.", 4025003, 'dbservices.fetch.dataTooLong');
            case 22002:
                throw new DbServicesException("Indicator variable required but not supplied.", 4015006, 'dbservices.fetch.indicatorVariableRequired');
            case 22003:
                throw new DbServicesException("Numeric value out of range.", 4016006, 'dbservices.fetch.numericValueOutOfRange');
            case 22004:
                throw new DbServicesException("Null value not allowed.", 4019006, 'dbservices.fetch.nullValueNotAllowed');
            case 22005:
                throw new DbServicesException("Error in assignment.", 4015007, 'dbservices.fetch.errorInAssignment');
            case 22007:
                throw new DbServicesException("Invalid datetime format.", 4016007, 'dbservices.fetch.invalidDateTimeFormat');
            case 22008:
                throw new DbServicesException("Datetime field overflow", 4016008, 'dbservices.fetch.datetimeFieldOverflow');
            case 22012:
                throw new DbServicesException("Divisible by zero.", 4013004, 'dbservices.fetch.divisibleByZero');
            case 22018:
                throw new DbServicesException("Invalid character value for cast.", 4016009, 'dbservices.fetch.invalidCharacterValue');
            default:
                error_log("PDOException in fetch: " . $exception->getMessage() . " - Code: " . $exception->getCode());
                throw new DbServicesException("Unknown error occurred during fetching results. Visit error_log for details.", 4013005, 'dbservices.fetch.unknownError');
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
            throw new DbServicesException("Connection not established.", 4003009, 'dbservices.transaction.connectionNotEstablished.6');
        }
    }
}