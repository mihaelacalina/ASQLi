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
include_once __DIR__ . "/Auth.php";
include_once __DIR__ . "/Table.php";




/**
 * This is a resut set that is returned from a query.
 */
class QueryResult {
    protected PDOStatement $Statement;
    protected $LastInsertId = false;
    protected PDO $Connection;

    protected array $Binds;

    public function __construct(PDOStatement $Statement, PDO $Connection, bool $GetInsertId) {
        $this -> Connection = $Connection;
        $this -> Statement = $Statement;
        $this -> Binds = [];

        if ($GetInsertId) {
            try {
                $this -> LastInsertId = $this -> Connection -> lastInsertId();
            } catch (PDOException $Exception) {
                $this -> LastInsertId = new QueryException("Unable to get last insert id ({$Exception -> getMessage()})");
            }
        }
    }

    /**
     * Binds a column index or name to a variable.
     * 
     * @see Fetch in order to fetch a row into the bound variables.
     * 
     * @param int|string $Column The 1-indexed column index or case-sensitive name.
     * @param mixed $Buffer The buffer variable that will be set to the fetched value.
     * @param DataType $DataType The type to which the data will be cast.
     * 
     * @throws QueryException If the column cannot be bound to the buffer.
     * 
     * @return void
     */
    public function BindValue(int|string $Column, mixed &$Buffer, DataType $DataType = DataType::String) {
        $I = count($this -> Binds);
        $Type = PDO::PARAM_STR;

        switch ($DataType) {
            case DataType::Integer:
                $Type = PDO::PARAM_INT;
                break;
            case DataType::Stream:
                $Type = PDO::PARAM_LOB;
                break;
            default:
                break;
        }

        try {
            $SetValue = function($Value) use (&$Buffer) {
                $Buffer = $Value;
            };

            $this -> Binds[$I] = [0, $SetValue, $DataType];
            $this -> Statement -> bindColumn($Column, $this -> Binds[$I][0], $Type);
        } catch (PDOException $Exception) {
            throw new QueryException("Unable to bind column to variable ({$Exception -> getMessage()})");
        }
    }

    /**
     * Fetches a row into the bound variables.
     * 
     * @see BindValue To bind columns to variables.
     * @throws QueryException If the row cannot be fetched.
     * 
     * @return void
     */
    public function Fetch(): void {
        try {
            $this -> Statement -> fetch(PDO::FETCH_BOUND);
        } catch (PDOException $Exception) {
            throw new QueryException("Unable to fetch row ({$Exception -> getMessage()})");
        }

        foreach ($this -> Binds as $I => $Bind) {
            $RawValue = $Bind[0];
            $Type = $Bind[2];

            switch ($Type) {
                case DataType::Json:
                    $Bind[1](json_decode($RawValue));
                    break;
                
                default:
                    $Bind[1]($RawValue);
                    break;
            }
        }
    }

    /**
     * Fetches a row as an associative array from the result set.
     * 
     * Note:
     *  When fetching a row as an associative array, all values will be read into memory, even the large binary objects.
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
     * Fetches a row as an object from the result set.
     * 
     * Note:
     *  When fetching a row as an object, all values will be read into memory, even the large binary objects.
     * 
     * Note:
     *  The object constructor will not be called.
     * 
     * Note:
     *  Suppose all already bound variables are invalid and need to be rebound with BindValue.
     * 
     * @param $ClassName The name of the object class.
     * 
     * @throws QueryException If the object row cannot be fetched.
     * @throws \RuntimeException If no object with the provided ClassName can be created.
     * 
     * @return object|bool The object or false if there are no rows left.
     */
    public function GetObject(string $ClassName): object|bool {
        $Meta = $this -> GetMetadata();
        $ReflectionClass = new \ReflectionClass($ClassName);
        $Object = $ReflectionClass -> newInstanceWithoutConstructor();

        $Setters = [];

        /** @var \ReflectionProperty[] */
        $Properties = $ReflectionClass -> getProperties();

        foreach ($Properties as $__ => $Property) {
            $Attributes = $Property -> getAttributes();
            $Name = $Property -> getName();
            $Type = DataType::String;
            
            foreach ($Attributes as $__ => $Attribute) {
                if ($Attribute -> getName() !== DbAttribute::class) {
                    continue;
                }

                $Instance = $Attribute -> newInstance();

                $Name = $Instance -> RowName;
                $Type = $Instance -> Type;
            }

            $Setters[$Name] = function($Value) use ($Property, $Object) {
                $Property -> setAccessible(true);
                
                $Property -> setValue($Object, $Value);
            };
        }

        $Buffer = [];

        foreach ($Meta -> GetColumnNames() as $_ => $ColumnName) {
            if (!isset($Setters[$ColumnName])) {
                continue;
            }

            $Buffer[$ColumnName] = [null, $Type];

            try {
                $RawType = PDO::PARAM_STR;

                switch ($Type) {
                    case DataType::Integer:
                        $RawType = PDO::PARAM_INT;
                        break;

                    case DataType::Stream:
                        $RawType = PDO::PARAM_LOB;
                        break;
                    
                    default:
                        break;
                }

                $this -> Statement -> bindColumn($ColumnName, $Buffer[$ColumnName][0], $RawType);
            } catch (PDOException $Exception) {
                throw new QueryException("Unable to bind column to variable ({$Exception -> getMessage()})");
            }
        }

        try {
            if (!$this -> Statement -> fetch(PDO::FETCH_BOUND)) {
                return false;
            }
        } catch (PDOException $Exception) {
            throw new QueryException("Unable to fetch row ({$Exception -> getMessage()})");
        }

        foreach ($Buffer as $Name => $Data) {
            $Setter = @$Setters[$Name];
            $Value = $Data[0];
            $Type = $Data[1];


            if (!isset($Setters[$Name])) {
                continue;
            }

            if ($Type === DataType::Json) {
                $Setter(json_decode($Value));
            } else {
                $Setter($Value);
            }
        }

        return $Object;
    }

    /**
     * Fetches all rows as an associative array of objects from the result set.
     * 
     * Note:
     *  When fetching a row as an object, all values will be read into memory, even the large binary objects.
     * 
     * Note:
     *  The object constructor will not be called.
     * 
     * Note:
     *  Suppose all already bound variables are invalid and need to be rebound with BindValue.
     * 
     * @param $ClassName The name of the object class.
     * 
     * @throws QueryException If the object row cannot be fetched.
     * @throws \RuntimeException If no object with the provided ClassName can be created.
     * 
     * @return object[] The objects or an empty array if there are none left.
     */
    public function GetObjects(string $ClassName): array {
        $Object = null;
        $Objects = [];

        while (true) {
            $Object = $this -> GetObject($ClassName);

            if ($Object === false) {
                break;
            }

            $Objects[] = $Object;
        }

        return $Objects;
    }

    /**
     * Gets the metadata of all the columns.
     * 
     * @return TableMetadata
     */
    public function GetMetadata(): TableMetadata {
        $Metadata = [];
        $I = 0;

        while (true) {
            $ColumnMeta = $this -> Statement -> getColumnMeta($I);
            
            if ($ColumnMeta === false) {
                break;
            }
            
            $Metadata[] = new ColumnMetadata($ColumnMeta);
            $I ++;
        }
        

        return new TableMetadata($Metadata);
    }

    /**
     * Fetches all rows as an array of associative arrays from the result set.
     * 
     * Note:
     *  When fetching a row as an associative array, all values will be read into memory, even the large binary objects.
     * 
     * @throws QueryException If the rows cannot be fetched.
     * 
     * @return array|bool The rows or false if there are no rows left.
     */
    public function GetRows() {
        try {
            return $this -> Statement -> fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $Exception) {
            throw new QueryException("Unable to fetch rows ({$Exception -> getMessage()})");
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
    protected ?PDO $Connection;

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
     * Attempts to disconnect from the database.
     * 
     * @throws ConnectionException if an exception occurs while disconnecting.
     */
    public function Disconnect(): void {
        try {
            $this -> Connection = null;
        } catch (PDOException $Exception) {
            throw new ConnectionException("Unable to connect to database ({$Exception -> getMessage()})");
        }
    }

    /**
     * Checks if the connection has been established and not yet closed.
     * 
     * @return bool True if the connection is still active, false otherwise.
     */
    public function IsConnected(): bool {
        return $this -> Connection !== null;
    }

    /**
     * Prepares a statement and executes it.
     * 
     * Note:
     *  The arguments can contain ints, floats, strings, booleans, null, arrays of serializable objects and a file handle for LBOs.
     *  The arrays will be stored as json.
     *  @see https://www.php.net/manual/en/pdo.lobs.php
     * 
     * @param string $Query The sql statement, with "?" as placeholders.
     * @param mixed[] $Arguments The arguments for the prepared statement.
     * @param bool $GetInsertId If the insert id should be autimatically fetched after execution and stored for future use.
     * @throws QueryException If the query ahs a syntax error in it or cannot be prepared for any other reason.
     * @return QueryResult The result of this query
     */
    public function Query(string $Query, bool $GetInsertId = false, mixed ...$Arguments) {
        try {
            $Query = $this -> Connection -> prepare($Query);
            
            if (!$Query -> setFetchMode(PDO::FETCH_ASSOC)) {
                throw new QueryException("Unable to set fetch mode to associative array");
            }

            for ($I = 0; $I < count($Arguments); $I++) {
                $Value = $Arguments[$I];
                $Index = $I + 1;

                if (is_resource($Value) && get_resource_type($Value) === "stream") {
                    $Query -> bindParam($Index, $Value, PDO::PARAM_LOB);
                    continue;
                }

                if (is_string($Value)) {
                    $Query -> bindParam($Index, $Value, PDO::PARAM_STR);
                    continue;
                }

                if (is_int($Value)) {
                    $Query -> bindParam($Index, $Value, PDO::PARAM_INT);
                    continue;
                }

                if (is_bool($Value)) {
                    $Query -> bindParam($Index, $Value, PDO::PARAM_BOOL);
                    continue;
                }

                if (is_array($Value)) {
                    $EncodedValue = json_encode($Value);
                    $Query -> bindParam($Index, $EncodedValue, PDO::PARAM_STR);
                    continue;
                }

                
                $Query -> bindParam($Index, $Value, PDO::PARAM_STR);
            }

            $Query -> execute();

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