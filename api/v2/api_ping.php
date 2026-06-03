<?php

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ApiResponse::error('BAD_REQUEST', 'Apenas GET e permitido', [], 405);
    }

    ApiResponse::success([
        'status' => 'ok',
        'timestamp' => date('c'),
    ]);
} catch (Throwable $e) {
    error_log('[api_ping] ' . $e->getMessage());
    ApiResponse::internalError();
}
