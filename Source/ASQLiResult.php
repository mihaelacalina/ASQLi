<?
include_once __DIR__ . "/Exceptions.php";
include_once __DIR__ . "/Enums.php";



class ASQLiResult implements Iterator {
	protected ASQLiRowFormat $RowFormat = ASQLiRowFormat::Associative;
	protected object $ObjectTemplate;
	protected mysqli_result $Result;
	protected int $CurrentRow = 0;
	protected mysqli $Mysqli;

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

	public function FetchAllRows() {
		$Buffer = [];
		
		foreach ($this as $Index => $Row) {
			$Buffer[$Index] = $Row;
		}

		return $Buffer;
	}

	/**
	 * Fetches a row and sets the parameters of the provided object.
	 * 
	 * The format of the row array can be set with ASQLiResult::SetRowFormat() and can be retrieved using ASQLiResult::GetRowFormat().
	 * If a column name doesnt exist in the provided object, that value will be silently ignored.
	 * 
	 * This function alters the provided object directly.
	 */
	public function LoadSerializedObject(object $Object) {
		$this -> X_Seek($this -> CurrentRow);

		$Row = $this -> X_FetchRow(ASQLiRowFormat::Associative);
		$ReflectionObject = new ReflectionObject($Object);

		foreach ($Row as $Name => $Value) {
			try {
				$Property = $ReflectionObject -> getProperty($Name);

				$Property -> setAccessible(true);
				$Property -> setValue($Object, $Value);
			} catch (ReflectionException) {}
		}
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
	
	#region Protected
		protected function X_FetchRow(ASQLiRowFormat $RowFormat) {
			$Data = false;
			
			try {
				$Data = @$this -> Result -> fetch_array(X_GetRowFormatId($RowFormat));
				
				if ($Data === false) {
					ASQLiHandleEx($this -> Mysqli);
				}
			} catch (Exception) {
				ASQLiHandleEx($this -> Mysqli);
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
					ASQLiHandleEx($this -> Mysqli);
				}
			} catch (Exception) {
				ASQLiHandleEx($this -> Mysqli);
			}
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