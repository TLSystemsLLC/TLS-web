<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Session.php';

/**
 * Authentication Manager
 * Handles user login, logout, and session management
 * 
 * @author Tony Lyle
 * @version 1.0
 */
class Auth
{
    private Database $db;
    private Session $session;

    /**
     * Constructor
     * 
     * @param string $server Database server
     * @param string $username Database username  
     * @param string $password Database password
     */
    public function __construct(string $server, string $username, string $password)
    {
        $this->db = new Database($server, $username, $password);
        $this->session = new Session();
    }

    /**
     * Authenticate user login
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

            // Validate customer database name (basic security check)
            if (!$this->isValidDatabaseName($customer)) {
                return [
                    'success' => false,
                    'message' => 'Invalid customer database specified'
                ];
            }

            // Connect to customer database
            $this->db->connect($customer);

            // Execute login procedure
            $returnCode = $this->db->executeStoredProcedureWithReturn('spUser_Login', [
                $userId,
                $password
            ]);

            if ($returnCode === 0) {
                // Login successful - get user menus
                $menus = $this->getUserMenus($userId);
                
                // Create user session
                $this->session->start();
                $this->session->set('user_id', $userId);
                $this->session->set('customer_db', $customer);
                $this->session->set('user_menus', $menus);
                $this->session->set('login_time', time());
                
                // Log successful login
                error_log("Successful login: User '{$userId}' to database '{$customer}'");
                
                return [
                    'success' => true,
                    'message' => 'Login successful',
                    'user_id' => $userId,
                    'customer' => $customer,
                    'menus' => $menus
                ];
            } else {
                // Login failed
                error_log("Failed login attempt: User '{$userId}' to database '{$customer}' - Code: {$returnCode}");
                
                return [
                    'success' => false,
                    'message' => 'Invalid username or password'
                ];
            }

        } catch (DatabaseException $e) {
            error_log('Login database error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Database connection error. Please contact system administrator.'
            ];
        } catch (Exception $e) {
            error_log('Login error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred during login. Please try again.'
            ];
        } finally {
            $this->db->disconnect();
        }
    }

    /**
     * Get user menu permissions
     * 
     * @param string $userId User ID
     * @return array User menu permissions
     */
    private function getUserMenus(string $userId): array
    {
        try {
            $results = $this->db->executeStoredProcedure('spUser_Menus', [$userId]);
            
            $menus = [];
            foreach ($results as $row) {
                $menus[] = $row['MenuName'];
            }
            
            return $menus;
            
        } catch (Exception $e) {
            error_log("Error getting user menus for '{$userId}': " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if user has access to specific menu
     * 
     * @param string $menuName Menu name to check
     * @return bool True if user has access
     */
    public function hasMenuAccess(string $menuName): bool
    {
        if (!$this->isLoggedIn()) {
            return false;
        }

        $userId = $this->session->get('user_id');
        $customerDb = $this->session->get('customer_db');
        
        try {
            $this->db->connect($customerDb);
            $returnCode = $this->db->executeStoredProcedureWithReturn('spUser_Menu', [
                $userId,
                $menuName
            ]);
            
            return $returnCode === 0;
            
        } catch (Exception $e) {
            error_log("Error checking menu access for '{$userId}', menu '{$menuName}': " . $e->getMessage());
            return false;
        } finally {
            $this->db->disconnect();
        }
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
        
        error_log("User logout: '{$userId}' from database '{$customerDb}'");
    }

    /**
     * Validate database name for security
     * 
     * @param string $dbName Database name
     * @return bool True if valid
     */
    private function isValidDatabaseName(string $dbName): bool
    {
        // Basic validation - alphanumeric, underscore, hyphen only
        return preg_match('/^[a-zA-Z0-9_-]+$/', $dbName) === 1;
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