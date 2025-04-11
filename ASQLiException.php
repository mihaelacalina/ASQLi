<?php
/*
    (c) 2025 Slincu Mihaela-Calina - All rights reserved.

    11/04/2025 11:45:00 PM
*/
namespace ASQLi;
use Exception, PDO;

/**
 * Base class for all exceptions thrown by an ASQLi instance.
 */
class DatabaseException extends Exception {}

/**
 * Exception thrown when a connection cannot be established to the server.
 */
class ConnectionException extends DatabaseException {}

/**
 * Exception thrown when trying to read an environment variable but it cannot be found.
 */
class MissingEnvException extends DatabaseException {}

/**
 * Exception thrown when the provided unix socket file does not exist.
 */
class MissingUnixSocketException extends DatabaseException {}


/**
 * Exception thrown when an error occurs while running a query.
 */
class QueryException extends DatabaseException {}


/**
 * Exception thrown when an error occurs while trying to set a driver-dependent attribute.
 */
class DriverAttributeException extends DatabaseException {}


/**
 * General transaction exception.
 */
class TransactionException extends DatabaseException {}