<?php

require_once __DIR__ . '/../config/database.php';

function getSessionClientIp(): string
{
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($forwarded !== '') {
        $ips = explode(',', $forwarded);
        $first = trim($ips[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            return $first;
        }
    }
    $realIp = $_SERVER['HTTP_X_REAL_IP'] ?? '';
    if ($realIp !== '' && filter_var($realIp, FILTER_VALIDATE_IP)) {
        return $realIp;
    }
    return trim($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function getSessionUserAgent(): string
{
    $ua = trim($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
    return mb_substr($ua, 0, 255);
}

function hashSessionToken(string $token): string
{
    return hash('sha256', $token); // Mantido SHA-256 para tokens de sessão (não senhas)
}

function ensureUserSessionsTable(database $db): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $result = $db->query("SELECT 1 FROM user_sessions LIMIT 1");
    if (($result['status'] ?? '') !== 'success') {
        throw new RuntimeException('Tabela user_sessions indisponivel. Execute a migracao de banco.');
    }

    $checked = true;
}

function createSessionToken(): string
{
    return bin2hex(random_bytes(32));
}

function registerUniqueSession(database $db, int $usuarioId, string $token): void
{
    ensureUserSessionsTable($db);

    $db->query(
        "INSERT INTO user_sessions (usuario_id, session_token_hash, session_id, ip_address, user_agent, active, last_seen)
         VALUES (:usuario_id, :session_token_hash, :session_id, :ip_address, :user_agent, 1, NOW())
         ON DUPLICATE KEY UPDATE
             session_token_hash = VALUES(session_token_hash),
             session_id = VALUES(session_id),
             ip_address = VALUES(ip_address),
             user_agent = VALUES(user_agent),
             active = 1,
             last_seen = NOW(),
             updated_at = CURRENT_TIMESTAMP",
        [
            ':usuario_id' => $usuarioId,
            ':session_token_hash' => hashSessionToken($token),
            ':session_id' => session_id(),
            ':ip_address' => getSessionClientIp(),
            ':user_agent' => getSessionUserAgent(),
        ]
    );
}

function invalidateUniqueSession(database $db, int $usuarioId, ?string $token = null): void
{
    ensureUserSessionsTable($db);
    if ($token) {
        $db->query(
            "UPDATE user_sessions
             SET active = 0, updated_at = CURRENT_TIMESTAMP
             WHERE usuario_id = :usuario_id
               AND session_token_hash = :session_token_hash",
            [
                ':usuario_id' => $usuarioId,
                ':session_token_hash' => hashSessionToken($token),
            ]
        );
        return;
    }

    $db->query(
        "UPDATE user_sessions
         SET active = 0, updated_at = CURRENT_TIMESTAMP
         WHERE usuario_id = :usuario_id",
        [':usuario_id' => $usuarioId]
    );
}

function isUniqueSessionValid(database $db, int $usuarioId, ?string $token): bool
{
    if (!$token) {
        return false;
    }

    ensureUserSessionsTable($db);
    $result = $db->query(
        "SELECT session_token_hash, session_id, active
         FROM user_sessions
         WHERE usuario_id = :usuario_id
         LIMIT 1",
        [':usuario_id' => $usuarioId]
    );

    if (($result['status'] ?? '') !== 'success' || empty($result['data'])) {
        return false;
    }

    $row = $result['data'][0];
    if ((int)($row->active ?? 0) !== 1) {
        return false;
    }

    if (!hash_equals((string)$row->session_token_hash, hashSessionToken($token))) {
        return false;
    }

    $db->query(
        "UPDATE user_sessions
         SET session_id = :session_id,
             ip_address = :ip_address,
             user_agent = :user_agent,
             last_seen = NOW(),
             updated_at = CURRENT_TIMESTAMP
         WHERE usuario_id = :usuario_id
           AND session_token_hash = :session_token_hash
           AND active = 1",
        [
            ':session_id' => session_id(),
            ':ip_address' => getSessionClientIp(),
            ':user_agent' => getSessionUserAgent(),
            ':usuario_id' => $usuarioId,
            ':session_token_hash' => hashSessionToken($token),
        ]
    );

    return true;
}

function clearLocalSessionState(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}
