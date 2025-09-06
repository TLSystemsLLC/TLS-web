<?php
declare(strict_types=1);

/**
 * Database Connection Manager for SQL Server
 * Handles secure connections to SQL Server with proper error handling
 * 
 * @author Tony Lyle
 * @version 1.0
 */
class Database
{
    private ?PDO $connection = null;
    private string $server;
    private string $username;
    private string $password;
    private string $database = '';
    private int $port;
    
    /**
     * Constructor
     * 
     * @param string $server Database server address
     * @param string $username Database username
     * @param string $password Database password
     * @param int $port Database port (default 1433)
     */
    public function __construct(string $server, string $username, string $password, int $port = 1433)
    {
        $this->server = $server;
        $this->username = $username;
        $this->password = $password;
        $this->port = $port;
    }

    /**
     * Connect to specific database
     * 
     * @param string $database Database name
     * @throws DatabaseException on connection failure
     */
    public function connect(string $database): void
    {
        try {
            $this->database = $database;
            
            $dsn = sprintf(
                'sqlsrv:Server=%s,%d;Database=%s',
                $this->server,
                $this->port,
                $database
            );
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];
            
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
            
            // Log successful connection (without sensitive details)
            error_log("Database connection established to: {$database}");
            
        } catch (PDOException $e) {
            $this->logError('Connection failed', $e);
            throw new DatabaseException('Database connection failed', 0, $e);
        }
    }

    /**
     * Execute stored procedure and return results
     * 
     * @param string $procedureName Stored procedure name
     * @param array $parameters Parameters for the procedure
     * @return array|int Results or return value for procedures
     * @throws DatabaseException on execution failure
     */
    public function executeStoredProcedure(string $procedureName, array $parameters = []): array|int
    {
        if (!$this->connection) {
            throw new DatabaseException('No database connection established');
        }

        try {
            // Build parameter placeholders
            $placeholders = str_repeat('?,', count($parameters));
            $placeholders = rtrim($placeholders, ',');
            
            $sql = "EXEC {$procedureName} {$placeholders}";
            
            $stmt = $this->connection->prepare($sql);
            $stmt->execute(array_values($parameters));
            
            // For procedures that return data
            $results = $stmt->fetchAll();
            
            // For procedures that return a status code
            if (empty($results)) {
                // Try to get return value
                $returnStmt = $this->connection->query("SELECT @@ROWCOUNT as affected_rows");
                $returnData = $returnStmt->fetch();
                return (int)$returnData['affected_rows'] ?? 0;
            }
            
            return $results;
            
        } catch (PDOException $e) {
            $this->logError("Stored procedure execution failed: {$procedureName}", $e);
            throw new DatabaseException('Database operation failed', 0, $e);
        }
    }

    /**
     * Execute stored procedure and get return value
     * 
     * @param string $procedureName Stored procedure name
     * @param array $parameters Parameters for the procedure
     * @return int Return value from procedure
     * @throws DatabaseException on execution failure
     */
    public function executeStoredProcedureWithReturn(string $procedureName, array $parameters = []): int
    {
        if (!$this->connection) {
            throw new DatabaseException('No database connection established');
        }

        try {
            // Build parameter placeholders with return parameter
            $placeholders = str_repeat('?,', count($parameters));
            $placeholders = rtrim($placeholders, ',');
            
            $sql = "DECLARE @ReturnValue INT; EXEC @ReturnValue = {$procedureName} {$placeholders}; SELECT @ReturnValue as ReturnValue";
            
            $stmt = $this->connection->prepare($sql);
            $stmt->execute(array_values($parameters));
            
            $result = $stmt->fetch();
            return (int)($result['ReturnValue'] ?? 99);
            
        } catch (PDOException $e) {
            $this->logError("Stored procedure execution failed: {$procedureName}", $e);
            throw new DatabaseException('Database operation failed', 0, $e);
        }
    }

    /**
     * Execute query and return results
     * 
     * @param string $sql SQL query
     * @param array $parameters Parameters for the query
     * @return array Query results
     * @throws DatabaseException on execution failure
     */
    public function query(string $sql, array $parameters = []): array
    {
        if (!$this->connection) {
            throw new DatabaseException('No database connection established');
        }

        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($parameters);
            
            // For multi-statement queries with OUTPUT parameters, we need to get the last result set
            $results = [];
            do {
                try {
                    $data = $stmt->fetchAll();
                    if (!empty($data)) {
                        $results = $data; // Keep the last non-empty result set
                    }
                } catch (PDOException $e) {
                    // Some result sets may not have fields (like EXEC statements)
                    // Continue to next result set
                }
            } while ($stmt->nextRowset());
            
            return $results;
            
        } catch (PDOException $e) {
            $this->logError('Query execution failed', $e);
            throw new DatabaseException('Database query failed', 0, $e);
        }
    }

    /**
     * Disconnect from database
     */
    public function disconnect(): void
    {
        $this->connection = null;
        if ($this->database) {
            error_log("Database connection closed: {$this->database}");
        }
    }

    /**
     * Get current database name
     * 
     * @return string Current database name
     */
    public function getCurrentDatabase(): string
    {
        return $this->database;
    }

    /**
     * Check if connected to database
     * 
     * @return bool True if connected
     */
    public function isConnected(): bool
    {
        return $this->connection !== null;
    }

    /**
     * Log database errors
     * 
     * @param string $message Error message
     * @param PDOException $exception Exception details
     */
    private function logError(string $message, PDOException $exception): void
    {
        $logMessage = sprintf(
            'Database Error: %s | Code: %s | Message: %s | Database: %s',
            $message,
            $exception->getCode(),
            $exception->getMessage(),
            $this->database
        );
        
        error_log($logMessage);
    }

    /**
     * Destructor - ensure connection is closed
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}

/**
 * Custom Database Exception
 */
class DatabaseException extends Exception
{
    public function getDisplayMessage(): string
    {
        return 'A database error occurred. Please contact system administrator if the problem persists.';
    }
}