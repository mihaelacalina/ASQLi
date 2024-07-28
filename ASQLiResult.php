<?
include_once __DIR__ . "/ASQLiExceptions.php";


/**
 * The format for the row arrays.
 */
enum ASQLiRowFormat {
	case Associative;
	case Object;
	case Numeric;
}



/**
 * This is a mysqli result set.
 * The data maybe buffered locally or on the mysql server.
 * 
 * The rows can be accessed with array index operators and this object can also be used in a foreach loop.
 * 
 * @author atheramew
 */
class ASQLiResult implements Iterator, ArrayAccess {
	protected ASQLiRowFormat $RowFormat = ASQLiRowFormat::Associative;
	protected ?object $ObjectTemplate = null;
	protected mysqli_result $Result;
	protected int $CurrentRow = 0;
	protected mysqli $Mysqli;

	/**
	 * Not supposed to create class.
	 * 
	 * @see ASQLiConnection ::ExecuteQuery
	 * @see ASQLiStatement ::GetResult
	 */
	public function __construct(mysqli_result $Result, mysqli $Mysqli) {
		$this -> Result = $Result;
		$this -> Mysqli = $Mysqli;
	}

	/**
	 * Fetches a row from the buffer.
	 * 
	 * The format of the row array can be set with ASQLiResult::SetRowFormat() and can be retrieved using ASQLiResult::GetRowFormat().
	 * The object template can be set with ASQLiResult::SetTemplate().
	 */
	public function FetchRow() {
		$this -> X_Seek($this -> CurrentRow);
		$this -> CurrentRow ++;
		
		return $this -> X_FetchRow($this -> RowFormat);
	}

	/**
	 * Fetches all rows from this result into an array.
	 * 
	 * **Warning**: This is a memory-intensive action and can **crash** with a big enough result set.
	 * 
	 * @return array The array containing all rows in this result set.
	 */
	public function FetchAllRows() {
		$Buffer = [];
		
		foreach ($this as $Index => $Row) {
			$Buffer[$Index] = $Row;
		}

		return $Buffer;
	}

	/**
	 * Seeks to the specified row.
	 * 
	 * @param int $RowIndex The index of the row. First row is at index 0.
	 */
	public function Seek(int $RowIndex) {
		$this -> X_Seek($RowIndex);
		$this -> CurrentRow = $RowIndex;
	}

	/**
	 * Sets the default object template for object serialization.
	 * 
	 * @param object $Object The template object. The provided argument won't be modified.
	 */
	public function SetTemplate(object $Object) {
		$this -> ObjectTemplate = $Object;
	}

	/**
	 * Sets the row fomat that this ASQLi Result will output data in.
	 * 
	 * @param ASQLiRowFormat $Type The format.
	 */
	public function SetRowFormat(ASQLiRowFormat $Type) {
		$this -> RowFormat = $Type;
	}

	/**
	 * Gets the row fomat that this ASQLi Result will output data in.
	 * 
	 * @return ASQLiRowFormat $Type The format.
	 */
	public function GetRowFormat() {
		return $this -> RowFormat;
	}

	/**
	 * Gets the row count.
	 * 
	 * @return int|string The number of rows.
	 */
	public function GetRowCount() {
		return $this -> Result -> num_rows;
	}
	
	#region Protected
		protected function X_FetchRow(ASQLiRowFormat $RowFormat) {
			$Data = false;
			
			try {
				$Data = @$this -> Result -> fetch_array(X_GetRowFormatId($RowFormat));
				
				if ($Data === false) {
					X_ASQLiHandleEx($this -> Mysqli);
				}
			} catch (Exception) {
				X_ASQLiHandleEx($this -> Mysqli);
			}

			if ($RowFormat != ASQLiRowFormat::Object) {
				return $Data;
			}
			
			// Object case

			if (!is_object($this -> ObjectTemplate)) {
				throw new ASQLiException(1, "The set object template isn't an object.");
			}

			$Object = clone $this -> ObjectTemplate;
			$ReflectionObject = new ReflectionObject($Object);

			foreach ($Data as $Name => $Value) {
				try {
					$Property = $ReflectionObject -> getProperty($Name);

					$Property -> setAccessible(true);
					$Property -> setValue($Object, $Value);
				} catch (ReflectionException) {}
			}

			return $Object;
		}

		protected function X_Seek(int $RowIndex) {
			if ($RowIndex >= $this -> Result -> num_rows) {
				throw new ASQLiException(2, "Reached end of result set.");
			}

			try {
				if (@$this -> Result -> data_seek($RowIndex) === false) {
					X_ASQLiHandleEx($this -> Mysqli);
				}
			} catch (Exception) {
				X_ASQLiHandleEx($this -> Mysqli);
			}
		}
	#endregion

	#region ArrayAccess
		public function offsetSet($Index, $Value): void {}

		public function offsetExists($Index): bool {
			return $Index >= 0 && $Index < $this -> Result -> num_rows;
		}

		public function offsetUnset($Index): void {}

		public function offsetGet($Index): mixed {
			$this -> X_Seek($Index);
			return $this -> X_FetchRow($this -> RowFormat);
		}
	#endregion

	#region Iterator
		protected int $CurrentRowIterator = 0;


		public function rewind(): void {
			$this -> CurrentRowIterator = 0;
		}
		
		public function current(): mixed {
			$this -> X_Seek($this -> CurrentRowIterator);
			return $this -> X_FetchRow($this -> RowFormat);
		}

		public function key(): mixed {
			return $this -> CurrentRowIterator;
		}

		public function next(): void {
			$this -> CurrentRowIterator ++;
		}

		public function valid(): bool {
			return $this -> CurrentRowIterator < $this -> Result -> num_rows;
		}
	#endregion
}



function X_GetRowFormatId(ASQLiRowFormat $Format) {
	switch ($Format) {
		case ASQLiRowFormat::Associative:
			return MYSQLI_ASSOC;
		case ASQLiRowFormat::Numeric:
			return MYSQLI_NUM;
		case ASQLiRowFormat::Object:
			return MYSQLI_ASSOC;
	}
}