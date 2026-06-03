<?php

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ApiResponse::error('BAD_REQUEST', 'Apenas GET é permitido');
    }

    configureSessionSecurity();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['usuario'])) {
        ApiResponse::unauthorized();
    }

    $db = db();

    $usuarioId = currentUserId();
    $sessionToken = $_SESSION['session_token'] ?? null;

    $tempo_maximo = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 3600;
    $now = time();

    if (!isset($_SESSION['session_started_at'])) {
        $_SESSION['session_started_at'] = $now;
    }
    if (defined('SESSION_ABSOLUTE_TIMEOUT') && SESSION_ABSOLUTE_TIMEOUT > 0) {
        $elapsed = $now - (int)$_SESSION['session_started_at'];
        if ($elapsed >= SESSION_ABSOLUTE_TIMEOUT) {
            invalidateUniqueSession($db, $usuarioId, $sessionToken);
            clearLocalSessionState();
            ApiResponse::error('UNAUTHORIZED', 'Sessão expirada por tempo máximo.', ['status' => 'expired', 'reason' => 'absolute_timeout'], 401);
        }
    }

    if (!isset($_SESSION['ultimo_acesso'])) {
        $_SESSION['ultimo_acesso'] = $now;
        ApiResponse::success(['status' => 'active', 'time_left' => $tempo_maximo]);
    }

    $inatividade = $now - (int)$_SESSION['ultimo_acesso'];

    if ($inatividade >= $tempo_maximo) {
        invalidateUniqueSession($db, $usuarioId, $sessionToken);
        clearLocalSessionState();
        ApiResponse::error('UNAUTHORIZED', 'Sessão expirada por inatividade.', ['status' => 'expired'], 401);
    }

    $tempo_restante = max(0, $tempo_maximo - $inatividade);
    ApiResponse::success(['status' => 'active', 'time_left' => $tempo_restante]);

} catch (Throwable $e) {
    error_log('[session_check] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao verificar sessão.');
}
