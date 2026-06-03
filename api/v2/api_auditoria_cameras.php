<?php

function parseDateFilter(?string $value, bool $endOfDay = false): ?string
{
    if (!$value || !is_string($value)) {
        return null;
    }

    $value = trim($value);
    if ($value === '') {
        return null;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
        return $value . ($endOfDay ? ' 23:59:59' : ' 00:00:00');
    }

    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value) === 1) {
        $parts = explode('/', $value);
        $normalized = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
        return $normalized . ($endOfDay ? ' 23:59:59' : ' 00:00:00');
    }

    return null;
}

function normalizeText($value): string
{
    return trim((string)($value ?? ''));
}

function buildResumoMudancas(int $operacaoId, ?string $dadosAntes, ?string $dadosDepois): string
{
    if ($operacaoId === 1) {
        return 'Cadastro de câmera realizado.';
    }
    if ($operacaoId === 3) {
        return 'Exclusão de câmera realizada.';
    }

    if ($operacaoId !== 2) {
        return 'Alteração registrada.';
    }

    $antes = json_decode((string)$dadosAntes, true);
    $depois = json_decode((string)$dadosDepois, true);

    if (!is_array($antes) || !is_array($depois)) {
        return 'Atualização de câmera realizada.';
    }

    $camposAlterados = [];
    foreach ($depois as $campo => $valorNovo) {
        $valorAntigo = $antes[$campo] ?? null;
        if ($valorAntigo !== $valorNovo) {
            $camposAlterados[] = $campo;
        }
    }

    if (empty($camposAlterados)) {
        return 'Atualização sem mudanças detectadas.';
    }

    return 'Campos alterados: ' . implode(', ', array_slice($camposAlterados, 0, 8));
}

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
    if (!userHasAccess('admin')) {
        ApiResponse::forbidden('Perfil sem permissao para acessar este recurso.');
    }

    $db = db();

    $page = max(1, (int)($_GET['page_num'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? 20);
    if ($perPage < 1) $perPage = 20;
    if ($perPage > 100) $perPage = 100;
    $offset = ($page - 1) * $perPage;

    $usuarioId = (int)($_GET['usuario_id'] ?? 0);
    $equipamentoId = (int)($_GET['equipamento_id'] ?? 0);
    $operacao = strtoupper(normalizeText($_GET['operacao'] ?? ''));
    $busca = normalizeText($_GET['busca'] ?? '');
    $dataInicial = parseDateFilter($_GET['data_inicial'] ?? null, false);
    $dataFinal = parseDateFilter($_GET['data_final'] ?? null, true);

    $where = ["a.entidade = 'equipamentos'"];
    $params = [];

    if ($usuarioId > 0) {
        $where[] = 'a.changed_by = :usuario_id';
        $params[':usuario_id'] = $usuarioId;
    }

    if ($equipamentoId > 0) {
        $where[] = 'a.entidade_id = :equipamento_id';
        $params[':equipamento_id'] = $equipamentoId;
    }

    if (in_array($operacao, ['INSERT', 'UPDATE', 'DELETE'], true)) {
        $mapa = ['INSERT' => 1, 'UPDATE' => 2, 'DELETE' => 3];
        $where[] = 'a.operacao_id = :operacao_id';
        $params[':operacao_id'] = $mapa[$operacao];
    }

    if ($dataInicial !== null) {
        $where[] = 'a.created_at >= :data_inicial';
        $params[':data_inicial'] = $dataInicial;
    }

    if ($dataFinal !== null) {
        $where[] = 'a.created_at <= :data_final';
        $params[':data_final'] = $dataFinal;
    }

    if ($busca !== '') {
        $where[] = '(e.codigo_publico LIKE :busca OR e.numero_serie LIKE :busca OR e.ip LIKE :busca OR u.nome LIKE :busca OR u.usuario LIKE :busca)';
        $params[':busca'] = '%' . $busca . '%';
    }

    $whereSql = implode(' AND ', $where);

    $countSql = "
        SELECT COUNT(*) as total
        FROM auditoria_eventos a
        LEFT JOIN usuarios u ON u.id = a.changed_by
        LEFT JOIN equipamentos e ON e.id = a.entidade_id
        WHERE {$whereSql}
    ";
    $countResult = $db->query($countSql, $params);
    $total = 0;
    if ($countResult['status'] === 'success' && !empty($countResult['data'])) {
        $total = (int)($countResult['data'][0]->total ?? 0);
    }

    $sql = "
        SELECT
            a.id,
            a.entidade_id as equipamento_id,
            a.operacao_id,
            a.dados_antes,
            a.dados_depois,
            a.origem,
            a.created_at,
            a.changed_by as usuario_id,
            u.nome as usuario_nome,
            u.usuario as usuario_login,
            e.codigo_publico,
            e.numero_serie,
            e.ip
        FROM auditoria_eventos a
        LEFT JOIN usuarios u ON u.id = a.changed_by
        LEFT JOIN equipamentos e ON e.id = a.entidade_id
        WHERE {$whereSql}
        ORDER BY a.created_at DESC, a.id DESC
        LIMIT {$perPage} OFFSET {$offset}
    ";
    $result = $db->query($sql, $params);

    $rows = [];
    if ($result['status'] === 'success') {
        foreach ($result['data'] as $item) {
            $row = (array)$item;
            $row['operacao'] = $item->operacao_id;
            $row['resumo'] = buildResumoMudancas(
                (int)$item->operacao_id,
                $item->dados_antes ?? null,
                $item->dados_depois ?? null
            );
            $rows[] = $row;
        }
    }

    ApiResponse::paginated($rows, $page, $perPage, $total);

} catch (Throwable $e) {
    error_log('[api_auditoria_cameras] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao carregar auditoria.');
}
