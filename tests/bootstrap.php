<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/security.php';
require_once __DIR__ . '/../inc/single_session.php';
require_once __DIR__ . '/../src/ErrorHandler.php';

registerGlobalErrorHandlers('api');

if (session_status() === PHP_SESSION_NONE) {
    configureSessionSecurity();
    session_start();
}

function getTestDatabase(): database
{
    static $db = null;
    if ($db === null) {
        $db = new database();
    }
    return $db;
}

function assertApiResponse(array $response, int $expectedHttpCode = 200): void
{
    if ($expectedHttpCode === 200) {
        PHPUnit\Framework\Assert::assertTrue($response['success'] ?? false, 'Expected success=true');
    }
}
