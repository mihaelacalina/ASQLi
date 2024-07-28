<?
include_once __DIR__ . "/ASQLiResult.php";


/**
 * This is a prepared statement.
 * 
 * @see ASQLiConnection For preparing a statement.
 * 
 * @author atheramew
 */
class ASQLiStatement {
	protected string $BoundTypes = "";
	protected array $BoundValues = [];

	protected mysqli_stmt $Statement;
	protected mysqli $Mysqli;

	
	public function __construct(mysqli_stmt $Statement, mysqli $Mysqli) {
		$this -> Statement = $Statement;
		$this -> Mysqli = $Mysqli;
	}

	/**
	 * Binds an integer to the next unknown value.
	 * 
	 * @param int $Value The value.
	 */
	public function BindInt(int &$Value) {
		$this -> BoundTypes .= "i";
		$this -> BoundValues[] = $Value;
	}

	/**
	 * Binds a string to the next unknown value.
	 * 
	 * @param string $Value The value.
	 */
	public function BindString(string &$Value) {
		$this -> BoundTypes .= "s";
		$this -> BoundValues[] = $Value;
	}

	/**
	 * Binds a float to the next unknown value.
	 * 
	 * @param float $Value The value.
	 */
	public function BindFloat(float &$Value) {
		$this -> BoundTypes .= "d";
		$this -> BoundValues[] = $Value;
	}

	/**
	 * Binds a blob to the next unknown value.
	 * 
	 * @param string $Value The value.
	 */
	public function BindBlob(string &$Value) {
		$this -> BoundTypes .= "b";
		$this -> BoundValues[] = $Value;
	}

	/**
	 * Clears all the bound variables from the query.
	 */
	public function ClearBound() {
		$this -> BoundTypes = "";
		$this -> BoundValues = [];
	}

	/**
	 * Attempts to execute this prepared statement.
	 * 
	 * @see GetResult
	 */
	public function Execute() {

		if (strlen($this -> BoundTypes) > 0) {
			try {
				$Result = @$this -> Statement -> bind_param($this -> BoundTypes, ...$this -> BoundValues);

				if ($Result === false) {
					X_ASQLiHandleEx($this -> Mysqli);
				}
			} catch (Exception) {
				X_ASQLiHandleEx($this -> Mysqli);
			}
		}

		try {
			$Result = @$this -> Statement -> execute();

			if ($Result === false) {
				X_ASQLiHandleEx($this -> Mysqli);
			}
		} catch (Exception) {
			X_ASQLiHandleEx($this -> Mysqli);
		}
	}

	/**
	 * Get result set from the last execution.
	 * 
	 * @return ASQLiResult The result set.
	 */
	public function GetResult() {
		try {
			$Result = @$this -> Statement -> get_result();

			if ($Result === false) {
				X_ASQLiHandleEx($this -> Mysqli);
			}

			return new ASQLiResult($Result, $this -> Mysqli);
		} catch (Exception) {
			X_ASQLiHandleEx($this -> Mysqli);
		}
	}
}