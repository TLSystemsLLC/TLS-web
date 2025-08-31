<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/classes/Database.php';

// Initialize authentication
$auth = new Auth(
    Config::get('DB_SERVER'),
    Config::get('DB_USERNAME'),
    Config::get('DB_PASSWORD')
);

// Require authentication
$auth->requireAuth('/tls/login.php');

$user = $auth->getCurrentUser();
$db = new Database(
    Config::get('DB_SERVER'),
    Config::get('DB_USERNAME'),
    Config::get('DB_PASSWORD')
);

echo "<h2>Database Table Testing</h2>";

try {
    $db->connect($user['customer_db']);
    echo "<p>✅ Connected to database: " . htmlspecialchars($user['customer_db']) . "</p>";
    
    // Test 1: Check if tSecurity table exists
    try {
        $result = $db->query("SELECT COUNT(*) as count FROM dbo.tSecurity", []);
        echo "<p>✅ tSecurity table exists with " . $result[0]['count'] . " records</p>";
        
        // Show first few records
        $sample = $db->query("SELECT TOP 5 Menu, Description FROM dbo.tSecurity ORDER BY Menu", []);
        echo "<h3>Sample tSecurity records:</h3><ul>";
        foreach ($sample as $row) {
            echo "<li>" . htmlspecialchars($row['Menu']) . " - " . htmlspecialchars($row['Description']) . "</li>";
        }
        echo "</ul>";
    } catch (Exception $e) {
        echo "<p>❌ Error accessing tSecurity table: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // Test 2: Check if tSecurityGroups table exists
    try {
        $result = $db->query("SELECT COUNT(*) as count FROM dbo.tSecurityGroups", []);
        echo "<p>✅ tSecurityGroups table exists with " . $result[0]['count'] . " records</p>";
        
        // Show available user groups
        $groups = $db->query("SELECT DISTINCT UserGroup FROM dbo.tSecurityGroups ORDER BY UserGroup", []);
        echo "<h3>Available User Groups:</h3><ul>";
        foreach ($groups as $row) {
            echo "<li>" . htmlspecialchars($row['UserGroup']) . "</li>";
        }
        echo "</ul>";
    } catch (Exception $e) {
        echo "<p>❌ Error accessing tSecurityGroups table: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // Test 3: Test spUser_Menu stored procedure
    try {
        $returnCode = $db->executeStoredProcedureWithReturn('spUser_Menu', [$user['user_id'], 'mnuLoadEntry']);
        echo "<p>✅ spUser_Menu works. Test result for mnuLoadEntry: " . ($returnCode === 0 ? 'Granted' : 'Denied') . "</p>";
    } catch (Exception $e) {
        echo "<p>❌ Error testing spUser_Menu: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // Test 4: Test spUsers_GetAll stored procedure
    try {
        $users = $db->executeStoredProcedure('spUsers_GetAll', []);
        echo "<p>✅ spUsers_GetAll works. Found " . count($users) . " users</p>";
    } catch (Exception $e) {
        echo "<p>❌ Error testing spUsers_GetAll: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Database connection error: " . htmlspecialchars($e->getMessage()) . "</p>";
} finally {
    $db->disconnect();
}
?>