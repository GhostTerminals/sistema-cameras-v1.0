<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('ACCESS_LEVEL_USER', 1);
define('ACCESS_LEVEL_SUPERVISOR', 2);
define('ACCESS_LEVEL_ADMIN', 3);

define('CSRF_TOKEN_BYTES', 32);
define('PASSWORD_MIN_LENGTH', 8);
define('TEMP_PASSWORD_LENGTH', 12);

function getCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_BYTES));
    }

    return $_SESSION['csrf_token'];
}

function validateCsrfToken(?string $token): bool
{
    if (!$token || empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

function requireApiCsrf(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $safeMethods = ['GET', 'HEAD', 'OPTIONS'];
    if (in_array(strtoupper($method), $safeMethods, true)) {
        return;
    }

    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCsrfToken($token)) {
        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'code' => 'VALIDATION_ERROR',
            'message' => 'Token CSRF invalido ou ausente.',
            'data' => [],
            'meta' => [
                'timestamp' => date('Y-m-d\TH:i:s\Z'),
                'request_id' => class_exists('ApiResponse') ? ApiResponse::getRequestId() : 'unknown'
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function currentUserId(): ?int
{
    if (!isset($_SESSION['usuario']) || !isset($_SESSION['usuario']->id)) {
        return null;
    }

    return (int) $_SESSION['usuario']->id;
}

function userHasAccess(string $required = 'user'): bool
{
    if (!isset($_SESSION['usuario'])) {
        return false;
    }

    $levels = [
        'user' => ACCESS_LEVEL_USER,
        'supervisor' => ACCESS_LEVEL_SUPERVISOR,
        'admin' => ACCESS_LEVEL_ADMIN,
    ];

    $userLevel = resolveUserAccessLevel($_SESSION['usuario']);
    $_SESSION['usuario']->nivel_acesso = $userLevel;
    return ($levels[$userLevel] ?? 0) >= ($levels[$required] ?? 1);
}

function normalizeAccessLevelName(?string $name): ?string
{
    if ($name === null) {
        return null;
    }

    $normalized = strtolower(trim($name));
    if (in_array($normalized, ['admin', 'supervisor', 'user'], true)) {
        return $normalized;
    }

    return null;
}

function mapAccessIdToName($id): ?string
{
    if ($id === null || $id === '') {
        return null;
    }

    $map = [
        ACCESS_LEVEL_ADMIN => 'admin',
        ACCESS_LEVEL_SUPERVISOR => 'supervisor',
        ACCESS_LEVEL_USER => 'user',
    ];

    $intId = (int) $id;
    return $map[$intId] ?? null;
}

function resolveUserAccessLevel($user): string
{
    if (!$user) {
        return 'user';
    }

    if (isset($user->nivel_acesso)) {
        $normalized = normalizeAccessLevelName((string) $user->nivel_acesso);
        if ($normalized !== null) {
            return $normalized;
        }
    }

    if (isset($user->nivel_acesso_id)) {
        $fromId = mapAccessIdToName($user->nivel_acesso_id);
        if ($fromId !== null) {
            return $fromId;
        }
    }

    return 'user';
}

function ensureSessionUserAccessLevel(): void
{
    if (!isset($_SESSION['usuario'])) {
        return;
    }

    $level = resolveUserAccessLevel($_SESSION['usuario']);
    if (!isset($_SESSION['usuario']->nivel_acesso) || $_SESSION['usuario']->nivel_acesso !== $level) {
        $_SESSION['usuario']->nivel_acesso = $level;
    }
}

ensureSessionUserAccessLevel();

function validatePasswordPolicy(string $password, ?array &$errors = null): bool
{
    $errors = [];
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'A senha deve ter pelo menos ' . PASSWORD_MIN_LENGTH . ' caracteres.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'A senha deve conter pelo menos uma letra maiuscula.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'A senha deve conter pelo menos uma letra minuscula.';
    }
    if (!preg_match('/\\d/', $password)) {
        $errors[] = 'A senha deve conter pelo menos um numero.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'A senha deve conter pelo menos um caractere especial.';
    }

    return empty($errors);
}

function auditEvent($db, string $entidade, ?int $entidadeId, string $operacao, ?array $dadosAntes = null, ?array $dadosDepois = null, string $origem = 'web'): void
{
    try {
        $usuarioId = currentUserId();
        $mapaOperacao = ['INSERT' => 1, 'UPDATE' => 2, 'DELETE' => 3];
        $operacaoId = $mapaOperacao[strtoupper($operacao)] ?? 1;
        $db->query(
            "INSERT INTO auditoria_eventos (entidade, entidade_id, operacao_id, dados_antes, dados_depois, origem, changed_by, created_at)
             VALUES (:entidade, :entidade_id, :operacao_id, :dados_antes, :dados_depois, :origem, :changed_by, NOW())",
            [
                ':entidade' => $entidade,
                ':entidade_id' => $entidadeId,
                ':operacao_id' => $operacaoId,
                ':dados_antes' => $dadosAntes ? json_encode($dadosAntes, JSON_UNESCAPED_UNICODE) : null,
                ':dados_depois' => $dadosDepois ? json_encode($dadosDepois, JSON_UNESCAPED_UNICODE) : null,
                ':origem' => $origem,
                ':changed_by' => $usuarioId,
            ]
        );
    } catch (Throwable $e) {
        error_log('Falha ao registrar auditoria: ' . $e->getMessage());
    }
}

function generateTemporaryPassword(int $length = 12): string
{
    $length = max(PASSWORD_MIN_LENGTH, $length);
    $lower = 'abcdefghjkmnpqrstuvwxyz';
    $upper = 'ABCDEFGHJKMNPQRSTUVWXYZ';
    $digits = '23456789';
    $special = '!@#$%*-_';
    $all = $lower . $upper . $digits . $special;

    $password = [
        $upper[random_int(0, strlen($upper) - 1)],
        $lower[random_int(0, strlen($lower) - 1)],
        $digits[random_int(0, strlen($digits) - 1)],
        $special[random_int(0, strlen($special) - 1)],
    ];

    while (count($password) < $length) {
        $password[] = $all[random_int(0, strlen($all) - 1)];
    }

    shuffle($password);
    return implode('', $password);
}

// Funções centralizadas de segurança de senha
function hashPassword(string $password): string
{
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}
