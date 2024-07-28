<?

/**
 * General mysql exception.
 */
class ASQLiException extends Exception {
	public string $Exception = "";
	public int $ExceptionId = 0;

	public function __construct(int $ExceptionId, string $Exception) {
		$this -> ExceptionId = $ExceptionId;
		$this -> Exception = $Exception;

		$this -> message = "Exception {$ExceptionId}: {$Exception}";
	}
}

/**
 * An exception that occurs while connecting.
 */
class ASQLiConnectionException extends ASQLiException {}



function X_ASQLiHandleEx(mysqli $Mysqli) {
	if (!$Mysqli -> errno) {
		throw new ASQLiException(-1, "Unknown exception occured.");
	}

	throw new ASQLiException($Mysqli -> errno, $Mysqli -> error);
}