<?php

function getRequestDatabase(): database
{
    static $db = null;
    if ($db === null) {
        $db = new database();
    }

    return $db;
}

function verificarTimeout($isApi = false, $tempo_maximo = SESSION_TIMEOUT)
{
    if (!isset($_SESSION['usuario'])) {
        return 0;
    }

    $now = time();
    if (!isset($_SESSION['session_started_at'])) {
        $_SESSION['session_started_at'] = $now;
    }
    if (SESSION_ABSOLUTE_TIMEOUT > 0) {
        $elapsed = $now - (int)$_SESSION['session_started_at'];
        if ($elapsed >= SESSION_ABSOLUTE_TIMEOUT) {
            $usuarioId = currentUserId();
            $sessionToken = $_SESSION['session_token'] ?? null;
            if ($usuarioId) {
                try {
                    $db = getRequestDatabase();
                    invalidateUniqueSession($db, $usuarioId, $sessionToken);
                } catch (Throwable $e) {
                    error_log('Erro ao invalidar sessao por timeout absoluto: ' . $e->getMessage());
                }
            }

            clearLocalSessionState();

            if ($isApi) {
                http_response_code(401);
                header('Content-Type: application/json; charset=UTF-8');
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                header('Pragma: no-cache');
                header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
                echo json_encode([
                    'status' => 'expired',
                    'success' => false,
                    'reason' => 'absolute_timeout',
                    'error' => 'Sessao expirada',
                    'message' => 'Sessao encerrada por tempo maximo'
                ]);
                exit;
            }

            header('Location: index.php?page=login&timeout=1');
            exit;
        }
    }

    if (!isset($_SESSION['ultimo_acesso'])) {
        $_SESSION['ultimo_acesso'] = $now;
        return $tempo_maximo;
    }

    $inatividade = $now - $_SESSION['ultimo_acesso'];
    $time_left = $tempo_maximo - $inatividade;

    if ($inatividade >= $tempo_maximo) {
        $usuarioId = currentUserId();
        $sessionToken = $_SESSION['session_token'] ?? null;
        if ($usuarioId) {
            try {
                $db = getRequestDatabase();
                invalidateUniqueSession($db, $usuarioId, $sessionToken);
            } catch (Throwable $e) {
                error_log('Erro ao invalidar sessao por timeout: ' . $e->getMessage());
            }
        }

        clearLocalSessionState();

        if ($isApi) {
            http_response_code(401);
            header('Content-Type: application/json; charset=UTF-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
            echo json_encode([
                'status' => 'expired',
                'success' => false,
                'error' => 'Sessao expirada',
                'message' => 'Sessao encerrada por inatividade'
            ]);
            exit;
        }

        header('Location: index.php?page=login&timeout=1');
        exit;
    }

    if (!$isApi) {
        $_SESSION['ultimo_acesso'] = time();
    }

    return max(0, $time_left);
}

function enforceUniqueSession(bool $isApi = false): void
{
    if (!isset($_SESSION['usuario'])) {
        return;
    }

    $usuarioId = currentUserId();
    $sessionToken = $_SESSION['session_token'] ?? null;
    if (!$usuarioId || !$sessionToken) {
        clearLocalSessionState();
        if ($isApi) {
            http_response_code(401);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success' => false,
                'status' => 'expired',
                'reason' => 'concurrent_login',
                'error' => 'Sessao invalida. Realize login novamente.',
                'message' => 'Sessao invalida. Realize login novamente.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Location: index.php?page=login&concurrent=1');
        exit;
    }

    try {
        $db = getRequestDatabase();
        $valid = isUniqueSessionValid($db, $usuarioId, $sessionToken);
    } catch (Throwable $e) {
        error_log('Erro ao validar sessao unica: ' . $e->getMessage());
        $valid = false;
    }

    if ($valid) {
        return;
    }

    clearLocalSessionState();

    if ($isApi) {
        http_response_code(401);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'status' => 'expired',
            'reason' => 'concurrent_login',
            'error' => 'Sua conta foi acessada em outro dispositivo.',
            'message' => 'Sua conta foi acessada em outro dispositivo.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Location: index.php?page=login&concurrent=1');
    exit;
}
