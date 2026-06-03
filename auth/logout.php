<?php
require_once __DIR__ . '/../config/app.php';
configureSessionSecurity();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../inc/security.php';
require_once __DIR__ . '/../inc/single_session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?page=home');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? null;
if (!validateCsrfToken($csrfToken)) {
    header('Location: index.php?page=home');
    exit;
}

$usuarioId = currentUserId();
$sessionToken = $_SESSION['session_token'] ?? null;
if ($usuarioId) {
    try {
        $db = db();
        $logoutAll = isset($_POST['logout_all']) && $_POST['logout_all'] === '1';
        if ($logoutAll) {
            invalidateUniqueSession($db, $usuarioId);
        } else {
            invalidateUniqueSession($db, $usuarioId, $sessionToken);
        }
    } catch (Throwable $e) {
        error_log('Erro ao invalidar sessao no logout: ' . $e->getMessage());
    }
}

clearLocalSessionState();
header('Location: index.php?page=login');
exit;
