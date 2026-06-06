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

    $validator = new RequestValidator($_GET);
    $validator->validate([
        'id' => 'numeric|min:0',
        'busca' => 'string|max:100',
        'excluir_manutencao' => 'string|max:5',
        'page_num' => 'numeric|min:1',
        'per_page' => 'numeric|min:1|max:200',
        'status' => 'string|max:50',
        'modelo_id' => 'numeric|min:1',
        'local_id' => 'numeric|min:1',
    ]);

    if ($validator->fails()) {
        ApiResponse::validationError($validator->errors());
    }

    $id = max(0, (int)($_GET['id'] ?? 0));
    $busca = trim((string)($_GET['busca'] ?? ''));

    $status = trim($_GET['status'] ?? '');
    $modeloId = (int)($_GET['modelo_id'] ?? 0);
    $localId = (int)($_GET['local_id'] ?? 0);
    $excluirManutencao = !empty($_GET['excluir_manutencao']);
    $page = max(1, (int)($_GET['page'] ?? $_GET['page_num'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? 50);
    if ($perPage < 1) $perPage = 50;
    if ($perPage > 200) $perPage = 200;
    $offset = ($page - 1) * $perPage;

    $db = db();

    $whereParts = ['e.tipo_equipamento_id = :tipo_equipamento_id'];
    $params = [':tipo_equipamento_id' => 1];

    if ($id > 0) {
        $whereParts[] = 'e.id = :id';
        $params[':id'] = $id;
    }

    if ($busca !== '') {
        $buscaParam = '%' . $busca . '%';

        $buscaParts = [];
        $isIp = filter_var($busca, FILTER_VALIDATE_IP);

        if ($isIp) {
            $buscaParts[] = 'INET_ATON(e.ip) = INET_ATON(:busca_ip)';
            $params[':busca_ip'] = $busca;
        } else {
            $buscaParts[] = '(e.ip LIKE :busca_ip_like OR e.numero_serie LIKE :busca_serie)';
            $params[':busca_ip_like'] = $buscaParam;
            $params[':busca_serie'] = $buscaParam;
        }

        if (strlen($busca) >= 3) {
            $buscaParts[] = 'MATCH(l.nome, l.logradouro, l.bairro) AGAINST(:busca_fulltext IN BOOLEAN MODE)';
            $params[':busca_fulltext'] = $busca . '*';

            $buscaParts[] = 'MATCH(cm.nome) AGAINST(:busca_modelo IN BOOLEAN MODE)';
            $params[':busca_modelo'] = $busca . '*';

            $buscaParts[] = 'MATCH(sec.nome) AGAINST(:busca_secretaria IN BOOLEAN MODE)';
            $params[':busca_secretaria'] = $busca . '*';
        } else {
            $buscaParts[] = '(l.nome LIKE :busca_local OR cm.nome LIKE :busca_cm OR sec.nome LIKE :busca_sec OR CAST(e.id AS CHAR) LIKE :busca_id)';
            $params[':busca_local'] = $buscaParam;
            $params[':busca_cm'] = $buscaParam;
            $params[':busca_sec'] = $buscaParam;
            $params[':busca_id'] = $buscaParam;
        }

        $whereParts[] = '(' . implode(' OR ', $buscaParts) . ')';
    }

    if ($status !== '') {
        $whereParts[] = 's.nome = :status';
        $params[':status'] = strtoupper($status);
    }

    if ($modeloId > 0) {
        $whereParts[] = 'e.modelo_id = :modelo_id';
        $params[':modelo_id'] = $modeloId;
    }

    if ($localId > 0) {
        $whereParts[] = 'e.local_id = :local_id';
        $params[':local_id'] = $localId;
    }

    $whereSql = empty($whereParts) ? '' : ('WHERE ' . implode(' AND ', $whereParts));

    $baseFrom = "
        FROM equipamentos e
        LEFT JOIN status s ON e.status_id = s.id
        LEFT JOIN locais l ON e.local_id = l.id
        LEFT JOIN secretarias sec ON e.secretaria_id = sec.id
        LEFT JOIN catalogo_modelos cm ON e.modelo_id = cm.id
        LEFT JOIN origem_link ol ON e.origem_link_id = ol.id
        LEFT JOIN equipamentos_camera ec ON ec.equipamento_id = e.id
        LEFT JOIN equipamentos_lpr elpr ON elpr.equipamento_id = e.id
        LEFT JOIN equipamentos_dvr edvr ON edvr.equipamento_id = e.id
        LEFT JOIN equipamentos_totem etot ON etot.equipamento_id = e.id
        LEFT JOIN classificacao_enderecos ce ON l.classificacao_endereco_id = ce.id
        LEFT JOIN tipo_cameras tc ON e.tipo_camera_id = tc.id
        LEFT JOIN (
            SELECT equipamento_id, MIN(os_status_id) AS manutencao_status
            FROM equipamentos_manutencoes
            WHERE os_status_id IN (1, 2)
            GROUP BY equipamento_id
        ) em ON em.equipamento_id = e.id
        {$whereSql}
    ";

    $countSql = "SELECT COUNT(*) AS total {$baseFrom}";
    $countResult = $db->query($countSql, $params);
    if (!$countResult || $countResult['status'] !== 'success') {
        ApiResponse::internalError('Erro ao contar câmeras.');
    }
    $total = !empty($countResult['data']) ? (int)($countResult['data'][0]->total ?? 0) : 0;

    $sql = "SELECT
                e.id,
                COALESCE(l.nome, CONCAT('EQUIPAMENTO ', e.id)) AS descricao,
                CASE WHEN em.manutencao_status IS NOT NULL THEN 1 ELSE 0 END AS em_manutencao,
                e.ip,
                e.numero_serie,
                e.numero_serie AS serie_mac,
                e.data_instalacao,
                e.observacao,
                e.local_id,
                e.secretaria_id,
                e.status_id,
                e.modelo_id,
                e.marca_id,
                e.tipo_equipamento_id AS tipo_id,
                e.tipo_camera_id,
                tc.nome AS tipo_camera_nome,
                e.transmissao_id,
                e.origem_link_id,
                e.patrimonio,
                ec.mosaico,
                ec.coordenadas,
                ec.numero_ruas,
                ol.inscricao,
                s.nome AS status_nome,
                l.nome AS local_nome,
                l.logradouro AS local_logradouro,
                l.bairro AS local_bairro,
                l.cidade AS local_cidade,
                l.uf AS local_uf,
                l.cep AS local_cep,
                l.numero AS local_numero,
                l.descricao_posicao,
                ce.nome AS tipo_logradouro,
                l.tem_alarme,
                l.alarme_conta,
                sec.nome AS secretaria_nome,
                cm.nome AS modelo_nome,
                elpr.sentido_via AS lpr_sentido_via,
                elpr.faixa_monitorada AS lpr_faixa_monitorada,
                elpr.leitura_noturna AS lpr_leitura_noturna,
                edvr.modelo AS dvr_modelo,
                edvr.canais AS dvr_canais,
                edvr.armazenamento_tb AS dvr_armazenamento_tb,
                etot.quantidade_cameras AS totem_quantidade_cameras,
                etot.tem_facial AS totem_tem_facial,
                etot.tem_lpr AS totem_tem_lpr
            {$baseFrom}
            ORDER BY e.id DESC
            LIMIT {$perPage} OFFSET {$offset}";

    $result = $db->query($sql, $params);
    if (!$result || $result['status'] !== 'success') {
        ApiResponse::internalError('Erro ao buscar câmeras.');
    }

    $data = $result['data'] ?? [];
    ApiResponse::paginated($data, $page, $perPage, $total);

} catch (Throwable $e) {
    error_log('[api_cameras] ' . $e->getMessage());
    ApiResponse::internalError();
}
