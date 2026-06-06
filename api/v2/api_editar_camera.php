<?php

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiResponse::error('BAD_REQUEST', 'Apenas POST é permitido');
    }

    EquipamentoService::requireAccess();
    $data = EquipamentoService::parseJsonInput();

    $validator = new RequestValidator($data);
    $validator->validate([
        'id' => 'required|numeric|min:1',
        'status_id' => 'required|numeric|min:1',
        'procedimento_id' => 'required|numeric|min:1',
        'regiao_id' => 'required|numeric|min:1',
        'tipo_id' => 'required|numeric|min:1',
        'local_id' => 'required|numeric|min:1',
        'secretaria_id' => 'required|numeric|min:1',
        'marca_id' => 'required|numeric|min:1',
    ]);

    if ($validator->fails()) {
        ApiResponse::validationError($validator->errors());
    }

    $svc = new EquipamentoService();
    $svc->beginTransaction();

    $fields = $svc->extractCommonData($data);
    $equipId = max(1, (int)($data['id'] ?? 0));
    $localId = max(1, (int)($data['local_id'] ?? 0));

    $svc->checkLocationExists($localId);
    $fields['classif_endereco_id'] = $svc->resolveClassificacaoEndereco(
        $fields['classif_endereco_id'],
        $data['tipo_logradouro'] ?? null
    );

    $modeloId = $svc->resolveModelo($fields['marca_id'], $fields['tipo_id'], $data);
    $svc->validateTipoSpecific($fields['tipo_id'], $fields['dvr_modelo'], $fields['totem_quantidade_cameras']);

    $before = $svc->loadBeforeSnapshot($equipId);

    $svc->setAppUserContext($_SESSION['usuario']->id ?? null);

    $equipData = $svc->buildEquipData($fields, $localId, $modeloId);
    $svc->updateEquipamento($equipData, $equipId);

    $svc->saveTipoSpecificUpsert($equipId, $fields['tipo_id'], $data);

    db()->query(
        "UPDATE locais SET
            tem_alarme = ?,
            alarme_conta = ?,
            classificacao_endereco_id = COALESCE(NULLIF(?, ''), classificacao_endereco_id),
            logradouro = COALESCE(NULLIF(?, ''), logradouro), bairro = COALESCE(NULLIF(?, ''), bairro),
            cidade = COALESCE(NULLIF(?, ''), cidade), uf = COALESCE(NULLIF(?, ''), uf),
            cep = COALESCE(NULLIF(?, ''), cep), numero = COALESCE(NULLIF(?, ''), numero),
            descricao_posicao = COALESCE(NULLIF(?, ''), descricao_posicao) WHERE id = ?",
        [$fields['tem_alarme'], $fields['alarme_conta'], $fields['classif_endereco_id'], $fields['logradouro'], $fields['bairro'], $fields['cidade'], $fields['uf'], $fields['cep'], $fields['numero'], $fields['descricao_posicao'], $localId]
    );

    $auditAfter = $svc->buildAuditData($equipId, $fields);
    auditEvent(db(), 'equipamentos', $equipId, 'UPDATE', $before, $auditAfter, 'api');

    $svc->commit();

    ApiResponse::success([
        'message' => 'Equipamento atualizado com sucesso!',
        'redirect' => '../../index.php?page=controle_cameras',
    ]);

} catch (Throwable $e) {
    try {
        if (isset($svc) && db()->getConnection()->inTransaction()) {
            $svc->rollback();
        }
    } catch (Exception $ignored) {}

    error_log('[api_editar_camera] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao editar câmera.');
}
