<?php
/**
 * API Response - Formato padronizado de respostas
 * 
 * Todos os endpoints retornam este formato:
 * {
 *   "success": true/false,
 *   "code": "CAMERA_NOT_FOUND",
 *   "message": "Camera com ID 123 não encontrada",
 *   "data": {},
 *   "meta": {
 *     "timestamp": "2026-05-29T19:57:43Z",
 *     "version": "v2",
 *     "request_id": "req_abc123"
 *   }
 * }
 */

class ApiResponse
{
    private static string $requestId = '';
    private static array $errorCodes = [
        // 2xx Success
        'SUCCESS' => 200,
        'CREATED' => 201,
        'ACCEPTED' => 202,
        'NO_CONTENT' => 204,

        // 4xx Client Errors
        'BAD_REQUEST' => 400,
        'UNAUTHORIZED' => 401,
        'FORBIDDEN' => 403,
        'NOT_FOUND' => 404,
        'CONFLICT' => 409,
        'UNPROCESSABLE_ENTITY' => 422,
        'RATE_LIMITED' => 429,

        // 5xx Server Errors
        'INTERNAL_ERROR' => 500,
        'SERVICE_UNAVAILABLE' => 503,

        // Custom API Errors
        'INVALID_VERSION' => 400,
        'INVALID_FORMAT' => 400,
        'VALIDATION_ERROR' => 422,
        'RESOURCE_NOT_FOUND' => 404,
        'UNAUTHORIZED_ACTION' => 403,
        'DUPLICATE_RESOURCE' => 409,
        'OPERATION_FAILED' => 500,
    ];

    /**
     * Gera ou obtém request ID (para tracing)
     */
    public static function getRequestId(): string
    {
        if (empty(self::$requestId)) {
            self::$requestId = 'req_' . substr(hash('sha256', uniqid(mt_rand(), true)), 0, 16);
        }
        return self::$requestId;
    }

    /**
     * Envia resposta de sucesso
     */
    public static function success($data = null, string $code = 'SUCCESS', ?int $httpCode = null): void
    {
        $httpCode = $httpCode ?? self::$errorCodes[$code] ?? 200;

        $response = [
            'success' => true,
            'code' => $code,
            'message' => self::getMessageForCode($code),
            'data' => $data ?? [],
            'meta' => self::getMeta()
        ];

        self::send($response, $httpCode);
    }

    /**
     * Envia resposta de erro
     */
    public static function error(string $code, ?string $message = null, $data = null, ?int $httpCode = null): void
    {
        $httpCode = $httpCode ?? self::$errorCodes[$code] ?? 500;

        $response = [
            'success' => false,
            'code' => $code,
            'message' => $message ?? self::getMessageForCode($code),
            'data' => $data ?? [],
            'meta' => self::getMeta()
        ];

        self::send($response, $httpCode);
    }

    /**
     * Envia resposta de validação com múltiplos erros
     */
    public static function validationError(array $errors): void
    {
        $response = [
            'success' => false,
            'code' => 'VALIDATION_ERROR',
            'message' => 'Erro de validação nos campos fornecidos',
            'data' => [
                'errors' => $errors
            ],
            'meta' => self::getMeta()
        ];

        self::send($response, 422);
    }

    /**
     * Envia resposta paginada
     */
    public static function paginated(array $items, int $page, int $perPage, int $total, ?string $code = 'SUCCESS'): void
    {
        $totalPages = (int)ceil($total / $perPage);

        $response = [
            'success' => true,
            'code' => $code ?? 'SUCCESS',
            'message' => self::getMessageForCode($code ?? 'SUCCESS'),
            'data' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ],
            'meta' => self::getMeta()
        ];

        self::send($response, 200);
    }

    /**
     * Envia resposta de criação (201)
     */
    public static function created($resource, $id = null): void
    {
        $response = [
            'success' => true,
            'code' => 'CREATED',
            'message' => 'Recurso criado com sucesso',
            'data' => [
                'resource' => $resource,
                'id' => $id
            ],
            'meta' => self::getMeta()
        ];

        self::send($response, 201);
    }

    /**
     * Envia resposta de recurso não encontrado (404)
     */
    public static function notFound(string $resource, $id = null): void
    {
        $message = "Recurso '{$resource}'";
        if ($id !== null) {
            $message .= " com ID {$id}";
        }
        $message .= " não encontrado";

        $response = [
            'success' => false,
            'code' => 'NOT_FOUND',
            'message' => $message,
            'data' => [
                'resource' => $resource,
                'id' => $id
            ],
            'meta' => self::getMeta()
        ];

        self::send($response, 404);
    }

    /**
     * Envia resposta de acesso negado (403)
     */
    public static function forbidden(?string $reason = null): void
    {
        $response = [
            'success' => false,
            'code' => 'FORBIDDEN',
            'message' => $reason ?? 'Acesso negado',
            'data' => [],
            'meta' => self::getMeta()
        ];

        self::send($response, 403);
    }

    /**
     * Envia resposta de não autorizado (401)
     */
    public static function unauthorized(?string $reason = null): void
    {
        $response = [
            'success' => false,
            'code' => 'UNAUTHORIZED',
            'message' => $reason ?? 'Não autorizado. Faça login para continuar.',
            'data' => [],
            'meta' => self::getMeta()
        ];

        self::send($response, 401);
    }

    /**
     * Envia resposta de rate limit (429)
     */
    public static function rateLimited(?int $retryAfter = null): void
    {
        if ($retryAfter) {
            header("Retry-After: {$retryAfter}");
        }

        $response = [
            'success' => false,
            'code' => 'RATE_LIMITED',
            'message' => 'Limite de requisições excedido. Tente novamente mais tarde.',
            'data' => $retryAfter ? ['retry_after' => $retryAfter] : [],
            'meta' => self::getMeta()
        ];

        self::send($response, 429);
    }

    /**
     * Envia resposta de erro interno (500)
     */
    public static function internalError(?string $message = null): void
    {
        $response = [
            'success' => false,
            'code' => 'INTERNAL_ERROR',
            'message' => $message ?? 'Erro ao processar requisição. Tente novamente mais tarde.',
            'data' => [],
            'meta' => self::getMeta()
        ];

        self::send($response, 500);
    }

    /**
     * Obtém mensagem para código de erro
     */
    private static function getMessageForCode(string $code): string
    {
        $messages = [
            'SUCCESS' => 'Operação realizada com sucesso',
            'CREATED' => 'Recurso criado com sucesso',
            'ACCEPTED' => 'Requisição aceita para processamento',
            'NO_CONTENT' => 'Sem conteúdo',
            'BAD_REQUEST' => 'Requisição inválida',
            'UNAUTHORIZED' => 'Não autorizado',
            'FORBIDDEN' => 'Acesso negado',
            'NOT_FOUND' => 'Recurso não encontrado',
            'CONFLICT' => 'Conflito ao processar requisição',
            'UNPROCESSABLE_ENTITY' => 'Entidade não processável',
            'RATE_LIMITED' => 'Limite de requisições excedido',
            'INTERNAL_ERROR' => 'Erro ao processar requisição',
            'SERVICE_UNAVAILABLE' => 'Serviço indisponível',
            'INVALID_VERSION' => 'Versão da API inválida',
            'INVALID_FORMAT' => 'Formato de requisição inválido',
            'VALIDATION_ERROR' => 'Erro de validação',
            'RESOURCE_NOT_FOUND' => 'Recurso não encontrado',
            'UNAUTHORIZED_ACTION' => 'Ação não autorizada',
            'DUPLICATE_RESOURCE' => 'Recurso duplicado',
            'OPERATION_FAILED' => 'Falha ao executar operação',
        ];

        return $messages[$code] ?? 'Erro desconhecido';
    }

    /**
     * Obtém metadados da resposta
     */
    private static function getMeta(): array
    {
        return [
            'timestamp' => date('Y-m-d\TH:i:s\Z'),
            'version' => 'v2',
            'request_id' => self::getRequestId(),
            'timezone' => date_default_timezone_get()
        ];
    }

    /**
     * Envia resposta como JSON
     */
    private static function send(array $response, int $httpCode): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
