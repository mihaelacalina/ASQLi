<?


class ASQLiStatement {
	protected mysqli_stmt $Statement;
	protected array $BoundTypes = [];
	protected array $Values = [];


	public function __construct(mysqli_stmt $Statement) {
		$this -> Statement = $Statement;
	}

	public function BindInt(int &$Value) {
		$this -> BoundTypes[] = "i";
		$this -> Values[] = $Value;
	}

	public function BindString(string &$Value) {
		$this -> BoundTypes[] = "s";
		$this -> Values[] = $Value;
	}
}