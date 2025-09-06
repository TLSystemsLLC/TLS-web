<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';

echo "<h1>TLS Database Connection Test</h1>\n";
echo "<style>body { font-family: Arial, sans-serif; margin: 40px; } .success { color: green; } .error { color: red; } .info { color: blue; }</style>\n";

// Test configuration loading
echo "<h2>Configuration Test</h2>\n";
try {
    echo "<p class='info'>DB Server: " . Config::get('DB_SERVER') . "</p>\n";
    echo "<p class='info'>DB Username: " . Config::get('DB_USERNAME') . "</p>\n";
    echo "<p class='info'>DB Port: " . Config::get('DB_PORT') . "</p>\n";
    echo "<p class='success'>✓ Configuration loaded successfully</p>\n";
} catch (Exception $e) {
    echo "<p class='error'>✗ Configuration error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

// Test SQL Server extension
echo "<h2>PHP Extension Test</h2>\n";
if (extension_loaded('pdo_sqlsrv')) {
    echo "<p class='success'>✓ PDO SQL Server extension is loaded</p>\n";
} else {
    echo "<p class='error'>✗ PDO SQL Server extension is NOT loaded</p>\n";
    echo "<p>Available PDO drivers: " . implode(', ', PDO::getAvailableDrivers()) . "</p>\n";
}

// Test database connection
echo "<h2>Database Connection Test</h2>\n";
try {
    $db = new Database(
        Config::get('DB_SERVER'),
        Config::get('DB_USERNAME'),
        Config::get('DB_PASSWORD')
    );
    
    // Test connection to a known database (you may need to adjust this)
    echo "<p class='info'>Attempting to connect to database server...</p>\n";
    
    // Try connecting to master database first
    $db->connect('master');
    echo "<p class='success'>✓ Successfully connected to SQL Server master database</p>\n";
    
    // Test a simple query
    $result = $db->query("SELECT @@VERSION as version");
    if (!empty($result)) {
        echo "<p class='success'>✓ Successfully executed test query</p>\n";
        echo "<p class='info'>SQL Server Version: " . htmlspecialchars($result[0]['version']) . "</p>\n";
    }
    
    $db->disconnect();
    echo "<p class='success'>✓ Successfully disconnected from database</p>\n";
    
} catch (Exception $e) {
    echo "<p class='error'>✗ Database connection error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p class='info'>This may be normal if your SQL Server is not accessible from this network location.</p>\n";
}

// Test list available databases
echo "<h2>Available Databases Test</h2>\n";
try {
    $db = new Database(
        Config::get('DB_SERVER'),
        Config::get('DB_USERNAME'),
        Config::get('DB_PASSWORD')
    );
    
    $db->connect('master');
    $databases = $db->query("SELECT name FROM sys.databases WHERE name NOT IN ('master', 'tempdb', 'model', 'msdb') ORDER BY name");
    
    echo "<p class='success'>✓ Successfully retrieved database list</p>\n";
    echo "<ul>\n";
    foreach ($databases as $db_info) {
        echo "<li>" . htmlspecialchars($db_info['name']) . "</li>\n";
    }
    echo "</ul>\n";
    
    $db->disconnect();
    
} catch (Exception $e) {
    echo "<p class='error'>✗ Could not retrieve database list: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

echo "<hr>\n";
echo "<p><a href='/tls/'>← Back to TLS Application</a></p>\n";
?>