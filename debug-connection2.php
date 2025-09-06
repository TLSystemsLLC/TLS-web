<?php
declare(strict_types=1);

echo "<h1>TLS Database Connection Debug - Minimal Options</h1>\n";
echo "<style>body { font-family: Arial, sans-serif; margin: 40px; } .success { color: green; } .error { color: red; } .info { color: blue; }</style>\n";

// Load config
require_once __DIR__ . '/config/config.php';

$server = Config::get('DB_SERVER');
$username = Config::get('DB_USERNAME');
$password = Config::get('DB_PASSWORD');

echo "<h2>Minimal Connection Test</h2>\n";

try {
    $dsn = "sqlsrv:Server=$server,1433;Database=master";
    echo "<p class='info'>DSN: $dsn</p>\n";
    
    // Try with minimal options
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "<p class='success'>✓ Connection successful with minimal options</p>\n";
    
    // Test a simple query
    $stmt = $pdo->query("SELECT @@VERSION as version");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p class='success'>✓ Query successful</p>\n";
    echo "<p class='info'>SQL Server Version: " . htmlspecialchars(substr($result['version'], 0, 100)) . "...</p>\n";
    
    // Test listing databases
    $stmt = $pdo->query("SELECT name FROM sys.databases WHERE name NOT IN ('master', 'tempdb', 'model', 'msdb') ORDER BY name");
    $databases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p class='success'>✓ Database list retrieved</p>\n";
    echo "<ul>\n";
    foreach ($databases as $db) {
        echo "<li>" . htmlspecialchars($db['name']) . "</li>\n";
    }
    echo "</ul>\n";
    
    $pdo = null;
    
} catch (PDOException $e) {
    echo "<p class='error'>✗ Failed: " . htmlspecialchars($e->getMessage()) . "</p>\n";
} catch (Exception $e) {
    echo "<p class='error'>✗ General error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

echo "<hr>\n";
echo "<p><a href='/tls/'>← Back to TLS Application</a></p>\n";
?>