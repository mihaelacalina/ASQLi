# ASQLi
ASQLi is a library I made to facilitate my intereactions with MySQL databases. This library Explicits the exceptions that can occur and simplifies loading rows into php classes.

## Usage
Using ASQLi is simple! To get started, the ASQLi\Connection class has some static methods that can create connections to different databases such as Mysql/Mariadb and SQLite. We'll work with SQLite for this example.

The following code will create a SQLite instance in memory, create a table and insert some user-provided data in it.

```php
    use ASQLi\Connection;

    $Connection = Connection::SQLite();

    $Connection -> Connect();

    $Connection -> Query("CREATE TABLE test (id INTEGER PRIMARY KEY AUTOINCREMENT, data TEXT);");
    $Connection -> Query("INSERT INTO test (data) VALUES (?);", false, "Some unsafe data! \"; DROP ALL TABLES; SELECT \"");

    $Result = $Connection -> Query("SELECT * FROM test");
    $Data = $Result -> GetRow();

    print_r($Data);

    // Theoretically the garbage collector would close the connection in this case nonetheless.
    $Connection -> Disconnect();
```

For more information refer to the source code documentation.