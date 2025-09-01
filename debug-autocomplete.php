<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/classes/Database.php';

$auth = new Auth(
    Config::get('DB_SERVER'),
    Config::get('DB_USERNAME'),
    Config::get('DB_PASSWORD')
);

$auth->requireAuth('/tls/login.php');
$user = $auth->getCurrentUser();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Autocomplete Debug Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Autocomplete Debug Test</h2>
        <p><strong>User:</strong> <?php echo $user['user_id']; ?></p>
        <p><strong>Database:</strong> <?php echo $user['customer_db']; ?></p>
        
        <div class="row">
            <div class="col-md-6">
                <h4>1. Basic Input Test</h4>
                <input type="text" id="test-input" class="form-control" placeholder="Type 'walmart' here">
                <div id="test-output" class="mt-2"></div>
            </div>
            <div class="col-md-6">
                <h4>2. API Test</h4>
                <button id="api-test" class="btn btn-primary">Test API Directly</button>
                <div id="api-output" class="mt-2"></div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <h4>3. Database Test</h4>
                <?php
                try {
                    $db = new Database(
                        Config::get('DB_SERVER'),
                        Config::get('DB_USERNAME'),
                        Config::get('DB_PASSWORD')
                    );
                    
                    echo "<p><strong>Connecting to database:</strong> {$user['customer_db']}</p>";
                    $db->connect($user['customer_db']);
                    echo "<div class='alert alert-success'>Database Connected Successfully!</div>";
                    
                    // Test if we can use stored procedures instead
                    echo "<p><strong>Testing stored procedures...</strong></p>";
                    try {
                        $spResults = $db->executeStoredProcedureWithReturn('spUser_Login', [$user['user_id'], 'testpass']);
                        echo "<div class='alert alert-info'>Stored procedure test completed (return code: $spResults)</div>";
                    } catch (Exception $spE) {
                        echo "<div class='alert alert-warning'>Stored procedure error: " . $spE->getMessage() . "</div>";
                    }
                    
                    // Test simple query
                    echo "<p><strong>Testing simple query...</strong></p>";
                    try {
                        $sql = "SELECT COUNT(*) as total FROM tCustomer";
                        $countResults = $db->query($sql, []);
                        echo "<div class='alert alert-success'>Customer count query successful: " . ($countResults[0]['total'] ?? 'N/A') . " total customers</div>";
                        
                        // Test Arconic specifically
                        echo "<p><strong>Testing Arconic search...</strong></p>";
                        $arconicSql = "SELECT TOP 5 CustomerKey, CompanyName, EndDate FROM tCustomer WHERE CompanyName LIKE 'Arconic%'";
                        $arconicResults = $db->query($arconicSql, []);
                        
                        if (!empty($arconicResults)) {
                            echo "<div class='alert alert-success'>Found " . count($arconicResults) . " Arconic customers:</div><ul>";
                            foreach($arconicResults as $row) {
                                $endDateDisplay = $row['EndDate'] ? $row['EndDate'] : 'NULL';
                                echo "<li>{$row['CustomerKey']}: {$row['CompanyName']} - EndDate: {$endDateDisplay}</li>";
                            }
                            echo "</ul>";
                        } else {
                            echo "<div class='alert alert-warning'>No Arconic customers found</div>";
                        }
                        
                        // Test our API filtering logic
                        echo "<p><strong>Testing API filtering logic...</strong></p>";
                        $activeSql = "SELECT COUNT(*) as total FROM tCustomer WHERE (EndDate = '1899-12-30' OR EndDate IS NULL)";
                        $activeResults = $db->query($activeSql, []);
                        echo "<div class='alert alert-info'>Active customers (EndDate = 1899-12-30 or NULL): " . ($activeResults[0]['total'] ?? 'N/A') . "</div>";
                        
                        $arconicActiveSql = "SELECT COUNT(*) as total FROM tCustomer WHERE CompanyName LIKE 'Arconic%' AND (EndDate = '1899-12-30' OR EndDate IS NULL)";
                        $arconicActiveResults = $db->query($arconicActiveSql, []);
                        echo "<div class='alert alert-info'>Active Arconic customers: " . ($arconicActiveResults[0]['total'] ?? 'N/A') . "</div>";
                        
                        // Test address data with QK filtering
                        echo "<p><strong>Testing address data with NameQual='QK'...</strong></p>";
                        $addressQkCountSql = "SELECT COUNT(*) as total FROM tNameAddress WHERE NameQual = 'QK'";
                        $addressQkResults = $db->query($addressQkCountSql, []);
                        echo "<div class='alert alert-info'>Total addresses with NameQual='QK': " . ($addressQkResults[0]['total'] ?? 'N/A') . "</div>";
                        
                        // Show sample QK addresses
                        $sampleQkSql = "SELECT TOP 5 NameKey, QuickKey, Name1, City, State FROM tNameAddress WHERE NameQual = 'QK' ORDER BY QuickKey";
                        $sampleQkResults = $db->query($sampleQkSql, []);
                        
                        if (!empty($sampleQkResults)) {
                            echo "<div class='alert alert-success'>Sample QK addresses:</div><ul>";
                            foreach($sampleQkResults as $row) {
                                echo "<li>QK: {$row['QuickKey']} - {$row['Name1']} - {$row['City']}, {$row['State']} (NameKey: {$row['NameKey']})</li>";
                            }
                            echo "</ul>";
                        } else {
                            echo "<div class='alert alert-warning'>No QK addresses found</div>";
                        }
                        
                        // If count works, try the full query
                        if (!empty($countResults)) {
                            $sql = "SELECT TOP 5 CustomerKey, CompanyName FROM tCustomer ORDER BY CompanyName";
                            $results = $db->query($sql, []);
                            
                            echo "<p><strong>Sample Customers:</strong></p><ul>";
                            foreach($results as $row) {
                                echo "<li>{$row['CustomerKey']}: {$row['CompanyName']}</li>";
                            }
                            echo "</ul>";
                        }
                    } catch (Exception $queryE) {
                        echo "<div class='alert alert-danger'>Query Error: " . $queryE->getMessage() . "</div>";
                        echo "<div class='alert alert-info'>Detailed error: " . get_class($queryE) . "</div>";
                        
                        // Try getting more details
                        if ($queryE->getPrevious()) {
                            echo "<div class='alert alert-info'>Previous error: " . $queryE->getPrevious()->getMessage() . "</div>";
                        }
                    }
                    
                } catch (Exception $e) {
                    echo "<div class='alert alert-danger'>Connection Error: " . $e->getMessage() . "</div>";
                    echo "<div class='alert alert-info'>Error type: " . get_class($e) . "</div>";
                }
                ?>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <h4>Console Output:</h4>
                <div id="console" style="background: #000; color: #0f0; padding: 10px; font-family: monospace; height: 200px; overflow-y: scroll;"></div>
            </div>
        </div>
    </div>

    <script>
        // Override console.log to also display in our debug area
        const originalConsoleLog = console.log;
        const consoleDiv = document.getElementById('console');
        
        function addToConsole(message) {
            consoleDiv.innerHTML += new Date().toLocaleTimeString() + ': ' + message + '<br>';
            consoleDiv.scrollTop = consoleDiv.scrollHeight;
        }
        
        console.log = function(...args) {
            originalConsoleLog.apply(console, args);
            addToConsole(args.join(' '));
        };
        
        console.log('Debug page loaded');
        
        // Test 1: Basic input detection
        document.getElementById('test-input').addEventListener('input', function(e) {
            console.log('Input detected: "' + e.target.value + '"');
            document.getElementById('test-output').innerHTML = 'You typed: ' + e.target.value;
            
            if (e.target.value.length >= 2) {
                console.log('Length >= 2, would trigger search now');
            }
        });
        
        // Test 2: Direct API test
        document.getElementById('api-test').addEventListener('click', function() {
            console.log('Testing API directly...');
            
            // Test addresses (origin/destination) with QK filtering
            fetch('/tls/api/lookup.php?type=addresses&q=test&limit=10')
                .then(response => {
                    console.log('Address API Response status:', response.status);
                    return response.text();
                })
                .then(data => {
                    console.log('Address API Response:', data);
                    document.getElementById('api-output').innerHTML = '<h5>Address Lookup Results:</h5><pre>' + data + '</pre>';
                    
                    // Also test customers
                    return fetch('/tls/api/lookup.php?type=customers&q=Arconic');
                })
                .then(response => {
                    console.log('Customer API Response status:', response.status);
                    return response.text();
                })
                .then(data => {
                    console.log('Customer API Response:', data);
                    document.getElementById('api-output').innerHTML += '<h5>Customer Lookup Results:</h5><pre>' + data + '</pre>';
                })
                .catch(error => {
                    console.log('API Error:', error);
                    document.getElementById('api-output').innerHTML = '<div class="alert alert-danger">API Error: ' + error + '</div>';
                });
        });
        
        console.log('Event listeners attached');
    </script>
</body>
</html>