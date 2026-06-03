<?php

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiResponse::error('BAD_REQUEST', 'Apenas POST é permitido.');
    }

    configureSessionSecurity();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['usuario'])) {
        ApiResponse::unauthorized();
    }

    $userRole = $_SESSION['usuario']->nivel_acesso ?? ($_SESSION['usuario']->nivel ?? ($_SESSION['usuario']->role ?? ''));
    $allowedRoles = ['admin'];
    if (!in_array($userRole, $allowedRoles)) {
        ApiResponse::unauthorized();
    }

    clearLocalSessionState();

    ApiResponse::success(['message' => 'Sessão limpa.']);
} catch (Throwable $e) {
    error_log('[limpar_sessoes_orfas] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao limpar sessões órfãs.');
}
