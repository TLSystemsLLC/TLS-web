<?php
declare(strict_types=1);

/**
 * Session Manager
 * Handles secure session management with proper configuration
 * 
 * @author Tony Lyle
 * @version 1.0
 */
class Session
{
    private bool $started = false;

    /**
     * Constructor - configure secure session settings
     */
    public function __construct()
    {
        $this->configureSession();
    }

    /**
     * Configure secure session settings
     */
    private function configureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Secure session configuration
            ini_set('session.cookie_httponly', '1');
            // Only require secure cookies on HTTPS (not for localhost development)
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? '1' : '0');
            ini_set('session.use_only_cookies', '1');
            // Use Lax for development compatibility with Safari, Strict for production
            $samesite = (isset($_SERVER['HTTPS']) || $_SERVER['SERVER_NAME'] !== 'localhost') ? 'Strict' : 'Lax';
            ini_set('session.cookie_samesite', $samesite);
            ini_set('session.gc_maxlifetime', (string)(Config::get('SESSION_TIMEOUT', 3600)));
            
            // Session name
            session_name('TLS_SESSION');
        }
    }

    /**
     * Start session if not already started
     */
    public function start(): void
    {
        if (!$this->started && session_status() === PHP_SESSION_NONE) {
            if (session_start()) {
                $this->started = true;
                
                // Regenerate session ID periodically for security
                if (!$this->has('_session_created')) {
                    session_regenerate_id(true);
                    $this->set('_session_created', time());
                } elseif ((time() - $this->get('_session_created', 0)) > 300) {
                    // Regenerate every 5 minutes
                    session_regenerate_id(true);
                    $this->set('_session_created', time());
                }
            }
        }
    }

    /**
     * Set session value
     * 
     * @param string $key Session key
     * @param mixed $value Session value
     */
    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    /**
     * Get session value
     * 
     * @param string $key Session key
     * @param mixed $default Default value if key not found
     * @return mixed Session value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if session key exists
     * 
     * @param string $key Session key
     * @return bool True if key exists
     */
    public function has(string $key): bool
    {
        $this->start();
        return isset($_SESSION[$key]);
    }

    /**
     * Remove session key
     * 
     * @param string $key Session key
     */
    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    /**
     * Clear all session data
     */
    public function clear(): void
    {
        $this->start();
        $_SESSION = [];
    }

    /**
     * Destroy session completely
     */
    public function destroy(): void
    {
        $this->start();
        
        // Clear session data
        $_SESSION = [];
        
        // Delete session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(
                session_name(),
                '',
                time() - 3600,
                '/',
                '',
                true,
                true
            );
        }
        
        // Destroy session
        session_destroy();
        $this->started = false;
    }

    /**
     * Get session ID
     * 
     * @return string Session ID
     */
    public function getId(): string
    {
        $this->start();
        return session_id();
    }

    /**
     * Check if session is active
     * 
     * @return bool True if session is active
     */
    public function isActive(): bool
    {
        return $this->started && session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * Get all session data (for debugging)
     * 
     * @return array All session data
     */
    public function all(): array
    {
        $this->start();
        return $_SESSION;
    }
}