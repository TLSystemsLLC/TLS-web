<?php
declare(strict_types=1);

/**
 * TLS Operations Configuration Manager
 * Handles loading and managing application configuration from .env file
 * 
 * @author Tony Lyle
 * @version 1.0
 */
class Config
{
    private static array $config = [];
    private static bool $loaded = false;

    /**
     * Load configuration from .env file
     * 
     * @param string $envFile Path to .env file
     * @throws Exception if .env file not found or invalid
     */
    public static function load(string $envFile = __DIR__ . '/.env'): void
    {
        if (self::$loaded) {
            return;
        }

        if (!file_exists($envFile)) {
            throw new Exception('.env file not found at: ' . $envFile);
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse key=value pairs
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                
                self::$config[$key] = $value;
                
                // Set as environment variable if not already set
                if (!isset($_ENV[$key])) {
                    $_ENV[$key] = $value;
                }
            }
        }

        self::$loaded = true;
    }

    /**
     * Get configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $default Default value if key not found
     * @return mixed Configuration value
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$config[$key] ?? $default;
    }

    /**
     * Get all configuration values
     * 
     * @return array All configuration values
     */
    public static function all(): array
    {
        return self::$config;
    }

    /**
     * Check if configuration key exists
     * 
     * @param string $key Configuration key
     * @return bool True if key exists
     */
    public static function has(string $key): bool
    {
        return array_key_exists($key, self::$config);
    }

    /**
     * Set configuration value (runtime only)
     * 
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     */
    public static function set(string $key, mixed $value): void
    {
        self::$config[$key] = $value;
    }
}

// Set timezone first before loading any other configuration
ini_set('date.timezone', 'America/Chicago');
date_default_timezone_set('America/Chicago'); // Central Time Zone

// Load configuration on include
try {
    Config::load();
} catch (Exception $e) {
    error_log('Configuration loading failed: ' . $e->getMessage());
    // Set minimal defaults for development
    Config::set('DB_SERVER', '35.226.40.170');
    Config::set('DB_USERNAME', 'Admin');
    Config::set('DB_PASSWORD', 'Aspen4Home!');
    Config::set('DB_PORT', '1433');
    Config::set('APP_NAME', 'TLS Operations');
    Config::set('APP_DEBUG', 'true');
    Config::set('APP_TIMEZONE', 'America/Chicago'); // Central Time Zone
}

// Ensure timezone is set correctly
$timezone = Config::get('APP_TIMEZONE', 'America/Chicago');
date_default_timezone_set($timezone);