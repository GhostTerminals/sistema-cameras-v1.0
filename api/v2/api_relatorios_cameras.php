<?php

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ApiResponse::error('BAD_REQUEST', 'Apenas GET é permitido');
    }

    if (session_status() === PHP_SESSION_NONE) {
        configureSessionSecurity();
        session_start();
    }

    if (!isset($_SESSION['usuario'])) {
        ApiResponse::unauthorized();
    }
    if (!userHasAccess('supervisor')) {
        ApiResponse::forbidden('Perfil sem permissao para acessar este recurso.');
    }

    $db = db();

    $page = max(1, (int)($_GET['page_num'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? 50);
    if ($perPage < 1) $perPage = 50;
    if ($perPage > 200) $perPage = 200;
    $offset = ($page - 1) * $perPage;

    $whereParts = [];
    $params = [];

    if (!empty($_GET['data_inicial'])) {
        $dataInicialStr = trim($_GET['data_inicial']);
        $dataInicial = DateTime::createFromFormat('d/m/Y', $dataInicialStr);
        if ($dataInicial) {
            $whereParts[] = "e.data_instalacao >= :data_inicial";
            $params[':data_inicial'] = $dataInicial->format('Y-m-d') . ' 00:00:00';
        }
    }

    if (!empty($_GET['data_final'])) {
        $dataFinalStr = trim($_GET['data_final']);
        $dataFinal = DateTime::createFromFormat('d/m/Y', $dataFinalStr);
        if ($dataFinal) {
            $whereParts[] = "e.data_instalacao <= :data_final";
            $params[':data_final'] = $dataFinal->format('Y-m-d') . ' 23:59:59';
        }
    }

    if (!empty($_GET['status'])) {
        $whereParts[] = "e.status_id = :status";
        $params[':status'] = $_GET['status'];
    }

    if (!empty($_GET['local'])) {
        $whereParts[] = "e.local_id = :local";
        $params[':local'] = $_GET['local'];
    }

    if (!empty($_GET['regiao'])) {
        $whereParts[] = "e.regiao_id = :regiao";
        $params[':regiao'] = $_GET['regiao'];
    }

    if (!empty($_GET['pesquisa'])) {
        $pesquisa = '%' . trim($_GET['pesquisa']) . '%';
        $whereParts[] = "e.ip LIKE :pesquisa";
        $params[':pesquisa'] = $pesquisa;
    }

    $whereSql = empty($whereParts) ? '' : ('WHERE ' . implode(' AND ', $whereParts));
    $baseFrom = "
        FROM equipamentos e
        LEFT JOIN catalogo_modelos cm ON e.modelo_id = cm.id
        LEFT JOIN marcas ma ON e.marca_id = ma.id
        LEFT JOIN locais l ON e.local_id = l.id
        LEFT JOIN secretarias sec ON e.secretaria_id = sec.id
        LEFT JOIN status s ON e.status_id = s.id
        LEFT JOIN procedimentos proc ON e.procedimento_id = proc.id
        LEFT JOIN regioes r ON e.regiao_id = r.id
        LEFT JOIN transmissoes t ON e.transmissao_id = t.id
        LEFT JOIN origem_link ol ON e.origem_link_id = ol.id
        LEFT JOIN tipo_cameras tc ON e.tipo_camera_id = tc.id
        LEFT JOIN classificacao_enderecos ce ON l.classificacao_endereco_id = ce.id
        LEFT JOIN equipamentos_camera ec ON ec.equipamento_id = e.id
        LEFT JOIN equipamentos_lpr elpr ON elpr.equipamento_id = e.id
        LEFT JOIN central_alarmes ca ON l.alarme_conta = ca.conta
        {$whereSql}
    ";

    $countSql = "SELECT COUNT(*) AS total {$baseFrom}";
    $countResult = $db->query($countSql, $params);
    if (!$countResult || $countResult['status'] !== 'success') {
        ApiResponse::internalError('Erro ao contar registros.');
    }
    $total = !empty($countResult['data']) ? (int)($countResult['data'][0]->total ?? 0) : 0;

    $query = "
        SELECT
            e.id,
            COALESCE(l.nome, CONCAT('EQUIPAMENTO ', e.id)) AS nome_local,
            COALESCE(l.nome, CONCAT('EQUIPAMENTO ', e.id)) AS descricao,
            e.ip,
            e.porta,
            elpr.url_acesso,
            e.numero_serie AS serie_mac,
            e.patrimonio,
            e.data_instalacao,
            e.observacao,
            e.created_at,
            e.updated_at,
            e.local_id,
            e.secretaria_id,
            e.status_id,
            e.modelo_id,
            e.marca_id,
            e.tipo_equipamento_id AS tipo_id,
            e.tipo_camera_id,
            e.procedimento_id,
            e.regiao_id,
            e.transmissao_id,
            e.origem_link_id,
            cm.nome AS modelo_nome,
            ma.nome AS marca_nome,
            l.nome AS local_nome,
            l.logradouro AS local_logradouro,
            l.bairro AS local_bairro,
            l.cidade AS local_cidade,
            l.uf AS local_uf,
            l.cep AS local_cep,
            l.numero AS local_numero,
            sec.nome AS secretaria_nome,
            s.nome AS status_nome,
            proc.nome AS procedimento_nome,
            r.nome AS regiao_nome,
            t.tipo AS transmissao_nome,
            ol.nome AS origem_link_nome,
            tc.nome AS tipo_camera_nome,
            ce.nome AS tipo_logradouro,
            ec.mosaico,
            ec.coordenadas,
            ec.numero_ruas,
            ca.conta AS alarme_conta
        {$baseFrom}
        ORDER BY e.data_instalacao DESC
        LIMIT {$perPage} OFFSET {$offset}
    ";

    $result = $db->query($query, $params);
    if (!$result || $result['status'] !== 'success') {
        ApiResponse::internalError('Erro na consulta.');
    }

    $dados = $result['data'] ?? [];
    ApiResponse::paginated($dados, $page, $perPage, $total);

} catch (Throwable $e) {
    error_log('[api_relatorios_cameras] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao gerar relatório.');
}
