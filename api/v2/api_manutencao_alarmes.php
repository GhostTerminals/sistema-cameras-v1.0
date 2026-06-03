<?php
require_once __DIR__ . '/api_manutencao_utils.php';

function loadManutencaoPayload(
    database $db,
    int $alarmeId,
    int $page,
    int $perPage,
    string $busca,
    string $sortBy,
    string $sortDir,
    bool $includeLists,
    string $dataInicial = '',
    string $dataFinal = ''
): array {
    $alarme = [];
    $statusOptions = [];
    $procedimentoOptions = [];
    $defaults = getMaintenanceDefaultIds($db);

    if ($includeLists) {
        $alarmeResult = $db->query(
            "SELECT a.id, a.conta, a.local, a.endereco, a.ip, a.status, m.nome AS modelo_central
             FROM central_alarmes a
             LEFT JOIN catalogo_modelos_alarmes m ON m.id = a.modelo_alarme_id
             ORDER BY a.local ASC, a.id ASC
             LIMIT 1000"
        );
        $alarme = $alarmeResult['status'] === 'success' ? array_map(fn($row) => (array)$row, $alarmeResult['data']) : [];

        $statusResult = $db->query("SELECT id, nome FROM status ORDER BY nome ASC");
        $statusOptions = $statusResult['status'] === 'success' ? array_map(fn($row) => (array)$row, $statusResult['data']) : [];

        $procedimentosResult = $db->query("SELECT id, nome FROM procedimentos ORDER BY nome ASC");
        $procedimentoOptions = $procedimentosResult['status'] === 'success' ? array_map(fn($row) => (array)$row, $procedimentosResult['data']) : [];
    }

    $whereParts = [];
    $params = [];
    if ($alarmeId > 0) {
        $whereParts[] = 'm.alarme_id = :alarme_id';
        $params[':alarme_id'] = $alarmeId;
    }
    if ($busca !== '') {
        $paramVal = '%' . $busca . '%';
        if (ctype_digit($busca)) {
            $buscaParts = [
                'm.local_servico = :busca_conta',
                'm.endereco_servico = :busca_conta2',
                'a.ip = :busca_conta3',
                'm.descricao LIKE :busca1',
                'm.tecnico LIKE :busca2',
                'a.conta = :busca_conta_num'
            ];
            $params[':busca_conta'] = $busca;
            $params[':busca_conta2'] = $busca;
            $params[':busca_conta3'] = $busca;
            $params[':busca1'] = $paramVal;
            $params[':busca2'] = $paramVal;
            $params[':busca_conta_num'] = (int)$busca;
        } else {
            $buscaParts = [
                'm.local_servico LIKE :busca1',
                'm.endereco_servico LIKE :busca2',
                'a.ip LIKE :busca3',
                'm.descricao LIKE :busca4',
                'm.tecnico LIKE :busca5',
                'm.numero_os LIKE :busca6',
                'm.pecas_previstas LIKE :busca7',
                'a.local LIKE :busca8'
            ];
            $params[':busca1'] = $paramVal;
            $params[':busca2'] = $paramVal;
            $params[':busca3'] = $paramVal;
            $params[':busca4'] = $paramVal;
            $params[':busca5'] = $paramVal;
            $params[':busca6'] = $paramVal;
            $params[':busca7'] = $paramVal;
            $params[':busca8'] = $paramVal;
        }
        $whereParts[] = '(' . implode(' OR ', $buscaParts) . ')';
    }
    $whereSql = empty($whereParts) ? '' : 'AND ' . implode(' AND ', $whereParts);

    $historicoParts = $whereParts;
    $historicoParams = $params;
    if ($busca !== '') {
        $historicoParts = array_values(array_filter($historicoParts, function ($p) {
            return strpos($p, ':busca1') === false && strpos($p, ':busca2') === false &&
                   strpos($p, ':busca3') === false && strpos($p, ':busca4') === false &&
                   strpos($p, ':busca5') === false;
        }));

        $paramVal = '%' . $busca . '%';
        $historicBuscaParts = [
            'm.local_servico LIKE :bh01',
            'm.endereco_servico LIKE :bh02',
            'a.ip LIKE :bh03',
            'm.descricao LIKE :bh04',
            'm.tecnico LIKE :bh05',
            'm.numero_os LIKE :bh06',
            'm.pecas_previstas LIKE :bh07',
            'a.local LIKE :bh08'
        ];
        $historicoParams[':bh01'] = $paramVal;
        $historicoParams[':bh02'] = $paramVal;
        $historicoParams[':bh03'] = $paramVal;
        $historicoParams[':bh04'] = $paramVal;
        $historicoParams[':bh05'] = $paramVal;
        $historicoParams[':bh06'] = $paramVal;
        $historicoParams[':bh07'] = $paramVal;
        $historicoParams[':bh08'] = $paramVal;

        if (ctype_digit($busca)) {
            $historicBuscaParts[] = 'a.conta = :bh09';
            $historicoParams[':bh09'] = (int)$busca;
        } else {
            $historicBuscaParts[] = 'CAST(a.conta AS CHAR) LIKE :bh09';
            $historicoParams[':bh09'] = $paramVal;
        }
        $historicoParts[] = '(' . implode(' OR ', $historicBuscaParts) . ')';
        unset($historicoParams[':busca1'], $historicoParams[':busca2'], $historicoParams[':busca3'], $historicoParams[':busca4'], $historicoParams[':busca5']);
        unset($historicoParams[':busca_conta'], $historicoParams[':busca_conta2'], $historicoParams[':busca_conta3'], $historicoParams[':busca_conta_num']);
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

    $pendingResult = $db->query(
        "SELECT
            m.id, m.alarme_id, m.numero_os, m.problemas, m.data_hora, m.created_at,
            COALESCE(a.local, CONCAT('ALARME ', a.id)) AS alarme_nome,
            a.ip, a.conta, u.nome AS usuario_nome
        FROM alarmes_manutencoes m
        INNER JOIN central_alarmes a ON a.id = m.alarme_id
        LEFT JOIN usuarios u ON u.id = m.created_by
        WHERE m.os_status_id = 1 {$whereSql}
        ORDER BY m.created_at DESC
        LIMIT 200",
        $params
    );
    $pendingOrders = $pendingResult['status'] === 'success' ? array_map(fn($row) => (array)$row, $pendingResult['data']) : [];

    $executingResult = $db->query(
        "SELECT
            m.id, m.alarme_id, m.procedimento_id, m.numero_os, m.problemas,
            m.data_hora, m.created_at,
            COALESCE(a.local, CONCAT('ALARME ', a.id)) AS alarme_nome,
            a.ip, a.conta, u.nome AS usuario_nome
        FROM alarmes_manutencoes m
        INNER JOIN central_alarmes a ON a.id = m.alarme_id
        LEFT JOIN usuarios u ON u.id = m.created_by
        WHERE m.os_status_id = 2 {$whereSql}
        ORDER BY m.updated_at DESC, m.created_at DESC
        LIMIT 200",
        $params
    );
    $executingOrders = $executingResult['status'] === 'success' ? array_map(fn($row) => (array)$row, $executingResult['data']) : [];

    $countResult = $db->query(
        "SELECT COUNT(*) AS total
        FROM alarmes_manutencoes m
        INNER JOIN central_alarmes a ON a.id = m.alarme_id
        WHERE (m.os_status_id = 3 OR m.os_status_id IS NULL) {$historicoWhereSql}",
        $historicoParams
    );
    $total = 0;
    if ($countResult['status'] === 'success' && !empty($countResult['data'])) {
        $total = (int)($countResult['data'][0]->total ?? 0);
    }

    $offset = ($page - 1) * $perPage;
    $sortMap = [
        'data_hora'    => 'COALESCE(m.data_execucao, m.data_hora, m.created_at)',
        'numero_os'    => 'm.numero_os',
        'alarme'       => 'a.local',
        'procedimento' => 'p.nome',
        'status'       => 's.nome',
        'tecnico'      => 'm.tecnico',
        'descricao'    => 'm.descricao',
        'pecas_previstas' => 'm.pecas_previstas',
        'usuario'      => 'u.nome',
    ];
    $sortColumn = $sortMap[$sortBy] ?? 'COALESCE(m.data_execucao, m.data_hora, m.created_at)';
    $direction = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';
    $orderSql = "ORDER BY {$sortColumn} {$direction}, m.id DESC";

    $historyResult = $db->query(
        "SELECT
            m.id,
            m.alarme_id,
            m.numero_os,
            m.tecnico,
            m.local_servico,
            m.endereco_servico,
            m.descricao,
            m.pecas_previstas,
            m.problemas,
            m.data_hora,
            m.data_execucao,
            m.created_at,
            COALESCE(a.local, CONCAT('ALARME ', a.id)) AS alarme_nome,
            a.ip,
            a.conta,
            m2.nome AS modelo_nome,
            p.nome AS procedimento_nome,
            s.nome AS status_nome,
            u.nome AS usuario_nome
        FROM alarmes_manutencoes m
        INNER JOIN central_alarmes a ON a.id = m.alarme_id
        LEFT JOIN catalogo_modelos_alarmes m2 ON m2.id = a.modelo_alarme_id
        LEFT JOIN procedimentos p ON p.id = m.procedimento_id
        LEFT JOIN status s ON s.id = m.status_id
        LEFT JOIN usuarios u ON u.id = COALESCE(m.executado_por, m.created_by)
        WHERE (m.os_status_id = 3 OR m.os_status_id IS NULL) {$historicoWhereSql}
        {$orderSql}
        LIMIT {$perPage} OFFSET {$offset}",
        $historicoParams
    );
    $historico = $historyResult['status'] === 'success' ? array_map(fn($row) => (array)$row, $historyResult['data']) : [];

    return [
        'alarme' => $alarme,
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

    $userRole = $_SESSION['usuario']->nivel_acesso ?? ($_SESSION['usuario']->nivel ?? ($_SESSION['usuario']->role ?? ''));
    $allowedRoles = ['supervisor', 'admin'];
    if (!in_array($userRole, $allowedRoles)) {
        ApiResponse::unauthorized();
    }

    $db = db();

    if ($method === 'GET') {
        $alarmeId = max(0, (int)($_GET['alarme_id'] ?? $_GET['equipamento_id'] ?? 0));
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

        $payload = loadManutencaoPayload($db, $alarmeId, $page, $perPage, $busca, $sortBy, $sortDir, $includeLists, $dataInicial, $dataFinal);
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
                'alarme_id' => 'required|numeric|min:1',
            ]);
            if ($validator->fails()) {
                ApiResponse::validationError($validator->errors());
            }

            $alarmeId = max(1, (int)($payload['alarme_id'] ?? $payload['equipamento_id'] ?? 0));
            $problemas = trim((string)($payload['problemas'] ?? ''));
            $dataHora = trim((string)($payload['data_hora'] ?? date('Y-m-d H:i:s')));
            $numeroOs = trim((string)($payload['numero_os'] ?? '')) ?: null;

            if (strlen($problemas) < 5) {
                ApiResponse::error('VALIDATION_ERROR', 'Descreva os problemas da ordem de serviço.');
            }

            $alarmeCheck = $db->query(
                "SELECT id FROM central_alarmes WHERE id = :id LIMIT 1",
                [':id' => $alarmeId]
            );
            if ($alarmeCheck['status'] !== 'success' || empty($alarmeCheck['data'])) {
                ApiResponse::notFound('alarme', $alarmeId);
            }

            $insert = $db->query(
                "INSERT INTO alarmes_manutencoes
                    (alarme_id, numero_os, problemas, data_hora, descricao, created_by, os_status_id)
                 VALUES
                    (:alarme_id, :numero_os, :problemas, :data_hora, :descricao, :created_by, 1)",
                [
                    ':alarme_id' => $alarmeId,
                    ':numero_os' => $numeroOs,
                    ':problemas' => $problemas,
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
            $alarmeId = max(1, (int)($payload['alarme_id'] ?? $payload['equipamento_id'] ?? 0));

            if (!$osId || !$alarmeId) {
                ApiResponse::error('VALIDATION_ERROR', 'ID da OS e Alarme são obrigatórios.');
            }

            $osResult = $db->query(
                "SELECT id, alarme_id, os_status_id FROM alarmes_manutencoes WHERE id = :id LIMIT 1",
                [':id' => $osId]
            );
            if ($osResult['status'] !== 'success' || empty($osResult['data'])) {
                ApiResponse::notFound('ordem de serviço', $osId);
            }
            $osRecord = (array)$osResult['data'][0];
            if ((int)($osRecord['alarme_id'] ?? 0) !== $alarmeId) {
                ApiResponse::error('VALIDATION_ERROR', 'A ordem de serviço selecionada não corresponde ao alarme informado.');
            }
            if (($osRecord['os_status_id'] ?? 0) !== 1) {
                ApiResponse::error('VALIDATION_ERROR', 'A ordem de serviço precisa estar cadastrada para iniciar execução.');
            }

            $update = $db->query(
                "UPDATE alarmes_manutencoes
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
            $alarmeId = max(1, (int)($payload['alarme_id'] ?? $payload['equipamento_id'] ?? 0));
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
                "SELECT id, alarme_id, os_status_id FROM alarmes_manutencoes WHERE id = :id LIMIT 1",
                [':id' => $osId]
            );
            if ($osResult['status'] !== 'success' || empty($osResult['data'])) {
                ApiResponse::notFound('ordem de serviço', $osId);
            }
            $osRecord = (array)$osResult['data'][0];
            if ((int)($osRecord['alarme_id'] ?? 0) !== $alarmeId) {
                ApiResponse::error('VALIDATION_ERROR', 'A ordem de serviço selecionada não corresponde ao alarme informado.');
            }
            if (($osRecord['os_status_id'] ?? 0) !== 2) {
                ApiResponse::error('VALIDATION_ERROR', 'A ordem de serviço precisa estar em execução antes de finalizar.');
            }

            $db->beginTransaction();

            $update = $db->query(
                "UPDATE alarmes_manutencoes SET
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
                    "UPDATE alarmes_manutencoes SET status_novo = (SELECT nome FROM status WHERE id = :sid)
                     WHERE id = :id",
                    [
                        ':sid' => $statusId,
                        ':id' => $osId,
                    ]
                );
                if ($updateStatus['status'] !== 'success') {
                    throw new RuntimeException('Falha ao atualizar status_novo.');
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
    error_log('[api_manutencao_alarmes] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao processar manutenção de alarmes.');
}
