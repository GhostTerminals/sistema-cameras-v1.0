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
    $data = is_array($jsonInput) ? $jsonInput : [];

    if (empty($data)) {
        ApiResponse::error('BAD_REQUEST', 'Nenhum dado recebido.');
    }

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

    $equipId = max(1, (int)($data['id'] ?? 0));
    $statusId = max(1, (int)($data['status_id'] ?? 0));
    $procedimentoId = max(1, (int)($data['procedimento_id'] ?? 0));
    $regiaoId = max(1, (int)($data['regiao_id'] ?? 0));
    $tipoId = max(1, (int)($data['tipo_id'] ?? 0));
    $tipoCameraId = max(0, (int)($data['tipo_camera'] ?? 0)) ?: null;
    $localId = max(1, (int)($data['local_id'] ?? 0));
    $secretariaId = max(1, (int)($data['secretaria_id'] ?? 0));
    $marcaId = max(1, (int)($data['marca_id'] ?? 0));
    $transmissaoId = max(0, (int)($data['transmissao_id'] ?? 0)) ?: null;
    $origemLinkId = max(0, (int)($data['origem_link_id'] ?? 0)) ?: null;
    $inscricaoLink = trim((string)($data['inscricao'] ?? '')) ?: null;
    $descricaoPosicao = trim((string)($data['descricao_posicao'] ?? '')) ?: null;
    $dataInstalacao = trim((string)($data['data_instalacao'] ?? '')) ?: null;
    $normalizedIp = trim((string)($data['ip'] ?? '')) ?: null;

    $classifEnderecoId = max(0, (int)($data['classificacao_endereco_id'] ?? 0)) ?: null;
    $logradouro = trim((string)($data['logradouro'] ?? '')) ?: null;
    $bairro = trim((string)($data['bairro'] ?? '')) ?: null;
    $cidade = trim((string)($data['cidade'] ?? '')) ?: null;
    $uf = trim((string)($data['uf'] ?? '')) ?: null;
    $cep = trim((string)($data['cep'] ?? '')) ?: null;
    $numero = trim((string)($data['numero'] ?? '')) ?: null;

    $temAlarme = !empty($data['tem_alarme']) ? 1 : 0;
    $alarmeConta = ($temAlarme && !empty($data['alarme_conta']))
                    ? trim((string)($data['alarme_conta'] ?? ''))
                    : null;

    $lprSentidoVia = trim((string)($data['lpr_sentido_via'] ?? '')) ?: null;
    $lprFaixaMonitorada = trim((string)($data['lpr_faixa_monitorada'] ?? '')) ?: null;
    $lprLeituraNoturna = !empty($data['lpr_leitura_noturna']) ? 1 : 0;
    $dvrModelo = trim((string)($data['dvr_modelo'] ?? '')) ?: null;
    $dvrCanais = max(0, (int)($data['dvr_canais'] ?? 0)) ?: null;
    $dvrArmazenamentoTb = $data['dvr_armazenamento_tb'] ?? null;
    if ($dvrArmazenamentoTb !== null) {
        $dvrArmazenamentoTb = str_replace(',', '.', $dvrArmazenamentoTb);
        if (!is_numeric($dvrArmazenamentoTb)) $dvrArmazenamentoTb = null;
    }
    $totemQuantidadeCameras = max(0, (int)($data['totem_quantidade_cameras'] ?? 0)) ?: null;
    $totemTemFacial = !empty($data['totem_tem_facial']) ? 1 : 0;
    $totemTemLpr = !empty($data['totem_tem_lpr']) ? 1 : 0;

    if ($tipoId === 3 && empty($dvrModelo)) {
        ApiResponse::error('VALIDATION_ERROR', 'Informe o modelo do DVR.');
    }
    if ($tipoId === 4 && empty($totemQuantidadeCameras)) {
        ApiResponse::error('VALIDATION_ERROR', 'Informe a quantidade de câmeras do Totem.');
    }

    $db = db();
    $db->beginTransaction();

    if ($classifEnderecoId === null && !empty($data['tipo_logradouro'])) {
        $ceResult = $db->query("SELECT id FROM classificacao_enderecos WHERE UPPER(nome) = UPPER(?) LIMIT 1", [trim($data['tipo_logradouro'])]);
        if ($ceResult['status'] === 'success' && !empty($ceResult['data'])) {
            $classifEnderecoId = (int)$ceResult['data'][0]->id;
        }
    }

    $modeloId = null;
    $before = null;
    $beforeResult = $db->query(
        "SELECT e.*, c.mosaico, c.coordenadas, c.numero_ruas,
                l.tem_alarme, l.alarme_conta,
                elpr.sentido_via AS lpr_sentido_via,
                elpr.faixa_monitorada AS lpr_faixa_monitorada,
                elpr.leitura_noturna AS lpr_leitura_noturna,
                edvr.modelo AS dvr_modelo,
                edvr.canais AS dvr_canais,
                edvr.armazenamento_tb AS dvr_armazenamento_tb,
                etot.quantidade_cameras AS totem_quantidade_cameras,
                etot.tem_facial AS totem_tem_facial,
                etot.tem_lpr AS totem_tem_lpr
         FROM equipamentos e
         LEFT JOIN locais l ON e.local_id = l.id
         LEFT JOIN equipamentos_camera c ON c.equipamento_id = e.id
         LEFT JOIN equipamentos_lpr elpr ON elpr.equipamento_id = e.id
         LEFT JOIN equipamentos_dvr edvr ON edvr.equipamento_id = e.id
         LEFT JOIN equipamentos_totem etot ON etot.equipamento_id = e.id
         WHERE e.id = ?",
        [$equipId]
    );
    if ($beforeResult['status'] === 'success' && !empty($beforeResult['data'])) {
        $before = (array)$beforeResult['data'][0];
    }

    if (!empty($data['modelo_existente'])) {
        $modeloId = max(1, (int)($data['modelo_existente'] ?? 0));
        $check = $db->query(
            "SELECT id FROM catalogo_modelos WHERE id = ? AND marca_id = ? AND tipo_equipamento_id = ?",
            [$modeloId, $marcaId, $tipoId]
        );
        if ($check['status'] !== 'success' || empty($check['data'])) {
            throw new Exception('Modelo selecionado não pertence à marca/tipo informados.');
        }
    } elseif (!empty($data['novo_modelo_nome'])) {
        $novoModelo = strtoupper(trim($data['novo_modelo_nome']));
        if (strlen($novoModelo) < 2) {
            throw new Exception('Nome do modelo deve ter pelo menos 2 caracteres.');
        }

        $check = $db->query(
            "SELECT id FROM catalogo_modelos WHERE tipo_equipamento_id = ? AND marca_id = ? AND UPPER(nome) = UPPER(?)",
            [$tipoId, $marcaId, $novoModelo]
        );

        if ($check['status'] === 'success' && !empty($check['data'])) {
            $modeloId = (int)$check['data'][0]->id;
        } else {
            $insertModelo = $db->query(
                "INSERT INTO catalogo_modelos (tipo_equipamento_id, marca_id, nome) VALUES (?, ?, ?)",
                [$tipoId, $marcaId, $novoModelo]
            );
            if ($insertModelo['status'] !== 'success') {
                throw new Exception('Erro ao criar novo modelo.');
            }
            $modeloId = (int)$db->lastInsertId();
        }
    } else {
        throw new Exception('Informe ou selecione um modelo.');
    }

    $db->query("SET @app_user_id = ?", [$_SESSION['usuario']->id ?? null]);
    $db->query("SET @app_origem = 'api'");

    $equipData = [
        ':id' => $equipId,
        ':tipo_equipamento_id' => $tipoId,
        ':tipo_camera_id' => $tipoCameraId,
        ':status_id' => $statusId,
        ':procedimento_id' => $procedimentoId,
        ':regiao_id' => $regiaoId,
        ':local_id' => $localId,
        ':secretaria_id' => $secretariaId,
        ':marca_id' => $marcaId,
        ':modelo_id' => $modeloId,
        ':ip' => $normalizedIp,
        ':patrimonio' => !empty($data['patrimonio']) ? strtoupper(trim($data['patrimonio'])) : null,
        ':numero_serie' => !empty($data['serie_mac']) ? strtoupper(trim($data['serie_mac'])) : null,
        ':transmissao_id' => $transmissaoId,
        ':origem_link_id' => $origemLinkId,
        ':inscricao' => $inscricaoLink,
        ':data_instalacao' => $dataInstalacao,
        ':observacao' => !empty($data['observacao']) ? trim($data['observacao']) : null
    ];

    $result = $db->query(
        "UPDATE equipamentos SET
            tipo_equipamento_id = :tipo_equipamento_id,
            tipo_camera_id = :tipo_camera_id,
            status_id = :status_id,
            procedimento_id = :procedimento_id,
            regiao_id = :regiao_id,
            local_id = :local_id,
            secretaria_id = :secretaria_id,
            marca_id = :marca_id,
            modelo_id = :modelo_id,
            ip = :ip,
            patrimonio = :patrimonio,
            numero_serie = :numero_serie,
            transmissao_id = :transmissao_id,
            origem_link_id = :origem_link_id,
            inscricao = :inscricao,
            data_instalacao = :data_instalacao,
            observacao = :observacao
         WHERE id = :id",
        $equipData
    );

    if ($result['status'] !== 'success') {
        throw new Exception('Erro ao atualizar equipamento.');
    }

    $upsertCamera = $db->query(
        "INSERT INTO equipamentos_camera (equipamento_id, mosaico, coordenadas, numero_ruas)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            mosaico = VALUES(mosaico),
            coordenadas = VALUES(coordenadas),
            numero_ruas = VALUES(numero_ruas)",
        [
            $equipId,
            !empty($data['mosaico']) ? strtoupper(trim($data['mosaico'])) : null,
            !empty($data['coordenadas']) ? trim($data['coordenadas']) : null,
            !empty($data['numero_ruas']) ? strtoupper(trim($data['numero_ruas'])) : null
        ]
    );

    if ($upsertCamera['status'] !== 'success') {
        throw new Exception('Erro ao atualizar detalhes da câmera.');
    }

    if ($tipoId === 2) {
        $db->query(
            "INSERT INTO equipamentos_lpr (equipamento_id, sentido_via, faixa_monitorada, leitura_noturna)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                sentido_via = VALUES(sentido_via),
                faixa_monitorada = VALUES(faixa_monitorada),
                leitura_noturna = VALUES(leitura_noturna)",
            [$equipId, $lprSentidoVia, $lprFaixaMonitorada, $lprLeituraNoturna]
        );
    } elseif ($tipoId === 3) {
        $db->query(
            "INSERT INTO equipamentos_dvr (equipamento_id, modelo, canais, armazenamento_tb)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                modelo = VALUES(modelo),
                canais = VALUES(canais),
                armazenamento_tb = VALUES(armazenamento_tb)",
            [$equipId, $dvrModelo, $dvrCanais, $dvrArmazenamentoTb]
        );
    } elseif ($tipoId === 4) {
        $db->query(
            "INSERT INTO equipamentos_totem (equipamento_id, quantidade_cameras, tem_facial, tem_lpr)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                quantidade_cameras = VALUES(quantidade_cameras),
                tem_facial = VALUES(tem_facial),
                tem_lpr = VALUES(tem_lpr)",
            [$equipId, $totemQuantidadeCameras, $totemTemFacial, $totemTemLpr]
        );
    }

    $db->query(
        "UPDATE locais SET
            tem_alarme = ?,
            alarme_conta = ?,
            classificacao_endereco_id = COALESCE(NULLIF(?, ''), classificacao_endereco_id),
            logradouro = COALESCE(NULLIF(?, ''), logradouro), bairro = COALESCE(NULLIF(?, ''), bairro),
            cidade = COALESCE(NULLIF(?, ''), cidade), uf = COALESCE(NULLIF(?, ''), uf),
            cep = COALESCE(NULLIF(?, ''), cep), numero = COALESCE(NULLIF(?, ''), numero),
            descricao_posicao = COALESCE(NULLIF(?, ''), descricao_posicao) WHERE id = ?",
        [$temAlarme, $alarmeConta, $classifEnderecoId, $logradouro, $bairro, $cidade, $uf, $cep, $numero, $descricaoPosicao, $localId]
    );

    $auditAfter = [
        'equipamento_id' => $equipId,
        'tipo_equipamento_id' => $tipoId,
        'tipo_camera_id' => $tipoCameraId,
        'status_id' => $statusId,
        'procedimento_id' => $procedimentoId,
        'regiao_id' => $regiaoId,
        'local_id' => $localId,
        'secretaria_id' => $secretariaId,
        'marca_id' => $marcaId,
        'modelo_id' => $modeloId,
        'ip' => $normalizedIp,
        'patrimonio' => !empty($data['patrimonio']) ? strtoupper(trim($data['patrimonio'])) : null,
        'numero_serie' => !empty($data['serie_mac']) ? strtoupper(trim($data['serie_mac'])) : null,
        'transmissao_id' => $transmissaoId,
        'origem_link_id' => $origemLinkId,
        'inscricao' => $inscricaoLink,
        'descricao_posicao' => $descricaoPosicao,
        'data_instalacao' => $dataInstalacao,
        'observacao' => !empty($data['observacao']) ? trim($data['observacao']) : null,
        'mosaico' => !empty($data['mosaico']) ? strtoupper(trim($data['mosaico'])) : null,
        'coordenadas' => !empty($data['coordenadas']) ? trim($data['coordenadas']) : null,
        'numero_ruas' => !empty($data['numero_ruas']) ? strtoupper(trim($data['numero_ruas'])) : null,
        'tem_alarme' => $temAlarme,
        'alarme_conta' => $alarmeConta,
        'numero' => $numero,
        'lpr_sentido_via' => $lprSentidoVia,
        'lpr_faixa_monitorada' => $lprFaixaMonitorada,
        'lpr_leitura_noturna' => $lprLeituraNoturna,
        'dvr_modelo' => $dvrModelo,
        'dvr_canais' => $dvrCanais,
        'dvr_armazenamento_tb' => $dvrArmazenamentoTb,
        'totem_quantidade_cameras' => $totemQuantidadeCameras,
        'totem_tem_facial' => $totemTemFacial,
        'totem_tem_lpr' => $totemTemLpr,
    ];
    auditEvent($db, 'equipamentos', $equipId, 'UPDATE', $before, $auditAfter, 'api');

    $db->commit();

    ApiResponse::success([
        'message' => 'Equipamento atualizado com sucesso!',
        'redirect' => '../../index.php?page=controle_cameras',
    ]);

} catch (Throwable $e) {
    try {
        if (isset($db) && $db->getConnection()->inTransaction()) {
            $db->rollback();
        }
    } catch (Exception $ignored) {}

    error_log('[api_editar_camera] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao editar câmera.');
}
