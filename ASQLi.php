<?
include_once __DIR__ . "/Source/ASQLiResult.php";
include_once __DIR__ . "/Source/Exceptions.php";
include_once __DIR__ . "/Source/Enums.php";


class ASQLiConnection {
	public array $Exceptions = [];

	protected ?mysqli $Connection = null;
	protected array $Data = [];


	public function __construct(?string $Address = null, ?string $Username = null, ?string $Password = null, ?string $Database = null, ?int $Port = null, ?string $Socket = null) {
		$this -> Data = [$Address, $Username, $Password, $Database, $Port, $Socket];	
	}

	/**
	 * Attempts to connect to the database.
	 */
	public function Connect() {
		$TempConnection = null;

		try {
			$TempConnection = @new mysqli(...$this -> Data);
		} catch (Exception) {
			if (!mysqli_connect_errno()) {
				throw new ASQLiException(-1, "Unknown exception occured.");
			}

			throw new ASQLiException(mysqli_connect_errno(), mysqli_connect_error());
		}

		if ($TempConnection === false) {
			if (!mysqli_connect_errno()) {
				throw new ASQLiException(-1, "Unknown exception occured.");
			}

			throw new ASQLiException(mysqli_connect_errno(), mysqli_connect_error());
		}

		$this -> Connection = $TempConnection;
	}

	public function RunQuery(string $Query, ASQLiResultType $ResultType = ASQLiResultType::Store) {
		$this -> X_ForceConnected();
		$QueryResult = false;

		try {
			$QueryResult = @$this -> Connection -> query($Query, $ResultType -> value);
		} catch (Exception) {
			ASQLiHandleEx($this -> Connection);
		}

		if ($QueryResult === false) {
			ASQLiHandleEx($this -> Connection);
		}

		return new ASQLiResult($QueryResult, $this -> Connection);
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