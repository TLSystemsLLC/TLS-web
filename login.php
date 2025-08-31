<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Auth.php';

// Initialize authentication
$auth = new Auth(
    Config::get('DB_SERVER'),
    Config::get('DB_USERNAME'),
    Config::get('DB_PASSWORD')
);

// If already logged in, redirect to dashboard
if ($auth->isLoggedIn()) {
    header('Location: /dashboard.php');
    exit;
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer = trim($_POST['customer'] ?? '');
    $userId = trim($_POST['userid'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!empty($customer) && !empty($userId) && !empty($password)) {
        $result = $auth->login($customer, $userId, $password);
        
        if ($result['success']) {
            header('Location: /dashboard.php');
            exit;
        } else {
            $error = $result['message'];
        }
    } else {
        $error = 'All fields are required';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(Config::get('APP_NAME', 'TLS Operations')) ?> - Login</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header i {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 1rem;
        }
        
        .login-header h1 {
            color: #333;
            font-weight: 600;
            font-size: 1.5rem;
        }
        
        .form-control {
            border: none;
            border-bottom: 2px solid #e0e0e0;
            border-radius: 0;
            padding: 15px 0;
            background: transparent;
            transition: border-color 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: none;
            background: transparent;
        }
        
        .form-label {
            color: #666;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 50px;
            padding: 15px 30px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: transform 0.3s ease;
            width: 100%;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        
        .alert {
            border: none;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        
        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .footer-text {
            text-align: center;
            margin-top: 2rem;
            color: #666;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="bi bi-truck"></i>
            <h1><?= htmlspecialchars(Config::get('APP_NAME', 'TLS Operations')) ?></h1>
            <p class="text-muted mb-0">Transportation Management System</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="login.php" novalidate>
            <div class="mb-3">
                <label for="customer" class="form-label">
                    <i class="bi bi-building me-2"></i>Customer
                </label>
                <input 
                    type="text" 
                    class="form-control" 
                    id="customer" 
                    name="customer" 
                    value="<?= htmlspecialchars($_POST['customer'] ?? '') ?>"
                    required 
                    autofocus
                    placeholder="Enter customer database name"
                >
            </div>
            
            <div class="mb-3">
                <label for="userid" class="form-label">
                    <i class="bi bi-person me-2"></i>User ID
                </label>
                <input 
                    type="text" 
                    class="form-control" 
                    id="userid" 
                    name="userid" 
                    value="<?= htmlspecialchars($_POST['userid'] ?? '') ?>"
                    required
                    placeholder="Enter your user ID"
                >
            </div>
            
            <div class="mb-4">
                <label for="password" class="form-label">
                    <i class="bi bi-lock me-2"></i>Password
                </label>
                <input 
                    type="password" 
                    class="form-control" 
                    id="password" 
                    name="password" 
                    required
                    placeholder="Enter your password"
                >
            </div>
            
            <button type="submit" class="btn btn-primary btn-login">
                <i class="bi bi-box-arrow-in-right me-2"></i>
                Sign In
            </button>
        </form>
        
        <div class="footer-text">
            <p>&copy; <?= date('Y') ?> TLS Operations. All rights reserved.</p>
        </div>
    </div>

    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>