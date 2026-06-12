<?php

if (!defined('API_ROOT')) {
    define('API_ROOT', __DIR__);
}
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/inc/security.php';
require_once APP_ROOT . '/inc/single_session.php';
require_once APP_ROOT . '/src/ErrorHandler.php';
require_once APP_ROOT . '/src/Api/ApiResponse.php';
require_once APP_ROOT . '/src/Api/RequestValidator.php';
require_once APP_ROOT . '/src/Api/RateLimiter.php';
require_once APP_ROOT . '/src/Services/EquipamentoService.php';

function requiredAccessForApiEndpoint(string $endpoint): string
{
    $adminEndpoints = [
        'auditoria_cameras',
        'limpar_sessoes_orfas',
        'health',
        'api_health',
    ];

    $supervisorEndpoints = [
        'cadastrar_alarmes',
        'cadastrar_cameras',
        'editar_alarme',
        'editar_camera',
        'excluir_anexo',
        'excluir_camera',
        'manutencao_alarmes',
        'manutencao_cameras',
        'relatorios_cameras',
        'upload_anexo',
    ];

    if (in_array($endpoint, $adminEndpoints, true)) {
        return 'admin';
    }

    if (in_array($endpoint, $supervisorEndpoints, true)) {
        return 'supervisor';
    }

    return 'user';
}

function requireApiAccessForEndpoint(string $endpoint): void
{
    configureSessionSecurity();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['usuario'])) {
        ApiResponse::unauthorized();
    }

    $requiredAccess = requiredAccessForApiEndpoint($endpoint);
    if (!userHasAccess($requiredAccess)) {
        ApiResponse::forbidden('Perfil sem permissao para acessar este recurso.');
    }
}

function executeApiRequest(?string $endpointOverride = null): void
{
    registerGlobalErrorHandlers('api');

    $version = 'v2';

    $page = $endpointOverride ?? $_GET['page'] ?? '';
    $apiFile = basename(str_replace('api/', '', $page));
    $apiFile = preg_replace('/^api_/', '', $apiFile);
    $endpoint = $apiFile;

    $filepath = API_ROOT . "/{$version}/api_{$apiFile}.php";

    if (!file_exists($filepath)) {
        $filepathAlt = API_ROOT . "/{$version}/{$apiFile}.php";
        if (file_exists($filepathAlt)) {
            $filepath = $filepathAlt;
        }
    }

    $exists = file_exists($filepath);

    $rateLimiter = new RateLimiter(
        storagePath: sys_get_temp_dir() . '/ratelimit',
        strategy: 'sliding_window',
        configs: [
            '/^auth:/' => [10, 60],
            '/^login:/' => [5, 60],
            '/^POST:/' => [30, 60],
            '/^PUT:/' => [30, 60],
            '/^DELETE:/' => [20, 60],
            '/^GET:/' => [120, 60],
        ]
    );

    if ($_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $rateLimitKey = "{$method}:{$endpoint}:{$clientIp}";

        if (!$rateLimiter->check($rateLimitKey)) {
            ApiResponse::rateLimited($rateLimiter->getRetryAfter($rateLimitKey));
        }
    }

    header('Content-Type: application/json; charset=utf-8');
    header('X-API-Version: v2');
    header('X-Request-ID: ' . ApiResponse::getRequestId());
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $allowedOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $isSameOrigin = false;
    if (!empty($allowedOrigin)) {
        if (defined('APP_ALLOWED_ORIGINS') && APP_ALLOWED_ORIGINS !== '') {
            $configuredOrigins = array_map('trim', explode(',', APP_ALLOWED_ORIGINS));
            $isSameOrigin = in_array($allowedOrigin, $configuredOrigins, true);
        } else {
            $serverScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $serverPort = $_SERVER['SERVER_PORT'] ?? '80';
            $serverName = $_SERVER['SERVER_NAME'] ?? '';
            if ($serverName !== '') {
                $defaultPort = ($serverScheme === 'https') ? '443' : '80';
                $expectedOrigin = ($serverPort === $defaultPort)
                    ? "{$serverScheme}://{$serverName}"
                    : "{$serverScheme}://{$serverName}:{$serverPort}";
                $isSameOrigin = $allowedOrigin === $expectedOrigin;
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        if (!$isSameOrigin) {
            http_response_code(204);
            exit;
        }
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Version, X-Request-ID');
        header('Access-Control-Max-Age: 86400');
        http_response_code(204);
        exit;
    }

    if (!$isSameOrigin && !empty($allowedOrigin)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Cross-origin requests not allowed']);
        exit;
    }

    if (!$exists) {
        ApiResponse::notFound('endpoint', $endpoint);
    }

    $publicEndpoints = ['auth/login', 'auth/register', 'ping', 'api_ping'];
    $noRotateEndpoints = ['renovar_sessao'];
    if (!in_array($endpoint, $publicEndpoints, true)) {
        requireApiAccessForEndpoint($endpoint);
    }
    $shouldRotate = !in_array($endpoint, $noRotateEndpoints, true);
    requireApiCsrf($shouldRotate);

    if ($exists && file_exists($filepath)) {
        include $filepath;
    } else {
        ApiResponse::notFound('endpoint', $endpoint);
    }
}
