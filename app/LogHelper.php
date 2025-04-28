<?php

declare(strict_types=1);

namespace App;

use Psr\Log\LogLevel;

use function Hyperf\Support\env;

class LogHelper
{
    private const LEVELS = [
        LogLevel::EMERGENCY,
        LogLevel::ALERT,
        LogLevel::CRITICAL,
        LogLevel::ERROR,
        LogLevel::WARNING,
        LogLevel::NOTICE,
        LogLevel::INFO,
        LogLevel::DEBUG,
    ];

    /**
     * Mapping of PSR-3 log levels to Couchbase log levels.
     *
     * @see https://github.com/couchbase/php-couchbase/blob/v3.2.2/api/couchbase.php#L5
     */
    private const COUCHBASE_LOG_LEVELS = [
        LogLevel::EMERGENCY => 'FATAL',
        LogLevel::ALERT     => 'FATAL',
        LogLevel::CRITICAL  => 'FATAL',
        LogLevel::ERROR     => 'ERROR',
        LogLevel::WARNING   => 'WARN',
        LogLevel::NOTICE    => 'INFO',
        LogLevel::INFO      => 'INFO',
        LogLevel::DEBUG     => 'DEBUG',
    ];

    protected static string $logLevel;

    /**
     * Get the log level from the environment variable LOG_LEVEL or default to WARNING.
     *
     * @return string The log level.
     */
    public static function getLogLevel(): string
    {
        if (!isset(self::$logLevel)) {
            $level          = env('LOG_LEVEL');
            self::$logLevel = in_array($level, self::LEVELS, true) ? $level : LogLevel::WARNING;
        }

        return self::$logLevel;
    }

    /**
     * Get all log levels that are equal to or more severe than the given level.
     *
     * @return string[] List of log levels.
     */
    public static function getLevelsAboveOrEqualTo(): array
    {
        $level = self::getLogLevel();
        $index = array_search($level, self::LEVELS, true);

        return array_slice(self::LEVELS, 0, $index + 1);
    }

    /**
     * Get the Couchbase log level corresponding to the current log level.
     *
     * @return string The Couchbase log level.
     */
    public static function getCouchbaseLogLevel(): string
    {
        return self::COUCHBASE_LOG_LEVELS[self::getLogLevel()];
    }
}
