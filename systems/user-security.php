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

// Check menu access
if (!$auth->hasMenuAccess('mnuUserSecurity')) {
    header('HTTP/1.0 403 Forbidden');
    echo '<h1>403 Forbidden</h1><p>You do not have permission to access User Security.</p>';
    exit;
}

// Get current user and initialize menu manager
$user = $auth->getCurrentUser();
$menuManager = new MenuManager($auth);
$menuConfig = include __DIR__ . '/../config/menus.php';

// Initialize database connection
$db = new Database(
    Config::get('DB_SERVER'),
    Config::get('DB_USERNAME'),
    Config::get('DB_PASSWORD')
);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $db->connect($user['customer_db']);
        
        switch ($_POST['action']) {
            case 'get_user_permissions':
                $userId = $_POST['user_id'] ?? '';
                if (empty($userId)) {
                    throw new Exception('User ID required');
                }
                
                error_log("Loading permissions for user: {$userId}");
                $permissions = getUserPermissions($db, $userId);
                error_log("Loaded " . count($permissions) . " permissions");
                echo json_encode(['success' => true, 'permissions' => $permissions]);
                break;
                
            case 'save_permissions':
                $userId = $_POST['user_id'] ?? '';
                $permissionChanges = json_decode($_POST['changes'] ?? '[]', true);
                
                if (empty($userId)) {
                    throw new Exception('User ID required');
                }
                
                if (empty($permissionChanges)) {
                    echo json_encode(['success' => true, 'message' => 'No changes to save']);
                    break;
                }
                
                $savedCount = savePermissionChanges($db, $userId, $permissionChanges);
                echo json_encode([
                    'success' => true, 
                    'message' => "Successfully saved {$savedCount} permission changes"
                ]);
                break;
                
            case 'apply_role_template':
                $userId = $_POST['user_id'] ?? '';
                $role = $_POST['role'] ?? '';
                
                if (empty($userId) || empty($role)) {
                    throw new Exception('User ID and role required');
                }
                
                $rolePermissions = getRoleTemplate($db, $role);
                saveUserPermissions($db, $userId, $rolePermissions);
                echo json_encode(['success' => true, 'permissions' => $rolePermissions]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } finally {
        $db->disconnect();
    }
    exit;
}

// Get all users for dropdown
$users = [];
try {
    $db->connect($user['customer_db']);
    $results = $db->executeStoredProcedure('spUsers_GetAll', []);
    foreach ($results as $row) {
        $users[] = [
            'user_id' => trim($row['UserID']),
            'user_name' => trim($row['UserName'] ?? $row['UserID'])
        ];
    }
} catch (Exception $e) {
    error_log("Error loading users: " . $e->getMessage());
} finally {
    $db->disconnect();
}

/**
 * Get user permissions for all menu items
 */
function getUserPermissions($db, $userId) {
    $permissions = [];
    
    // Get all available permissions from tSecurity table
    try {
        error_log("Querying tSecurity table...");
        $securityItems = $db->query("SELECT Menu, Description FROM dbo.tSecurity ORDER BY Menu", []);
        error_log("Found " . count($securityItems) . " items in tSecurity table");
        
        foreach ($securityItems as $item) {
            $menuKey = trim($item['Menu']);
            if (empty($menuKey)) continue;
            
            try {
                $returnCode = $db->executeStoredProcedureWithReturn('spUser_Menu', [$userId, $menuKey]);
                $permissions[$menuKey] = ($returnCode === 0);
                error_log("Permission {$menuKey} for {$userId}: " . ($returnCode === 0 ? 'granted' : 'denied'));
            } catch (Exception $e) {
                error_log("Error checking permission {$menuKey} for {$userId}: " . $e->getMessage());
                $permissions[$menuKey] = false;
            }
        }
    } catch (Exception $e) {
        error_log("Error loading security permissions: " . $e->getMessage());
        throw $e; // Re-throw to trigger error response
    }
    
    return $permissions;
}

/**
 * Save only the changed user permissions
 */
function savePermissionChanges($db, $userId, $permissionChanges) {
    $savedCount = 0;
    
    foreach ($permissionChanges as $change) {
        $menuKey = $change['menu'];
        $granted = $change['granted'];
        
        try {
            $db->executeStoredProcedureWithReturn('spUser_Menu_Save', [$userId, $menuKey, $granted]);
            $savedCount++;
            error_log("Saved permission change: {$menuKey} for user {$userId} = " . ($granted ? 'granted' : 'denied'));
        } catch (Exception $e) {
            error_log("Error saving permission {$menuKey} for user {$userId}: " . $e->getMessage());
        }
    }
    
    return $savedCount;
}

/**
 * Save user permissions (legacy - kept for role templates)
 */
function saveUserPermissions($db, $userId, $permissionData) {
    foreach ($permissionData as $menuKey => $granted) {
        try {
            $db->executeStoredProcedureWithReturn('spUser_Menu_Save', [$userId, $menuKey, $granted]);
        } catch (Exception $e) {
            error_log("Error saving permission {$menuKey} for user {$userId}: " . $e->getMessage());
        }
    }
}

/**
 * Get role template permissions from database
 */
function getRoleTemplate($db, $role) {
    $permissions = [];
    
    try {
        // Get permissions for the specified role from tSecurityGroups
        $results = $db->query("SELECT Menu FROM dbo.tSecurityGroups WHERE UserGroup = ? ORDER BY Menu", [ucfirst(strtolower($role))]);
        
        foreach ($results as $row) {
            $menuKey = trim($row['Menu']);
            $permissions[$menuKey] = true;
        }
    } catch (Exception $e) {
        error_log("Error loading role template '{$role}': " . $e->getMessage());
    }
    
    return $permissions;
}

/**
 * Extract all menu items from nested menu config
 */
function extractAllMenuItems($menuConfig, $result = []) {
    foreach ($menuConfig as $key => $data) {
        $result[$key] = $data;
        if (isset($data['items'])) {
            $result = extractAllMenuItems($data['items'], $result);
        }
    }
    return $result;
}

/**
 * Organize menu items by category using current web menu structure
 */
function organizeMenusByCategory($db, $menuConfig) {
    $organized = [];
    
    try {
        // Get all permissions from database with descriptions
        $securityItems = $db->query("SELECT Menu, Description FROM dbo.tSecurity ORDER BY Menu", []);
        
        // Create lookup for descriptions
        $descriptions = [];
        foreach ($securityItems as $item) {
            $menuKey = trim($item['Menu']);
            $descriptions[$menuKey] = trim($item['Description']) ?: "Access to {$menuKey} functionality";
        }
        
        // Use current web menu structure for organization
        foreach ($menuConfig as $categoryKey => $categoryData) {
            if (isset($categoryData['separator'])) continue;
            
            $category = [
                'key' => $categoryKey,
                'label' => $categoryData['label'],
                'icon' => $categoryData['icon'] ?? 'bi-gear',
                'items' => []
            ];
            
            if (isset($categoryData['items'])) {
                $category['items'] = extractMenuItemsFlat($categoryData['items'], $descriptions);
            }
            
            // Only include categories that have items with database permissions
            if (!empty($category['items'])) {
                $organized[] = $category;
            }
        }
        
        // Add Security category for 'sec' items that aren't in regular menus
        $securityItems = [];
        foreach ($descriptions as $menuKey => $description) {
            if (str_starts_with($menuKey, 'sec')) {
                $securityItems[] = [
                    'key' => $menuKey,
                    'label' => $description, // Use the description as the label for security items
                    'description' => $description
                ];
            }
        }
        
        if (!empty($securityItems)) {
            $organized[] = [
                'key' => 'security',
                'label' => 'Security',
                'icon' => 'bi-shield-lock',
                'items' => $securityItems
            ];
        }
        
    } catch (Exception $e) {
        error_log("Error organizing menus by category: " . $e->getMessage());
    }
    
    return $organized;
}

/**
 * Get appropriate icon for category
 */
function getCategoryIcon($category) {
    $icons = [
        'Accounting' => 'bi-calculator',
        'Dispatch' => 'bi-truck', 
        'Logistics' => 'bi-diagram-3',
        'Safety' => 'bi-shield-check',
        'Payroll' => 'bi-clock',
        'Reports' => 'bi-file-earmark-bar-graph',
        'Systems' => 'bi-gear',
        'Imaging' => 'bi-images'
    ];
    
    return $icons[$category] ?? 'bi-gear';
}

/**
 * Extract menu items in flat structure for display
 */
function extractMenuItemsFlat($items, $descriptions, $prefix = '') {
    $result = [];
    
    foreach ($items as $key => $data) {
        if (isset($data['separator'])) continue;
        // Remove the filter for 'sec' items - include all security options
        
        if (isset($data['items'])) {
            // This is a subcategory
            $subcategoryItems = extractMenuItemsFlat($data['items'], $descriptions, $data['label'] . ' - ');
            $result = array_merge($result, $subcategoryItems);
        } else {
            // Only include menu items that exist in the database security table
            if (isset($descriptions[$key])) {
                $result[] = [
                    'key' => $key,
                    'label' => $prefix . $data['label'],
                    'description' => $descriptions[$key]
                ];
            }
        }
    }
    
    return $result;
}

/**
 * Generate description for menu item
 */
function getMenuDescription($menuKey, $label) {
    $descriptions = [
        'mnuLoadEntry' => 'Create and manage transportation loads',
        'mnuCOAMaint' => 'Add, edit, and manage chart of accounts',
        'mnuVoucher' => 'Enter and process accounts payable vouchers',
        'mnuCarrierMaint' => 'Manage carrier information and settings',
        'mnuDriverMaint' => 'Maintain driver records and information',
        // Add more descriptions as needed
    ];
    
    return $descriptions[$menuKey] ?? "Access to {$label} functionality";
}

// Get organized menus using current web structure + database descriptions
$organizedMenus = [];
try {
    $db->connect($user['customer_db']);
    $organizedMenus = organizeMenusByCategory($db, $menuConfig);
} catch (Exception $e) {
    error_log("Error loading organized menus: " . $e->getMessage());
} finally {
    $db->disconnect();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(Config::get('APP_NAME', 'TLS Operations')) ?> - User Security</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/tls/css/app.css" rel="stylesheet">
    
    <style>
        /* Use standardized TLS card styling for permission cards */
        .permission-card {
            /* Inherits from tls-form-card styles */
        }
        .permission-item {
            padding: 0.5rem 0;
            border-bottom: 1px solid #f8f9fa;
        }
        .permission-item:last-child {
            border-bottom: none;
        }
        .form-check-input:checked {
            background-color: #198754;
            border-color: #198754;
        }
        .bulk-controls {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 0.75rem;
            margin-bottom: 1rem;
        }
        .stats-badge {
            font-size: 0.75rem;
            margin-left: 0.5rem;
        }
        .search-highlight {
            background-color: #fff3cd;
        }
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        /* Use standardized save indicator positioning from tls-save-indicator */
    </style>
</head>
<body>
    <!-- Navigation -->
    <?= $menuManager->generateMainMenu() ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="tls-page-header">
                    <h2 class="tls-page-title"><i class="bi bi-shield-lock me-2"></i>User Security Management</h2>
                    <div class="tls-top-actions">
                        <button class="btn tls-btn-primary" id="saveBtn" disabled>
                            <i class="bi bi-check-lg me-2"></i>Save Changes
                        </button>
                        <button class="btn tls-btn-secondary ms-2" id="resetBtn" disabled>
                            <i class="bi bi-arrow-clockwise me-2"></i>Reset
                        </button>
                        <span class="tls-change-counter" id="changeCounter" style="display: none;">0</span>
                    </div>
                </div>

                <!-- User Selection and Search -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <label for="userSelect" class="form-label fw-bold">Select User:</label>
                        <select class="form-select" id="userSelect">
                            <option value="">Choose a user...</option>
                            <?php foreach ($users as $userData): ?>
                                <option value="<?= htmlspecialchars($userData['user_id']) ?>">
                                    <?= htmlspecialchars($userData['user_name']) ?> (<?= htmlspecialchars($userData['user_id']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="searchBox" class="form-label fw-bold">Search Permissions:</label>
                        <input type="text" class="form-control" id="searchBox" placeholder="Search menu items..." disabled>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Permission Summary:</label>
                        <div class="d-flex align-items-center" id="permissionSummary">
                            <span class="badge bg-secondary me-2">0 Granted</span>
                            <span class="badge bg-secondary">0 Denied</span>
                            <span class="badge bg-secondary ms-2">0 Total</span>
                        </div>
                    </div>
                </div>

                <!-- Bulk Controls -->
                <div class="bulk-controls" id="bulkControls" style="display: none;">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Bulk Actions:</strong>
                            <button class="btn btn-sm btn-outline-success ms-2" id="grantAllBtn">
                                <i class="bi bi-check-all"></i> Grant All Visible
                            </button>
                            <button class="btn btn-sm btn-outline-danger ms-1" id="denyAllBtn">
                                <i class="bi bi-x-lg"></i> Deny All Visible
                            </button>
                        </div>
                        <div class="col-md-6 text-end">
                            <strong>Quick Roles:</strong>
                            <button class="btn btn-sm btn-outline-primary ms-1 role-template" data-role="dispatch">Dispatch</button>
                            <button class="btn btn-sm btn-outline-primary ms-1 role-template" data-role="broker">Broker</button>
                            <button class="btn btn-sm btn-outline-primary ms-1 role-template" data-role="accounting">Accounting</button>
                        </div>
                    </div>
                </div>

                <!-- Permission Cards Grid -->
                <div class="row" id="permissionCards" style="display: none;">
                    <?php foreach ($organizedMenus as $category): ?>
                        <div class="col-lg-6">
                            <div class="permission-card tls-form-card" data-category="<?= htmlspecialchars($category['key']) ?>">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="<?= htmlspecialchars($category['icon']) ?> me-2"></i>
                                        <?= htmlspecialchars($category['label']) ?>
                                    </div>
                                    <div>
                                        <span class="stats-badge badge bg-secondary category-stats">0/0</span>
                                        <button class="btn btn-sm btn-outline-secondary ms-2 toggle-category" data-bs-toggle="collapse" data-bs-target="#category-<?= htmlspecialchars($category['key']) ?>">
                                            <i class="bi bi-chevron-down"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body collapse show" id="category-<?= htmlspecialchars($category['key']) ?>">
                                    <?php foreach ($category['items'] as $item): ?>
                                        <div class="permission-item d-flex justify-content-between align-items-center" data-menu-key="<?= htmlspecialchars($item['key']) ?>">
                                            <div>
                                                <label class="form-check-label fw-medium"><?= htmlspecialchars($item['label']) ?></label>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input permission-toggle" type="checkbox" data-permission="<?= htmlspecialchars($item['key']) ?>">
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Loading indicator -->
                <div class="text-center" id="loadingIndicator" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading user permissions...</p>
                </div>

                <!-- No user selected message -->
                <div class="text-center text-muted" id="noUserMessage">
                    <i class="bi bi-person-x display-1"></i>
                    <p class="mt-3">Select a user to view and manage their permissions.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Save Indicator Banner -->
    <div class="tls-save-indicator" id="saveIndicator" style="display: none;">
        <i class="bi bi-exclamation-triangle me-2"></i>
        You have unsaved permission changes. Remember to save before navigating away.
    </div>

    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let currentUserId = '';
        let userPermissions = {};
        let hasUnsavedChanges = false;
        let originalPermissions = {};
        let permissionChanges = []; // Track individual changes

        document.addEventListener('DOMContentLoaded', function() {
            const userSelect = document.getElementById('userSelect');
            const searchBox = document.getElementById('searchBox');
            const saveBtn = document.getElementById('saveBtn');
            const resetBtn = document.getElementById('resetBtn');
            const saveIndicator = document.getElementById('saveIndicator');

            // User selection change
            userSelect.addEventListener('change', function() {
                const userId = this.value;
                if (userId) {
                    loadUserPermissions(userId);
                } else {
                    hidePermissions();
                }
            });

            // Permission toggle change
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('permission-toggle')) {
                    const permissionKey = e.target.dataset.permission;
                    const newValue = e.target.checked;
                    const oldValue = originalPermissions[permissionKey] || false;
                    
                    userPermissions[permissionKey] = newValue;
                    
                    // Track this change if it's different from original
                    if (newValue !== oldValue) {
                        // Remove any existing change for this permission
                        permissionChanges = permissionChanges.filter(change => change.menu !== permissionKey);
                        
                        // Add the new change
                        permissionChanges.push({
                            menu: permissionKey,
                            granted: newValue
                        });
                        
                        hasUnsavedChanges = true;
                    } else {
                        // Remove change if back to original value
                        permissionChanges = permissionChanges.filter(change => change.menu !== permissionKey);
                        hasUnsavedChanges = permissionChanges.length > 0;
                    }
                    
                    updateUI();
                }
            });

            // Bulk actions
            document.getElementById('grantAllBtn').addEventListener('click', function() {
                const visibleToggles = getVisiblePermissionToggles();
                let changesProcessed = 0;
                let changesNeeded = 0;
                
                // Count how many changes we need to make
                visibleToggles.forEach(toggle => {
                    if (!toggle.checked) {
                        changesNeeded++;
                    }
                });
                
                if (changesNeeded === 0) {
                    return; // No changes needed
                }
                
                // Process each toggle
                visibleToggles.forEach(toggle => {
                    if (!toggle.checked) {
                        const permissionKey = toggle.dataset.permission;
                        const oldValue = originalPermissions[permissionKey] || false;
                        const newValue = true;
                        
                        toggle.checked = true;
                        userPermissions[permissionKey] = newValue;
                        
                        // Track this change if it's different from original
                        if (newValue !== oldValue) {
                            // Remove any existing change for this permission
                            permissionChanges = permissionChanges.filter(change => change.menu !== permissionKey);
                            
                            // Add the new change
                            permissionChanges.push({
                                menu: permissionKey,
                                granted: newValue
                            });
                        }
                        
                        changesProcessed++;
                    }
                });
                
                // Update hasUnsavedChanges and UI after all changes are processed
                if (changesProcessed > 0) {
                    hasUnsavedChanges = permissionChanges.length > 0;
                    console.log(`Bulk Action: Processed ${changesProcessed} changes, total tracked changes: ${permissionChanges.length}, hasUnsavedChanges: ${hasUnsavedChanges}`);
                    updateUI();
                }
            });

            document.getElementById('denyAllBtn').addEventListener('click', function() {
                const visibleToggles = getVisiblePermissionToggles();
                let changesProcessed = 0;
                let changesNeeded = 0;
                
                // Count how many changes we need to make
                visibleToggles.forEach(toggle => {
                    if (toggle.checked) {
                        changesNeeded++;
                    }
                });
                
                if (changesNeeded === 0) {
                    return; // No changes needed
                }
                
                // Process each toggle
                visibleToggles.forEach(toggle => {
                    if (toggle.checked) {
                        const permissionKey = toggle.dataset.permission;
                        const oldValue = originalPermissions[permissionKey] || false;
                        const newValue = false;
                        
                        toggle.checked = false;
                        userPermissions[permissionKey] = newValue;
                        
                        // Track this change if it's different from original
                        if (newValue !== oldValue) {
                            // Remove any existing change for this permission
                            permissionChanges = permissionChanges.filter(change => change.menu !== permissionKey);
                            
                            // Add the new change
                            permissionChanges.push({
                                menu: permissionKey,
                                granted: newValue
                            });
                        } else {
                            // Remove change if back to original value
                            permissionChanges = permissionChanges.filter(change => change.menu !== permissionKey);
                        }
                        
                        changesProcessed++;
                    }
                });
                
                // Update hasUnsavedChanges and UI after all changes are processed
                if (changesProcessed > 0) {
                    hasUnsavedChanges = permissionChanges.length > 0;
                    console.log(`Bulk Action: Processed ${changesProcessed} changes, total tracked changes: ${permissionChanges.length}, hasUnsavedChanges: ${hasUnsavedChanges}`);
                    updateUI();
                }
            });

            // Role templates
            document.querySelectorAll('.role-template').forEach(btn => {
                btn.addEventListener('click', function() {
                    const role = this.dataset.role;
                    applyRoleTemplate(role);
                });
            });

            // Search functionality
            searchBox.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                filterPermissions(searchTerm);
            });

            // Save button
            saveBtn.addEventListener('click', function() {
                if (hasUnsavedChanges && currentUserId) {
                    savePermissions();
                }
            });

            // Reset button
            resetBtn.addEventListener('click', function() {
                if (currentUserId) {
                    userPermissions = { ...originalPermissions };
                    permissionChanges = []; // Clear all tracked changes
                    updatePermissionToggles();
                    hasUnsavedChanges = false;
                    updateUI();
                }
            });

            // Search functionality
            function filterPermissions(searchTerm) {
                const permissionItems = document.querySelectorAll('.permission-item');
                permissionItems.forEach(item => {
                    const label = item.querySelector('.form-check-label').textContent.toLowerCase();
                    const description = item.querySelector('.text-muted').textContent.toLowerCase();
                    
                    if (label.includes(searchTerm) || description.includes(searchTerm)) {
                        item.style.display = 'flex';
                        if (searchTerm) {
                            item.querySelector('.form-check-label').classList.add('search-highlight');
                        } else {
                            item.querySelector('.form-check-label').classList.remove('search-highlight');
                        }
                    } else {
                        item.style.display = 'none';
                        item.querySelector('.form-check-label').classList.remove('search-highlight');
                    }
                });
                updateCategoryStats();
            }

            function getVisiblePermissionToggles() {
                return Array.from(document.querySelectorAll('.permission-toggle'))
                    .filter(toggle => toggle.closest('.permission-item').style.display !== 'none');
            }

            function loadUserPermissions(userId) {
                currentUserId = userId;
                showLoading();
                
                console.log('Loading permissions for user:', userId);
                
                fetch('/tls/systems/user-security.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=get_user_permissions&user_id=${encodeURIComponent(userId)}`
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers);
                    return response.text(); // Get as text first to see what we're getting
                })
                .then(text => {
                    console.log('Raw response:', text);
                    try {
                        const data = JSON.parse(text);
                        if (data.success) {
                            console.log('Permissions loaded:', Object.keys(data.permissions).length, 'items');
                            userPermissions = data.permissions;
                            originalPermissions = { ...data.permissions };
                            permissionChanges = []; // Clear changes when loading new user
                            updatePermissionToggles();
                            showPermissions();
                            hasUnsavedChanges = false;
                            updateUI();
                        } else {
                            console.error('Server error:', data.error);
                            alert('Error loading permissions: ' + data.error);
                        }
                    } catch (parseError) {
                        console.error('JSON parse error:', parseError);
                        console.error('Response was:', text);
                        // Don't show alert if permissions actually loaded and the error is just in updating UI
                        if (Object.keys(userPermissions).length > 0) {
                            console.log('Permissions loaded successfully despite UI update error');
                            showPermissions();
                        } else {
                            alert('Error loading permissions: Invalid response format');
                        }
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    alert('Error loading permissions: ' + error.message);
                })
                .finally(() => {
                    hideLoading();
                });
            }

            function updatePermissionToggles() {
                document.querySelectorAll('.permission-toggle').forEach(toggle => {
                    const permission = toggle.dataset.permission;
                    toggle.checked = userPermissions[permission] || false;
                });
                updateCategoryStats();
                updateSummaryStats();
            }

            function updateCategoryStats() {
                document.querySelectorAll('.permission-card').forEach(card => {
                    const toggles = card.querySelectorAll('.permission-toggle');
                    const visibleToggles = Array.from(toggles).filter(t => 
                        t.closest('.permission-item') && t.closest('.permission-item').style.display !== 'none'
                    );
                    const grantedCount = visibleToggles.filter(t => t.checked).length;
                    const totalCount = visibleToggles.length;
                    
                    const statsBadge = card.querySelector('.category-stats');
                    if (statsBadge) {
                        statsBadge.textContent = `${grantedCount}/${totalCount}`;
                        
                        if (totalCount === 0) {
                            statsBadge.className = 'stats-badge badge bg-secondary';
                        } else if (grantedCount === totalCount) {
                            statsBadge.className = 'stats-badge badge bg-success';
                        } else if (grantedCount === 0) {
                            statsBadge.className = 'stats-badge badge bg-danger';
                        } else {
                            statsBadge.className = 'stats-badge badge bg-warning';
                        }
                    }
                });
            }

            function updateSummaryStats() {
                const allToggles = document.querySelectorAll('.permission-toggle');
                const grantedCount = Array.from(allToggles).filter(t => t.checked).length;
                const totalCount = allToggles.length;
                const deniedCount = totalCount - grantedCount;
                
                const summary = document.getElementById('permissionSummary');
                if (summary) {
                    summary.innerHTML = `
                        <span class="badge bg-success me-2">${grantedCount} Granted</span>
                        <span class="badge bg-danger">${deniedCount} Denied</span>
                        <span class="badge bg-secondary ms-2">${totalCount} Total</span>
                    `;
                }
            }

            function applyRoleTemplate(role) {
                if (!currentUserId) return;
                
                fetch('/tls/systems/user-security.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=apply_role_template&user_id=${encodeURIComponent(currentUserId)}&role=${encodeURIComponent(role)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        userPermissions = data.permissions;
                        updatePermissionToggles();
                        hasUnsavedChanges = false;
                        updateUI();
                        
                        // Show success message
                        const alert = document.createElement('div');
                        alert.className = 'alert alert-success alert-dismissible fade show mt-3';
                        alert.innerHTML = `
                            <i class="bi bi-check-circle me-2"></i>
                            <strong>${role.charAt(0).toUpperCase() + role.slice(1)} role template applied successfully!</strong>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        `;
                        document.querySelector('.container-fluid .row .col-12').appendChild(alert);
                        setTimeout(() => alert.remove(), 5000);
                    } else {
                        alert('Error applying role template: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error applying role template');
                });
            }

            function savePermissions() {
                if (permissionChanges.length === 0) {
                    alert('No changes to save');
                    return;
                }
                
                console.log('Saving', permissionChanges.length, 'permission changes:', permissionChanges);
                
                fetch('/tls/systems/user-security.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=save_permissions&user_id=${encodeURIComponent(currentUserId)}&changes=${encodeURIComponent(JSON.stringify(permissionChanges))}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update original permissions to reflect saved changes
                        permissionChanges.forEach(change => {
                            originalPermissions[change.menu] = change.granted;
                        });
                        
                        permissionChanges = []; // Clear tracked changes
                        hasUnsavedChanges = false;
                        updateUI();
                        
                        // Show success message with count
                        const alert = document.createElement('div');
                        alert.className = 'alert alert-success alert-dismissible fade show mt-3';
                        alert.innerHTML = `
                            <i class="bi bi-check-circle me-2"></i>
                            <strong>${data.message}</strong>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        `;
                        document.querySelector('.container-fluid .row .col-12').appendChild(alert);
                        setTimeout(() => alert.remove(), 5000);
                    } else {
                        alert('Error saving permissions: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error saving permissions');
                });
            }

            function updateUI() {
                saveBtn.disabled = !hasUnsavedChanges;
                resetBtn.disabled = !hasUnsavedChanges;
                saveIndicator.style.display = hasUnsavedChanges ? 'block' : 'none';
                
                // Update change counter
                const changeCounter = document.getElementById('changeCounter');
                if (changeCounter) {
                    changeCounter.textContent = permissionChanges.length;
                    changeCounter.style.display = hasUnsavedChanges ? 'inline-block' : 'none';
                }
                
                updateCategoryStats();
                updateSummaryStats();
            }

            function showLoading() {
                document.getElementById('loadingIndicator').style.display = 'block';
                document.getElementById('noUserMessage').style.display = 'none';
                document.getElementById('permissionCards').style.display = 'none';
                document.getElementById('bulkControls').style.display = 'none';
            }

            function hideLoading() {
                document.getElementById('loadingIndicator').style.display = 'none';
            }

            function showPermissions() {
                document.getElementById('permissionCards').style.display = 'block';
                document.getElementById('bulkControls').style.display = 'block';
                document.getElementById('noUserMessage').style.display = 'none';
                searchBox.disabled = false;
            }

            function hidePermissions() {
                document.getElementById('permissionCards').style.display = 'none';
                document.getElementById('bulkControls').style.display = 'none';
                document.getElementById('noUserMessage').style.display = 'block';
                searchBox.disabled = true;
                searchBox.value = '';
                currentUserId = '';
                hasUnsavedChanges = false;
                updateUI();
            }
        });
    </script>
</body>
</html>