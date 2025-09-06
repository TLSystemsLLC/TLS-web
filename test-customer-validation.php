<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';

echo "<h1>Customer ID Validation Test</h1>\n";
echo "<style>body { font-family: Arial, sans-serif; margin: 40px; } .success { color: green; } .error { color: red; } .info { color: blue; }</style>\n";

try {
    $db = new Database(
        Config::get('DB_SERVER'),
        Config::get('DB_USERNAME'),
        Config::get('DB_PASSWORD')
    );
    
    echo "<h2>Testing spGetOperationsDB Stored Procedure</h2>\n";
    
    $db->connect('master');
    echo "<p class='success'>✓ Connected to master database</p>\n";
    
    try {
        $results = $db->executeStoredProcedure('spGetOperationsDB', []);
        echo "<p class='success'>✓ spGetOperationsDB procedure executed successfully</p>\n";
        
        echo "<h3>Valid Customer IDs:</h3>\n";
        echo "<ul>\n";
        foreach ($results as $row) {
            // The procedure might return different column names, let's see what we get
            echo "<li>Row data: " . htmlspecialchars(json_encode($row)) . "</li>\n";
        }
        echo "</ul>\n";
        
    } catch (Exception $e) {
        echo "<p class='error'>✗ spGetOperationsDB failed: " . htmlspecialchars($e->getMessage()) . "</p>\n";
        
        // Try the alternative approach
        echo "<h3>Trying Alternative: SELECT name FROM OperationsDB</h3>\n";
        try {
            $results = $db->query("SELECT name FROM OperationsDB");
            echo "<p class='success'>✓ OperationsDB table query successful</p>\n";
            
            echo "<h3>Valid Customer IDs from table:</h3>\n";
            echo "<ul>\n";
            foreach ($results as $row) {
                echo "<li>" . htmlspecialchars($row['name']) . "</li>\n";
            }
            echo "</ul>\n";
            
        } catch (Exception $e2) {
            echo "<p class='error'>✗ OperationsDB table query failed: " . htmlspecialchars($e2->getMessage()) . "</p>\n";
            
            // Fallback: show available databases that match pattern
            echo "<h3>Fallback: Available Databases</h3>\n";
            $databases = $db->query("SELECT name FROM sys.databases WHERE name NOT IN ('master', 'tempdb', 'model', 'msdb') ORDER BY name");
            echo "<ul>\n";
            foreach ($databases as $db_info) {
                echo "<li>" . htmlspecialchars($db_info['name']) . "</li>\n";
            }
            echo "</ul>\n";
        }
    }
    
    $db->disconnect();
    
} catch (Exception $e) {
    echo "<p class='error'>✗ Connection failed: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

echo "<hr>\n";
echo "<p><a href='/tls/'>← Back to TLS Application</a></p>\n";
?>