<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';

echo "<h1>TLS Login Procedures Test</h1>\n";
echo "<style>body { font-family: Arial, sans-serif; margin: 40px; } .success { color: green; } .error { color: red; } .info { color: blue; }</style>\n";

$testDatabases = ['DEMO', 'TLSYS', 'TLSystems'];

foreach ($testDatabases as $dbName) {
    echo "<h2>Testing Database: $dbName</h2>\n";
    
    try {
        $db = new Database(
            Config::get('DB_SERVER'),
            Config::get('DB_USERNAME'),
            Config::get('DB_PASSWORD')
        );
        
        $db->connect($dbName);
        echo "<p class='success'>✓ Connected to $dbName</p>\n";
        
        // Check if spUser_Login exists
        try {
            $checkProc = $db->query("SELECT 1 FROM sys.procedures WHERE name = 'spUser_Login'");
            if (!empty($checkProc)) {
                echo "<p class='success'>✓ spUser_Login procedure exists</p>\n";
            } else {
                echo "<p class='error'>✗ spUser_Login procedure not found</p>\n";
            }
        } catch (Exception $e) {
            echo "<p class='error'>✗ Error checking procedures: " . htmlspecialchars($e->getMessage()) . "</p>\n";
        }
        
        // Check if spUser_Menus exists
        try {
            $checkProc = $db->query("SELECT 1 FROM sys.procedures WHERE name = 'spUser_Menus'");
            if (!empty($checkProc)) {
                echo "<p class='success'>✓ spUser_Menus procedure exists</p>\n";
            } else {
                echo "<p class='error'>✗ spUser_Menus procedure not found</p>\n";
            }
        } catch (Exception $e) {
            echo "<p class='error'>✗ Error checking procedures: " . htmlspecialchars($e->getMessage()) . "</p>\n";
        }
        
        // Check if tUser table exists
        try {
            $checkTable = $db->query("SELECT 1 FROM sys.tables WHERE name = 'tUser'");
            if (!empty($checkTable)) {
                echo "<p class='success'>✓ tUser table exists</p>\n";
                
                // Get sample users (just userids, not passwords)
                $users = $db->query("SELECT TOP 5 UserID FROM tUser WHERE Active = 1 ORDER BY UserID");
                if (!empty($users)) {
                    echo "<p class='info'>Sample active users: ";
                    foreach ($users as $user) {
                        echo htmlspecialchars($user['UserID']) . " ";
                    }
                    echo "</p>\n";
                }
            } else {
                echo "<p class='error'>✗ tUser table not found</p>\n";
            }
        } catch (Exception $e) {
            echo "<p class='error'>✗ Error checking tUser table: " . htmlspecialchars($e->getMessage()) . "</p>\n";
        }
        
        $db->disconnect();
        
    } catch (Exception $e) {
        echo "<p class='error'>✗ Could not connect to $dbName: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    }
    
    echo "<hr>\n";
}

echo "<p><a href='/tls/'>← Back to TLS Application</a></p>\n";
?>