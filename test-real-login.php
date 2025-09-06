<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Auth.php';

echo "<h1>Real Login Test</h1>\n";
echo "<style>body { font-family: Arial, sans-serif; margin: 40px; } .success { color: green; } .error { color: red; } .info { color: blue; }</style>\n";

// Test with real credentials
$customerId = 'CWKI2';
$userId = 'tlyle';
$password = 'Tls0901';

echo "<h2>Testing Login with Real Credentials</h2>\n";
echo "<p class='info'>Customer ID: " . htmlspecialchars($customerId) . "</p>\n";
echo "<p class='info'>User ID: " . htmlspecialchars($userId) . "</p>\n";
echo "<p class='info'>Password: [PROVIDED]</p>\n";

$auth = new Auth(
    Config::get('DB_SERVER'),
    Config::get('DB_USERNAME'),
    Config::get('DB_PASSWORD')
);

$result = $auth->login($customerId, $userId, $password);

echo "<h3>Login Result:</h3>\n";

if ($result['success']) {
    echo "<p class='success'>✅ LOGIN SUCCESSFUL!</p>\n";
    echo "<p class='info'>Message: " . htmlspecialchars($result['message']) . "</p>\n";
    echo "<p class='info'>User ID: " . htmlspecialchars($result['user_id']) . "</p>\n";
    echo "<p class='info'>Customer: " . htmlspecialchars($result['customer']) . "</p>\n";
    
    echo "<h3>User Menus:</h3>\n";
    if (!empty($result['menus'])) {
        echo "<ul>\n";
        foreach ($result['menus'] as $menu) {
            echo "<li>" . htmlspecialchars($menu) . "</li>\n";
        }
        echo "</ul>\n";
    } else {
        echo "<p class='info'>No menus returned</p>\n";
    }
    
    // Test getting current user
    echo "<h3>Current User Session:</h3>\n";
    $currentUser = $auth->getCurrentUser();
    if ($currentUser) {
        echo "<p class='success'>✅ Session active</p>\n";
        echo "<p class='info'>Session User ID: " . htmlspecialchars($currentUser['user_id']) . "</p>\n";
        echo "<p class='info'>Session Customer: " . htmlspecialchars($currentUser['customer_db']) . "</p>\n";
        echo "<p class='info'>Login Time: " . date('Y-m-d H:i:s', $currentUser['login_time']) . "</p>\n";
        echo "<p class='info'>Menu Count: " . count($currentUser['menus']) . "</p>\n";
    } else {
        echo "<p class='error'>❌ No active session found</p>\n";
    }
    
} else {
    echo "<p class='error'>❌ LOGIN FAILED</p>\n";
    echo "<p class='error'>Message: " . htmlspecialchars($result['message']) . "</p>\n";
}

echo "<hr>\n";
echo "<p><strong>Next Steps:</strong></p>\n";
if ($result['success']) {
    echo "<p>✅ Login successful! You can now test:</p>\n";
    echo "<ul>\n";
    echo "<li><a href='/tls/dashboard.php'>Dashboard with real user session</a></li>\n";
    echo "<li><a href='/tls/logout.php'>Logout functionality</a></li>\n";
    echo "</ul>\n";
} else {
    echo "<p>❌ Check credentials or database connectivity</p>\n";
}

echo "<hr>\n";
echo "<p><a href='/tls/'>← Back to TLS Application</a></p>\n";
?>