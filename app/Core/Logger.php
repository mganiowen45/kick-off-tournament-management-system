<?php
declare(strict_types=1);

namespace App\Core;

final class Logger
{
    private static string $logDir = __DIR__ . '/../../storage/logs';
    private static string $requestId = '';

    public static function init(): void
    {
        if (!is_dir(self::$logDir)) {
            mkdir(self::$logDir, 0755, true);
        }
        if (self::$requestId === '') {
            self::$requestId = bin2hex(random_bytes(8));
        }
    }

    public static function log(string $level, string $component, string $message, array $context = []): void
    {
        self::init();

        $logFile = self::$logDir . '/operational-' . date('Y-m-d') . '.log';
        
        $entry = [
            'timestamp' => date('Y-m-d\TH:i:sP'),
            'level' => strtoupper($level),
            'request_id' => self::$requestId,
            'component' => $component,
            'message' => $message,
        ];

        if (!empty($context)) {
            // Strip any known sensitive keys
            foreach (['password', 'token', 'secret', 'key'] as $sensitive) {
                if (isset($context[$sensitive])) {
                    $context[$sensitive] = '***';
                }
            }
            $entry['context'] = $context;
        }

        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }

    public static function debug(string $component, string $message, array $context = []): void
    {
        // Suppress debug in production if needed, for now just log it
        self::log('DEBUG', $component, $message, $context);
    }

    public static function info(string $component, string $message, array $context = []): void
    {
        self::log('INFO', $component, $message, $context);
    }

    public static function warning(string $component, string $message, array $context = []): void
    {
        self::log('WARNING', $component, $message, $context);
    }

    public static function error(string $component, string $message, array $context = []): void
    {
        self::log('ERROR', $component, $message, $context);
    }

    public static function critical(string $component, string $message, array $context = []): void
    {
        self::log('CRITICAL', $component, $message, $context);
    }

    public static function cleanOldLogs(int $daysToKeep = 14): void
    {
        self::init();
        $files = glob(self::$logDir . '/operational-*.log');
        $now = time();
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > ($daysToKeep * 86400)) {
                unlink($file);
            }
        }
    }
}
