<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Database.php';

header('Content-Type: application/json');

$auth = new Auth(
    Config::get('DB_SERVER'),
    Config::get('DB_USERNAME'),
    Config::get('DB_PASSWORD')
);

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = $auth->getCurrentUser();
$db = new Database(
    Config::get('DB_SERVER'),
    Config::get('DB_USERNAME'),
    Config::get('DB_PASSWORD')
);
$db->connect($user['customer_db']);

$type = $_GET['type'] ?? '';
$query = $_GET['q'] ?? '';
$limit = min((int)($_GET['limit'] ?? 10), 50);

try {
    switch ($type) {
        case 'addresses':
            $results = lookupAddresses($db, $query, $limit);
            break;
        case 'customers':
            $results = lookupCustomers($db, $query, $limit);
            break;
        case 'commodities':
            $results = lookupCommodities($db, $query, $limit);
            break;
        case 'customer_locations':
            $customerId = (int)($_GET['customer_id'] ?? 0);
            $results = lookupCustomerLocations($db, $customerId, $query, $limit);
            break;
        case 'drivers':
            $includeInactive = isset($_GET['include_inactive']) && $_GET['include_inactive'] === 'true';
            $results = lookupDrivers($db, $query, $limit, $includeInactive);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid lookup type']);
            exit;
    }
    
    echo json_encode($results);
} catch (Exception $e) {
    error_log('Lookup API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function lookupAddresses(Database $db, string $query, int $limit): array
{
    // Filter for NameQual='QK' addresses only and make QuickKey searchable
    $sql = "SELECT TOP $limit 
                NameKey, 
                QuickKey,
                LTRIM(RTRIM(ISNULL(Name1, ''))) as Name1, 
                LTRIM(RTRIM(ISNULL(Name2, ''))) as Name2,
                LTRIM(RTRIM(ISNULL(Address1, ''))) as Addr1,
                LTRIM(RTRIM(ISNULL(Address2, ''))) as Addr2,
                LTRIM(RTRIM(ISNULL(City, ''))) + ', ' + ISNULL(State, '') + ' ' + ISNULL(Zip, '') as CityStateZip,
                LTRIM(RTRIM(ISNULL(City, ''))) as City,
                LTRIM(RTRIM(ISNULL(State, ''))) as State,
                LTRIM(RTRIM(ISNULL(Zip, ''))) as Zip,
                Phone,
                '' as Contact
            FROM tNameAddress 
            WHERE NameQual = 'QK'
                AND (LTRIM(RTRIM(Name1)) LIKE ? 
                    OR LTRIM(RTRIM(City)) LIKE ? 
                    OR LTRIM(RTRIM(State)) LIKE ?
                    OR LTRIM(RTRIM(Zip)) LIKE ?
                    OR QuickKey LIKE ?)
            ORDER BY QuickKey, LTRIM(RTRIM(Name1)), LTRIM(RTRIM(City))";
    
    $searchTerm = '%' . $query . '%';
    $results = $db->query($sql, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    
    return array_map(function($row) {
        $name1 = trim($row['Name1']);
        $name2 = trim($row['Name2']);
        $fullName = $name1 . ($name2 ? ', ' . $name2 : '');
        
        return [
            'id' => $row['NameKey'],
            'quick_key' => $row['QuickKey'],
            'label' => $row['QuickKey'] . ' - ' . $fullName . ' - ' . $row['CityStateZip'],
            'company1' => $name1,
            'company2' => $name2,
            'addr1' => trim($row['Addr1']),
            'addr2' => trim($row['Addr2']),
            'city' => trim($row['City']),
            'state' => trim($row['State']),
            'zip' => trim($row['Zip']),
            'phone' => $row['Phone'],
            'contact' => $row['Contact']
        ];
    }, $results);
}

function lookupCustomers(Database $db, string $query, int $limit): array
{
    // First, let's check if we have any customers at all
    try {
        $totalCustomers = $db->query("SELECT COUNT(*) as total FROM tCustomer WHERE EndDate = '1899-12-30 00:00:00.000' OR EndDate IS NULL", []);
        error_log("Total active customers in database: " . ($totalCustomers[0]['total'] ?? 0));
    } catch (Exception $e) {
        error_log("Error counting customers: " . $e->getMessage());
    }
    
    // Filter for active customers: EndDate = '1899-12-30' means no end date (active)
    // Any other date means customer was made inactive on that date
    $sql = "SELECT TOP $limit 
                CustomerKey,
                CompanyName,
                Terms1 as Terms,
                CreditLimit,
                ARBal,
                EndDate,
                FreightCoordinator,
                CustomerType,
                Source,
                SalesMgr
            FROM tCustomer 
            WHERE (CompanyName LIKE ? 
                OR CustomerKey = TRY_CAST(? AS INT))
                AND (EndDate = '1899-12-30 00:00:00.000' OR EndDate IS NULL)
            ORDER BY CompanyName";
    
    $searchTerm = '%' . $query . '%';
    error_log("Customer search query: $sql with params: searchTerm=$searchTerm, query=$query");
    $results = $db->query($sql, [$searchTerm, $query]);
    
    return array_map(function($row) {
        return [
            'id' => $row['CustomerKey'],
            'label' => $row['CompanyName'] . ' (' . $row['CustomerKey'] . ')',
            'company_name' => $row['CompanyName'],
            'terms' => $row['Terms'],
            'credit_limit' => $row['CreditLimit'],
            'ar_balance' => $row['ARBal'],
            'freight_coordinator' => $row['FreightCoordinator'],
            'customer_type' => $row['CustomerType'],
            'source' => $row['Source'],
            'sales_mgr' => $row['SalesMgr'],
            'end_date' => $row['EndDate']
        ];
    }, $results);
}

function lookupCommodities(Database $db, string $query, int $limit): array
{
    $sql = "SELECT DISTINCT TOP $limit 
                Commodity
            FROM tLoadHeader 
            WHERE Commodity LIKE ?
                AND Commodity IS NOT NULL
                AND Commodity != ''
            ORDER BY Commodity";
    
    $searchTerm = '%' . $query . '%';
    $results = $db->query($sql, [$searchTerm]);
    
    return array_map(function($row) {
        return [
            'id' => $row['Commodity'],
            'label' => $row['Commodity']
        ];
    }, $results);
}

function lookupCustomerLocations(Database $db, int $customerId, string $query, int $limit): array
{
    // tCustomerLocation gets address info from tNameAddress via NameKey
    if ($query === '*') {
        // Show all locations for this customer (no search filter)
        $sql = "SELECT TOP $limit 
                    cl.LocationCode,
                    na.Name1 as LocationName,
                    na.Address1 as Addr1,
                    na.Address2 as Addr2,
                    LTRIM(RTRIM(ISNULL(na.City, ''))) + ', ' + ISNULL(na.State, '') + ' ' + ISNULL(na.Zip, '') as CityStateZip,
                    LTRIM(RTRIM(ISNULL(na.City, ''))) as City,
                    na.State,
                    na.Zip,
                    na.Phone,
                    '' as Contact
                FROM tCustomerLocation cl
                LEFT JOIN tNameAddress na ON cl.NameKey = na.NameKey
                WHERE cl.CustomerKey = ?
                ORDER BY cl.LocationCode, na.Name1";
        
        $results = $db->query($sql, [$customerId]);
    } else {
        // Normal search with query
        $sql = "SELECT TOP $limit 
                    cl.LocationCode,
                    na.Name1 as LocationName,
                    na.Address1 as Addr1,
                    na.Address2 as Addr2,
                    LTRIM(RTRIM(ISNULL(na.City, ''))) + ', ' + ISNULL(na.State, '') + ' ' + ISNULL(na.Zip, '') as CityStateZip,
                    LTRIM(RTRIM(ISNULL(na.City, ''))) as City,
                    na.State,
                    na.Zip,
                    na.Phone,
                    '' as Contact
                FROM tCustomerLocation cl
                LEFT JOIN tNameAddress na ON cl.NameKey = na.NameKey
                WHERE cl.CustomerKey = ?
                    AND (na.Name1 LIKE ? 
                        OR cl.LocationCode = TRY_CAST(? AS INT)
                        OR LTRIM(RTRIM(na.City)) LIKE ?)
                ORDER BY cl.LocationCode, na.Name1";
        
        $searchTerm = '%' . $query . '%';
        $results = $db->query($sql, [$customerId, $searchTerm, $query, $searchTerm]);
    }
    
    return array_map(function($row) {
        $formattedLocationCode = str_pad($row['LocationCode'], 2, '0', STR_PAD_LEFT);
        return [
            'id' => $row['LocationCode'],
            'label' => $row['LocationName'] . ' (' . $formattedLocationCode . ') - ' . $row['CityStateZip'],
            'location_name' => $row['LocationName'],
            'location_code_formatted' => $formattedLocationCode,
            'address' => $row['Addr1'],
            'address2' => $row['Addr2'],
            'city_state_zip' => $row['CityStateZip'],
            'city' => trim($row['City']),
            'state' => $row['State'],
            'zip' => $row['Zip'],
            'phone' => $row['Phone'],
            'contact' => $row['Contact']
        ];
    }, $results);
}

function lookupDrivers(Database $db, string $query, int $limit, bool $includeInactive = false): array
{
    // Debug: Check driver counts based on EndDate only
    try {
        $totalDrivers = $db->query("SELECT COUNT(*) as total FROM tDriver", []);
        error_log("Total drivers in database: " . ($totalDrivers[0]['total'] ?? 0));
        
        $activeDrivers = $db->query("SELECT COUNT(*) as total FROM tDriver WHERE EndDate = '1899-12-30 00:00:00.000'", []);
        error_log("Active drivers (no end date): " . ($activeDrivers[0]['total'] ?? 0));
        
        $inactiveDrivers = $db->query("SELECT COUNT(*) as total FROM tDriver WHERE EndDate != '1899-12-30 00:00:00.000'", []);
        error_log("Inactive drivers (with end date): " . ($inactiveDrivers[0]['total'] ?? 0));
        
    } catch (Exception $e) {
        error_log("Error counting drivers: " . $e->getMessage());
    }
    
    // Build WHERE clause based on active/inactive toggle
    $dateFilter = $includeInactive ? 
        "" : // No filter - include all drivers
        "AND EndDate = '1899-12-30 00:00:00.000'"; // Only active drivers (no end date)
    
    // Check if query is purely numeric (DriverKey search)
    $isNumeric = ctype_digit($query);
    
    if ($isNumeric) {
        // Exact DriverKey search
        $sql = "SELECT TOP $limit 
                    DriverKey,
                    DriverID,
                    FirstName,
                    MiddleName,
                    LastName,
                    Email,
                    Active,
                    DriverType,
                    EndDate,
                    PhysicalExpires,
                    LicenseExpires,
                    ISNULL(FirstName, '') + ' ' + ISNULL(MiddleName + ' ', '') + ISNULL(LastName, '') as FullName
                FROM tDriver 
                WHERE DriverKey = ?
                    $dateFilter
                ORDER BY LastName, FirstName";
        
        $searchParam = [(int)$query];
        error_log("Driver exact search query: $sql with DriverKey: $query, includeInactive: " . ($includeInactive ? 'true' : 'false'));
        error_log("Searching for exact DriverKey match: " . $query);
    } else {
        // Text search (names only - NO DriverID for PII protection)
        $sql = "SELECT TOP $limit 
                    DriverKey,
                    DriverID,
                    FirstName,
                    MiddleName,
                    LastName,
                    Email,
                    Active,
                    DriverType,
                    EndDate,
                    PhysicalExpires,
                    LicenseExpires,
                    ISNULL(FirstName, '') + ' ' + ISNULL(MiddleName + ' ', '') + ISNULL(LastName, '') as FullName
                FROM tDriver 
                WHERE (FirstName LIKE ? 
                    OR LastName LIKE ?
                    OR (FirstName + ' ' + LastName) LIKE ?)
                    $dateFilter
                ORDER BY LastName, FirstName";
        
        $searchTerm = '%' . $query . '%';
        $searchParam = [$searchTerm, $searchTerm, $searchTerm];
        error_log("Driver text search query: $sql with searchTerm: $searchTerm, includeInactive: " . ($includeInactive ? 'true' : 'false'));
    }
    
    $results = $db->query($sql, $searchParam);
    error_log("Driver search returned " . count($results) . " results");
    
    return array_map(function($row) {
        $displayName = trim($row['FirstName'] . ' ' . ($row['MiddleName'] ? $row['MiddleName'] . ' ' : '') . $row['LastName']);
        // Determine if driver is active based on EndDate
        $isActive = $row['EndDate'] === '1899-12-30 00:00:00.000';
        $statusLabel = $isActive ? '' : ' (INACTIVE)';
        
        // Debug: log each DriverKey being returned
        error_log("API returning DriverKey: " . $row['DriverKey'] . " for driver: " . $displayName);
        
        return [
            'id' => $row['DriverKey'],
            'label' => $displayName . ' (' . $row['DriverKey'] . ')' . $statusLabel,
            'driver_id' => $row['DriverID'],
            'first_name' => $row['FirstName'],
            'middle_name' => $row['MiddleName'],
            'last_name' => $row['LastName'],
            'full_name' => $displayName,
            'email' => $row['Email'],
            'active' => $row['Active'],
            'driver_type' => $row['DriverType'],
            'end_date' => $row['EndDate'],
            'physical_expires' => $row['PhysicalExpires'],
            'license_expires' => $row['LicenseExpires']
        ];
    }, $results);
}
?>