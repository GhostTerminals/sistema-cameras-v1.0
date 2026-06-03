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

    $caminhoAbsoluto = __DIR__ . '/../../public/' . $anexo->caminho;
    if (file_exists($caminhoAbsoluto)) {
        @unlink($caminhoAbsoluto);
    }

    $db->query(
        "DELETE FROM equipamentos_anexos WHERE id = :id",
        [':id' => $anexoId]
    );

    $userId = currentUserId();
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
            auditEvent($userId, 'excluir_anexo', "Anexo #{$anexoId} excluído do {$entityType} #{$entityId}");
        }
    } catch (Throwable $e) {
        error_log('Erro ao registrar auditoria: ' . $e->getMessage());
    }

    ApiResponse::success(['message' => 'Anexo excluído com sucesso.']);

} catch (Throwable $e) {
    error_log('[api_excluir_anexo] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao excluir anexo.');
}
