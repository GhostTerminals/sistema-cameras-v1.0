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
    $result = $db->query("SELECT id, nome FROM catalogo_modelos_alarmes ORDER BY nome ASC");

    if ($result['status'] === 'success') {
        ApiResponse::success(['modelos' => $result['data'] ?? []]);
    }

    ApiResponse::internalError('Erro ao buscar modelos de alarmes.');

} catch (Throwable $e) {
    error_log('[api_modelos_alarmes] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao buscar modelos de alarmes.');
}
