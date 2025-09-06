<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';

echo "<h1>Testing spUser_GetUser Stored Procedure</h1>\n";
echo "<style>body { font-family: Arial, sans-serif; margin: 40px; } .success { color: green; } .error { color: red; } .info { color: blue; }</style>\n";

try {
    $db = new Database(Config::get('DB_SERVER'), Config::get('DB_USERNAME'), Config::get('DB_PASSWORD'));
    $db->connect('CWKI2');
    
    echo "<h2>Testing spUser_GetUser with user 'tlyle'</h2>\n";
    
    // Test spUser_GetUser with the known user
    $result = $db->executeStoredProcedure('spUser_GetUser', ['tlyle']);
    
    echo "<h3 class='info'>Results:</h3>\n";
    echo "<pre>\n";
    print_r($result);
    echo "</pre>\n";
    
    if (!empty($result)) {
        echo "<h3 class='success'>User Details Found:</h3>\n";
        foreach ($result as $row) {
            echo "<ul>\n";
            foreach ($row as $key => $value) {
                echo "<li><strong>" . htmlspecialchars($key) . ":</strong> " . htmlspecialchars($value ?? 'NULL') . "</li>\n";
            }
            echo "</ul>\n";
            break; // Only show first row
        }
    } else {
        echo "<p class='error'>No results returned</p>\n";
    }
    
    $db->disconnect();
    
} catch (Exception $e) {
    echo "<p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

echo "<hr>\n";
echo "<p><a href='/tls/'>← Back to TLS Application</a></p>\n";
?>