<?
include_once __DIR__ . "/ASQLi.php";

$Connection = new ASQLiConnection("127.0.0.1", "root", null, "main");
$Connection -> Connect();

class Test {
	public string $Value;
	public int $Index;
}

$PQuery = $Connection -> PrepareQuery("SELECT * FROM test", ASQLiResultType::Store);

$PQuery -> Execute();
$Result = $PQuery -> GetResult();
$Result -> SetRowFormat(ASQLiRowFormat::Object);
$Result -> SetTemplate(new Test());