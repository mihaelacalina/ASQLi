<?

enum ASQLiResultType: int {
	case Store = MYSQLI_STORE_RESULT;
	case Use = MYSQLI_USE_RESULT;
}

enum ASQLiRowFormat: int {
	case Associative = 0;
	case Object      = 1;
	case Numeric     = 2;
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