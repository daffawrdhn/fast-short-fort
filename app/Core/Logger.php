<?php

declare(strict_types=1);

namespace App\Core;

use Monolog\Level;
use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

class Logger
{
    private static ?MonologLogger $instance = null;

    public static function getInstance(): MonologLogger
    {
        if (self::$instance === null) {
            $logDir = dirname(__DIR__, 2) . '/storage/logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }

            self::$instance = new MonologLogger('fort');

            $level = Env::get('APP_DEBUG', 'false') === 'true' ? Level::Debug : Level::Info;

            self::$instance->pushHandler(
                new RotatingFileHandler($logDir . '/app.log', 30, $level)
            );

            self::$instance->pushHandler(
                new RotatingFileHandler($logDir . '/error.log', 30, Level::Error)
            );
        }
        return self::$instance;
    }

    public static function emergency(string|\Stringable $message, array $context = []): void
    {
        self::getInstance()->emergency($message, $context);
    }
    public static function alert(string|\Stringable $message, array $context = []): void
    {
        self::getInstance()->alert($message, $context);
    }
    public static function critical(string|\Stringable $message, array $context = []): void
    {
        self::getInstance()->critical($message, $context);
    }
    public static function error(string|\Stringable $message, array $context = []): void
    {
        self::getInstance()->error($message, $context);
    }
    public static function warning(string|\Stringable $message, array $context = []): void
    {
        self::getInstance()->warning($message, $context);
    }
    public static function notice(string|\Stringable $message, array $context = []): void
    {
        self::getInstance()->notice($message, $context);
    }
    public static function info(string|\Stringable $message, array $context = []): void
    {
        self::getInstance()->info($message, $context);
    }
    public static function debug(string|\Stringable $message, array $context = []): void
    {
        self::getInstance()->debug($message, $context);
    }
}
