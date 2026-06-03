<?php

function registerGlobalErrorHandlers(string $context = 'web'): void
{
    set_error_handler(static function (
        int $severity,
        string $message,
        string $file = '',
        int $line = 0
    ): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    set_exception_handler(static function (Throwable $exception) use ($context): void {
        error_log(sprintf(
            '[%s] %s in %s:%d',
            strtoupper($context),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        ));

        if (!headers_sent()) {
            http_response_code(500);
        }

        if ($context === 'api') {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'success' => false,
                'status' => 'error',
                'error' => 'Erro interno do servidor.',
                'message' => 'Erro interno do servidor.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        echo 'Erro interno do servidor.';
        exit;
    });

    register_shutdown_function(static function () use ($context): void {
        $error = error_get_last();
        if ($error === null) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($error['type'] ?? 0, $fatalTypes, true)) {
            return;
        }

        error_log(sprintf(
            '[%s] FATAL %s in %s:%d',
            strtoupper($context),
            (string)($error['message'] ?? 'erro fatal'),
            (string)($error['file'] ?? 'unknown'),
            (int)($error['line'] ?? 0)
        ));

        if (!headers_sent()) {
            http_response_code(500);
        }

        if ($context === 'api') {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'success' => false,
                'status' => 'error',
                'error' => 'Erro interno do servidor.',
                'message' => 'Erro interno do servidor.'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        echo 'Erro interno do servidor.';
    });
}

