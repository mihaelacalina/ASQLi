<?
include_once __DIR__ . "/ASQLiExceptions.php";
include_once __DIR__ . "/ASQLiStatement.php";
include_once __DIR__ . "/ASQLiResult.php";

/**
 * The desired type of result set.
 * 
 * Store will buffer the result locally and Use will buffer it on the remote server.
 */
enum ASQLiResultType: int {
	case Store = MYSQLI_STORE_RESULT;
	case Use = MYSQLI_USE_RESULT;
}

/**
 * This is a connection to a mysqli server.
 * 
 * @author atheramew
 */
class ASQLiConnection {
	protected ?mysqli $Connection = null;
	protected array $Data = [];

	/**
	 * Creates a new ASQLiConnection without connecting to the server.
	 * 
	 * @see ASQLiConnection::Connect
	 */
	public function __construct(?string $Address = null, ?string $Username = null, ?string $Password = null, ?string $Database = null, ?int $Port = null, ?string $Socket = null) {
		$this -> Data = [$Address, $Username, $Password, $Database, $Port, $Socket];	
	}

	/**
	 * Attempts to connect to the database.
	 * 
	 * @throws ASQLiConnectionException If an exception occurs.
	 */
	public function Connect() {
		$TempConnection = null;

		try {
			$TempConnection = @new mysqli(...$this -> Data);

			if ($TempConnection === false) {
				throw new Exception();
			}
		} catch (Exception) {
			if (!mysqli_connect_errno()) {
				throw new ASQLiConnectionException(-1, "Unknown exception occured.");
			}

			throw new ASQLiConnectionException(mysqli_connect_errno(), mysqli_connect_error());
		}

		$this -> Connection = $TempConnection;
	}
	
	/**
	 * Executes the provided query.
	 * 
	 * @param string $Query The SQL query.
	 */
	public function ExecuteQuery(string $Query, ASQLiResultType $ResultType = ASQLiResultType::Store) {
		$this -> X_ForceConnected();

		try {
			$QueryResult = @$this -> Connection -> query($Query, $ResultType -> value);
			if ($QueryResult === false) {
				X_ASQLiHandleEx($this -> Connection);
			}
	
			return new ASQLiResult($QueryResult, $this -> Connection);
		} catch (Exception) {
			X_ASQLiHandleEx($this -> Connection);
		}
	}

	/**
	 * Creates a prepared statement from the provided query.
	 * 
	 * @param string $Query The SQL query.
	 */
	public function PrepareQuery(string $Query) {
		try {
			$RawPrepared = @$this -> Connection -> prepare($Query);

			if ($RawPrepared === false) {
				X_ASQLiHandleEx($this -> Connection);
			}

			return new ASQLiStatement($RawPrepared, $this -> Connection);
		} catch (Exception) {
			X_ASQLiHandleEx($this -> Connection);
		}
	}

	/**
	 * Closes this connection to the database.
	 */
	public function Disconnect() {
		$this -> X_ForceConnected();

		try {
			$this -> Connection -> close();
		} catch (Exception) {
			throw new ASQLiException(-1, "Unknown exception occured while disconnecting.");
		}
	}

	#region Protected
		protected function X_ForceConnected() {
			if (is_null($this -> Connection)) {
				throw new ASQLiException(0, "ASQLiConnection object connection has not been established.");
			}
		}
	#endregion
}