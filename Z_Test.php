<?
include_once __DIR__ . "/ASQLi.php";

$Connection = new ASQLiConnection("127.0.0.1", "root", null, "main");
$Connection -> Connect();

class Test {
	public string $Value;
	public int $Index;
}

$Result = $Connection -> PrepareQuery("SELECT * FROM test", ASQLiResultType::Store);