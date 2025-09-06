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
$auth->requireAuth('/tls/login.php');

// Get current user
$user = $auth->getCurrentUser();

// Initialize menu manager
$menuManager = new MenuManager($auth);

// Get menu name from query parameter
$menuName = $_GET['menu'] ?? 'Unknown Feature';
$menuName = htmlspecialchars($menuName);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(Config::get('APP_NAME', 'TLS Operations')) ?> - Under Development</title>
    
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
        
        .under-development-card {
            background: linear-gradient(135deg, #ffc107 0%, #ff8f00 100%);
            border: none;
            color: white;
            text-align: center;
            padding: 3rem 2rem;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .under-development-card i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
        }
        
        .feature-list {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
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
                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="mb-4">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/tls/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Under Development</li>
                    </ol>
                </nav>
                
                <!-- Under Development Message -->
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="under-development-card">
                            <i class="bi bi-tools"></i>
                            <h1 class="display-6 mb-3">Feature Under Development</h1>
                            <h4 class="mb-3"><?= $menuName ?></h4>
                            <p class="lead mb-4">
                                This feature is currently under development. 
                                We're working hard to bring you enhanced functionality with a modern, 
                                user-friendly interface.
                            </p>
                            <div class="d-flex justify-content-center gap-3">
                                <a href="/tls/dashboard.php" class="btn btn-light btn-lg">
                                    <i class="bi bi-house-door me-2"></i>Return to Dashboard
                                </a>
                                <button type="button" class="btn btn-outline-light btn-lg" onclick="history.back()">
                                    <i class="bi bi-arrow-left me-2"></i>Go Back
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Development Progress -->
                <div class="row mt-5">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-check-circle me-2"></i>Completed Features
                                </h5>
                            </div>
                            <div class="card-body feature-list">
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-2">
                                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                                        User Authentication System
                                    </li>
                                    <li class="mb-2">
                                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                                        Menu Security Integration
                                    </li>
                                    <li class="mb-2">
                                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                                        Database Connection Management
                                    </li>
                                    <li class="mb-2">
                                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                                        Responsive Navigation System
                                    </li>
                                    <li class="mb-2">
                                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                                        Session Management
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-gear me-2"></i>In Development
                                </h5>
                            </div>
                            <div class="card-body feature-list">
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-2">
                                        <i class="bi bi-clock text-warning me-2"></i>
                                        Core Business Functions
                                    </li>
                                    <li class="mb-2">
                                        <i class="bi bi-clock text-warning me-2"></i>
                                        Advanced Reporting
                                    </li>
                                    <li class="mb-2">
                                        <i class="bi bi-clock text-warning me-2"></i>
                                        Enhanced User Interface
                                    </li>
                                    <li class="mb-2">
                                        <i class="bi bi-clock text-warning me-2"></i>
                                        Mobile Optimization
                                    </li>
                                    <li class="mb-2">
                                        <i class="bi bi-clock text-warning me-2"></i>
                                        Workflow Improvements
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Contact Information -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="alert alert-info" role="alert">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Need this feature urgently?</strong> 
                            Please contact your system administrator to discuss prioritization of specific features.
                            All functionality will maintain full compatibility with your existing business processes.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>