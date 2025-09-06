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
                            $_POST['driver_type'] ?? 'F',
                            $_POST['email'] ?? '',
                            isset($_POST['twic']) ? 1 : 0,
                            isset($_POST['coil_cert']) ? 1 : 0,
                            intval($_POST['company_id'] ?? 3),
                            $_POST['arcnc'] ?? null,
                            $_POST['txcnc'] ?? null,
                            isset($_POST['company_driver']) ? 1 : 0,
                            isset($_POST['eobr']) ? 1 : 0,
                            $_POST['eobr_start'] ?? null,
                            floatval($_POST['weekly_cash'] ?? 0.00),
                            isset($_POST['card_exception']) ? 1 : 0,
                            $_POST['driver_spec'] ?? 'OTH',
                            isset($_POST['medical_verification']) ? 1 : 0,
                            $_POST['mvr_due'] ?? null,
                            floatval($_POST['company_loaded_pay'] ?? 0.00),
                            floatval($_POST['company_empty_pay'] ?? 0.00),
                            $_POST['pay_type'] ?? 'P',
                            floatval($_POST['company_tarp_pay'] ?? 0.00),
                            floatval($_POST['company_stop_pay'] ?? 0.00)
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
                        <span class="tls-change-counter" id="tls-change-counter" style="display: none;">0</span>
                        <?php else: ?>
                        <!-- New Driver Button (shown when no driver is loaded) -->
                        <button type="button" class="btn tls-btn-primary" onclick="newDriver()">
                            <i class="bi-plus me-1"></i> New Driver
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Save Indicator -->
                <div class="tls-save-indicator" id="tls-save-indicator" style="display: none;">
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        You have unsaved changes. Remember to save before navigating away.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
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
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="driver_search" class="form-label">Search Driver:</label>
                                    <div class="position-relative">
                                        <input type="text" class="form-control" id="driver_search" 
                                               placeholder="Type driver name or ID...">
                                        <div id="search-spinner" class="position-absolute top-50 end-0 translate-middle-y me-3" style="display: none;">
                                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                                <span class="visually-hidden">Searching...</span>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="hidden" id="driver_key" name="driver_key">
                                    <div id="search-status" class="form-text text-muted mt-1">Type at least 2 characters to search</div>
                                </div>
                                <div class="col-md-6 d-flex align-items-end">
                                    <div class="me-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="include_inactive" name="include_inactive">
                                            <label class="form-check-label" for="include_inactive">
                                                Include Inactive Drivers
                                            </label>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi-search"></i> Load Driver
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php if ($driver): ?>
                <!-- Driver Information Form -->
                <form method="POST" id="driverForm">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="driver_key" value="<?php echo htmlspecialchars((string)($driver['DriverKey'] ?? '')); ?>">
                    
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
                                <div class="col-md-3">
                                    <label for="email" class="form-label">Email:</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($driver['Email'] ?? ''); ?>" 
                                           maxlength="50">
                                </div>
                                <div class="col-md-3">
                                    <label for="driver_type" class="form-label">Driver Type:</label>
                                    <select class="form-select" id="driver_type" name="driver_type">
                                        <option value="F" <?php echo ($driver['DriverType'] ?? '') === 'F' ? 'selected' : ''; ?>>Full Time</option>
                                        <option value="P" <?php echo ($driver['DriverType'] ?? '') === 'P' ? 'selected' : ''; ?>>Part Time</option>
                                        <option value="C" <?php echo ($driver['DriverType'] ?? '') === 'C' ? 'selected' : ''; ?>>Contractor</option>
                                    </select>
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
                    
                    <!-- License & Certification -->
                    <div class="tls-form-card">
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
                        </div>
                    </div>
                    
                    <!-- Employment Details -->
                    <div class="tls-form-card">
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
                        </div>
                    </div>
                    
                    <!-- Tax ID (PII) Section -->
                    <div class="tls-form-card tls-pii-section">
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
                </form>
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
                
                if (query.length < 2) {
                    console.log('Query too short, hiding results');
                    this.hideResults();
                    this.updateStatus('Type at least 2 characters to search');
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
            const driverAutocomplete = new TLSAutocomplete(
                document.getElementById('driver_search'),
                document.getElementById('driver_key'),
                'drivers',
                function(driver) {
                    console.log('Driver selected:', driver);
                    console.log('Hidden field value set to:', document.getElementById('driver_key').value);
                    // Submit the search form automatically when driver is selected
                    document.getElementById('searchForm').submit();
                }
            );
            
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
                changeCounterId: 'tls-change-counter',
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
            
            console.log('TLS Form Tracker initialized for driver maintenance');
        });
        <?php endif; ?>
    </script>
</body>
</html>