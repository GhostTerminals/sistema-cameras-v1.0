<?php

try {
    configureSessionSecurity();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['usuario']) || !userHasAccess('admin')) {
        ApiResponse::unauthorized();
    }

    function checkTable(database $db, string $table): bool
    {
        static $allowedTables = ['auditoria_eventos', 'login_attempts', 'user_sessions'];
        if (!in_array($table, $allowedTables, true)) {
            error_log('Tentativa de acesso a tabela não permitida na health check: ' . $table);
            return false;
        }
        $result = $db->query("SELECT 1 FROM `{$table}` LIMIT 1");
        return ($result['status'] ?? '') === 'success';
    }

    $health = [
        'status' => 'ok',
        'environment' => defined('ENVIRONMENT') ? ENVIRONMENT : 'unknown',
        'checks' => [
            'db_connection' => false,
            'table_auditoria_eventos' => false,
            'table_login_attempts' => false,
            'table_user_sessions' => false,
            'https' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]
    ];

    try {
        $db = db();
        $health['checks']['db_connection'] = true;
        $health['checks']['table_auditoria_eventos'] = checkTable($db, 'auditoria_eventos');
        $health['checks']['table_login_attempts'] = checkTable($db, 'login_attempts');
        $health['checks']['table_user_sessions'] = checkTable($db, 'user_sessions');
    } catch (Throwable $e) {
        $health['status'] = 'degraded';
    }

    ApiResponse::success($health);

} catch (Throwable $e) {
    error_log('[api_health] ' . $e->getMessage());
    ApiResponse::internalError();
}
