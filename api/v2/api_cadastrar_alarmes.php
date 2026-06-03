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
        'conta' => 'required|numeric|min:1',
        'local' => 'required|string|max:255',
        'status' => 'required|string|max:20',
    ]);

    if ($validator->fails()) {
        ApiResponse::validationError($validator->errors());
    }

    $conta = max(1, (int)($payload['conta'] ?? 0));
    $local = trim((string)($payload['local'] ?? ''));
    $status = trim((string)($payload['status'] ?? ''));

    if ($local === '' || $status === '') {
        ApiResponse::error('VALIDATION_ERROR', 'Local e Status são obrigatórios.');
    }

    $db = db();

    $params = [
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

    $sql = "INSERT INTO central_alarmes (
        regiao, conta, status, local, endereco, numero, pgm1, pgm2, mac, ip,
        integracao, camera_gm, quant_camera_gm, ip_dvr, cameras_dvr, modelo_alarme_id,
        quant_repetidor, qtde_sensores, documentacao, monitorada, numero_sei,
        data_atualizacao, observacao
    ) VALUES (
        :regiao, :conta, :status, :local, :endereco, :numero, :pgm1, :pgm2, :mac, :ip,
        :integracao, :camera_gm, :quant_camera_gm, :ip_dvr, :cameras_dvr, :modelo_alarme_id,
        :quant_repetidor, :qtde_sensores, :documentacao, :monitorada, :numero_sei,
        :data_atualizacao, :observacao
    )";

    $result = $db->query($sql, $params);
    if (!$result || $result['status'] !== 'success') {
        ApiResponse::internalError('Falha ao cadastrar alarme.');
    }

    $id = (int)$db->lastInsertId();
    $auditAfter = $params;
    $auditAfter['id'] = $id;
    auditEvent($db, 'alarmes', $id, 'INSERT', null, $auditAfter, 'api');

    ApiResponse::created([
        'alarme_id' => $id,
        'redirect' => '../../index.php?page=editar_alarmes&id=' . $id,
    ], $id);

} catch (Throwable $e) {
    error_log('[api_cadastrar_alarmes] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao cadastrar alarme.');
}
