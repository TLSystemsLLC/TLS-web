<?php
declare(strict_types=1);

/**
 * Date Helper Class
 * Provides timezone-aware date formatting for the TLS application
 * 
 * @author Tony Lyle
 * @version 1.0
 */
class DateHelper
{
    private const TIMEZONE = 'America/Chicago'; // Central Time Zone

    /**
     * Format timestamp for dashboard display
     * 
     * @param int $timestamp Unix timestamp
     * @return string Formatted date in Central Time
     */
    public static function formatDashboard(int $timestamp): string
    {
        $dateTime = new DateTime();
        $dateTime->setTimestamp($timestamp);
        $dateTime->setTimezone(new DateTimeZone(self::TIMEZONE));
        
        return $dateTime->format('M j, Y g:i A');
    }

    /**
     * Format current date/time for display
     * 
     * @param string $format PHP date format string
     * @return string Formatted current date/time in Central Time
     */
    public static function formatNow(string $format = 'M j, Y g:i A'): string
    {
        $dateTime = new DateTime('now', new DateTimeZone(self::TIMEZONE));
        return $dateTime->format($format);
    }

    /**
     * Format timestamp with custom format
     * 
     * @param int $timestamp Unix timestamp
     * @param string $format PHP date format string
     * @return string Formatted date in Central Time
     */
    public static function format(int $timestamp, string $format): string
    {
        $dateTime = new DateTime();
        $dateTime->setTimestamp($timestamp);
        $dateTime->setTimezone(new DateTimeZone(self::TIMEZONE));
        
        return $dateTime->format($format);
    }

    /**
     * Get current timezone
     * 
     * @return string Timezone identifier
     */
    public static function getTimezone(): string
    {
        return self::TIMEZONE;
    }
}