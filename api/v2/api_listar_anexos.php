<?php

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ApiResponse::error('BAD_REQUEST', 'Apenas GET é permitido');
    }

    configureSessionSecurity();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['usuario'])) {
        ApiResponse::unauthorized();
    }

    $equipamentoId = isset($_GET['equipamento_id']) ? (int)$_GET['equipamento_id'] : null;
    $alarmeId = isset($_GET['alarme_id']) ? (int)$_GET['alarme_id'] : null;
    $manutencaoCameraId = isset($_GET['manutencao_camera_id']) ? (int)$_GET['manutencao_camera_id'] : null;
    $manutencaoAlarmeId = isset($_GET['manutencao_alarme_id']) ? (int)$_GET['manutencao_alarme_id'] : null;
    $tipo = isset($_GET['tipo']) ? trim($_GET['tipo']) : null;

    if (!$equipamentoId && !$alarmeId && !$manutencaoCameraId && !$manutencaoAlarmeId) {
        ApiResponse::error('VALIDATION_ERROR', 'Informe equipamento_id, alarme_id, manutencao_camera_id ou manutencao_alarme_id.');
    }

    $db = db();

    $conditions = [];
    $params = [];

    if ($equipamentoId) {
        $conditions[] = 'ea.equipamento_id = :eid';
        $params[':eid'] = $equipamentoId;
    }

    if ($alarmeId) {
        $conditions[] = 'ea.alarme_id = :aid';
        $params[':aid'] = $alarmeId;
    }

    if ($manutencaoCameraId) {
        $conditions[] = 'ea.manutencao_camera_id = :mcid';
        $params[':mcid'] = $manutencaoCameraId;
    }

    if ($manutencaoAlarmeId) {
        $conditions[] = 'ea.manutencao_alarme_id = :maid';
        $params[':maid'] = $manutencaoAlarmeId;
    }

    $tipoPermitido = ['foto', 'documento', 'anexo'];
    if ($tipo && in_array($tipo, $tipoPermitido, true)) {
        $conditions[] = 'ea.tipo = :tipo';
        $params[':tipo'] = $tipo;
    }

    $where = implode(' AND ', $conditions);

    $result = $db->query(
        "SELECT ea.id, ea.equipamento_id, ea.alarme_id, ea.manutencao_camera_id, ea.manutencao_alarme_id, ea.tipo,
                ea.nome_original, ea.nome_arquivo, ea.caminho,
                ea.mime_type, ea.tamanho, ea.descricao,
                ea.created_by, ea.created_at,
                u.nome AS usuario_nome
         FROM equipamentos_anexos ea
         LEFT JOIN usuarios u ON u.id = ea.created_by
         WHERE {$where}
         ORDER BY ea.created_at DESC",
        $params
    );

    if ($result['status'] !== 'success') {
        ApiResponse::internalError('Erro ao consultar anexos.');
    }

    $baseUrl = defined('BASE_URL') ? BASE_URL : '';

    function formatFileSize(int $bytes): string {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 0, ',', '.') . ' KB';
        }
        return $bytes . ' B';
    }

    $anexos = array_map(function ($row) use ($baseUrl) {
        $ar = (array)$row;
        $ar['url'] = $baseUrl . '/' . $row->caminho;
        $ar['tamanho_formatado'] = formatFileSize((int)$row->tamanho);
        return $ar;
    }, $result['data'] ?? []);

    ApiResponse::success([
        'data' => $anexos,
        'total' => count($anexos),
    ]);

} catch (Throwable $e) {
    error_log('[api_listar_anexos] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao listar anexos.');
}
