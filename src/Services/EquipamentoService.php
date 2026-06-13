<?php

class EquipamentoService
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? db();
    }

    public static function requireAccess(): void
    {
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
    }

    public static function parseJsonInput(): array
    {
        $jsonInput = json_decode(file_get_contents('php://input'), true);
        $data = is_array($jsonInput) ? $jsonInput : [];
        if (empty($data)) {
            ApiResponse::error('BAD_REQUEST', 'Nenhum dado recebido.');
        }
        return $data;
    }

    public function extractCommonData(array $data): array
    {
        return [
            'status_id' => max(1, (int)($data['status_id'] ?? 0)),
            'procedimento_id' => max(1, (int)($data['procedimento_id'] ?? 0)),
            'regiao_id' => max(1, (int)($data['regiao_id'] ?? 0)),
            'tipo_id' => max(1, (int)($data['tipo_id'] ?? 0)),
            'tipo_camera_id' => max(0, (int)($data['tipo_camera'] ?? 0)) ?: null,
            'secretaria_id' => max(1, (int)($data['secretaria_id'] ?? 0)),
            'marca_id' => max(1, (int)($data['marca_id'] ?? 0)),
            'transmissao_id' => max(0, (int)($data['transmissao_id'] ?? 0)) ?: null,
            'origem_link_id' => max(0, (int)($data['origem_link_id'] ?? 0)) ?: null,
            'inscricao_link' => trim((string)($data['inscricao'] ?? '')) ?: null,
            'descricao_posicao' => trim((string)($data['descricao_posicao'] ?? '')) ?: null,
            'data_instalacao' => trim((string)($data['data_instalacao'] ?? '')) ?: null,
            'normalized_ip' => trim((string)($data['ip'] ?? '')) ?: null,
            'classif_endereco_id' => max(0, (int)($data['classificacao_endereco_id'] ?? 0)) ?: null,
            'logradouro' => trim((string)($data['logradouro'] ?? '')) ?: null,
            'bairro' => trim((string)($data['bairro'] ?? '')) ?: null,
            'cidade' => trim((string)($data['cidade'] ?? '')) ?: null,
            'uf' => trim((string)($data['uf'] ?? '')) ?: null,
            'cep' => trim((string)($data['cep'] ?? '')) ?: null,
            'numero' => trim((string)($data['numero'] ?? '')) ?: null,
            'tem_alarme' => !empty($data['tem_alarme']) ? 1 : 0,
            'alarme_conta' => !empty($data['alarme_conta']) ? (int)$data['alarme_conta'] : null,
            'patrimonio' => !empty($data['patrimonio']) ? strtoupper(trim($data['patrimonio'])) : null,
            'numero_serie' => !empty($data['serie_mac']) ? strtoupper(trim($data['serie_mac'])) : null,
            'observacao' => !empty($data['observacao']) ? trim($data['observacao']) : null,
            'mosaico' => trim((string)($data['mosaico'] ?? '')) ?: null,
            'coordenadas' => trim((string)($data['coordenadas'] ?? '')) ?: null,
            'numero_ruas' => trim((string)($data['numero_ruas'] ?? '')) ?: null,
            'lpr_sentido_via' => trim((string)($data['lpr_sentido_via'] ?? '')) ?: null,
            'lpr_faixa_monitorada' => trim((string)($data['lpr_faixa_monitorada'] ?? '')) ?: null,
            'lpr_leitura_noturna' => !empty($data['lpr_leitura_noturna']) ? 1 : 0,
            'lpr_url_acesso' => trim((string)($data['lpr_url_acesso'] ?? '')) ?: null,
            'dvr_modelo' => trim((string)($data['dvr_modelo'] ?? '')) ?: null,
            'dvr_canais' => max(0, (int)($data['dvr_canais'] ?? 0)) ?: null,
            'dvr_armazenamento_tb' => $this->parseDecimal($data['dvr_armazenamento_tb'] ?? null),
            'totem_quantidade_cameras' => max(0, (int)($data['totem_quantidade_cameras'] ?? 0)) ?: null,
            'totem_tem_facial' => !empty($data['totem_tem_facial']) ? 1 : 0,
            'totem_tem_lpr' => !empty($data['totem_tem_lpr']) ? 1 : 0,
        ];
    }

    private function parseDecimal($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = str_replace(',', '.', $value);
        return is_numeric($value) ? (float)$value : null;
    }

    public function validateRequiredIds(array $data): void
    {
        $required = ['status_id', 'procedimento_id', 'regiao_id', 'tipo_id', 'secretaria_id', 'marca_id'];
        $missing = [];
        foreach ($required as $field) {
            if (empty($data[$field]) || (int)$data[$field] < 1) {
                $missing[] = $field;
            }
        }
        if (!empty($missing)) {
            throw new RuntimeException('Campos obrigatórios faltando ou inválidos: ' . implode(', ', $missing));
        }
    }

    public function resolveLocation(array $data, int $secretariaId, string $nomeLocal): int
    {
        $localId = max(0, (int)($data['local_id'] ?? 0)) ?: null;

        if ($localId !== null) {
            $checkLocal = $this->db->query("SELECT id FROM locais WHERE id = ? LIMIT 1", [$localId]);
            if ($checkLocal['status'] !== 'success' || empty($checkLocal['data'])) {
                throw new Exception('Local selecionado não encontrado.');
            }
            return $localId;
        }

        $localResult = $this->db->query(
            "SELECT id FROM locais WHERE UPPER(nome) = UPPER(?) AND secretaria_id = ? LIMIT 1",
            [strtoupper($nomeLocal), $secretariaId]
        );

        if ($localResult['status'] === 'success' && !empty($localResult['data'])) {
            return (int)$localResult['data'][0]->id;
        }

        return $this->createLocation($data, $secretariaId, $nomeLocal);
    }

    public function checkLocationExists(int $localId): int
    {
        $checkLocal = $this->db->query("SELECT id FROM locais WHERE id = ? LIMIT 1", [$localId]);
        if ($checkLocal['status'] !== 'success' || empty($checkLocal['data'])) {
            throw new Exception('Local selecionado não encontrado.');
        }
        return $localId;
    }

    private function createLocation(array $data, int $secretariaId, string $nomeLocal): int
    {
        $fields = $this->extractCommonData($data);
        $insertLocal = $this->db->query(
            "INSERT INTO locais (nome, logradouro, bairro, cidade, uf, cep, numero, secretaria_id, descricao_posicao, tipo_local_id, classificacao_endereco_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [strtoupper($nomeLocal), $fields['logradouro'], $fields['bairro'], $fields['cidade'], $fields['uf'], $fields['cep'], $fields['numero'], $secretariaId, $fields['descricao_posicao'], max(1, (int)($data['tipo_local_id'] ?? 0)), $fields['classif_endereco_id']]
        );
        if ($insertLocal['status'] !== 'success') {
            throw new Exception('Erro ao criar local da câmera.');
        }
        return (int)$this->db->lastInsertId();
    }

    public function updateLocationFields(int $localId, array $data): void
    {
        $fields = $this->extractCommonData($data);

        if (!empty($fields['tem_alarme'])) {
            if (empty($fields['alarme_conta'])) {
                throw new Exception('Informe a conta do alarme.');
            }
            $checkConta = $this->db->query(
                "SELECT conta FROM central_alarmes WHERE conta = ? LIMIT 1",
                [$fields['alarme_conta']]
            );
            if ($checkConta['status'] !== 'success' || empty($checkConta['data'])) {
                throw new Exception('Conta do alarme informada não encontrada na central de alarmes.');
            }
        }

        $this->db->query(
            "UPDATE locais SET tem_alarme = ?, alarme_conta = ?, logradouro = ?, bairro = ?, cidade = ?, uf = ?, cep = ?, numero = ?, descricao_posicao = ?, tipo_local_id = ?, classificacao_endereco_id = ? WHERE id = ?",
            [$fields['tem_alarme'], $fields['alarme_conta'], $fields['logradouro'], $fields['bairro'], $fields['cidade'], $fields['uf'], $fields['cep'], $fields['numero'], $fields['descricao_posicao'], max(1, (int)($data['tipo_local_id'] ?? 0)), $fields['classif_endereco_id'], $localId]
        );
    }

    public function resolveClassificacaoEndereco(?int $classifEnderecoId, ?string $tipoLogradouro): ?int
    {
        if ($classifEnderecoId !== null) {
            return $classifEnderecoId;
        }
        if ($tipoLogradouro === null || $tipoLogradouro === '') {
            return null;
        }
        $ceResult = $this->db->query("SELECT id FROM classificacao_enderecos WHERE UPPER(nome) = UPPER(?) LIMIT 1", [trim($tipoLogradouro)]);
        if ($ceResult['status'] === 'success' && !empty($ceResult['data'])) {
            return (int)$ceResult['data'][0]->id;
        }
        return null;
    }

    public function resolveModelo(int $marcaId, int $tipoId, array $data): int
    {
        if (empty($data['modelo_existente']) && !empty($data['modelo_id'])) {
            $data['modelo_existente'] = $data['modelo_id'];
        }

        if (!empty($data['modelo_existente'])) {
            $modeloId = max(1, (int)($data['modelo_existente'] ?? 0));
            $check = $this->db->query(
                "SELECT id FROM catalogo_modelos WHERE id = ? AND marca_id = ? AND tipo_equipamento_id = ?",
                [$modeloId, $marcaId, $tipoId]
            );
            if ($check['status'] !== 'success' || empty($check['data'])) {
                throw new Exception('Modelo selecionado não pertence à marca/tipo informados.');
            }
            return $modeloId;
        }

        if (!empty($data['novo_modelo_nome'])) {
            $novoModelo = strtoupper(trim($data['novo_modelo_nome']));
            if (strlen($novoModelo) < 2) {
                throw new Exception('Nome do modelo deve ter pelo menos 2 caracteres.');
            }

            $check = $this->db->query(
                "SELECT id FROM catalogo_modelos WHERE tipo_equipamento_id = ? AND marca_id = ? AND UPPER(nome) = UPPER(?)",
                [$tipoId, $marcaId, $novoModelo]
            );

            if ($check['status'] === 'success' && !empty($check['data'])) {
                return (int)$check['data'][0]->id;
            }

            $insertModelo = $this->db->query(
                "INSERT INTO catalogo_modelos (tipo_equipamento_id, marca_id, nome) VALUES (?, ?, ?)",
                [$tipoId, $marcaId, $novoModelo]
            );
            if ($insertModelo['status'] !== 'success') {
                throw new Exception('Erro ao criar modelo.');
            }
            return (int)$this->db->lastInsertId();
        }

        throw new Exception('Informe ou selecione um modelo.');
    }

    public function validateCoordenadas(?string $coordenadas): void
    {
        if ($coordenadas !== null && !preg_match('/^[+-]?\d+(?:\.\d+)?\s*,\s*[+-]?\d+(?:\.\d+)?$/', $coordenadas)) {
            throw new Exception('Coordenadas inválidas. Use formato: latitude, longitude.');
        }
    }

    public function validateTipoSpecific(int $tipoId, ?string $dvrModelo, ?int $totemQuantidadeCameras): void
    {
        if ($tipoId === 3 && empty($dvrModelo)) {
            throw new Exception('Informe o modelo do DVR.');
        }
        if ($tipoId === 4 && empty($totemQuantidadeCameras)) {
            throw new Exception('Informe a quantidade de câmeras do Totem.');
        }
    }

    public function buildEquipData(array $fields, int $localId, int $modeloId): array
    {
        return [
            ':tipo_equipamento_id' => $fields['tipo_id'],
            ':tipo_camera_id' => $fields['tipo_camera_id'],
            ':status_id' => $fields['status_id'],
            ':procedimento_id' => $fields['procedimento_id'],
            ':regiao_id' => $fields['regiao_id'],
            ':local_id' => $localId,
            ':secretaria_id' => $fields['secretaria_id'],
            ':marca_id' => $fields['marca_id'],
            ':modelo_id' => $modeloId,
            ':ip' => $fields['normalized_ip'],
            ':patrimonio' => $fields['patrimonio'],
            ':numero_serie' => $fields['numero_serie'],
            ':transmissao_id' => $fields['transmissao_id'],
            ':origem_link_id' => $fields['origem_link_id'],
            ':inscricao' => $fields['inscricao_link'],
            ':data_instalacao' => $fields['data_instalacao'],
            ':observacao' => $fields['observacao'],
        ];
    }

    public function insertEquipamento(array $equipData): int
    {
        $insertEquip = $this->db->query(
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

        return (int)$this->db->lastInsertId();
    }

    public function updateEquipamento(array $equipData, int $equipId): void
    {
        $equipData[':id'] = $equipId;
        $result = $this->db->query(
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
    }

    public function loadBeforeSnapshot(int $equipId): ?array
    {
        $beforeResult = $this->db->query(
            "SELECT e.*, c.mosaico, c.coordenadas, c.numero_ruas,
                    l.tem_alarme, l.alarme_conta,
                    elpr.sentido_via AS lpr_sentido_via,
                    elpr.faixa_monitorada AS lpr_faixa_monitorada,
                    elpr.url_acesso AS lpr_url_acesso,
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
            return (array)$beforeResult['data'][0];
        }
        return null;
    }

    public function saveTipoSpecificInsert(int $equipId, int $tipoId, array $data): void
    {
        $fields = $this->extractCommonData($data);
        $tipoCameraId = (int)($data['tipo_camera'] ?? 0);

        $insertCamera = $this->db->query(
            "INSERT INTO equipamentos_camera (equipamento_id, mosaico, coordenadas, numero_ruas) VALUES (?, ?, ?, ?)",
            [$equipId, $fields['mosaico'], $fields['coordenadas'], $fields['numero_ruas']]
        );
        if ($insertCamera['status'] !== 'success') {
            throw new Exception('Erro ao salvar detalhes da câmera.');
        }

        if ($tipoId === 2 || $tipoCameraId === 3) {
            $result = $this->db->query(
                    "INSERT INTO equipamentos_lpr (equipamento_id, sentido_via, faixa_monitorada, url_acesso, leitura_noturna) VALUES (?, ?, ?, ?, ?)",
                    [$equipId, $fields['lpr_sentido_via'], $fields['lpr_faixa_monitorada'], $fields['lpr_url_acesso'], $fields['lpr_leitura_noturna']]
            );
            if ($result['status'] !== 'success') {
                throw new Exception('Erro ao salvar dados LPR.');
            }
        } elseif ($tipoId === 3) {
            $result = $this->db->query(
                "INSERT INTO equipamentos_dvr (equipamento_id, modelo, canais, armazenamento_tb) VALUES (?, ?, ?, ?)",
                [$equipId, $fields['dvr_modelo'], $fields['dvr_canais'], $fields['dvr_armazenamento_tb']]
            );
            if ($result['status'] !== 'success') {
                throw new Exception('Erro ao salvar dados DVR.');
            }
        } elseif ($tipoId === 4) {
            $result = $this->db->query(
                "INSERT INTO equipamentos_totem (equipamento_id, quantidade_cameras, tem_facial, tem_lpr) VALUES (?, ?, ?, ?)",
                [$equipId, $fields['totem_quantidade_cameras'], $fields['totem_tem_facial'], $fields['totem_tem_lpr']]
            );
            if ($result['status'] !== 'success') {
                throw new Exception('Erro ao salvar dados Totem.');
            }
        }
    }

    public function saveTipoSpecificUpsert(int $equipId, int $tipoId, array $data): void
    {
        $fields = $this->extractCommonData($data);
        $tipoCameraId = (int)($data['tipo_camera'] ?? 0);

        $this->db->query(
            "INSERT INTO equipamentos_camera (equipamento_id, mosaico, coordenadas, numero_ruas)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                mosaico = VALUES(mosaico),
                coordenadas = VALUES(coordenadas),
                numero_ruas = VALUES(numero_ruas)",
            [$equipId, $fields['mosaico'], $fields['coordenadas'], $fields['numero_ruas']]
        );

        if ($tipoId === 2 || $tipoCameraId === 3) {
            $this->db->query(
                "INSERT INTO equipamentos_lpr (equipamento_id, sentido_via, faixa_monitorada, url_acesso, leitura_noturna)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    sentido_via = VALUES(sentido_via),
                    faixa_monitorada = VALUES(faixa_monitorada),
                    url_acesso = VALUES(url_acesso),
                    leitura_noturna = VALUES(leitura_noturna)",
                [$equipId, $fields['lpr_sentido_via'], $fields['lpr_faixa_monitorada'], $fields['lpr_url_acesso'], $fields['lpr_leitura_noturna']]
            );
        } elseif ($tipoId === 3) {
            $this->db->query(
                "INSERT INTO equipamentos_dvr (equipamento_id, modelo, canais, armazenamento_tb)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    modelo = VALUES(modelo),
                    canais = VALUES(canais),
                    armazenamento_tb = VALUES(armazenamento_tb)",
                [$equipId, $fields['dvr_modelo'], $fields['dvr_canais'], $fields['dvr_armazenamento_tb']]
            );
        } elseif ($tipoId === 4) {
            $this->db->query(
                "INSERT INTO equipamentos_totem (equipamento_id, quantidade_cameras, tem_facial, tem_lpr)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    quantidade_cameras = VALUES(quantidade_cameras),
                    tem_facial = VALUES(tem_facial),
                    tem_lpr = VALUES(tem_lpr)",
                [$equipId, $fields['totem_quantidade_cameras'], $fields['totem_tem_facial'], $fields['totem_tem_lpr']]
            );
        }
    }

    public function buildAuditData(int $equipId, array $fields): array
    {
        return [
            'equipamento_id' => $equipId,
            'tipo_equipamento_id' => $fields['tipo_id'],
            'tipo_camera_id' => $fields['tipo_camera_id'],
            'status_id' => $fields['status_id'],
            'procedimento_id' => $fields['procedimento_id'],
            'regiao_id' => $fields['regiao_id'],
            'secretaria_id' => $fields['secretaria_id'],
            'marca_id' => $fields['marca_id'],
            'ip' => $fields['normalized_ip'],
            'patrimonio' => $fields['patrimonio'],
            'numero_serie' => $fields['numero_serie'],
            'transmissao_id' => $fields['transmissao_id'],
            'origem_link_id' => $fields['origem_link_id'],
            'inscricao' => $fields['inscricao_link'],
            'descricao_posicao' => $fields['descricao_posicao'],
            'data_instalacao' => $fields['data_instalacao'],
            'observacao' => $fields['observacao'],
            'mosaico' => $fields['mosaico'],
            'coordenadas' => $fields['coordenadas'],
            'numero_ruas' => $fields['numero_ruas'],
            'numero' => $fields['numero'],
            'tem_alarme' => $fields['tem_alarme'],
            'alarme_conta' => $fields['alarme_conta'],
            'lpr_sentido_via' => $fields['lpr_sentido_via'],
            'lpr_faixa_monitorada' => $fields['lpr_faixa_monitorada'],
            'lpr_url_acesso' => $fields['lpr_url_acesso'],
            'lpr_leitura_noturna' => $fields['lpr_leitura_noturna'],
            'dvr_modelo' => $fields['dvr_modelo'],
            'dvr_canais' => $fields['dvr_canais'],
            'dvr_armazenamento_tb' => $fields['dvr_armazenamento_tb'],
            'totem_quantidade_cameras' => $fields['totem_quantidade_cameras'],
            'totem_tem_facial' => $fields['totem_tem_facial'],
            'totem_tem_lpr' => $fields['totem_tem_lpr'],
        ];
    }

    public function setAppUserContext(?int $userId): void
    {
        $this->db->query("SET @app_user_id = ?", [$userId]);
        $this->db->query("SET @app_origem = 'api'");
    }

    public function beginTransaction(): void
    {
        $this->db->beginTransaction();
    }

    public function commit(): void
    {
        $this->db->commit();
    }

    public function rollback(): void
    {
        try {
            if ($this->db->getConnection()->inTransaction()) {
                $this->db->rollback();
            }
        } catch (Exception $ignored) {}
    }

    public function handleException(Throwable $e, string $logPrefix): void
    {
        $this->rollback();
        error_log("[$logPrefix] " . $e->getMessage());
        ApiResponse::internalError('Erro ao processar requisição.');
    }
}
