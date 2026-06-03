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

    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        ApiResponse::error('VALIDATION_ERROR', 'ID não informado.');
    }

    $db = db();

    $check = $db->query("SELECT id FROM equipamentos WHERE id = ?", [$id]);
    if (empty($check['data'])) {
        ApiResponse::notFound('câmera', $id);
    }

    $before = null;
    $beforeResult = $db->query(
        "SELECT e.*, c.mosaico, c.coordenadas, c.numero_ruas
         FROM equipamentos e
         LEFT JOIN equipamentos_camera c ON c.equipamento_id = e.id
         WHERE e.id = ?",
        [$id]
    );
    if ($beforeResult['status'] === 'success' && !empty($beforeResult['data'])) {
        $before = (array)$beforeResult['data'][0];
    }

    $db->query("SET @app_user_id = ?", [$_SESSION['usuario']->id ?? null]);
    $db->query("SET @app_origem = 'api'");
    $result = $db->query("UPDATE equipamentos SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL", [$id]);

    if ($result['status'] === 'success' && !empty($result['affected_rows']) && $result['affected_rows'] > 0) {
        auditEvent($db, 'equipamentos', (int)$id, 'DELETE', $before, null, 'api');
        ApiResponse::success([
            'message' => 'Câmera excluída com sucesso',
            'affected_rows' => $result['affected_rows'],
        ]);
    }

    ApiResponse::error('NOT_FOUND', 'Câmera já foi excluída ou não encontrada.');

} catch (Throwable $e) {
    error_log('[api_excluir_camera] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao excluir câmera.');
}
