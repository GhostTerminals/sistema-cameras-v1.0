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
        'page_num' => 'numeric|min:1',
        'per_page' => 'numeric|min:1|max:100',
        'status' => 'string|max:50',
        'regiao' => 'string|max:50',
        'conta' => 'numeric|min:0',
        'nome' => 'string|max:100',
        'busca' => 'string|max:100',
    ]);

    if ($validator->fails()) {
        ApiResponse::validationError($validator->errors());
    }

    $id = max(0, (int)($_GET['id'] ?? 0));
    $page = max(1, (int)($_GET['page_num'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? 20);
    if ($perPage < 1) $perPage = 20;
    if ($perPage > 100) $perPage = 100;
    $offset = ($page - 1) * $perPage;

    $status = trim((string)($_GET['status'] ?? ''));
    $regiao = trim((string)($_GET['regiao'] ?? ''));
    $conta = trim((string)($_GET['conta'] ?? ''));
    $nome = trim((string)($_GET['nome'] ?? ''));
    $busca = trim((string)($_GET['busca'] ?? ''));

    $db = db();
    $where = [];
    $params = [];

    if ($id > 0) {
        $where[] = 'a.id = :id';
        $params[':id'] = $id;
    }
    if ($status !== '') {
        $where[] = 'a.status = :status';
        $params[':status'] = $status;
    }
    if ($regiao !== '') {
        $where[] = 'a.regiao = :regiao';
        $params[':regiao'] = $regiao;
    }
    if ($conta !== '' && ctype_digit($conta)) {
        $where[] = 'a.conta = :conta';
        $params[':conta'] = (int)$conta;
    }
    if ($nome !== '') {
        $where[] = '(a.local LIKE :nome OR a.endereco LIKE :nome)';
        $params[':nome'] = '%' . $nome . '%';
    }

    if ($busca !== '') {
        $paramVal = '%' . $busca . '%';

        if (ctype_digit($busca)) {
            $where[] = '(a.conta = :busca_conta OR
                       a.local LIKE :busca1 OR
                       a.endereco LIKE :busca2 OR
                       a.observacao LIKE :busca3 OR
                       a.numero_sei LIKE :busca4 OR
                       a.ip LIKE :busca5 OR
                       a.ip_dvr LIKE :busca6)';
            $params[':busca_conta'] = (int)$busca;
            $params[':busca1'] = $paramVal;
            $params[':busca2'] = $paramVal;
            $params[':busca3'] = $paramVal;
            $params[':busca4'] = $paramVal;
            $params[':busca5'] = $paramVal;
            $params[':busca6'] = $paramVal;
        } else {
            $where[] = '(a.local LIKE :busca1 OR
                       a.endereco LIKE :busca2 OR
                       a.observacao LIKE :busca3 OR
                       a.numero_sei LIKE :busca4 OR
                       a.ip LIKE :busca5 OR
                       a.ip_dvr LIKE :busca6 OR
                       CAST(a.conta AS CHAR) LIKE :busca7)';
            $params[':busca1'] = $paramVal;
            $params[':busca2'] = $paramVal;
            $params[':busca3'] = $paramVal;
            $params[':busca4'] = $paramVal;
            $params[':busca5'] = $paramVal;
            $params[':busca6'] = $paramVal;
            $params[':busca7'] = $paramVal;
        }
    }

    $whereSql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));

    $countSql = "SELECT COUNT(*) AS total FROM central_alarmes a {$whereSql}";
    $countResult = $db->query($countSql, $params);

    if (!$countResult || $countResult['status'] !== 'success') {
        $msg = (string)($countResult['error'] ?? '');
        if (stripos($msg, "doesn't exist") !== false || stripos($msg, 'Base table or view not found') !== false) {
            ApiResponse::error('NOT_FOUND', 'Tabela de alarmes não encontrada. Execute o script SQL.');
        }
        ApiResponse::internalError('Erro ao carregar alarmes.');
    }

    $total = !empty($countResult['data']) ? (int)($countResult['data'][0]->total ?? 0) : 0;

    $startTime = microtime(true);

    $sql = "
        SELECT
            a.id,
            a.conta,
            a.status,
            a.regiao,
            a.local,
            a.endereco,
            a.numero,
            a.pgm1,
            a.pgm2,
            a.mac,
            a.ip,
            a.integracao,
            a.camera_gm,
            a.quant_camera_gm,
            a.ip_dvr,
            a.cameras_dvr,
            a.modelo_alarme_id,
            a.quant_repetidor,
            a.qtde_sensores,
            a.documentacao,
            a.monitorada,
            a.numero_sei,
            a.data_atualizacao,
            a.observacao,
            m.nome AS modelo_central
        FROM central_alarmes a
        LEFT JOIN catalogo_modelos_alarmes m ON m.id = a.modelo_alarme_id
        {$whereSql}
        ORDER BY a.data_atualizacao DESC, a.id DESC
        LIMIT {$perPage} OFFSET {$offset}
    ";

    $result = $db->query($sql, $params);

    $queryTime = microtime(true) - $startTime;
    if ($queryTime > 1.0) {
        error_log("Consulta lenta em api_alarmes: {$queryTime}s");
    }

    if (!$result || $result['status'] !== 'success') {
        $msg = (string)($result['error'] ?? '');
        if (stripos($msg, "doesn't exist") !== false || stripos($msg, 'Base table or view not found') !== false) {
            ApiResponse::error('NOT_FOUND', 'Tabela central_alarmes não encontrada.');
        }
        ApiResponse::internalError('Erro ao consultar alarmes.');
    }

    $rows = $result['data'] ?? [];
    ApiResponse::paginated($rows, $page, $perPage, $total);

} catch (Throwable $e) {
    error_log('[api_alarmes] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao carregar alarmes.');
}
