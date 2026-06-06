<?php

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiResponse::error('BAD_REQUEST', 'Apenas POST é permitido');
    }

    EquipamentoService::requireAccess();
    $data = EquipamentoService::parseJsonInput();

    $svc = new EquipamentoService();
    $svc->validateRequiredIds($data);

    $nomeLocal = trim((string)($data['nome_local'] ?? ''));
    if ($nomeLocal === '') {
        ApiResponse::error('VALIDATION_ERROR', 'Informe o nome/local da câmera.');
    }

    $tipoLocalId = max(1, (int)($data['tipo_local_id'] ?? 0));
    $checkTipoLocal = db()->query("SELECT id FROM tipos_locais WHERE id = ? LIMIT 1", [$tipoLocalId]);
    if ($checkTipoLocal['status'] !== 'success' || empty($checkTipoLocal['data'])) {
        ApiResponse::error('VALIDATION_ERROR', 'Tipo de local selecionado não encontrado.');
    }

    $svc->beginTransaction();

    $fields = $svc->extractCommonData($data);
    $fields['classif_endereco_id'] = $svc->resolveClassificacaoEndereco(
        $fields['classif_endereco_id'],
        $data['tipo_logradouro'] ?? null
    );

    $localId = $svc->resolveLocation($data, $fields['secretaria_id'], $nomeLocal);
    $svc->updateLocationFields($localId, $data);

    $modeloId = $svc->resolveModelo($fields['marca_id'], $fields['tipo_id'], $data);
    $svc->validateCoordenadas($fields['coordenadas']);
    $svc->validateTipoSpecific($fields['tipo_id'], $fields['dvr_modelo'], $fields['totem_quantidade_cameras']);

    $svc->setAppUserContext($_SESSION['usuario']->id ?? null);

    $equipData = $svc->buildEquipData($fields, $localId, $modeloId);
    $equipId = $svc->insertEquipamento($equipData);

    $svc->saveTipoSpecificInsert($equipId, $fields['tipo_id'], $data);

    $auditAfter = $svc->buildAuditData($equipId, $fields);
    auditEvent(db(), 'equipamentos', $equipId, 'INSERT', null, $auditAfter, 'api');
    $svc->commit();

    ApiResponse::created([
        'message' => "Equipamento cadastrado com sucesso! ID: $equipId",
        'camera_id' => $equipId,
        'redirect' => 'index.php?page=controle_cameras',
    ], $equipId);

} catch (Throwable $e) {
    $rolledBack = false;

    try {
        if (isset($svc) && db()->getConnection()->inTransaction()) {
            $svc->rollback();
            $rolledBack = true;
        }
    } catch (Exception $ignored) {}

    if (isset($equipId) && !$rolledBack) {
        ApiResponse::created([
            'message' => "Equipamento cadastrado com sucesso! ID: $equipId",
            'camera_id' => $equipId,
            'redirect' => 'index.php?page=controle_cameras',
        ], $equipId);
    }

    error_log('[api_cadastrar_cameras] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao cadastrar equipamento.');
}
