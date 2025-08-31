<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/classes/MenuManager.php';

// Initialize authentication
$auth = new Auth(
    Config::get('DB_SERVER'),
    Config::get('DB_USERNAME'),
    Config::get('DB_PASSWORD')
);

// Require authentication
$auth->requireAuth('/login.php');

// Get current user
$user = $auth->getCurrentUser();

// Initialize menu manager
$menuManager = new MenuManager($auth);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(Config::get('APP_NAME', 'TLS Operations')) ?> - Dashboard</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        .sidebar {
            width: 280px;
            position: fixed;
            top: 80px;
            bottom: 0;
            left: 0;
            z-index: 100;
            overflow-y: auto;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 20px;
        }
        
        .dashboard-card {
            transition: transform 0.2s ease-in-out;
            cursor: pointer;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .stats-card .card-body {
            position: relative;
            overflow: hidden;
        }
        
        .stats-card .card-body::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transform: rotate(45deg);
        }
        
        .quick-access-card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .quick-access-card .card-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 2px solid #dee2e6;
        }
        
        .nav-link:hover {
            background-color: rgba(0,0,0,0.05);
            border-radius: 5px;
        }
        
        .collapse .nav-link {
            font-size: 0.9rem;
            padding-left: 2rem;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                position: relative;
                width: 100%;
                top: 0;
                margin-bottom: 20px;
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Main Navigation -->
    <?= $menuManager->generateMainMenu() ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar Navigation -->
            <div class="col-md-2 d-none d-md-block p-0">
                <?= $menuManager->generateSidebarMenu() ?>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <!-- Welcome Section -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h1 class="h3 mb-1">Welcome, <?= htmlspecialchars($user['user_id']) ?>!</h1>
                                <p class="text-muted">Connected to database: <strong><?= htmlspecialchars($user['customer_db']) ?></strong></p>
                            </div>
                            <div class="text-end">
                                <small class="text-muted">Last login: <?= date('M j, Y g:i A', $user['login_time']) ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="bi bi-truck display-4 mb-2"></i>
                                <h5 class="card-title">Active Loads</h5>
                                <h3 class="mb-0">--</h3>
                                <small class="opacity-75">Awaiting implementation</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="bi bi-people display-4 mb-2"></i>
                                <h5 class="card-title">Active Drivers</h5>
                                <h3 class="mb-0">--</h3>
                                <small class="opacity-75">Awaiting implementation</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="bi bi-cash-stack display-4 mb-2"></i>
                                <h5 class="card-title">Pending Invoices</h5>
                                <h3 class="mb-0">--</h3>
                                <small class="opacity-75">Awaiting implementation</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="bi bi-exclamation-triangle display-4 mb-2"></i>
                                <h5 class="card-title">Exceptions</h5>
                                <h3 class="mb-0">--</h3>
                                <small class="opacity-75">Awaiting implementation</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Access Section -->
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card quick-access-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-lightning-charge me-2"></i>Quick Access - Dispatch
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <a href="/dispatch/load-entry.php" class="btn btn-outline-primary w-100">
                                            <i class="bi bi-plus-circle me-2"></i>Load Entry
                                        </a>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <a href="/dispatch/available-loads.php" class="btn btn-outline-primary w-100">
                                            <i class="bi bi-list-ul me-2"></i>Available Loads
                                        </a>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <a href="/dispatch/load-inquiry.php" class="btn btn-outline-primary w-100">
                                            <i class="bi bi-search me-2"></i>Load Inquiry
                                        </a>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <a href="/dispatch/daily-count.php" class="btn btn-outline-primary w-100">
                                            <i class="bi bi-calendar-day me-2"></i>Daily Count
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <div class="card quick-access-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-calculator me-2"></i>Quick Access - Accounting
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <a href="/accounting/voucher-entry.php" class="btn btn-outline-success w-100">
                                            <i class="bi bi-receipt me-2"></i>Voucher Entry
                                        </a>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <a href="/accounting/deposit-entry.php" class="btn btn-outline-success w-100">
                                            <i class="bi bi-bank me-2"></i>Deposit Entry
                                        </a>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <a href="/accounting/collections.php" class="btn btn-outline-success w-100">
                                            <i class="bi bi-telephone me-2"></i>Collections
                                        </a>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <a href="/accounting/journal-entry.php" class="btn btn-outline-success w-100">
                                            <i class="bi bi-journal-plus me-2"></i>Journal Entry
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="row">
                    <div class="col-12">
                        <div class="card quick-access-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-clock-history me-2"></i>System Status
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 text-center">
                                        <div class="mb-3">
                                            <i class="bi bi-check-circle-fill text-success display-6"></i>
                                            <h6 class="mt-2">Database Connection</h6>
                                            <p class="text-muted mb-0">Connected and Active</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-center">
                                        <div class="mb-3">
                                            <i class="bi bi-gear-fill text-warning display-6"></i>
                                            <h6 class="mt-2">System Status</h6>
                                            <p class="text-muted mb-0">Under Development</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-center">
                                        <div class="mb-3">
                                            <i class="bi bi-shield-check text-primary display-6"></i>
                                            <h6 class="mt-2">Security</h6>
                                            <p class="text-muted mb-0">Menu Access Configured</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Auto-refresh dashboard statistics (placeholder for future implementation)
        function refreshStats() {
            // This will be implemented when we have actual data endpoints
            console.log('Stats refresh triggered');
        }
        
        // Refresh every 5 minutes
        setInterval(refreshStats, 300000);
        
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>