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
    $payload = is_array($jsonInput) ? $jsonInput : [];

    if (empty($payload)) {
        ApiResponse::error('BAD_REQUEST', 'Payload inválido.');
    }

    $validator = new RequestValidator($payload);
    $validator->validate([
        'id' => 'required|numeric|min:1',
        'conta' => 'required|numeric|min:1',
        'local' => 'required|string|max:255',
        'status' => 'required|string|max:20',
    ]);

    if ($validator->fails()) {
        ApiResponse::validationError($validator->errors());
    }

    $id = max(1, (int)($payload['id'] ?? 0));
    $conta = max(1, (int)($payload['conta'] ?? 0));
    $local = trim((string)($payload['local'] ?? ''));
    $status = trim((string)($payload['status'] ?? ''));

    $db = db();

    $before = null;
    $beforeResult = $db->query("SELECT * FROM central_alarmes WHERE id = ?", [$id]);
    if ($beforeResult['status'] === 'success' && !empty($beforeResult['data'])) {
        $before = (array)$beforeResult['data'][0];
    }

    $params = [
        ':id' => $id,
        ':regiao' => trim((string)($payload['regiao'] ?? '')) ?: null,
        ':conta' => $conta,
        ':status' => $status,
        ':local' => $local,
        ':endereco' => trim((string)($payload['endereco'] ?? '')) ?: null,
        ':numero' => trim((string)($payload['numero'] ?? '')) ?: null,
        ':pgm1' => trim((string)($payload['pgm1'] ?? '')) ?: null,
        ':pgm2' => trim((string)($payload['pgm2'] ?? '')) ?: null,
        ':mac' => trim((string)($payload['mac'] ?? '')) ?: null,
        ':ip' => trim((string)($payload['ip'] ?? '')) ?: null,
        ':integracao' => trim((string)($payload['integracao'] ?? '')) ?: null,
        ':camera_gm' => !empty($payload['camera_gm']) ? 1 : 0,
        ':quant_camera_gm' => max(0, (int)($payload['quant_camera_gm'] ?? 0)) ?: null,
        ':ip_dvr' => trim((string)($payload['ip_dvr'] ?? '')) ?: null,
        ':cameras_dvr' => max(0, (int)($payload['cameras_dvr'] ?? 0)) ?: null,
        ':modelo_alarme_id' => max(0, (int)($payload['modelo_alarme_id'] ?? 0)) ?: null,
        ':quant_repetidor' => max(0, (int)($payload['quant_repetidor'] ?? 0)) ?: null,
        ':qtde_sensores' => max(0, (int)($payload['qtde_sensores'] ?? 0)) ?: null,
        ':documentacao' => trim((string)($payload['documentacao'] ?? '')) ?: null,
        ':monitorada' => trim((string)($payload['monitorada'] ?? '')) ?: null,
        ':numero_sei' => trim((string)($payload['numero_sei'] ?? '')) ?: null,
        ':data_atualizacao' => trim((string)($payload['data_atualizacao'] ?? date('Y-m-d'))) ?: date('Y-m-d'),
        ':observacao' => trim((string)($payload['observacao'] ?? '')) ?: null,
    ];

    $sql = "UPDATE central_alarmes SET
        regiao = :regiao,
        conta = :conta,
        status = :status,
        local = :local,
        endereco = :endereco,
        numero = :numero,
        pgm1 = :pgm1,
        pgm2 = :pgm2,
        mac = :mac,
        ip = :ip,
        integracao = :integracao,
        camera_gm = :camera_gm,
        quant_camera_gm = :quant_camera_gm,
        ip_dvr = :ip_dvr,
        cameras_dvr = :cameras_dvr,
        modelo_alarme_id = :modelo_alarme_id,
        quant_repetidor = :quant_repetidor,
        qtde_sensores = :qtde_sensores,
        documentacao = :documentacao,
        monitorada = :monitorada,
        numero_sei = :numero_sei,
        data_atualizacao = :data_atualizacao,
        observacao = :observacao
     WHERE id = :id";

    $result = $db->query($sql, $params);
    if (!$result || $result['status'] !== 'success') {
        ApiResponse::internalError('Falha ao atualizar alarme.');
    }

    $auditAfter = $params;
    $auditAfter['id'] = $id;
    auditEvent($db, 'alarmes', $id, 'UPDATE', $before, $auditAfter, 'api');

    ApiResponse::success([
        'message' => 'Alarme atualizado com sucesso.',
        'redirect' => '../../index.php?page=controle_alarmes',
    ]);

} catch (Throwable $e) {
    error_log('[api_editar_alarme] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao atualizar alarme.');
}
