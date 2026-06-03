<?php

require_once __DIR__ . '/api_manutencao_utils.php';

function loadManutencaoPayload(
    database $db,
    int $equipamentoId,
    int $page,
    int $perPage,
    string $busca,
    string $sortBy,
    string $sortDir,
    bool $includeLists,
    string $dataInicial = '',
    string $dataFinal = ''
): array {
    $cameras = [];
    $statusOptions = [];
    $procedimentoOptions = [];
    $defaults = getMaintenanceDefaultIds($db);

    if ($includeLists) {
        $camerasQuery = "
            SELECT
                e.id,
                COALESCE(l.nome, e.codigo_publico, CONCAT('EQUIPAMENTO ', e.id)) AS descricao,
                l.nome AS local_nome,
                l.logradouro AS local_logradouro,
                l.numero AS local_numero,
                l.bairro AS local_bairro,
                l.cidade AS local_cidade,
                l.uf AS local_uf,
                l.cep AS local_cep,
                ce.nome AS tipo_logradouro,
                EXISTS (SELECT 1 FROM equipamentos_manutencoes em WHERE em.equipamento_id = e.id AND em.os_status_id IN (1, 2)) AS em_manutencao,
                e.ip,
                e.numero_serie,
                cm.nome AS modelo_nome,
                te.nome AS tipo_equipamento_nome,
                s.nome AS status_nome
            FROM equipamentos e
            LEFT JOIN locais l ON l.id = e.local_id
            LEFT JOIN classificacao_enderecos ce ON ce.id = l.classificacao_endereco_id
            LEFT JOIN catalogo_modelos cm ON cm.id = e.modelo_id
            LEFT JOIN tipos_equipamento te ON te.id = COALESCE(e.tipo_equipamento_id, cm.tipo_equipamento_id)
            LEFT JOIN status s ON s.id = e.status_id
            ORDER BY descricao ASC, e.id ASC
        ";
        $camerasResult = $db->query($camerasQuery);
        $cameras = $camerasResult['status'] === 'success' ? array_map(fn($row) => (array)$row, $camerasResult['data']) : [];

        $statusResult = $db->query("SELECT id, nome FROM status ORDER BY nome ASC");
        $statusOptions = $statusResult['status'] === 'success' ? array_map(fn($row) => (array)$row, $statusResult['data']) : [];

        $procedimentosResult = $db->query("SELECT id, nome FROM procedimentos ORDER BY nome ASC");
        $procedimentoOptions = $procedimentosResult['status'] === 'success' ? array_map(fn($row) => (array)$row, $procedimentosResult['data']) : [];
    }

    $whereParts = [];
    $params = [];
    if ($equipamentoId > 0) {
        $whereParts[] = 'm.equipamento_id = :equipamento_id';
        $params[':equipamento_id'] = $equipamentoId;
    }
    if ($busca !== '') {
        $paramVal = '%' . $busca . '%';
        $whereParts[] = '(m.local_servico LIKE :bc1 OR m.endereco_servico LIKE :bc2 OR e.ip LIKE :bc3)';
        $params[':bc1'] = $paramVal;
        $params[':bc2'] = $paramVal;
        $params[':bc3'] = $paramVal;
    }

    $whereSql = empty($whereParts) ? '' : 'AND ' . implode(' AND ', $whereParts);

    $historicoParts = $whereParts;
    $historicoParams = $params;
    if ($busca !== '') {
        $historicoParts = array_values(array_filter($historicoParts, function ($p) {
            return strpos($p, ':bc1') === false && strpos($p, ':bc2') === false && strpos($p, ':bc3') === false;
        }));
        $paramVal = '%' . $busca . '%';
        $historicoParts[] = '(m.local_servico LIKE :bh01 OR m.endereco_servico LIKE :bh02 OR e.ip LIKE :bh03 OR m.descricao LIKE :bh04 OR m.tecnico LIKE :bh05 OR m.numero_os LIKE :bh06 OR m.pecas_previstas LIKE :bh07 OR COALESCE(l.nome, e.codigo_publico, CONCAT(\'EQUIPAMENTO \', e.id)) LIKE :bh08 OR cm.nome LIKE :bh09 OR p.nome LIKE :bh10 OR u.nome LIKE :bh11)';
        $historicoParams[':bh01'] = $paramVal;
        $historicoParams[':bh02'] = $paramVal;
        $historicoParams[':bh03'] = $paramVal;
        $historicoParams[':bh04'] = $paramVal;
        $historicoParams[':bh05'] = $paramVal;
        $historicoParams[':bh06'] = $paramVal;
        $historicoParams[':bh07'] = $paramVal;
        $historicoParams[':bh08'] = $paramVal;
        $historicoParams[':bh09'] = $paramVal;
        $historicoParams[':bh10'] = $paramVal;
        $historicoParams[':bh11'] = $paramVal;
        unset($historicoParams[':bc1'], $historicoParams[':bc2'], $historicoParams[':bc3']);
    }
    if ($dataInicial !== '') {
        $historicoParts[] = 'COALESCE(m.data_execucao, m.data_hora, m.created_at) >= :data_inicial';
        $historicoParams[':data_inicial'] = $dataInicial . ' 00:00:00';
    }
    if ($dataFinal !== '') {
        $historicoParts[] = 'COALESCE(m.data_execucao, m.data_hora, m.created_at) <= :data_final';
        $historicoParams[':data_final'] = $dataFinal . ' 23:59:59';
    }
    $historicoWhereSql = empty($historicoParts) ? '' : 'AND ' . implode(' AND ', $historicoParts);

    $pendingQuery = "
        SELECT
            m.id,
            m.equipamento_id,
            m.procedimento_id,
            m.numero_os,
            m.local_servico,
            m.endereco_servico,
            m.problemas,
            m.data_hora,
            m.created_at,
            COALESCE(l.nome, e.codigo_publico, CONCAT('EQUIPAMENTO ', e.id)) AS camera_nome,
            e.ip,
            e.numero_serie,
            cm.nome AS modelo_nome,
            te.nome AS tipo_equipamento_nome,
            u.nome AS usuario_nome
        FROM equipamentos_manutencoes m
        INNER JOIN equipamentos e ON e.id = m.equipamento_id
        LEFT JOIN locais l ON l.id = e.local_id
        LEFT JOIN catalogo_modelos cm ON cm.id = e.modelo_id
        LEFT JOIN tipos_equipamento te ON te.id = COALESCE(e.tipo_equipamento_id, cm.tipo_equipamento_id)
        LEFT JOIN usuarios u ON u.id = m.created_by
        WHERE m.os_status_id = 1 {$whereSql}
        ORDER BY m.created_at DESC
        LIMIT 200
    ";
    $pendingResult = $db->query($pendingQuery, $params);
    $pendingOrders = $pendingResult['status'] === 'success' ? array_map(fn($row) => (array)$row, $pendingResult['data']) : [];

    $executingQuery = "
        SELECT
            m.id,
            m.equipamento_id,
            m.procedimento_id,
            m.numero_os,
            m.local_servico,
            m.endereco_servico,
            m.problemas,
            m.data_hora,
            m.created_at,
            COALESCE(l.nome, e.codigo_publico, CONCAT('EQUIPAMENTO ', e.id)) AS camera_nome,
            e.ip,
            e.numero_serie,
            cm.nome AS modelo_nome,
            te.nome AS tipo_equipamento_nome,
            u.nome AS usuario_nome
        FROM equipamentos_manutencoes m
        INNER JOIN equipamentos e ON e.id = m.equipamento_id
        LEFT JOIN locais l ON l.id = e.local_id
        LEFT JOIN catalogo_modelos cm ON cm.id = e.modelo_id
        LEFT JOIN tipos_equipamento te ON te.id = COALESCE(e.tipo_equipamento_id, cm.tipo_equipamento_id)
        LEFT JOIN usuarios u ON u.id = m.created_by
        WHERE m.os_status_id = 2 {$whereSql}
        ORDER BY m.updated_at DESC, m.created_at DESC
        LIMIT 200
    ";
    $executingResult = $db->query($executingQuery, $params);
    $executingOrders = $executingResult['status'] === 'success' ? array_map(fn($row) => (array)$row, $executingResult['data']) : [];

    $countQuery = "
        SELECT COUNT(*) AS total
        FROM equipamentos_manutencoes m
        INNER JOIN equipamentos e ON e.id = m.equipamento_id
        LEFT JOIN locais l ON l.id = e.local_id
        LEFT JOIN catalogo_modelos cm ON cm.id = e.modelo_id
        LEFT JOIN tipos_equipamento te ON te.id = COALESCE(e.tipo_equipamento_id, cm.tipo_equipamento_id)
        LEFT JOIN status s ON s.id = m.status_id
        LEFT JOIN procedimentos p ON p.id = m.procedimento_id
        LEFT JOIN usuarios u ON u.id = m.created_by
        WHERE (m.os_status_id = 3 OR m.os_status_id IS NULL) {$historicoWhereSql}
    ";
    $countResult = $db->query($countQuery, $historicoParams);
    $total = 0;
    if ($countResult['status'] === 'success' && !empty($countResult['data'])) {
        $total = (int)($countResult['data'][0]->total ?? 0);
    }
    $offset = ($page - 1) * $perPage;
    $sortMap = [
        'data_hora' => 'COALESCE(m.data_execucao, m.data_hora, m.created_at)',
        'numero_os' => 'm.numero_os',
        'camera' => "COALESCE(l.nome, e.codigo_publico, CONCAT('EQUIPAMENTO ', e.id))",
        'procedimento' => 'p.nome',
        'status' => 's.nome',
        'tecnico' => 'm.tecnico',
        'descricao' => 'm.descricao',
        'pecas_previstas' => 'm.pecas_previstas',
        'usuario' => 'u.nome',
    ];
    $sortColumn = $sortMap[$sortBy] ?? 'COALESCE(m.data_execucao, m.data_hora, m.created_at)';
    $direction = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';
    $orderSql = "ORDER BY {$sortColumn} {$direction}, m.id DESC";

    $historyQuery = "
        SELECT
            m.id,
            m.equipamento_id,
            m.numero_os,
            m.tecnico,
            m.local_servico,
            m.endereco_servico,
            m.descricao,
            m.pecas_previstas,
            m.data_hora,
            m.data_execucao,
            m.created_at,
            COALESCE(l.nome, e.codigo_publico, CONCAT('EQUIPAMENTO ', e.id)) AS camera_nome,
            e.ip,
            e.numero_serie,
            cm.nome AS modelo_nome,
            te.nome AS tipo_equipamento_nome,
            s.nome AS status_nome,
            p.nome AS procedimento_nome,
            u.nome AS usuario_nome
        FROM equipamentos_manutencoes m
        INNER JOIN equipamentos e ON e.id = m.equipamento_id
        LEFT JOIN locais l ON l.id = e.local_id
        LEFT JOIN catalogo_modelos cm ON cm.id = e.modelo_id
        LEFT JOIN tipos_equipamento te ON te.id = COALESCE(e.tipo_equipamento_id, cm.tipo_equipamento_id)
        LEFT JOIN status s ON s.id = m.status_id
        LEFT JOIN procedimentos p ON p.id = m.procedimento_id
        LEFT JOIN usuarios u ON u.id = m.created_by
        WHERE (m.os_status_id = 3 OR m.os_status_id IS NULL) {$historicoWhereSql}
        {$orderSql}
        LIMIT {$perPage} OFFSET {$offset}
    ";
    $historyResult = $db->query($historyQuery, $historicoParams);
    $historico = $historyResult['status'] === 'success' ? array_map(fn($row) => (array)$row, $historyResult['data']) : [];

    return [
        'cameras' => $cameras,
        'status_options' => $statusOptions,
        'procedimento_options' => $procedimentoOptions,
        'defaults' => $defaults,
        'pending_orders' => $pendingOrders,
        'executing_orders' => $executingOrders,
        'historico' => $historico,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 1,
        ],
        'sorting' => [
            'sort_by' => $sortBy,
            'sort_dir' => $direction,
        ],
    ];
}

try {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($method !== 'GET' && $method !== 'POST') {
        ApiResponse::error('BAD_REQUEST', 'Método não permitido.');
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

    $db = db();

    if ($method === 'GET') {
        $equipamentoId = max(0, (int)($_GET['equipamento_id'] ?? 0));
        $page = max(1, (int)($_GET['page_num'] ?? 1));
        $perPage = (int)($_GET['per_page'] ?? 20);
        if ($perPage < 10) $perPage = 10;
        if ($perPage > 100) $perPage = 100;
        $busca = trim((string)($_GET['busca'] ?? ''));
        $sortBy = trim((string)($_GET['sort_by'] ?? 'data_hora'));
        $sortDir = trim((string)($_GET['sort_dir'] ?? 'DESC'));
        $includeLists = !isset($_GET['include_lists']) || (int)$_GET['include_lists'] !== 0;
        $dataInicial = trim((string)($_GET['data_inicial'] ?? ''));
        $dataFinal = trim((string)($_GET['data_final'] ?? ''));

        $payload = loadManutencaoPayload($db, $equipamentoId, $page, $perPage, $busca, $sortBy, $sortDir, $includeLists, $dataInicial, $dataFinal);
        ApiResponse::success($payload);
    }

    if ($method === 'POST') {
        $jsonInput = json_decode(file_get_contents('php://input'), true);
        $payload = is_array($jsonInput) ? $jsonInput : [];
        $action = strtolower(trim((string)($payload['action'] ?? 'create_os')));
        $usuarioId = (int)($_SESSION['usuario']->id ?? 0);

        if ($usuarioId <= 0) {
            ApiResponse::error('UNAUTHORIZED', 'Usuário inválido.');
        }

        if ($action === 'create_os') {
            $validator = new RequestValidator($payload);
            $validator->validate([
                'equipamento_id' => 'required|numeric|min:1',
            ]);
            if ($validator->fails()) {
                ApiResponse::validationError($validator->errors());
            }

            $equipamentoId = max(1, (int)($payload['equipamento_id'] ?? 0));
            $problemas = trim((string)($payload['problemas'] ?? ''));
            $dataHora = trim((string)($payload['data_hora'] ?? date('Y-m-d H:i:s')));
            $numeroOs = trim((string)($payload['numero_os'] ?? '')) ?: null;
            $localServico = trim((string)($payload['local_servico'] ?? '')) ?: null;
            $enderecoServico = trim((string)($payload['endereco_servico'] ?? '')) ?: null;

            if (strlen($problemas) < 5) {
                ApiResponse::error('VALIDATION_ERROR', 'Descreva os problemas da ordem de serviço.');
            }

            $equipResult = $db->query(
                "SELECT id, secretaria_id FROM equipamentos WHERE id = :id LIMIT 1",
                [':id' => $equipamentoId]
            );
            if ($equipResult['status'] !== 'success' || empty($equipResult['data'])) {
                ApiResponse::notFound('câmera', $equipamentoId);
            }
            $equipamento = (array)$equipResult['data'][0];
            $secretariaId = (int)($equipamento['secretaria_id'] ?? 0);
            if ($secretariaId <= 0) {
                $secretariaId = null;
            }

            $insert = $db->query(
                "INSERT INTO equipamentos_manutencoes
                    (equipamento_id, secretaria_id, numero_os, problemas, local_servico, endereco_servico, data_hora, descricao, created_by, os_status_id)
                 VALUES
                    (:equipamento_id, :secretaria_id, :numero_os, :problemas, :local_servico, :endereco_servico, :data_hora, :descricao, :created_by, 1)",
                [
                    ':equipamento_id' => $equipamentoId,
                    ':secretaria_id' => $secretariaId,
                    ':numero_os' => $numeroOs,
                    ':problemas' => $problemas,
                    ':local_servico' => $localServico,
                    ':endereco_servico' => $enderecoServico,
                    ':data_hora' => $dataHora,
                    ':descricao' => '',
                    ':created_by' => $usuarioId,
                ]
            );

            if ($insert['status'] !== 'success') {
                ApiResponse::internalError('Falha ao criar ordem de serviço.');
            }

            ApiResponse::created(['message' => 'Ordem de serviço cadastrada com sucesso.'], null);
        }

        if ($action === 'start_os') {
            $osId = max(1, (int)($payload['os_id'] ?? 0));
            $equipamentoId = max(1, (int)($payload['equipamento_id'] ?? 0));

            if (!$osId || !$equipamentoId) {
                ApiResponse::error('VALIDATION_ERROR', 'ID da OS e Câmera são obrigatórios.');
            }

            $osResult = $db->query(
                "SELECT id, equipamento_id, os_status_id FROM equipamentos_manutencoes WHERE id = :id LIMIT 1",
                [':id' => $osId]
            );
            if ($osResult['status'] !== 'success' || empty($osResult['data'])) {
                ApiResponse::notFound('ordem de serviço', $osId);
            }
            $osRecord = (array)$osResult['data'][0];
            if ((int)($osRecord['equipamento_id'] ?? 0) !== $equipamentoId) {
                ApiResponse::error('VALIDATION_ERROR', 'A ordem de serviço selecionada não corresponde à câmera informada.');
            }
            if (($osRecord['os_status_id'] ?? 0) !== 1) {
                ApiResponse::error('VALIDATION_ERROR', 'A ordem de serviço precisa estar cadastrada para iniciar execução.');
            }

            $update = $db->query(
                "UPDATE equipamentos_manutencoes
                 SET os_status_id = 2, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id",
                [':id' => $osId]
            );
            if ($update['status'] !== 'success') {
                ApiResponse::internalError('Falha ao iniciar execução da ordem de serviço.');
            }

            ApiResponse::success(['message' => 'Ordem de serviço em execução.']);
        }

        if ($action === 'finalize_os') {
            $osId = max(1, (int)($payload['os_id'] ?? 0));
            $equipamentoId = max(1, (int)($payload['equipamento_id'] ?? 0));
            $descricao = trim((string)($payload['descricao'] ?? ''));
            $dataHora = trim((string)($payload['data_hora'] ?? date('Y-m-d H:i:s')));
            $procedimentoId = max(0, (int)($payload['procedimento_id'] ?? 0)) ?: null;
            $statusId = max(0, (int)($payload['status_id'] ?? 0)) ?: null;
            $numeroOs = trim((string)($payload['numero_os'] ?? '')) ?: null;
            $tecnico = trim((string)($payload['tecnico'] ?? '')) ?: null;
            $localServico = trim((string)($payload['local_servico'] ?? '')) ?: null;
            $enderecoServico = trim((string)($payload['endereco_servico'] ?? '')) ?: null;
            $pecasPrevistas = trim((string)($payload['pecas_previstas'] ?? '')) ?: null;

            if (strlen($descricao) < 5) {
                ApiResponse::error('VALIDATION_ERROR', 'Descrição deve ter ao menos 5 caracteres.');
            }

            $osResult = $db->query(
                "SELECT id, equipamento_id, os_status_id FROM equipamentos_manutencoes WHERE id = :id LIMIT 1",
                [':id' => $osId]
            );
            if ($osResult['status'] !== 'success' || empty($osResult['data'])) {
                ApiResponse::notFound('ordem de serviço', $osId);
            }
            $osRecord = (array)$osResult['data'][0];
            if ((int)($osRecord['equipamento_id'] ?? 0) !== $equipamentoId) {
                ApiResponse::error('VALIDATION_ERROR', 'A ordem de serviço selecionada não corresponde à câmera informada.');
            }
            if (($osRecord['os_status_id'] ?? 0) !== 2) {
                ApiResponse::error('VALIDATION_ERROR', 'A ordem de serviço precisa estar em execução antes de finalizar.');
            }

            $db->beginTransaction();

            $update = $db->query(
                "UPDATE equipamentos_manutencoes SET
                    procedimento_id = :procedimento_id,
                    status_id = :status_id,
                    numero_os = :numero_os,
                    tecnico = :tecnico,
                    local_servico = :local_servico,
                    endereco_servico = :endereco_servico,
                    descricao = :descricao,
                    pecas_previstas = :pecas_previstas,
                    data_execucao = :data_execucao,
                    executado_por = :executado_por,
                    os_status_id = 3
                 WHERE id = :id",
                [
                    ':procedimento_id' => $procedimentoId,
                    ':status_id' => $statusId,
                    ':numero_os' => $numeroOs,
                    ':tecnico' => $tecnico,
                    ':local_servico' => $localServico,
                    ':endereco_servico' => $enderecoServico,
                    ':descricao' => $descricao,
                    ':pecas_previstas' => $pecasPrevistas,
                    ':data_execucao' => $dataHora,
                    ':executado_por' => $usuarioId,
                    ':id' => $osId,
                ]
            );
            if ($update['status'] !== 'success') {
                throw new RuntimeException('Falha ao finalizar a ordem de serviço.');
            }

            if ($statusId !== null && $statusId > 0) {
                $updateStatus = $db->query(
                    "UPDATE equipamentos SET status_id = :status_id WHERE id = :equipamento_id",
                    [':status_id' => $statusId, ':equipamento_id' => $equipamentoId]
                );
                if ($updateStatus['status'] !== 'success') {
                    throw new RuntimeException('Falha ao atualizar status do equipamento.');
                }

                $statusHistorico = $db->query(
                    "INSERT INTO equipamentos_status_historico (equipamento_id, status_id, observacao, changed_by)
                     VALUES (:equipamento_id, :status_id, :observacao, :changed_by)",
                    [
                        ':equipamento_id' => $equipamentoId,
                        ':status_id' => $statusId,
                        ':observacao' => 'Execução de ordem de serviço: ' . substr($descricao, 0, 200),
                        ':changed_by' => $usuarioId,
                    ]
                );
                if ($statusHistorico['status'] !== 'success') {
                    throw new RuntimeException('Falha ao registrar histórico de status.');
                }
            }

            $db->commit();

            ApiResponse::success(['message' => 'Ordem de serviço marcada como realizada com sucesso.']);
        }

        ApiResponse::error('BAD_REQUEST', 'Ação da API não reconhecida.');
    }
} catch (Throwable $e) {
    if (isset($db) && method_exists($db, 'rollback')) {
        try { $db->rollback(); } catch (Throwable $ignored) {}
    }
    error_log('[api_manutencao_cameras] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao processar manutenção de câmeras.');
}
