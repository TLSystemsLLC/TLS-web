<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/MenuManager.php';

// Initialize authentication
$auth = new Auth(
    Config::get('DB_SERVER'),
    Config::get('DB_USERNAME'), 
    Config::get('DB_PASSWORD')
);

// Require authentication
$auth->requireAuth('/tls/login.php');

// Check menu permissions
if (!$auth->hasMenuAccess('mnuDriverMaint')) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

// Initialize variables
$driver = null;
$message = '';
$messageType = 'info';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = new Database(Config::get('DB_SERVER'), Config::get('DB_USERNAME'), Config::get('DB_PASSWORD'));
        $db->connect($auth->getCurrentUser()['customer_db']);
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'search':
                    $driverKey = intval($_POST['driver_key'] ?? 0);
                    error_log("Driver search - received driver_key: " . print_r($_POST['driver_key'] ?? 'NULL', true) . " (converted to int: $driverKey)");
                    if ($driverKey > 0) {
                        error_log("About to call spDriver_Get with DriverKey: $driverKey");
                        $result = $db->executeStoredProcedure('spDriver_Get', [$driverKey]);
                        error_log("spDriver_Get returned: " . print_r($result, true));
                        
                        if (is_array($result) && !empty($result) && is_array($result[0])) {
                            $driver = $result[0];
                            // Add the DriverKey back to the driver data since spDriver_Get doesn't return it
                            $driver['DriverKey'] = $driverKey;
                            error_log("Successfully loaded driver: " . ($driver['FirstName'] ?? 'Unknown') . ' ' . ($driver['LastName'] ?? 'Unknown'));
                        } else {
                            $message = 'Driver not found.';
                            $messageType = 'warning';
                            error_log("Driver search failed - result: " . print_r($result, true));
                        }
                    } else {
                        $message = 'Invalid driver key.';
                        $messageType = 'warning';
                    }
                    break;
                    
                case 'save':
                    // Validate required fields
                    $errors = [];
                    // Only require driver_id if we're editing an existing driver (has driver_key)
                    if (intval($_POST['driver_key'] ?? 0) > 0 && empty($_POST['driver_id'])) {
                        $errors[] = 'Driver ID is required for existing drivers';
                    }
                    if (empty($_POST['first_name'])) $errors[] = 'First name is required';
                    if (empty($_POST['last_name'])) $errors[] = 'Last name is required';
                    
                    if (empty($errors)) {
                        $params = [
                            intval($_POST['driver_key'] ?? 0),
                            $_POST['driver_id'] ?? '',
                            $_POST['first_name'] ?? '',
                            $_POST['middle_name'] ?? '',
                            $_POST['last_name'] ?? '',
                            $_POST['birth_date'] ?? null,
                            $_POST['license_number'] ?? '',
                            $_POST['license_state'] ?? '',
                            $_POST['license_expires'] ?? null,
                            $_POST['physical_date'] ?? null,
                            $_POST['physical_expires'] ?? null,
                            $_POST['start_date'] ?? null,
                            $_POST['end_date'] ?? null,
                            isset($_POST['active']) ? 1 : 0,
                            $_POST['favorite_route'] ?? '',
                            $_POST['email'] ?? '',
                            isset($_POST['twic']) ? 1 : 0,
                            isset($_POST['coil_cert']) ? 1 : 0,
                            intval($_POST['company_id'] ?? 3),
                            $_POST['arcnc'] ?? null,  // FIXED: DATETIME field, not boolean
                            $_POST['txcnc'] ?? null,  // FIXED: DATETIME field, not boolean
                            isset($_POST['company_driver']) ? 1 : 0,
                            isset($_POST['eobr']) ? 1 : 0,
                            $_POST['eobr_start'] ?? null,
                            $_POST['driver_spec'] ?? 'OTH',  // FIXED: Default value and max 3 chars
                            isset($_POST['medical_verification']) ? 1 : 0,
                            $_POST['mvr_due'] ?? null,
                            floatval($_POST['company_loaded_pay'] ?? 0.00),
                            floatval($_POST['company_empty_pay'] ?? 0.00),
                            $_POST['pay_type'] ?? 'P',  // FIXED: Default value
                            floatval($_POST['company_tarp_pay'] ?? 0.00),
                            floatval($_POST['company_stop_pay'] ?? 0.00)
                            // REMOVED: Contact information fields - they belong in tNameAddress table
                        ];
                        
                        $result = $db->executeStoredProcedure('spDriver_Save', $params);
                        $message = 'Driver saved successfully.';
                        $messageType = 'success';
                        
                        // Reload driver data
                        if (intval($_POST['driver_key'] ?? 0) > 0) {
                            $reloadDriverKey = intval($_POST['driver_key']);
                            $driverResult = $db->executeStoredProcedure('spDriver_Get', [$reloadDriverKey]);
                            if (is_array($driverResult) && !empty($driverResult)) {
                                $driver = $driverResult[0];
                                // Add the DriverKey back to the driver data since spDriver_Get doesn't return it
                                $driver['DriverKey'] = $reloadDriverKey;
                            }
                        }
                    } else {
                        $message = 'Please correct the following errors: ' . implode(', ', $errors);
                        $messageType = 'danger';
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $message = 'An error occurred: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Load validation options for DriverSpec dropdown with database-specific caching
$driverSpecOptions = [];
if ($driver) { // Only load if we have a driver loaded
    try {
        $customerDb = $auth->getCurrentUser()['customer_db'];
        $cacheFile = '/tmp/validation_cache_' . $customerDb . '.json';
        $cacheExpiry = 3600; // 1 hour cache
        
        // Check if cache exists and is still valid
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheExpiry) {
            // Load from cache
            $allValidation = json_decode(file_get_contents($cacheFile), true);
        } else {
            // Load from database and cache it
            $db = new Database(Config::get('DB_SERVER'), Config::get('DB_USERNAME'), Config::get('DB_PASSWORD'));
            $db->connect($customerDb);
            
            $allValidation = $db->executeStoredProcedure('spGetValidationTable', []);
            if ($allValidation && is_array($allValidation)) {
                // Cache the data with database-specific filename
                file_put_contents($cacheFile, json_encode($allValidation));
            }
        }
        
        if ($allValidation && is_array($allValidation)) {
            // Filter for DriverSpec entries
            $driverSpecOptions = array_filter($allValidation, function($item) {
                return isset($item['ColumnName']) && $item['ColumnName'] === 'DriverSpec';
            });
        }
    } catch (Exception $e) {
        error_log('Error loading DriverSpec validation options: ' . $e->getMessage());
    }
}

// Initialize menu manager
$menuManager = new MenuManager($auth);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Maintenance - TLS Operations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/tls/css/app.css">
    <script src="/tls/js/tls-form-tracker.js"></script>
    <style>
        .required {
            color: red;
        }
        /* Legacy compatibility */
        .readonly-field {
            background-color: #f8f9fa;
        }
        /* Address display styling */
        .address-display {
            background-color: #f8f9fa;
            border-radius: 4px;
            padding: 15px;
            min-height: 60px;
        }
        .address-info .row {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <?php echo $menuManager->generateMainMenu(); ?>
    
    <div class="container-fluid mt-3">
        <div class="row">
            <div class="col-12">
                <!-- Standardized Page Header -->
                <div class="tls-page-header">
                    <h2 class="tls-page-title"><i class="bi-person-badge me-2"></i>Driver Maintenance</h2>
                    <div class="tls-top-actions">
                        <?php if ($driver): ?>
                        <!-- Form Action Buttons (shown when driver is loaded) -->
                        <button type="button" class="btn tls-btn-primary" id="tls-save-btn" disabled>
                            <i class="bi-check-circle me-1"></i> Save Driver
                        </button>
                        <button type="button" class="btn tls-btn-secondary" id="tls-cancel-btn">
                            <i class="bi-x-circle me-1"></i> Cancel
                        </button>
                        <?php else: ?>
                        <!-- New Driver Button (shown when no driver is loaded) -->
                        <button type="button" class="btn tls-btn-primary" onclick="newDriver()">
                            <i class="bi-plus me-1"></i> New Driver
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Save Indicator Banner -->
                <div class="tls-save-indicator" id="tls-save-indicator" style="display: none;">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    You have <span id="tls-change-counter-text">0</span> unsaved changes. Remember to save before navigating away.
                </div>
                
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- Driver Search Section -->
                <div class="tls-form-card">
                    <div class="card-header">
                        <i class="bi-search"></i>Driver Search
                    </div>
                    <div class="card-body">
                        <form method="POST" id="searchForm">
                            <input type="hidden" name="action" value="search">
                            <!-- Row 1: Label and empty space for button -->
                            <div class="row">
                                <div class="col-md-8">
                                    <label for="driver_search" class="form-label">Search Driver:</label>
                                </div>
                                <div class="col-md-4">
                                    <!-- Empty space above button -->
                                </div>
                            </div>
                            
                            <!-- Row 2: Search input and Load Driver button -->
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="position-relative">
                                        <input type="text" class="form-control" id="driver_search" 
                                               placeholder="Type driver name or DriverKey...">
                                        <div id="search-spinner" class="position-absolute top-50 end-0 translate-middle-y me-3" style="display: none;">
                                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                                <span class="visually-hidden">Searching...</span>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="hidden" id="driver_key" name="driver_key">
                                </div>
                                <div class="col-md-4 d-flex align-items-start">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi-search"></i> Load Driver
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Row 3: Status text with checkbox on same row -->
                            <div class="row mt-1">
                                <div class="col-md-8">
                                    <div class="d-flex justify-content-between">
                                        <div id="search-status" class="form-text text-muted">Type driver name or enter DriverKey directly</div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="include_inactive" name="include_inactive">
                                            <label class="form-check-label form-text text-muted" for="include_inactive">
                                                Include Inactive Drivers
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <!-- Empty space to match button column -->
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php if ($driver): ?>
                
                <!-- Driver Form Layout -->
                <form method="POST" id="driverForm">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="driver_key" value="<?php echo htmlspecialchars((string)($driver['DriverKey'] ?? '')); ?>">
                    
                    <div class="mt-4">
                        <div class="row">
                            <!-- Left Column - Basic Info & Employment -->
                            <div class="col-lg-6">
                                <!-- Basic Information -->
                                <div class="tls-form-card">
                                    <div class="card-header">
                                        <i class="bi-person"></i>Basic Information
                                    </div>
                                    <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <label for="driver_key_display" class="form-label">Driver Key:</label>
                                                    <input type="text" class="form-control readonly-field" id="driver_key_display" 
                                                           value="<?php echo htmlspecialchars((string)($driver['DriverKey'] ?? '')); ?>" 
                                                           readonly>
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="first_name" class="form-label">First Name <span class="required">*</span>:</label>
                                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                                           value="<?php echo htmlspecialchars($driver['FirstName'] ?? ''); ?>" 
                                                           maxlength="15" required>
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="middle_name" class="form-label">Middle Name:</label>
                                                    <input type="text" class="form-control" id="middle_name" name="middle_name" 
                                                           value="<?php echo htmlspecialchars($driver['MiddleName'] ?? ''); ?>" 
                                                           maxlength="15">
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="last_name" class="form-label">Last Name <span class="required">*</span>:</label>
                                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                                           value="<?php echo htmlspecialchars($driver['LastName'] ?? ''); ?>" 
                                                           maxlength="15" required>
                                                </div>
                                            </div>
                                            <div class="row mt-3">
                                                <div class="col-md-3">
                                                    <label for="birth_date" class="form-label">Birth Date:</label>
                                                    <input type="date" class="form-control" id="birth_date" name="birth_date" 
                                                           value="<?php echo $driver['BirthDate'] ? date('Y-m-d', strtotime($driver['BirthDate'])) : ''; ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="email" class="form-label">Email:</label>
                                                    <input type="email" class="form-control" id="email" name="email" 
                                                           value="<?php echo htmlspecialchars($driver['Email'] ?? ''); ?>" 
                                                           maxlength="50">
                                                </div>
                                                <div class="col-md-3 d-flex align-items-end">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="active" name="active" 
                                                               <?php echo ($driver['Active'] ?? false) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="active">Active</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Employment Details -->
                                    <div class="tls-form-card mt-3">
                                        <div class="card-header">
                                            <i class="bi-briefcase"></i>Employment Details
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <label for="start_date" class="form-label">Start Date:</label>
                                                    <input type="date" class="form-control" id="start_date" name="start_date" 
                                                           value="<?php echo $driver['StartDate'] ? date('Y-m-d', strtotime($driver['StartDate'])) : ''; ?>">
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="end_date" class="form-label">End Date:</label>
                                                    <input type="date" class="form-control" id="end_date" name="end_date" 
                                                           value="<?php echo $driver['EndDate'] ? date('Y-m-d', strtotime($driver['EndDate'])) : ''; ?>">
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="company_id" class="form-label">Company:</label>
                                                    <select class="form-select" id="company_id" name="company_id">
                                                        <option value="3" <?php echo ($driver['CompanyID'] ?? 3) == 3 ? 'selected' : ''; ?>>TLS</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-check mt-4">
                                                        <input class="form-check-input" type="checkbox" id="company_driver" name="company_driver" 
                                                               <?php echo ($driver['CompanyDriver'] ?? false) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="company_driver">Company Driver</label>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Equipment and Route Information -->
                                            <div class="row mt-3">
                                                <div class="col-md-6">
                                                    <label for="favorite_route" class="form-label">Favorite Route:</label>
                                                    <input type="text" class="form-control" id="favorite_route" name="favorite_route" 
                                                           value="<?php echo htmlspecialchars($driver['FavoriteRoute'] ?? ''); ?>" 
                                                           maxlength="50">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="driver_spec" class="form-label">Driver Specification:</label>
                                                    <select class="form-select" id="driver_spec" name="driver_spec">
                                                        <option value="">Select Driver Spec</option>
                                                        <?php if (!empty($driverSpecOptions)): ?>
                                                            <?php foreach ($driverSpecOptions as $option): ?>
                                                                <option value="<?php echo htmlspecialchars($option['Code'] ?? ''); ?>" 
                                                                        <?php echo ($driver['DriverSpec'] ?? '') === ($option['Code'] ?? '') ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars(($option['Code'] ?? '') . ' - ' . ($option['Description'] ?? '')); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>  
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                
                                <!-- License & Certification -->
                                <div class="tls-form-card mt-3">
                                    <div class="card-header">
                                        <i class="bi-card-text"></i>License & Certification
                                    </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <label for="license_number" class="form-label">License Number:</label>
                                                    <input type="text" class="form-control" id="license_number" name="license_number" 
                                                           value="<?php echo htmlspecialchars($driver['LicenseNumber'] ?? ''); ?>" 
                                                           maxlength="15">
                                                </div>
                                                <div class="col-md-2">
                                                    <label for="license_state" class="form-label">State:</label>
                                                    <input type="text" class="form-control" id="license_state" name="license_state" 
                                                           value="<?php echo htmlspecialchars($driver['LicenseState'] ?? ''); ?>" 
                                                           maxlength="2" style="text-transform: uppercase;">
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="license_expires" class="form-label">License Expires:</label>
                                                    <input type="date" class="form-control" id="license_expires" name="license_expires" 
                                                           value="<?php echo $driver['LicenseExpires'] ? date('Y-m-d', strtotime($driver['LicenseExpires'])) : ''; ?>">
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="mvr_due" class="form-label">MVR Due:</label>
                                                    <input type="date" class="form-control" id="mvr_due" name="mvr_due" 
                                                           value="<?php echo $driver['MVRDue'] ? date('Y-m-d', strtotime($driver['MVRDue'])) : ''; ?>">
                                                </div>
                                            </div>
                                            <div class="row mt-3">
                                                <div class="col-md-3">
                                                    <label for="physical_date" class="form-label">Physical Date:</label>
                                                    <input type="date" class="form-control" id="physical_date" name="physical_date" 
                                                           value="<?php echo $driver['PhysicalDate'] ? date('Y-m-d', strtotime($driver['PhysicalDate'])) : ''; ?>">
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="physical_expires" class="form-label">Physical Expires:</label>
                                                    <input type="date" class="form-control" id="physical_expires" name="physical_expires" 
                                                           value="<?php echo $driver['PhysicalExpires'] ? date('Y-m-d', strtotime($driver['PhysicalExpires'])) : ''; ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="form-check mt-4">
                                                                <input class="form-check-input" type="checkbox" id="twic" name="twic" 
                                                                       <?php echo ($driver['TWIC'] ?? false) ? 'checked' : ''; ?>>
                                                                <label class="form-check-label" for="twic">TWIC</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="form-check mt-4">
                                                                <input class="form-check-input" type="checkbox" id="coil_cert" name="coil_cert" 
                                                                       <?php echo ($driver['CoilCert'] ?? false) ? 'checked' : ''; ?>>
                                                                <label class="form-check-label" for="coil_cert">Tanker</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="form-check mt-4">
                                                                <input class="form-check-input" type="checkbox" id="medical_verification" name="medical_verification" 
                                                                       <?php echo ($driver['MedicalVerification'] ?? false) ? 'checked' : ''; ?>>
                                                                <label class="form-check-label" for="medical_verification">Medical Verified</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Compliance Information -->
                                            <div class="row mt-3">
                                                <div class="col-md-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="eobr" name="eobr" 
                                                               <?php echo ($driver['EOBR'] ?? false) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="eobr">EOBR</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="eobr_start" class="form-label">EOBR Start:</label>
                                                    <input type="date" class="form-control" id="eobr_start" name="eobr_start" 
                                                           value="<?php echo $driver['EOBRStart'] ? date('Y-m-d', strtotime($driver['EOBRStart'])) : ''; ?>">
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="arcnc" class="form-label">ARCNC Date:</label>
                                                    <input type="date" class="form-control" id="arcnc" name="arcnc" 
                                                           value="<?php echo $driver['ARCNC'] ? date('Y-m-d', strtotime($driver['ARCNC'])) : ''; ?>">
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="txcnc" class="form-label">TXCNC Date:</label>
                                                    <input type="date" class="form-control" id="txcnc" name="txcnc" 
                                                           value="<?php echo $driver['TXCNC'] ? date('Y-m-d', strtotime($driver['TXCNC'])) : ''; ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Tax ID (PII) Section - Below License -->
                                    <div class="tls-form-card tls-pii-section mt-3">
                                        <div class="card-header">
                                            <i class="bi-shield-lock"></i>Tax Information (Protected)
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-4" id="tax_id_section" style="display: none;">
                                                    <label for="driver_id" class="form-label">Tax ID <span class="required">*</span>:</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" id="driver_id" name="driver_id" 
                                                               value="<?php echo htmlspecialchars($driver['DriverID'] ?? ''); ?>" 
                                                               maxlength="9" required>
                                                        <button type="button" class="btn btn-outline-secondary" onclick="hidePII()" title="Hide Tax ID">
                                                            <i class="bi-eye-slash"></i>
                                                        </button>
                                                    </div>
                                                    <small class="text-muted">Personally Identifiable Information</small>
                                                </div>
                                                <div class="col-md-4" id="show_pii_section">
                                                    <label class="form-label">Tax ID:</label>
                                                    <div>
                                                        <button type="button" class="btn btn-warning" onclick="showPII()" title="Show Tax ID (PII)">
                                                            <i class="bi-eye"></i> Show Tax ID
                                                        </button>
                                                        <small class="d-block text-muted mt-1">Click to view protected information</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Right Column - Address & Contacts -->
                                <div class="col-lg-6">
                                    <!-- Address Information -->
                                    <div class="tls-form-card">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <span><i class="bi-house"></i> Address Information</span>
                                            <button type="button" class="btn btn-sm tls-btn-primary" onclick="editDriverAddress()" id="edit-address-btn">
                                                <i class="bi-pencil"></i> Edit Address
                                            </button>
                                        </div>
                                        <div class="card-body">
                                            <div id="address-display" class="address-display">
                                                <div class="text-muted">No address information on file</div>
                                            </div>
                                            <div id="address-form" style="display: none;">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <label for="address_name1" class="form-label">Name:</label>
                                                        <input type="text" class="form-control" id="address_name1" name="address_name1" maxlength="30">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label for="address_name2" class="form-label">Name 2:</label>
                                                        <input type="text" class="form-control" id="address_name2" name="address_name2" maxlength="30">
                                                    </div>
                                                </div>
                                                <div class="row mt-3">
                                                    <div class="col-md-6">
                                                        <label for="address_address1" class="form-label">Address:</label>
                                                        <input type="text" class="form-control" id="address_address1" name="address_address1" maxlength="30">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label for="address_address2" class="form-label">Address 2:</label>
                                                        <input type="text" class="form-control" id="address_address2" name="address_address2" maxlength="30">
                                                    </div>
                                                </div>
                                                <div class="row mt-3">
                                                    <div class="col-md-4">
                                                        <label for="address_city" class="form-label">City:</label>
                                                        <input type="text" class="form-control" id="address_city" name="address_city" maxlength="25">
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label for="address_state" class="form-label">State:</label>
                                                        <input type="text" class="form-control" id="address_state" name="address_state" maxlength="2" style="text-transform: uppercase;">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label for="address_zip" class="form-label">ZIP:</label>
                                                        <input type="text" class="form-control" id="address_zip" name="address_zip" maxlength="10">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label for="address_phone" class="form-label">Phone:</label>
                                                        <input type="tel" class="form-control" id="address_phone" name="address_phone" maxlength="20">
                                                    </div>
                                                </div>
                                                <div class="row mt-3">
                                                    <div class="col-md-12">
                                                        <button type="button" class="btn tls-btn-primary me-2" onclick="saveDriverAddress()">
                                                            <i class="bi-check"></i> Save Address
                                                        </button>
                                                        <button type="button" class="btn tls-btn-secondary" onclick="cancelAddressEdit()">
                                                            <i class="bi-x"></i> Cancel
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Contacts Card -->
                                    <div class="tls-form-card mt-3">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <span><i class="bi-telephone"></i> Contacts <span class="badge bg-secondary" id="contact-count">0</span></span>
                                            <button type="button" class="btn btn-sm tls-btn-primary" onclick="showContactModal()">
                                                <i class="bi-plus-circle"></i> Add Contact
                                            </button>
                                        </div>
                                        <div class="card-body">
                                            <div id="contacts-loading" class="text-center" style="display: none;">
                                                <i class="bi-hourglass-split"></i> Loading contacts...
                                            </div>
                                            <div id="contacts-grid">
                                                <div class="text-muted">No contacts on file</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    
                                    <!-- Pay Information -->
                                    <div class="tls-form-card mt-3">
                                        <div class="card-header">
                                            <i class="bi-cash"></i>Pay Information
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <label for="pay_type" class="form-label">Pay Type:</label>
                                                    <select class="form-select" id="pay_type" name="pay_type">
                                                        <option value="" <?php echo empty($driver['PayType']) ? 'selected' : ''; ?>>Select Pay Type</option>
                                                        <option value="M" <?php echo ($driver['PayType'] ?? '') === 'M' ? 'selected' : ''; ?>>Mileage</option>
                                                        <option value="P" <?php echo ($driver['PayType'] ?? '') === 'P' ? 'selected' : ''; ?>>Percentage</option>
                                                        <option value="S" <?php echo ($driver['PayType'] ?? '') === 'S' ? 'selected' : ''; ?>>Salary</option>
                                                        <option value="W" <?php echo ($driver['PayType'] ?? '') === 'W' ? 'selected' : ''; ?>>Weekly</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="company_loaded_pay" class="form-label">Company Loaded Pay:</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">$</span>
                                                        <input type="number" class="form-control" id="company_loaded_pay" name="company_loaded_pay" 
                                                               value="<?php echo htmlspecialchars($driver['CompanyLoadedPay'] ?? ''); ?>" 
                                                               step="0.01" min="0">
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="company_empty_pay" class="form-label">Company Empty Pay:</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">$</span>
                                                        <input type="number" class="form-control" id="company_empty_pay" name="company_empty_pay" 
                                                               value="<?php echo htmlspecialchars($driver['CompanyEmptyPay'] ?? ''); ?>" 
                                                               step="0.01" min="0">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row mt-3">
                                                <div class="col-md-3">
                                                    <label for="company_tarp_pay" class="form-label">Company Tarp Pay:</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">$</span>
                                                        <input type="number" class="form-control" id="company_tarp_pay" name="company_tarp_pay" 
                                                               value="<?php echo htmlspecialchars($driver['CompanyTarpPay'] ?? ''); ?>" 
                                                               step="0.01" min="0">
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="company_stop_pay" class="form-label">Company Stop Pay:</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">$</span>
                                                        <input type="number" class="form-control" id="company_stop_pay" name="company_stop_pay" 
                                                               value="<?php echo htmlspecialchars($driver['CompanyStopPay'] ?? ''); ?>" 
                                                               step="0.01" min="0">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    </div> <!-- End Right Column -->
                                </div> <!-- End Two-Column Row -->
                                
                                <!-- Comments Section - Full Width -->
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <div class="tls-form-card">
                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                <span><i class="bi-chat-dots"></i> Comments <span class="badge bg-secondary" id="comment-count">0</span></span>
                                                <button type="button" class="btn btn-sm tls-btn-primary" onclick="showCommentModal()">
                                                    <i class="bi-plus-circle"></i> Add Comment
                                                </button>
                                            </div>
                                            <div class="card-body">
                                                <div id="comments-loading" class="text-center" style="display: none;">
                                                    <i class="bi-hourglass-split"></i> Loading comments...
                                                </div>
                                                <div id="comments-list">
                                                    <div class="text-muted">No comments on file</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                
                <!-- Contact Modal -->
                <div class="modal fade" id="contactModal" tabindex="-1" aria-labelledby="contactModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="contactModalLabel">Add/Edit Contact</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form id="contactForm">
                                    <input type="hidden" id="contact_key" name="contact_key">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label for="contact_first_name" class="form-label">First Name:</label>
                                            <input type="text" class="form-control" id="contact_first_name" name="first_name" maxlength="30">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="contact_last_name" class="form-label">Last Name:</label>
                                            <input type="text" class="form-control" id="contact_last_name" name="last_name" maxlength="30">
                                        </div>
                                    </div>
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <label for="contact_phone" class="form-label">Phone:</label>
                                            <input type="text" class="form-control" id="contact_phone" name="phone" maxlength="20">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="contact_mobile" class="form-label">Mobile:</label>
                                            <input type="text" class="form-control" id="contact_mobile" name="mobile" maxlength="20">
                                        </div>
                                    </div>
                                    <div class="row mt-3">
                                        <div class="col-md-12">
                                            <label for="contact_email" class="form-label">Email:</label>
                                            <input type="email" class="form-control" id="contact_email" name="email" maxlength="50">
                                        </div>
                                    </div>
                                    <div class="row mt-3">
                                        <div class="col-md-8">
                                            <label for="contact_relationship" class="form-label">Relationship:</label>
                                            <input type="text" class="form-control" id="contact_relationship" name="relationship" maxlength="30">
                                        </div>
                                        <div class="col-md-4 d-flex align-items-end">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="contact_is_primary" name="is_primary">
                                                <label class="form-check-label" for="contact_is_primary">Primary Contact</label>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn tls-btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn tls-btn-primary" onclick="saveContact()">Save Contact</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Comment Modal -->
                <div class="modal fade" id="commentModal" tabindex="-1" aria-labelledby="commentModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="commentModalLabel">Add Comment</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form id="commentForm">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <label for="comment_type" class="form-label">Comment Type:</label>
                                            <select class="form-select" id="comment_type" name="comment_type">
                                                <option value="General">General</option>
                                                <option value="Safety">Safety</option>
                                                <option value="Performance">Performance</option>
                                                <option value="Incident">Incident</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row mt-3">
                                        <div class="col-md-12">
                                            <label for="comment_text" class="form-label">Comment:</label>
                                            <textarea class="form-control" id="comment_text" name="comment_text" rows="4" maxlength="500" required></textarea>
                                            <small class="text-muted">Maximum 500 characters</small>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn tls-btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn tls-btn-primary" onclick="saveComment()">Save Comment</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Autocomplete functionality
        class TLSAutocomplete {
            constructor(inputElement, hiddenElement, apiType, onSelect = null) {
                this.input = inputElement;
                this.hidden = hiddenElement;
                this.apiType = apiType;
                this.onSelect = onSelect;
                this.resultsContainer = null;
                this.currentIndex = -1;
                this.searchTimeout = null;
                
                this.init();
            }
            
            init() {
                console.log('Initializing autocomplete for:', this.input.id, 'API type:', this.apiType);
                this.createResultsContainer();
                this.input.addEventListener('input', (e) => this.handleInput(e));
                this.input.addEventListener('keydown', (e) => this.handleKeydown(e));
                this.input.addEventListener('blur', (e) => this.handleBlur(e));
                this.input.addEventListener('focus', (e) => this.handleFocus(e));
            }
            
            createResultsContainer() {
                this.resultsContainer = document.createElement('div');
                this.resultsContainer.className = 'autocomplete-results position-absolute bg-white border rounded shadow-sm w-100';
                this.resultsContainer.style.zIndex = '1050';
                this.resultsContainer.style.maxHeight = '300px';
                this.resultsContainer.style.overflowY = 'auto';
                this.resultsContainer.style.display = 'none';
                this.input.parentNode.style.position = 'relative';
                this.input.parentNode.appendChild(this.resultsContainer);
            }
            
            handleInput(e) {
                const query = e.target.value.trim();
                console.log('Input event for:', this.input.id, 'Query:', query);
                clearTimeout(this.searchTimeout);
                
                // Allow single character searches for DriverKey
                if (query.length < 1) {
                    console.log('Empty query, hiding results');
                    this.hideResults();
                    this.updateStatus('Type driver name or DriverKey');
                    return;
                }
                
                console.log('Setting timeout for search...');
                this.searchTimeout = setTimeout(() => {
                    console.log('Executing search for:', query);
                    this.search(query);
                }, 300);
            }
            
            handleKeydown(e) {
                const items = this.resultsContainer.querySelectorAll('.autocomplete-item');
                
                switch(e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        this.currentIndex = Math.min(this.currentIndex + 1, items.length - 1);
                        this.updateSelection(items);
                        break;
                    case 'ArrowUp':
                        e.preventDefault();
                        this.currentIndex = Math.max(this.currentIndex - 1, -1);
                        this.updateSelection(items);
                        break;
                    case 'Enter':
                        e.preventDefault();
                        if (this.currentIndex >= 0 && items[this.currentIndex]) {
                            this.selectItem(JSON.parse(items[this.currentIndex].dataset.item));
                        }
                        break;
                    case 'Escape':
                        this.hideResults();
                        break;
                }
            }
            
            handleBlur(e) {
                setTimeout(() => this.hideResults(), 150);
            }
            
            handleFocus(e) {
                if (e.target.value.trim().length >= 2) {
                    this.search(e.target.value.trim());
                }
            }
            
            updateSelection(items) {
                items.forEach((item, index) => {
                    item.classList.toggle('active', index === this.currentIndex);
                });
            }
            
            search(query) {
                let url = `/tls/api/lookup.php?type=${this.apiType}&q=${encodeURIComponent(query)}`;
                
                // Add include_inactive parameter if checkbox is checked
                const includeInactive = document.getElementById('include_inactive')?.checked || false;
                if (includeInactive) {
                    url += '&include_inactive=true';
                }
                
                // Show loading indicator
                this.showLoading(true);
                this.updateStatus('Searching...');
                
                console.log('Fetching:', url);
                fetch(url)
                    .then(response => {
                        console.log('Response status:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('Search results:', data);
                        this.showLoading(false);
                        if (data.length === 0) {
                            this.updateStatus('No drivers found');
                        } else {
                            this.updateStatus('Found ' + data.length + ' drivers');
                        }
                        this.displayResults(data);
                    })
                    .catch(error => {
                        console.error('Search error:', error);
                        this.showLoading(false);
                        this.updateStatus('Search error occurred');
                        this.hideResults();
                    });
            }
            
            displayResults(results) {
                this.resultsContainer.innerHTML = '';
                this.currentIndex = -1;
                
                if (results.length === 0) {
                    this.hideResults();
                    return;
                }
                
                results.forEach((item, index) => {
                    const div = document.createElement('div');
                    div.className = 'autocomplete-item p-2 border-bottom';
                    div.style.cursor = 'pointer';
                    div.innerHTML = item.label;
                    div.dataset.item = JSON.stringify(item);
                    
                    div.addEventListener('click', () => {
                        this.selectItem(item);
                    });
                    
                    div.addEventListener('mouseenter', () => {
                        this.currentIndex = index;
                        this.updateSelection(this.resultsContainer.querySelectorAll('.autocomplete-item'));
                    });
                    
                    this.resultsContainer.appendChild(div);
                });
                
                this.showResults();
            }
            
            selectItem(item) {
                console.log('Selecting driver item:', item);
                this.input.value = item.label;
                this.hidden.value = item.id;
                console.log('Set hidden field value to:', item.id);
                this.hideResults();
                this.updateStatus('Driver selected: ' + item.full_name);
                
                if (this.onSelect) {
                    this.onSelect(item);
                }
            }
            
            
            showLoading(show) {
                const spinner = document.getElementById('search-spinner');
                if (spinner) {
                    spinner.style.display = show ? 'block' : 'none';
                }
            }
            
            updateStatus(message) {
                const status = document.getElementById('search-status');
                if (status) {
                    status.textContent = message;
                }
            }

            showResults() {
                this.resultsContainer.style.display = 'block';
            }
            
            hideResults() {
                this.resultsContainer.style.display = 'none';
                this.showLoading(false);
            }
        }
        
        // Initialize autocomplete fields
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing autocomplete fields...');
            
            // Driver search autocomplete
            const driverKeyField = document.getElementById('driver_key');
            const driverSearchField = document.getElementById('driver_search');
            
            // Initialize autocomplete for name searches
            const driverAutocomplete = new TLSAutocomplete(
                driverSearchField,
                driverKeyField,
                'drivers',
                function(driver) {
                    console.log('Driver selected:', driver);
                    console.log('Driver key set to:', driverKeyField.value);
                    // Auto-submit the form when driver is selected from dropdown
                    document.getElementById('searchForm').submit();
                }
            );
            
            // Let autocomplete handle all input - both names and DriverKeys
            // The API now searches by DriverKey as well as names
            
            // Handle form submission for direct DriverKey entry
            document.getElementById('searchForm').addEventListener('submit', function(e) {
                const searchInput = document.getElementById('driver_search');
                const hiddenKeyField = document.getElementById('driver_key');
                const searchValue = searchInput.value.trim();
                
                // If user typed a number and didn't select from autocomplete, use it as DriverKey
                if (searchValue && /^\d+$/.test(searchValue) && !hiddenKeyField.value) {
                    console.log('Direct DriverKey entry detected:', searchValue);
                    hiddenKeyField.value = searchValue;
                }
                
                // Make sure we have a driver_key value before submitting
                if (!hiddenKeyField.value) {
                    e.preventDefault();
                    alert('Please enter a valid DriverKey or select a driver from the search results.');
                    return false;
                }
            });
            
            // Re-trigger search when include_inactive checkbox changes
            document.getElementById('include_inactive')?.addEventListener('change', function() {
                const searchInput = document.getElementById('driver_search');
                if (searchInput.value.trim().length >= 2) {
                    // Clear current results and re-search
                    driverAutocomplete.search(searchInput.value.trim());
                }
            });
        });
        
        function newDriver() {
            // Clear the form and show empty driver form
            document.getElementById('searchForm').reset();
            if (document.getElementById('driverForm')) {
                location.href = location.pathname;
            }
        }
        
        function resetToSearch() {
            // Return to initial state (driver search)
            location.href = location.pathname;
        }
        
        function showPII() {
            // Show the PII field and hide the show button
            document.getElementById('tax_id_section').style.display = 'block';
            document.getElementById('show_pii_section').style.display = 'none';
        }
        
        function hidePII() {
            // Hide the PII field and show the show button
            document.getElementById('tax_id_section').style.display = 'none';
            document.getElementById('show_pii_section').style.display = 'block';
        }
        
        // License state uppercase conversion
        document.getElementById('license_state')?.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
        
        // Basic form validation
        document.getElementById('driverForm')?.addEventListener('submit', function(e) {
            // Only require driver_id if it's visible (Tax ID section is shown)
            const required = ['first_name', 'last_name'];
            const taxIdSection = document.getElementById('tax_id_section');
            if (taxIdSection && taxIdSection.style.display !== 'none') {
                required.push('driver_id');
            }
            
            let valid = true;
            
            required.forEach(field => {
                const input = document.getElementById(field);
                if (!input.value.trim()) {
                    input.classList.add('is-invalid');
                    valid = false;
                } else {
                    input.classList.remove('is-invalid');
                }
            });
            
            if (!valid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
        
        // Add CSS for autocomplete styling
        const style = document.createElement('style');
        style.textContent = `
            .autocomplete-item:hover,
            .autocomplete-item.active {
                background-color: #f8f9fa;
            }
            .autocomplete-results {
                max-height: 300px;
                overflow-y: auto;
                border: 1px solid #dee2e6 !important;
            }
        `;
        document.head.appendChild(style);

        // Initialize Form Tracker for change detection and save functionality
        <?php if ($driver): ?>
        let formTracker = null;
        document.addEventListener('DOMContentLoaded', function() {
            formTracker = new TLSFormTracker({
                formSelector: '#driverForm',
                saveButtonId: 'tls-save-btn',
                cancelButtonId: 'tls-cancel-btn',
                saveIndicatorId: 'tls-save-indicator',
                excludeFields: ['action', 'driver_key'], // Don't track these for changes
                onSave: function(changes) {
                    console.log('Saving driver changes:', changes);
                    // Submit the form normally
                    document.getElementById('driverForm').submit();
                },
                onCancel: function() {
                    console.log('Canceling driver changes');
                    resetToSearch();
                },
                confirmMessage: 'You have unsaved driver changes. Are you sure you want to leave?'
            });
            
            // Load all driver data on page load
            loadDriverAddress();
            loadDriverContacts();
            loadDriverComments();
            
            console.log('TLS Form Tracker initialized for driver maintenance');
        });
        <?php endif; ?>
        
        // Driver Collections Management
        let currentDriverKey = <?php echo json_encode($driver['DriverKey'] ?? 0); ?>;
        
        // Load Driver Address (single)
        function loadDriverAddress() {
            if (!currentDriverKey) return;
            
            fetch('/tls/api/driver-collections.php?action=get_addresses&driver_key=' + currentDriverKey)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.addresses && data.addresses.length > 0) {
                        displayDriverAddress(data.addresses[0]);
                    } else {
                        document.getElementById('address-display').innerHTML = '<div class="text-muted">No address information on file</div>';
                    }
                })
                .catch(error => {
                    console.error('Error loading address:', error);
                    document.getElementById('address-display').innerHTML = '<div class="text-danger">Error loading address</div>';
                });
        }
        
        function displayDriverAddress(address) {
            let html = '<div class="address-info">';
            
            if (address.Name1 || address.Name2) {
                html += '<div class="row mb-2">';
                if (address.Name1) html += '<div class="col-md-6"><strong>Name:</strong> ' + address.Name1 + '</div>';
                if (address.Name2) html += '<div class="col-md-6"><strong>Name 2:</strong> ' + address.Name2 + '</div>';
                html += '</div>';
            }
            
            if (address.Address1 || address.Address2) {
                html += '<div class="row mb-2">';
                if (address.Address1) html += '<div class="col-md-6"><strong>Address:</strong> ' + address.Address1 + '</div>';
                if (address.Address2) html += '<div class="col-md-6"><strong>Address 2:</strong> ' + address.Address2 + '</div>';
                html += '</div>';
            }
            
            html += '<div class="row mb-2">';
            if (address.City) html += '<div class="col-md-4"><strong>City:</strong> ' + address.City + '</div>';
            if (address.State) html += '<div class="col-md-2"><strong>State:</strong> ' + address.State + '</div>';
            if (address.Zip) html += '<div class="col-md-3"><strong>ZIP:</strong> ' + address.Zip + '</div>';
            if (address.Phone) html += '<div class="col-md-3"><strong>Phone:</strong> ' + address.Phone + '</div>';
            html += '</div>';
            
            html += '</div>';
            
            document.getElementById('address-display').innerHTML = html;
            // Store the NameKey for editing
            window.currentAddressKey = address.NameKey;
        }
        
        // Load Driver Contacts
        function loadDriverContacts() {
            if (!currentDriverKey) return;
            
            document.getElementById('contacts-loading').style.display = 'block';
            document.getElementById('contacts-grid').innerHTML = '';
            
            fetch('/tls/api/driver-collections.php?action=get_contacts&driver_key=' + currentDriverKey)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('contacts-loading').style.display = 'none';
                    
                    if (data.success && data.contacts) {
                        displayContacts(data.contacts);
                        document.getElementById('contact-count').textContent = data.contacts.length;
                    } else {
                        document.getElementById('contacts-grid').innerHTML = '<p class="text-muted">No contacts found.</p>';
                        document.getElementById('contact-count').textContent = '0';
                    }
                })
                .catch(error => {
                    console.error('Error loading contacts:', error);
                    document.getElementById('contacts-loading').style.display = 'none';
                    document.getElementById('contacts-grid').innerHTML = '<p class="text-danger">Error loading contacts.</p>';
                });
        }
        
        function displayContacts(contacts) {
            let html = '<div class="table-responsive"><table class="table table-hover">';
            html += '<thead><tr><th>Name</th><th>Phone</th><th>Relationship</th><th>Actions</th></tr></thead><tbody>';
            
            contacts.forEach(contact => {
                html += '<tr>';
                html += '<td>' + (contact.ContactName || '') + '</td>';
                html += '<td>' + (contact.Phone || '') + '</td>';
                html += '<td>' + (contact.Relationship || '') + '</td>';
                html += '<td>';
                html += '<button class="btn btn-sm btn-warning me-1" onclick="editContact(' + contact.ContactKey + ')"><i class="bi-pencil"></i></button>';
                html += '<button class="btn btn-sm btn-danger" onclick="deleteContact(' + contact.ContactKey + ')"><i class="bi-trash"></i></button>';
                html += '</td>';
                html += '</tr>';
            });
            
            html += '</tbody></table></div>';
            document.getElementById('contacts-grid').innerHTML = html;
        }
        
        // Load Driver Comments
        function loadDriverComments() {
            if (!currentDriverKey) return;
            
            document.getElementById('comments-loading').style.display = 'block';
            document.getElementById('comments-list').innerHTML = '';
            
            fetch('/tls/api/driver-collections.php?action=get_comments&driver_key=' + currentDriverKey)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('comments-loading').style.display = 'none';
                    
                    if (data.success && data.comments) {
                        displayComments(data.comments);
                        document.getElementById('comment-count').textContent = data.comments.length;
                    } else {
                        document.getElementById('comments-list').innerHTML = '<p class="text-muted">No comments found.</p>';
                        document.getElementById('comment-count').textContent = '0';
                    }
                })
                .catch(error => {
                    console.error('Error loading comments:', error);
                    document.getElementById('comments-loading').style.display = 'none';
                    document.getElementById('comments-list').innerHTML = '<p class="text-danger">Error loading comments.</p>';
                });
        }
        
        function displayComments(comments) {
            let html = '';
            const currentUser = '<?php echo $auth->getCurrentUser()['userid'] ?? ''; ?>';
            console.log('Current user for comment permissions:', currentUser);
            
            comments.forEach(comment => {
                console.log('Processing comment:', comment);
                html += '<div class="card mb-2">';
                html += '<div class="card-body">';
                html += '<div class="d-flex justify-content-between align-items-start">';
                html += '<div class="flex-grow-1">';
                // Comment text first, prominently displayed
                html += '<p class="card-text mb-2">' + (comment.CommentText || '') + '</p>';
                // Metadata in smaller text below
                html += '<small class="text-muted">';
                html += '<i class="bi-person-circle"></i> Entered by ' + (comment.CommentBy || 'Unknown');
                html += ' on ' + (comment.CommentDate ? new Date(comment.CommentDate).toLocaleDateString() : '');
                if (comment.EditedBy && comment.EditedBy !== comment.CommentBy) {
                    html += ' • Edited by ' + comment.EditedBy;
                    if (comment.EditedDate) {
                        html += ' on ' + new Date(comment.EditedDate).toLocaleDateString();
                    }
                }
                html += '</small>';
                html += '</div>';
                html += '<div class="btn-group ms-2" role="group">';
                if (comment.CommentBy === currentUser) {
                    html += '<button class="btn btn-sm btn-warning" onclick="editComment(' + comment.CommentKey + ')"><i class="bi-pencil"></i></button>';
                    html += '<button class="btn btn-sm btn-danger" onclick="deleteComment(' + comment.CommentKey + ')"><i class="bi-trash"></i></button>';
                }
                html += '</div>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
            });
            
            document.getElementById('comments-list').innerHTML = html;
        }
        
        // Modal Functions
        function editDriverAddress() {
            // Show the address form and hide display
            document.getElementById('address-display').style.display = 'none';
            document.getElementById('address-form').style.display = 'block';
            
            // Load current address data into form if exists
            if (window.currentAddressKey) {
                fetch('/tls/api/driver-collections.php?action=get_address&name_key=' + window.currentAddressKey)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.address) {
                            document.getElementById('address_name1').value = data.address.Name1 || '';
                            document.getElementById('address_name2').value = data.address.Name2 || '';
                            document.getElementById('address_address1').value = data.address.Address1 || '';
                            document.getElementById('address_address2').value = data.address.Address2 || '';
                            document.getElementById('address_city').value = data.address.City || '';
                            document.getElementById('address_state').value = data.address.State || '';
                            document.getElementById('address_zip').value = data.address.Zip || '';
                            document.getElementById('address_phone').value = data.address.Phone || '';
                        }
                    });
            }
        }
        
        function showContactModal(contactKey = null) {
            document.getElementById('contactForm').reset();
            document.getElementById('contact_key').value = contactKey || '';
            document.getElementById('contactModalLabel').textContent = contactKey ? 'Edit Contact' : 'Add Contact';
            
            if (contactKey) {
                // Load contact data for editing
                fetch('/tls/api/driver-collections.php?action=get_contact&contact_key=' + contactKey)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.contact) {
                            document.getElementById('contact_first_name').value = data.contact.FirstName || '';
                            document.getElementById('contact_last_name').value = data.contact.LastName || '';
                            document.getElementById('contact_phone').value = data.contact.Phone || '';
                            document.getElementById('contact_mobile').value = data.contact.Mobile || '';
                            document.getElementById('contact_email').value = data.contact.Email || '';
                            document.getElementById('contact_relationship').value = data.contact.Relationship || '';
                            document.getElementById('contact_is_primary').checked = data.contact.IsPrimary || false;
                        }
                    });
            }
            
            new bootstrap.Modal(document.getElementById('contactModal')).show();
        }
        
        function showCommentModal() {
            // Reset the form
            document.getElementById('commentForm').reset();
            
            // Reset modal state for new comment
            delete document.getElementById('commentModal').dataset.editingCommentKey;
            
            // Reset modal title and button text
            document.querySelector('#commentModal .modal-title').textContent = 'Add Comment';
            document.querySelector('#commentModal .btn-primary').textContent = 'Save Comment';
            
            new bootstrap.Modal(document.getElementById('commentModal')).show();
        }
        
        // Save Functions
        function saveDriverAddress() {
            const formData = new FormData();
            formData.append('action', 'save_address');
            formData.append('driver_key', currentDriverKey);
            formData.append('name_key', window.currentAddressKey || 0);
            formData.append('name1', document.getElementById('address_name1').value);
            formData.append('name2', document.getElementById('address_name2').value);
            formData.append('address1', document.getElementById('address_address1').value);
            formData.append('address2', document.getElementById('address_address2').value);
            formData.append('city', document.getElementById('address_city').value);
            formData.append('state', document.getElementById('address_state').value);
            formData.append('zip', document.getElementById('address_zip').value);
            formData.append('phone', document.getElementById('address_phone').value);
            
            fetch('/tls/api/driver-collections.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Hide form and show display
                    document.getElementById('address-form').style.display = 'none';
                    document.getElementById('address-display').style.display = 'block';
                    // Reload address data
                    loadDriverAddress();
                    alert('Address saved successfully.');
                } else {
                    alert('Error saving address: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error saving address:', error);
                alert('Error saving address.');
            });
        }
        
        function cancelAddressEdit() {
            // Hide form and show display without saving
            document.getElementById('address-form').style.display = 'none';
            document.getElementById('address-display').style.display = 'block';
        }
        
        function saveContact() {
            const formData = new FormData(document.getElementById('contactForm'));
            formData.append('driver_key', currentDriverKey);
            formData.append('action', 'save_contact');
            
            fetch('/tls/api/driver-collections.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('contactModal')).hide();
                    loadDriverContacts();
                    alert('Contact saved successfully.');
                } else {
                    alert('Error saving contact: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error saving contact:', error);
                alert('Error saving contact.');
            });
        }
        
        function saveComment() {
            const formData = new FormData(document.getElementById('commentForm'));
            formData.append('driver_key', currentDriverKey);
            
            // Check if we're editing an existing comment
            const editingCommentKey = document.getElementById('commentModal').dataset.editingCommentKey;
            if (editingCommentKey) {
                formData.append('comment_key', editingCommentKey);
                formData.append('action', 'update_comment');
            } else {
                formData.append('action', 'save_comment');
            }
            
            fetch('/tls/api/driver-collections.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('commentModal')).hide();
                    loadDriverComments();
                    alert(editingCommentKey ? 'Comment updated successfully.' : 'Comment saved successfully.');
                    
                    // Reset the modal state
                    delete document.getElementById('commentModal').dataset.editingCommentKey;
                } else {
                    alert('Error saving comment: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error saving comment:', error);
                alert('Error saving comment.');
            });
        }
        
        // Edit Functions
        
        function editContact(contactKey) {
            showContactModal(contactKey);
        }
        
        // Delete Functions
        
        function deleteContact(contactKey) {
            if (!confirm('Are you sure you want to delete this contact?')) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_contact');
            formData.append('driver_key', currentDriverKey);
            formData.append('contact_key', contactKey);
            
            fetch('/tls/api/driver-collections.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadDriverContacts();
                    alert('Contact deleted successfully.');
                } else {
                    alert('Error deleting contact: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error deleting contact:', error);
                alert('Error deleting contact.');
            });
        }
        
        function deleteComment(commentKey) {
            if (!confirm('Are you sure you want to delete this comment?')) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_comment');
            formData.append('driver_key', currentDriverKey);
            formData.append('comment_key', commentKey);
            
            fetch('/tls/api/driver-collections.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadDriverComments();
                    alert('Comment deleted successfully.');
                } else {
                    alert('Error deleting comment: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error deleting comment:', error);
                alert('Error deleting comment.');
            });
        }
        
        function editComment(commentKey) {
            // First get the current comment data
            fetch(`/tls/api/driver-collections.php?action=get_comment&comment_key=${commentKey}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.comment) {
                    // Populate the modal with existing comment data
                    document.getElementById('comment_text').value = data.comment.Comment || '';
                    document.getElementById('comment_type').value = data.comment.CommentType || 'General';
                    
                    // Store the comment key for updating
                    document.getElementById('commentModal').dataset.editingCommentKey = commentKey;
                    
                    // Change modal title and button text
                    document.querySelector('#commentModal .modal-title').textContent = 'Edit Comment';
                    document.querySelector('#commentModal .btn-primary').textContent = 'Update Comment';
                    
                    // Show the modal
                    new bootstrap.Modal(document.getElementById('commentModal')).show();
                } else {
                    alert('Error loading comment: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error loading comment:', error);
                alert('Error loading comment.');
            });
        }
        
        // Initialize on page load if driver is loaded
        if (currentDriverKey) {
            // Load initial counts
            loadDriverAddress();
            loadDriverContacts();
            loadDriverComments();
        }
    </script>
</body>
</html>