<?php

declare(strict_types=1);

namespace App;

use Psr\Log\LogLevel;

use function Hyperf\Support\env;

class LogHelper
{
    /**
     * Default log level if not set in the environment.
     */
    protected const DEFAULT_LOG_LEVEL = LogLevel::ERROR;

    private const LOG_LEVELS = [
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

    /**
     * Mapping of PSR-3 log levels to Swoole log levels.
     *
     * @see https://github.com/swoole/ide-helper/blob/5.1.7/src/swoole/constants.php#L251
     */
    private const SWOOLE_LOG_LEVELS = [
        LogLevel::EMERGENCY => SWOOLE_LOG_ERROR,
        LogLevel::ALERT     => SWOOLE_LOG_ERROR,
        LogLevel::CRITICAL  => SWOOLE_LOG_ERROR,
        LogLevel::ERROR     => SWOOLE_LOG_ERROR,
        LogLevel::WARNING   => SWOOLE_LOG_WARNING,
        LogLevel::NOTICE    => SWOOLE_LOG_NOTICE,
        LogLevel::INFO      => SWOOLE_LOG_INFO,
        LogLevel::DEBUG     => SWOOLE_LOG_DEBUG,
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
            self::$logLevel = in_array($level, self::LOG_LEVELS, true) ? $level : self::DEFAULT_LOG_LEVEL;
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
        $index = array_search($level, self::LOG_LEVELS, true);

        return array_slice(self::LOG_LEVELS, 0, $index + 1);
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

    /**
     * Get the Swoole log level corresponding to the current log level.
     *
     * @return int The Swoole log level.
     */
    public static function getSwooleLogLevel(): int
    {
        return self::SWOOLE_LOG_LEVELS[self::getLogLevel()];
    }
}
