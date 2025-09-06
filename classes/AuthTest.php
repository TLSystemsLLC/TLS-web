<?php
declare(strict_types=1);

require_once __DIR__ . '/Session.php';

/**
 * Test Authentication Manager
 * Handles user login without database for testing purposes
 * 
 * @author Tony Lyle
 * @version 1.0
 */
class AuthTest
{
    private Session $session;

    /**
     * Constructor
     * 
     * @param string $server Database server (ignored for testing)
     * @param string $username Database username (ignored for testing)
     * @param string $password Database password (ignored for testing)
     */
    public function __construct(string $server, string $username, string $password)
    {
        $this->session = new Session();
    }

    /**
     * Authenticate user login (test version)
     * 
     * @param string $customer Database name (customer)
     * @param string $userId User ID
     * @param string $password User password
     * @return array Login result with success status and user data
     */
    public function login(string $customer, string $userId, string $password): array
    {
        try {
            // Input validation
            if (empty($customer) || empty($userId) || empty($password)) {
                return [
                    'success' => false,
                    'message' => 'All fields are required'
                ];
            }

            // Test credentials - allow any user with password "test"
            if ($password === 'test') {
                // Create test user menus
                $menus = [
                    'mnuCOAMaint', 'mnuJE', 'mnuVoucher', 'mnuCollections', 
                    'mnuLoadEntry', 'mnuAvailLoads', 'mnuDailyCount',
                    'mnuAgentMaint', 'mnuDriverMaint', 'mnuUnitMaint'
                ];
                
                // Create user session
                $this->session->start();
                $this->session->set('user_id', $userId);
                $this->session->set('customer_db', $customer);
                $this->session->set('user_menus', $menus);
                $this->session->set('login_time', time());
                
                // Log successful login
                error_log("TEST: Successful login: User '{$userId}' to database '{$customer}'");
                
                return [
                    'success' => true,
                    'message' => 'Login successful (TEST MODE)',
                    'user_id' => $userId,
                    'customer' => $customer,
                    'menus' => $menus
                ];
            } else {
                error_log("TEST: Failed login attempt: User '{$userId}' to database '{$customer}'");
                
                return [
                    'success' => false,
                    'message' => 'Invalid username or password (use "test" as password for testing)'
                ];
            }

        } catch (Exception $e) {
            error_log('TEST: Login error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred during login. Please try again.'
            ];
        }
    }

    /**
     * Check if user has access to specific menu (test version)
     * 
     * @param string $menuName Menu name to check
     * @return bool True if user has access
     */
    public function hasMenuAccess(string $menuName): bool
    {
        if (!$this->isLoggedIn()) {
            return false;
        }

        // For testing, grant access to most menus
        $restrictedMenus = ['secAccountingSecurity', 'secAllChecks', 'secPostAP'];
        
        return !in_array($menuName, $restrictedMenus);
    }

    /**
     * Check if user is logged in
     * 
     * @return bool True if user is logged in
     */
    public function isLoggedIn(): bool
    {
        $this->session->start();
        
        if (!$this->session->has('user_id') || !$this->session->has('customer_db')) {
            return false;
        }

        // Check session timeout
        $loginTime = $this->session->get('login_time', 0);
        $sessionTimeout = (int)Config::get('SESSION_TIMEOUT', 3600);
        
        if ((time() - $loginTime) > $sessionTimeout) {
            $this->logout();
            return false;
        }

        return true;
    }

    /**
     * Get current user information
     * 
     * @return array|null User information or null if not logged in
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        return [
            'user_id' => $this->session->get('user_id'),
            'customer_db' => $this->session->get('customer_db'),
            'menus' => $this->session->get('user_menus', []),
            'login_time' => $this->session->get('login_time')
        ];
    }

    /**
     * Logout user
     */
    public function logout(): void
    {
        $userId = $this->session->get('user_id', 'unknown');
        $customerDb = $this->session->get('customer_db', 'unknown');
        
        $this->session->destroy();
        
        error_log("TEST: User logout: '{$userId}' from database '{$customerDb}'");
    }

    /**
     * Require authentication - redirect if not logged in
     * 
     * @param string $redirectUrl URL to redirect to for login
     */
    public function requireAuth(string $redirectUrl = '/login.php'): void
    {
        if (!$this->isLoggedIn()) {
            header("Location: {$redirectUrl}");
            exit;
        }
    }
}