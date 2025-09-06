<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Auth.php';

echo "<h1>Customer ID Authentication Test</h1>\n";
echo "<style>body { font-family: Arial, sans-serif; margin: 40px; } .success { color: green; } .error { color: red; } .info { color: blue; }</style>\n";

$auth = new Auth(
    Config::get('DB_SERVER'),
    Config::get('DB_USERNAME'),
    Config::get('DB_PASSWORD')
);

echo "<h2>Testing Customer ID Validation</h2>\n";

$testCases = [
    ['DEMO', 'Valid customer ID'],
    ['TLSYS', 'Valid customer ID'],  
    ['CWKI', 'Valid customer ID'],
    ['INVALID123', 'Invalid customer ID'],
    ['master', 'System database - should be invalid'],
    ['', 'Empty customer ID'],
    ['SQL_INJECTION;DROP--', 'SQL injection attempt']
];

foreach ($testCases as [$customerId, $description]) {
    echo "<h3>Testing: '$customerId' - $description</h3>\n";
    
    $result = $auth->login($customerId, 'testuser', 'testpass');
    
    if ($result['success']) {
        echo "<p class='success'>✓ Login allowed (not expected for most tests)</p>\n";
    } else {
        if (str_contains($result['message'], 'Invalid customer ID')) {
            echo "<p class='success'>✓ Correctly rejected invalid customer ID</p>\n";
        } elseif (str_contains($result['message'], 'Invalid username or password')) {
            echo "<p class='info'>✓ Customer ID valid, rejected due to credentials (expected for valid IDs)</p>\n";
        } else {
            echo "<p class='info'>ℹ Other rejection: " . htmlspecialchars($result['message']) . "</p>\n";
        }
    }
}

echo "<hr>\n";
echo "<h2>Valid Customer IDs from Master Database</h2>\n";

try {
    $db = new Database(Config::get('DB_SERVER'), Config::get('DB_USERNAME'), Config::get('DB_PASSWORD'));
    $db->connect('master');
    $validDbs = $db->executeStoredProcedure('spGetOperationsDB', []);
    $db->disconnect();
    
    echo "<p class='info'>Valid Customer IDs for login:</p>\n";
    echo "<ul>\n";
    foreach ($validDbs as $db_info) {
        echo "<li><strong>" . htmlspecialchars($db_info['name']) . "</strong></li>\n";
    }
    echo "</ul>\n";
    
} catch (Exception $e) {
    echo "<p class='error'>Error retrieving valid customer IDs: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

echo "<hr>\n";
echo "<p><a href='/tls/'>← Back to TLS Application</a></p>\n";
?>