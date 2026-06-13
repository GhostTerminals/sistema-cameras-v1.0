<?php

require_once __DIR__ . '/../config/database.php';

function getSessionClientIp(): string
{
    $remoteAddr = trim($_SERVER['REMOTE_ADDR'] ?? 'unknown');

    if (defined('PROXY_TRUSTED_IPS') && PROXY_TRUSTED_IPS !== '') {
        $trustedProxies = array_map('trim', explode(',', PROXY_TRUSTED_IPS));
        if (in_array($remoteAddr, $trustedProxies, true)) {
            $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            if ($forwarded !== '') {
                $ips = explode(',', $forwarded);
                $last = trim(end($ips));
                if (filter_var($last, FILTER_VALIDATE_IP)) {
                    return $last;
                }
            }
            $realIp = $_SERVER['HTTP_X_REAL_IP'] ?? '';
            if ($realIp !== '' && filter_var($realIp, FILTER_VALIDATE_IP)) {
                return $realIp;
            }
        }
    }

    return $remoteAddr;
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

    $pdo = $db->getConnection();
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            "SELECT usuario_id FROM user_sessions WHERE usuario_id = ? FOR UPDATE"
        )->execute([$usuarioId]);

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
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
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
        "SELECT session_token_hash, session_id, active, last_seen
         FROM user_sessions
         WHERE usuario_id = :usuario_id
           AND active = 1
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

    // Atualizar last_seen apenas a cada 5 minutos para reduzir write amplification
    $lastSeen = $row->last_seen ?? null;
    $touchInterval = 300;
    $shouldTouch = $lastSeen === null || (time() - strtotime((string)$lastSeen)) > $touchInterval;

    if ($shouldTouch) {
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
    }

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
