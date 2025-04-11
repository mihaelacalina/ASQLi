<?php
/*
    (c) 2025 Slincu Mihaela-Calina - All rights reserved.

    11/04/2025 01:39:00 AM
*/
namespace ASQLi;

use PDOException;
use PDOStatement;
use PDO;

include_once __DIR__ . "/ASQLiException.php";


/**
 * This is an account used to connect to a database.
 * @see FromEnv For secure retrieval of account from environment variables.
 * @see FromInfo For loading info directly.
 * @see AsRoot For a default root account with no password.
 */
class User {
    protected string $Name;
    protected string $Password;


    protected function __construct(#[\SensitiveParameter] string $Username, #[\SensitiveParameter] string $Password) {
        $this -> Password = $Password;
        $this -> Name = $Username;
    }

    /**
     * Returns an array containing the username and the password.
     * 
     * @return string[] The array containing the credentials
     */
    public function GetCredentials(): array {
        return [$this -> Name, $this -> Password];
    }

    /**
     * Creates an User object with the username and password read from the system environment variables.
     * 
     * @param string $User The name of the evironment variable that contains the username.
     * @param string $Pass The name of the evironment variable that contains the password.
     * 
     * @throws MissingEnvException If an environment variable is missing.
     * 
     * @return User The user object.
     */
    public static function FromEnv(string $User = "DB_USER", string $Pass = "DB_PASS"): User {
        $Username = getenv($User);
        $Password = getenv($Pass);

        if ($Username === false) {
            throw new MissingEnvException("Unable to get environment variable for username");
        }

        if ($Password === false) {
            throw new MissingEnvException("Unable to get environment variable for password");
        }

        return new static($Username, $Password);
    }

    /**
     * Creates an User object with the username and password read from the system environment variables.
     * 
     * @param string $Username The username.
     * @param string $Password The password.
     * 
     * @return User The user object.
     */
    public static function FromInfo(#[\SensitiveParameter] string $Username, #[\SensitiveParameter] string $Password): User {
        return new static($Username, $Password);
    }

    /**
     * Returns an account with the username set to "root" and no password.
     * 
     * Note:
     *  It would probably be ideal not to use the default root account in a production environment.
     * 
     * @return User The user.
     */
    public static function AsRoot(): User {
        return new static("root", "");
    }
}

/**
 * This is a resut set that is returned from a query.
 */
class QueryResult {
    protected PDOStatement $Statement;
    protected $LastInsertId = false;
    protected PDO $Connection;

    public function __construct(PDOStatement $Statement, PDO $Connection, bool $GetInsertId) {
        $this -> Connection = $Connection;
        $this -> Statement = $Statement;

        if ($GetInsertId) {
            try {
                $this -> LastInsertId = $this -> Connection -> lastInsertId();
            } catch (PDOException $Exception) {
                $this -> LastInsertId = new QueryException("Unable to get last insert id ({$Exception -> getMessage()})");
            }
        }
    }

    /**
     * Fetches a row as an associative array from the result set.
     * 
     * @throws QueryException If the row cannot be fetched.
     * 
     * @return array|bool The row or false if there are no rows left.
     */
    public function GetRow() {
        try {
            return $this -> Statement -> fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $Exception) {
            throw new QueryException("Unable to fetch row ({$Exception -> getMessage()})");
        }
    }

    /**
     * Gets the count of affected rows by INSERT, DELETE or UPDATE queries.
     * 
     * @throws QueryException If an error occurs while fetching the count.
     * 
     * @return int the number of affected rows
     */
    public function AffectedRows(): int {
        try {
            return $this -> Statement -> rowCount();
        } catch (PDOException $Exception) {
            throw new QueryException("Unable to get count of affected rows ({$Exception -> getMessage()})");
        }
    }

    /**
     * Returns the insert id of this query.
     * If this query didn't generate an insert id, the id returned may be from the last insert that did create one.
     * 
     * Note:
     *  In MariaDB and MySQL, when batch inserting multiple rows, the insert id of the first row is returned.
     * 
     * Note:
     *  If the proerty $GetInsertId was set to true when executing the query, the insert id is fetched right after the statement is executed, not when this function is called.  
     * 
     * @throws QueryException If an error occured while getting the last insert id.
     * 
     * @return string The last insert id.
     */
    public function GetInsertId(): string {
        if ($this -> LastInsertId === false) {
            try {
                return $this -> Connection -> lastInsertId();
            } catch (PDOException $Exception) {
                throw new QueryException("Unable to get last insert id ({$Exception -> getMessage()})");
            }
        }


        if (is_object($this -> LastInsertId)) {
            throw $this -> LastInsertId;
        }

        return $this -> LastInsertId;
    }
}

/**
 * This represents a connection to the database.
 * 
 * @see Query in order to run a query.
 */
class Connection {
    protected array $Parameters;
    protected PDO $Connection;

    protected function __construct() {}

    /**
     * Attempts to connect to the database.
     * 
     * @throws ConnectionException if an exception occurs while connecting.
     */
    public function Connect(): void {
        try {
            $this -> Connection = new PDO(...$this -> Parameters);
        } catch (PDOException $Exception) {
            throw new ConnectionException("Unable to connect to database ({$Exception -> getMessage()})");
        }
        
        if (!$this -> Connection -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION)) {
            throw new ConnectionException("Unable to set error mode to exception");
        }
    }

    /**
     * Prepares a statement and executes it.
     * 
     * @param string $Query The sql statement.
     * @param mixed[] $Arguments The arguments for the prepared statement.
     * @param bool $GetInsertId If the insert id should eb autimatically fetched after execution and stored for future use.
     * @throws QueryException If the query ahs a syntax error in it or cannot be prepared for any other reason.
     * @return QueryResult The result ofthis query
     */
    public function Query(string $Query, bool $GetInsertId = false, mixed ...$Arguments) {
        try {
            $Query = $this -> Connection -> prepare($Query);
            
            if (!$Query -> setFetchMode(PDO::FETCH_ASSOC)) {
                throw new QueryException("Unable to set fetch mode to associative array");
            }

            $Query -> execute($Arguments);

            return new QueryResult($Query, $this -> Connection, $GetInsertId);
        } catch (PDOException $Exception) {
            throw new QueryException("Unable to execute query ({$Exception -> getMessage()})");
        }

    }

    /**
     * Returns the last insert id.
     * 
     * Note:
     *  In MariaDB and MySQL, when batch inserting multiple rows, the insert id of the first row is returned.
     * 
     * Note:
     *  If the proerty $GetInsertId was set to true when executing the query, the insert id is fetched right after the statement is executed, not when this function is called.  
     * 
     * @throws QueryException If an error occured while getting the last insert id.
     * 
     * @return string The last insert id.
     */
    public function GetLastInsertId(): string {
        try {
            return $this -> Connection -> lastInsertId();
        } catch (PDOException $Exception) {
            throw new QueryException("Unable to get last insert id ({$Exception -> getMessage()})");
        }
    }

    /**
     * Sets a driver-dependant property to the given value.
     * 
     * @see https://www.php.net/manual/en/pdo.setattribute.php
     * 
     * @throws DriverAttributeException If an error occured while setting the attribute.
     * 
     * @return void
     */
    public function SetAttribute(int $Attribute, mixed $Value): void {
        if (!$this -> Connection -> setAttribute($Attribute, $Value)) {
            throw new DriverAttributeException("Unable to set attribute {$Attribute}.");
        }
    }

    #region Transactions

    /**
     * Starts a transaction.
     * 
     * @throws TransactionException if a transaction has already been started or when an error occurs.
     * @return void
     */
    public function StartTransaction(): void {
        try {
            $this -> Connection -> beginTransaction();
        } catch (PDOException $Exception) {
            throw new TransactionException("Unable to start transaction ({$Exception -> getMessage()})");
        }
    }
    
    /**
     * Commits the current transaction.
     * 
     * @throws TransactionException if not in a transaction or when an error occurs.
     * @return void
     */
    public function CommitTransaction(): void {
        try {
            $this -> Connection -> commit();
        } catch (PDOException $Exception) {
            throw new TransactionException("Unable to commit transaction ({$Exception -> getMessage()})");
        }
    }

    /**
     * Rolls back the current transaction.
     * 
     * @throws TransactionException if not in a transaction or when an error occurs.
     * @return void
     */
    public function RollbackTransaction(): void {
        try {
            $this -> Connection -> rollBack();
        } catch (PDOException $Exception) {
            throw new TransactionException("Unable to roll back transaction ({$Exception -> getMessage()})");
        }
    }

    /**
     * Checks if this connection is in a transaction.
     * 
     * @throws TransactionException If an error occurs.
     * @return bool True if inside a transaction, false otherwise.
     */
    public function InTransaction(): bool {
        try {
            return $this -> Connection -> inTransaction();
        } catch (PDOException $Exception) {
            throw new TransactionException("Unable to check if in a transaction ({$Exception -> getMessage()})");
        }
    }

    #endregion

    #region Constructors

    /**
     * Creates a connection to a MySQL or MariaDB database through a TCP/IP socket.
     * 
     * This function will never throw an exception.
     * 
     * @see Connect in order to connect to the database.
     * 
     * @param User $User The user account to connect as.
     * @param string $Database The database to connect to.
     * @param string $Address The address to that database.
     * @param int $Port The port on which the server is listening on.
     * @param string $Charset The character set in which the data will be read.
     * 
     * @return Connection The connection created. Call Connect to actually etsablish the connection.
     */
    public static function SocketMySQL(#[\SensitiveParameter] User $User, string $Database, string $Address = "127.0.0.1", int $Port = 3306, string $Charset = "utf8") {
        $Path = "mysql:host={$Address};port={$Port};dbname={$Database};charset={$Charset}";
        $Instance = new static();

        $Instance -> Parameters = [$Path, ...$User -> GetCredentials()];
        
        return $Instance;
    }

    /**
     * Creates a connection to a MySQL or MariaDB database through a unix socket.
     * 
     * @see Connect in order to connect to the database.
     * 
     * @param User $User The user account to connect as.
     * @param string $Database The database to connect to.
     * @param string $Address The path to the unix socket file.
     * @param string $Charset The character set in which the data will be read.
     * 
     * @throws MissingUnixSocketException if the socket file path does not point to an existing file.
     * 
     * @return Connection The connection created. Call Connect to actually establish the connection.
     */
    public static function UnixMySQL(#[\SensitiveParameter] User $User, string $Database, string $SocketFile, string $Charset = "utf8") {

        if (!file_exists($SocketFile)) {
            throw new MissingUnixSocketException("The unix socket path is not pointing to a unix socket file.");
        }

        $Path = "mysql:unix_socket={$SocketFile};dbname={$Database};charset={$Charset}";
        $Instance = new static();

        $Instance -> Parameters = [$Path, ...$User -> GetCredentials()];
        
        return $Instance;
    }
    
    /**
     * Creates a sqlite database with the provided file or in memory if the path is null.
     * 
     * @see Connect in order to connect to the database.
     * 
     * @param string $File The path to the SQLite database file. A memory database will be used if $File is null.
     * 
     * @return Connection The connection created. Call Connect to actually establish the connection.
     */
    public static function SQLite(?string $File = null) {
        $Instance = new static();

        $Path = "sqlite::memory:";

        if ($File !== null) {
            $Path = "sqlite:{$File}";
        }

        $Instance -> Parameters = [$Path];
        
        return $Instance;
    }
    
    /**
     * Creates a connection with the provided raw driver path.
     * 
     * @see https://www.php.net/manual/en/pdo.construct.php
     * @see Connect in order to connect to the database.
     * 
     * @param $parameters The parameters passed to PDO::__construct.
     * 
     * @return Connection The connection created. Call Connect to actually establish the connection.
     */
    public static function Raw(...$Parameters) {
        $Instance = new static();

        $Instance -> Parameters = $Parameters;
        
        return $Instance;
    }

    #endregion
}
