<?php

namespace ASQLi;

use Attribute;

/**
 * The data type of a column.
 */
enum DataType {
    /**
     * An interger.
     */
    case Integer;
    /**
     * A real number.
     */
    case Float;
    /**
     * This is a binary string. This can be a blob or any kind of datatype really on the server.
     */
    case String;
    /**
     * This would be any kind of serializable object or array.
     * 
     * When read, this will be deserialized into the original object.
     */
    case Json;

    /**
     * This datatype is the same as a file handle in php. It usually represents a BLOB.
     * 
     * @see https://www.php.net/manual/en/function.fopen.php
     */
    case Stream;
}

/**
 * This class is a wrapper for a table's ColumnMetadata.
 */
class TableMetadata {
    protected array $Metadata = [];

    public function __construct(array $Metadata) {
        $this -> Metadata = $Metadata;
    }

    /**
     * Gets the column metadata object for the specified column.
     * @param string|int $Column The 0-indexed column index or column name.
     * @return ColumnMetadata|null Returns the column metadata or null if the column does not exist.
     */
    public function GetColumnMetadata(string|int $Column): ?ColumnMetadata {
        if (is_int($Column)) {
            if ($Column < 0 || $Column >= count($this -> Metadata)) {
                return null;
            }

            return $this -> Metadata[$Column];
        }

        for ($I = 0; $I < count($this -> Metadata); $I ++) {
            $ColumnObject = $this -> Metadata[$I];

            if ($ColumnObject -> Name === $Column) {
                return $ColumnObject;
            }
        }

        return null;
    }

    /**
     * Returns a list of column names.
     * 
     * @return string[]
     */
    public function GetColumnNames(): array {
        $Names = [];

        for ($I = 0; $I < count($this -> Metadata); $I ++) {
            $Names[] = $this -> Metadata[$I] -> GetName();
        }

        return $Names;
    }

    /**
     * Returns the number of columns this table has.
     * 
     * @return int
     */
    public function GetColumnCount() {
        return count($this -> Metadata);
    }
}



/**
 * This is the metadata of a column.
 * This class encloses the type, name, length and precision of the column.
 */
class ColumnMetadata {
    protected ?string $DeclaredType = null;
    protected array $Flags;
    protected string $Name;
    protected ?string $TableName = null;
    protected int $Length;
    protected int $Precision;


    public function __construct(array $RawData) {
        if (isset($RawData["driver:decl_type"])) {
            $this -> DeclaredType = $RawData["driver:decl_type"];
        }

        $this -> Flags = $RawData["flags"];
        $this -> Name = $RawData["name"];

        if (isset($RawData["table"])) {
            $this -> TableName = $RawData["table"];
        }

        $this -> Length = $RawData["len"];
        $this -> Precision = $RawData["precision"];
    }

    /**
     * Gets the declared type of the column.
     * 
     * @return string|null The declared type.
     */
    public function GetDeclaredType(): ?string {
        return $this -> DeclaredType;
    }

    /**
     * Gets the name of the table from which this column comes.
     * 
     * @return string The name of the table.
     */
    public function GetTableName(): ?string {
        return $this -> TableName;
    }

    /**
     * Gets the flags of the column.
     * 
     * @return array The flags.
     */
    public function GetFlags(): array {
        return $this -> Flags;
    }

    /**
     * Gets the name of the column.
     * 
     * @return string The name.
     */
    public function GetName(): string {
        return $this -> Name;
    }

    /**
     * Gets the length of the column.
     * 
     * @return int The length.
     */
    public function GetLength(): int {
        return $this -> Length;
    }

    /**
     * Gets the precision of the column.
     * 
     * @return int The precision.
     */
    public function GetPrecision(): int {
        return $this -> Precision;
    }
}


#[Attribute(Attribute::TARGET_PROPERTY)]
class DbAttribute {
    public string $RowName;
    public DataType $Type;

    public function __construct(string $RowName, DataType $Type = DataType::String) {
        $this -> Type = $Type;
        $this -> RowName = $RowName;
    }
}