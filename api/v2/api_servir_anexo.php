<?php

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ApiResponse::error('BAD_REQUEST', 'Apenas GET e permitido', [], 405);
    }

    $id = isset($_GET['id']) ? max(1, (int)$_GET['id']) : 0;
    if (!$id) {
        ApiResponse::error('VALIDATION_ERROR', 'ID do anexo nao informado.');
    }

    $db = db();

    $result = $db->query(
        "SELECT id, caminho, mime_type, nome_original FROM equipamentos_anexos WHERE id = :id",
        [':id' => $id]
    );

    if ($result['status'] !== 'success' || empty($result['data'])) {
        ApiResponse::notFound('anexo', $id);
    }

    $anexo = $result['data'][0];
    $caminhoRelativo = $anexo->caminho ?? '';

    $uploadsBase = realpath(__DIR__ . '/../../public/uploads');
    $relativePath = str_replace('\\', '/', ltrim($caminhoRelativo, '/'));

    if ($uploadsBase === false || !str_starts_with($relativePath, 'uploads/')) {
        ApiResponse::error('VALIDATION_ERROR', 'Caminho do anexo invalido.');
    }

    $caminhoAbsoluto = realpath(__DIR__ . '/../../public/' . $relativePath);
    if ($caminhoAbsoluto === false) {
        ApiResponse::notFound('arquivo');
    }

    $uploadsBasePrefix = rtrim(str_replace('\\', '/', $uploadsBase), '/') . '/';
    $resolvedPath = str_replace('\\', '/', $caminhoAbsoluto);

    if (!str_starts_with($resolvedPath, $uploadsBasePrefix)) {
        ApiResponse::error('VALIDATION_ERROR', 'Caminho do anexo invalido.');
    }

    if (!is_file($caminhoAbsoluto) || !is_readable($caminhoAbsoluto)) {
        ApiResponse::notFound('arquivo');
    }

    $mimeType = $anexo->mime_type ?? mime_content_type($caminhoAbsoluto) ?: 'application/octet-stream';
    $nomeOriginal = $anexo->nome_original ?? basename($caminhoAbsoluto);
    $tamanho = filesize($caminhoAbsoluto);

    header_remove('Content-Type');
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: inline; filename="' . addslashes($nomeOriginal) . '"');
    header('Content-Length: ' . $tamanho);
    header('Cache-Control: private, max-age=3600');
    header('X-Content-Type-Options: nosniff');

    readfile($caminhoAbsoluto);
    exit;

} catch (Throwable $e) {
    error_log('[api_servir_anexo] ' . $e->getMessage());
    ApiResponse::internalError();
}
