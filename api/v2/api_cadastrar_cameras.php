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

    $db = db();

    $required = ['status_id', 'procedimento_id', 'regiao_id', 'tipo_id', 'tipo_local_id', 'secretaria_id', 'marca_id'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            ApiResponse::error('VALIDATION_ERROR', "Campo obrigatório faltando: $field");
        }
    }

    $validator = new RequestValidator($data);
    $validator->validate([
        'ip' => 'string|max:45',
        'status_id' => 'required|numeric|min:1',
        'procedimento_id' => 'required|numeric|min:1',
        'regiao_id' => 'required|numeric|min:1',
        'tipo_id' => 'required|numeric|min:1',
        'tipo_local_id' => 'required|numeric|min:1',
        'secretaria_id' => 'required|numeric|min:1',
        'marca_id' => 'required|numeric|min:1',
        'nome_local' => 'string|max:150',
    ]);

    $statusId = max(1, (int)($data['status_id'] ?? 0));
    $procedimentoId = max(1, (int)($data['procedimento_id'] ?? 0));
    $regiaoId = max(1, (int)($data['regiao_id'] ?? 0));
    $tipoId = max(1, (int)($data['tipo_id'] ?? 0));
    $tipoCameraId = max(0, (int)($data['tipo_camera'] ?? 0)) ?: null;
    $tipoLocalId = max(1, (int)($data['tipo_local_id'] ?? 0));
    $secretariaId = max(1, (int)($data['secretaria_id'] ?? 0));
    $marcaId = max(1, (int)($data['marca_id'] ?? 0));
    $transmissaoId = max(0, (int)($data['transmissao_id'] ?? 0)) ?: null;
    $origemLinkId = max(0, (int)($data['origem_link_id'] ?? 0)) ?: null;
    $inscricaoLink = trim((string)($data['inscricao'] ?? '')) ?: null;
    $descricaoPosicao = trim((string)($data['descricao_posicao'] ?? '')) ?: null;
    $dataInstalacao = trim((string)($data['data_instalacao'] ?? '')) ?: null;
    $normalizedIp = trim((string)($data['ip'] ?? '')) ?: null;

    $db->beginTransaction();

    $modeloId = null;
    $nomeLocal = trim((string)($data['nome_local'] ?? ''));
    if ($nomeLocal === '') {
        throw new Exception('Informe o nome/local da câmera.');
    }

    $checkTipoLocal = $db->query("SELECT id FROM tipos_locais WHERE id = ? LIMIT 1", [$tipoLocalId]);
    if ($checkTipoLocal['status'] !== 'success' || empty($checkTipoLocal['data'])) {
        throw new Exception('Tipo de local selecionado não encontrado.');
    }

    $classifEnderecoId = max(0, (int)($data['classificacao_endereco_id'] ?? 0)) ?: null;
    if ($classifEnderecoId === null && !empty($data['tipo_logradouro'])) {
        $ceResult = $db->query("SELECT id FROM classificacao_enderecos WHERE UPPER(nome) = UPPER(?) LIMIT 1", [trim($data['tipo_logradouro'])]);
        if ($ceResult['status'] === 'success' && !empty($ceResult['data'])) {
            $classifEnderecoId = (int)$ceResult['data'][0]->id;
        }
    }
    $logradouro = trim((string)($data['logradouro'] ?? '')) ?: null;
    $bairro = trim((string)($data['bairro'] ?? '')) ?: null;
    $cidade = trim((string)($data['cidade'] ?? '')) ?: null;
    $uf = trim((string)($data['uf'] ?? '')) ?: null;
    $cep = trim((string)($data['cep'] ?? '')) ?: null;
    $numero = trim((string)($data['numero'] ?? '')) ?: null;

    $localId = max(0, (int)($data['local_id'] ?? 0)) ?: null;
    if ($localId !== null) {
        $checkLocal = $db->query("SELECT id FROM locais WHERE id = ? LIMIT 1", [$localId]);
        if ($checkLocal['status'] !== 'success' || empty($checkLocal['data'])) {
            throw new Exception('Local selecionado não encontrado.');
        }
    } else {
        $localResult = $db->query(
            "SELECT id FROM locais WHERE UPPER(nome) = UPPER(?) AND secretaria_id = ? LIMIT 1",
            [strtoupper($nomeLocal), $secretariaId]
        );

        if ($localResult['status'] === 'success' && !empty($localResult['data'])) {
            $localId = (int)$localResult['data'][0]->id;
        } else {
            $insertLocal = $db->query(
                "INSERT INTO locais (nome, logradouro, bairro, cidade, uf, cep, numero, secretaria_id, descricao_posicao, tipo_local_id, classificacao_endereco_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [strtoupper($nomeLocal), $logradouro, $bairro, $cidade, $uf, $cep, $numero, $secretariaId, $descricaoPosicao, $tipoLocalId, $classifEnderecoId]
            );
            if ($insertLocal['status'] !== 'success') {
                throw new Exception('Erro ao criar local da câmera.');
            }
            $localId = (int)$db->lastInsertId();
        }
    }

    $temAlarme   = !empty($data['tem_alarme']) ? 1 : 0;
    $alarmeConta = ($temAlarme && !empty($data['alarme_conta']))
                    ? trim((string)($data['alarme_conta'] ?? ''))
                    : null;

    $db->query(
        "UPDATE locais SET tem_alarme = ?, alarme_conta = ?, logradouro = ?, bairro = ?, cidade = ?, uf = ?, cep = ?, numero = ?, descricao_posicao = ?, tipo_local_id = ?, classificacao_endereco_id = ? WHERE id = ?",
        [$temAlarme, $alarmeConta, $logradouro, $bairro, $cidade, $uf, $cep, $numero, $descricaoPosicao, $tipoLocalId, $classifEnderecoId, $localId]
    );

    if (empty($data['modelo_existente']) && !empty($data['modelo_id'])) {
        $data['modelo_existente'] = $data['modelo_id'];
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
                throw new Exception('Erro ao criar modelo.');
            }
            $modeloId = (int)$db->lastInsertId();
        }
    } else {
        throw new Exception('Informe ou selecione um modelo.');
    }

    $mosaico = trim((string)($data['mosaico'] ?? '')) ?: null;
    $coordenadas = trim((string)($data['coordenadas'] ?? '')) ?: null;
    if ($coordenadas !== null && !preg_match('/^[+-]?\d+(?:\.\d+)?\s*,\s*[+-]?\d+(?:\.\d+)?$/', $coordenadas)) {
        throw new Exception('Coordenadas inválidas. Use formato: latitude, longitude.');
    }
    $numeroRuas = trim((string)($data['numero_ruas'] ?? '')) ?: null;

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
        throw new Exception('Informe o modelo do DVR.');
    }
    if ($tipoId === 4 && empty($totemQuantidadeCameras)) {
        throw new Exception('Informe a quantidade de câmeras do Totem.');
    }

    $equipData = [
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

    $db->query("SET @app_user_id = ?", [$_SESSION['usuario']->id ?? null]);
    $db->query("SET @app_origem = 'api'");

    $insertEquip = $db->query(
        "INSERT INTO equipamentos (
            tipo_equipamento_id, tipo_camera_id, status_id, procedimento_id, regiao_id, local_id,
            secretaria_id, marca_id, modelo_id, ip, patrimonio, numero_serie,
            transmissao_id, origem_link_id, inscricao, data_instalacao, observacao
        ) VALUES (
            :tipo_equipamento_id, :tipo_camera_id, :status_id, :procedimento_id, :regiao_id, :local_id,
            :secretaria_id, :marca_id, :modelo_id, :ip, :patrimonio, :numero_serie,
            :transmissao_id, :origem_link_id, :inscricao, :data_instalacao, :observacao
        )",
        $equipData
    );

    if ($insertEquip['status'] !== 'success') {
        throw new Exception('Erro ao salvar equipamento.');
    }

    $equipId = (int)$db->lastInsertId();

    $insertCamera = $db->query(
        "INSERT INTO equipamentos_camera (equipamento_id, mosaico, coordenadas, numero_ruas)
         VALUES (?, ?, ?, ?)",
        [$equipId, $mosaico, $coordenadas, $numeroRuas]
    );

    if ($insertCamera['status'] !== 'success') {
        throw new Exception('Erro ao salvar detalhes da câmera.');
    }

    if ($tipoId === 2) {
        $insertLpr = $db->query(
            "INSERT INTO equipamentos_lpr (equipamento_id, sentido_via, faixa_monitorada, leitura_noturna)
             VALUES (?, ?, ?, ?)",
            [$equipId, $lprSentidoVia, $lprFaixaMonitorada, $lprLeituraNoturna]
        );
        if ($insertLpr['status'] !== 'success') {
            throw new Exception('Erro ao salvar dados LPR.');
        }
    } elseif ($tipoId === 3) {
        $insertDvr = $db->query(
            "INSERT INTO equipamentos_dvr (equipamento_id, modelo, canais, armazenamento_tb)
             VALUES (?, ?, ?, ?)",
            [$equipId, $dvrModelo, $dvrCanais, $dvrArmazenamentoTb]
        );
        if ($insertDvr['status'] !== 'success') {
            throw new Exception('Erro ao salvar dados DVR.');
        }
    } elseif ($tipoId === 4) {
        $insertTotem = $db->query(
            "INSERT INTO equipamentos_totem (equipamento_id, quantidade_cameras, tem_facial, tem_lpr)
             VALUES (?, ?, ?, ?)",
            [$equipId, $totemQuantidadeCameras, $totemTemFacial, $totemTemLpr]
        );
        if ($insertTotem['status'] !== 'success') {
            throw new Exception('Erro ao salvar dados Totem.');
        }
    }

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
        'mosaico' => $mosaico,
        'coordenadas' => $coordenadas,
        'numero_ruas' => $numeroRuas,
        'numero' => $numero,
        'tem_alarme'   => $temAlarme,
        'alarme_conta' => $alarmeConta,
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

    auditEvent($db, 'equipamentos', $equipId, 'INSERT', null, $auditAfter, 'api');
    $db->commit();

    ApiResponse::created([
        'message' => "Equipamento cadastrado com sucesso! ID: $equipId",
        'camera_id' => $equipId,
        'redirect' => 'index.php?page=controle_cameras',
    ], $equipId);

} catch (Throwable $e) {
    $rolledBack = false;

    try {
        if (isset($db) && $db->getConnection()->inTransaction()) {
            $db->rollback();
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
