<?php
declare(strict_types=1);

putenv('APP_ENV=testing');
putenv('DB_NAME=kick_off_test');
putenv('PAYMENT_MODE=sandbox');
putenv('CLICKPESA_API_KEY=test-key');
putenv('CLICKPESA_API_SECRET=test-secret');
putenv('CLICKPESA_WEBHOOK_SECRET=test-webhook');
putenv('APP_URL=http://localhost/kickoff5');
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_NAME'] = 'kick_off_test';
$_ENV['APP_URL'] = 'http://localhost/kickoff5';

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Core/Logger.php';

// Disable standard mail for testing
if (!function_exists('mail_test_mock')) {
    function mail_test_mock(...$args) {
        return true;
    }
}
