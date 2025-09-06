<?php
declare(strict_types=1);

echo "<h1>TLS Database Connection Debug</h1>\n";
echo "<style>body { font-family: Arial, sans-serif; margin: 40px; } .success { color: green; } .error { color: red; } .info { color: blue; }</style>\n";

// Load config
require_once __DIR__ . '/config/config.php';

$server = Config::get('DB_SERVER');
$username = Config::get('DB_USERNAME');
$password = Config::get('DB_PASSWORD');
$port = Config::get('DB_PORT', '1433');

echo "<h2>Connection Parameters</h2>\n";
echo "<p class='info'>Server: $server</p>\n";
echo "<p class='info'>Username: $username</p>\n";
echo "<p class='info'>Port: $port</p>\n";

echo "<h2>Direct PDO Connection Test</h2>\n";

try {
    // Test different DSN formats
    $dsn_formats = [
        "sqlsrv:Server=$server,$port;Database=master;TrustServerCertificate=1",
        "sqlsrv:Server=$server,$port;Database=master",
        "sqlsrv:server=$server,$port;database=master;TrustServerCertificate=true",
        "sqlsrv:server=$server;port=$port;database=master"
    ];
    
    foreach ($dsn_formats as $index => $dsn) {
        echo "<h3>Testing DSN Format " . ($index + 1) . "</h3>\n";
        echo "<p class='info'>DSN: $dsn</p>\n";
        
        try {
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 10
            ]);
            
            echo "<p class='success'>✓ Connection successful with DSN format " . ($index + 1) . "</p>\n";
            
            // Test a simple query
            $stmt = $pdo->query("SELECT @@VERSION as version");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<p class='success'>✓ Query successful: " . htmlspecialchars(substr($result['version'], 0, 100)) . "...</p>\n";
            
            $pdo = null;
            break; // Stop on first successful connection
            
        } catch (PDOException $e) {
            echo "<p class='error'>✗ Failed: " . htmlspecialchars($e->getMessage()) . "</p>\n";
        }
    }
    
} catch (Exception $e) {
    echo "<p class='error'>✗ General error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

echo "<hr>\n";
echo "<p><a href='/tls/'>← Back to TLS Application</a></p>\n";
?>