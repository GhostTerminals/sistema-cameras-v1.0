<?php

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiResponse::error('BAD_REQUEST', 'Apenas POST é permitido');
    }

    configureSessionSecurity();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['usuario'])) {
        ApiResponse::unauthorized();
    }
    if (!userHasAccess('supervisor')) {
        ApiResponse::forbidden('Perfil sem permissao para acessar este recurso.');
    }

    $jsonInput = json_decode(file_get_contents('php://input'), true);
    $input = is_array($jsonInput) ? $jsonInput : [];
    $anexoId = max(1, (int)($input['id'] ?? 0));

    if (!$anexoId) {
        ApiResponse::error('VALIDATION_ERROR', 'ID do anexo não informado.');
    }

    $db = db();

    $result = $db->query(
        "SELECT id, caminho, equipamento_id, alarme_id, manutencao_camera_id, manutencao_alarme_id FROM equipamentos_anexos WHERE id = :id",
        [':id' => $anexoId]
    );

    if ($result['status'] !== 'success' || empty($result['data'])) {
        ApiResponse::notFound('anexo', $anexoId);
    }

    $anexo = $result['data'][0];
    $before = (array)$anexo;

    $uploadsBase = realpath(__DIR__ . '/../../public/uploads');
    $relativePath = str_replace('\\', '/', ltrim((string)$anexo->caminho, '/'));

    if ($uploadsBase === false || !str_starts_with($relativePath, 'uploads/')) {
        error_log('[api_excluir_anexo] Caminho de anexo fora de uploads: ' . $relativePath);
        ApiResponse::error('VALIDATION_ERROR', 'Caminho do anexo invalido.');
    }

    $caminhoAbsoluto = realpath(__DIR__ . '/../../public/' . $relativePath);
    if ($caminhoAbsoluto !== false) {
        $uploadsBasePrefix = rtrim(str_replace('\\', '/', $uploadsBase), '/') . '/';
        $resolvedPath = str_replace('\\', '/', $caminhoAbsoluto);

        if (!str_starts_with($resolvedPath, $uploadsBasePrefix)) {
            error_log('[api_excluir_anexo] Tentativa de exclusao fora de uploads: ' . $resolvedPath);
            ApiResponse::error('VALIDATION_ERROR', 'Caminho do anexo invalido.');
        }

        if (is_file($caminhoAbsoluto) && !unlink($caminhoAbsoluto)) {
            ApiResponse::error('OPERATION_FAILED', 'Nao foi possivel excluir o arquivo do anexo.');
        }
    }

    $db->query(
        "DELETE FROM equipamentos_anexos WHERE id = :id",
        [':id' => $anexoId]
    );

    if ($anexo->manutencao_camera_id) {
        $entityType = 'manutencao_camera';
        $entityId = $anexo->manutencao_camera_id;
    } elseif ($anexo->manutencao_alarme_id) {
        $entityType = 'manutencao_alarme';
        $entityId = $anexo->manutencao_alarme_id;
    } else {
        $entityType = $anexo->equipamento_id ? 'equipamento' : 'alarme';
        $entityId = $anexo->equipamento_id ?? $anexo->alarme_id;
    }

    try {
        if (function_exists('auditEvent')) {
            $before['entidade_relacionada'] = $entityType;
            $before['entidade_relacionada_id'] = $entityId ? (int)$entityId : null;
            auditEvent($db, 'equipamentos_anexos', $anexoId, 'DELETE', $before, null, 'api');
        }
    } catch (Throwable $e) {
        error_log('Erro ao registrar auditoria: ' . $e->getMessage());
    }

    ApiResponse::success(['message' => 'Anexo excluído com sucesso.']);

} catch (Throwable $e) {
    error_log('[api_excluir_anexo] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao excluir anexo.');
}
