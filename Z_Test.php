<?
include_once __DIR__ . "/ASQLi.php";

$Connection = new ASQLiConnection("127.0.0.1", "root", null, "main");
$Connection -> Connect();

class Test {
	public string $Value;
	public int $Index;
}

$Result = $Connection -> RunQuery("SELECT * FROM test", ASQLiResultType::Store);
$Result -> SetTemplate(new Test());
$Result -> SetRowFormat(ASQLiRowFormat::Object);

var_dump($Result -> FetchAllRows());
