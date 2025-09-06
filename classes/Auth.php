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

            // Validate customer ID against master database
            if (!$this->isValidCustomerId($customer)) {
                return [
                    'success' => false,
                    'message' => 'Invalid customer ID specified'
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
                // Login successful - get user menus, details, and company info
                $menus = $this->getUserMenus($userId);
                $userDetails = $this->getUserDetails($userId);
                $companyInfo = $this->getCompanyInfo();
                
                // Create user session
                $this->session->start();
                $this->session->set('user_id', $userId);
                $this->session->set('customer_db', $customer);
                $this->session->set('user_menus', $menus);
                $this->session->set('user_details', $userDetails);
                $this->session->set('company_info', $companyInfo);
                $this->session->set('login_time', time());
                
                // Log successful login
                error_log("Successful login: User '{$userId}' to database '{$customer}'");
                
                return [
                    'success' => true,
                    'message' => 'Login successful',
                    'user_id' => $userId,
                    'customer' => $customer,
                    'menus' => $menus,
                    'user_details' => $userDetails
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
                $menus[] = trim($row['MenuName']); // Trim whitespace from menu names
            }
            
            return $menus;
            
        } catch (Exception $e) {
            error_log("Error getting user menus for '{$userId}': " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get user details from spUser_GetUser
     * 
     * @param string $userId User ID
     * @return array User details including UserName (display name)
     */
    private function getUserDetails(string $userId): array
    {
        try {
            // spUser_GetUser uses OUTPUT parameters, not a result set
            $sql = "DECLARE @TeamKey INT, @UserName VARCHAR(35), @PasswordChanged DATETIME, @Extension INT, @Email VARCHAR(50); " .
                   "EXEC spUser_GetUser ?, @TeamKey OUTPUT, @UserName OUTPUT, @PasswordChanged OUTPUT, @Extension OUTPUT, @Email OUTPUT; " .
                   "SELECT @TeamKey as TeamKey, @UserName as UserName, @PasswordChanged as PasswordChanged, @Extension as Extension, @Email as Email";
            
            $results = $this->db->query($sql, [$userId]);
            
            if (!empty($results)) {
                return $results[0]; // Return first row with user details
            }
            
            return [];
            
        } catch (Exception $e) {
            error_log("Error getting user details for '{$userId}': " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get company information using spCompany_Get
     * 
     * @return array Company details
     */
    private function getCompanyInfo(): array
    {
        try {
            $results = $this->db->executeStoredProcedure('spCompany_Get', [1]);
            
            if (!empty($results)) {
                return $results[0]; // Return first row with company details
            }
            
            return [];
            
        } catch (Exception $e) {
            error_log("Error getting company info: " . $e->getMessage());
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

        $userDetails = $this->session->get('user_details', []);
        $companyInfo = $this->session->get('company_info', []);
        
        return [
            'user_id' => $this->session->get('user_id'),
            'customer_db' => $this->session->get('customer_db'),
            'menus' => $this->session->get('user_menus', []),
            'login_time' => $this->session->get('login_time'),
            'user_name' => $userDetails['UserName'] ?? $this->session->get('user_id'), // Use UserName from spUser_GetUser or fallback to user_id
            'user_details' => $userDetails,
            'company_info' => $companyInfo,
            'company_name' => $companyInfo['CompanyName'] ?? $this->session->get('customer_db') // Use CompanyName or fallback to customer_db
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
     * Validate customer ID against master database operations list
     * 
     * @param string $customerId Customer ID
     * @return bool True if valid customer ID
     */
    private function isValidCustomerId(string $customerId): bool
    {
        // Basic validation first - alphanumeric, underscore, hyphen only
        if (preg_match('/^[a-zA-Z0-9_-]+$/', $customerId) !== 1) {
            return false;
        }

        try {
            // Create separate database connection for validation
            $masterDb = new Database(
                Config::get('DB_SERVER'),
                Config::get('DB_USERNAME'),
                Config::get('DB_PASSWORD')
            );
            $masterDb->connect('master');
            
            // Get valid operations databases
            $validDatabases = $masterDb->executeStoredProcedure('spGetOperationsDB', []);
            
            $masterDb->disconnect();
            
            // Check if customer ID is in the valid list
            foreach ($validDatabases as $db) {
                if (strtoupper($db['name']) === strtoupper($customerId)) {
                    return true;
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Customer ID validation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Require authentication - redirect if not logged in
     * 
     * @param string $redirectUrl URL to redirect to for login
     */
    public function requireAuth(string $redirectUrl = '/tls/login.php'): void
    {
        if (!$this->isLoggedIn()) {
            header("Location: {$redirectUrl}");
            exit;
        }
    }
}