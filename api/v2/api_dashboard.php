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

    $db = db();

    $stats = [
        'total' => 0,
        'ativas' => 0,
        'manutencao' => 0,
        'desativadas' => 0,
        'atrasada' => 0,
        'uptime' => 0,
        'alarmes_total' => 0,
        'alarmes_manutencao' => 0,
        'alarmes_atrasadas' => 0,
    ];

    $result = $db->query("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN s.nome = 'FUNCIONANDO' THEN 1 ELSE 0 END) as ativas,
            SUM(CASE WHEN s.nome = 'MANUTENCAO' THEN 1 ELSE 0 END) as manutencao,
            SUM(CASE WHEN s.nome = 'DESATIVADA' THEN 1 ELSE 0 END) as desativadas
        FROM equipamentos e
        LEFT JOIN status s ON e.status_id = s.id
        WHERE e.deleted_at IS NULL
    ");
    if ($result['status'] === 'success' && !empty($result['data'])) {
        $row = $result['data'][0];
        $stats['total'] = (int)($row->total ?? 0);
        $stats['ativas'] = (int)($row->ativas ?? 0);
        $stats['manutencao'] = (int)($row->manutencao ?? 0);
        $stats['desativadas'] = (int)($row->desativadas ?? 0);
    }

    $result = $db->query("
        SELECT COUNT(DISTINCT m.equipamento_id) as atrasada
        FROM equipamentos_manutencoes m
        JOIN equipamentos e ON m.equipamento_id = e.id
        WHERE e.deleted_at IS NULL
          AND m.data_hora < DATE_SUB(NOW(), INTERVAL 7 DAY)
          AND e.status_id = (SELECT id FROM status WHERE nome = 'MANUTENCAO' LIMIT 1)
    ");
    $stats['atrasada'] = $result['status'] === 'success' ? (int)($result['data'][0]->atrasada ?? 0) : 0;

    $stats['uptime'] = $stats['total'] > 0 ? (int)round(($stats['ativas'] / $stats['total']) * 100) : 0;

    $result = $db->query("
        SELECT COUNT(*) as total,
               SUM(CASE WHEN ca.status = 'FUNCIONANDO' OR ca.status IS NULL THEN 1 ELSE 0 END) as manutencao
        FROM central_alarmes ca
    ");
    if ($result['status'] === 'success' && !empty($result['data'])) {
        $row = $result['data'][0];
        $stats['alarmes_total'] = (int)($row->total ?? 0);
        $stats['alarmes_manutencao'] = (int)($row->manutencao ?? 0);
    }

    $result = $db->query("
        SELECT COUNT(DISTINCT am.alarme_id) as atrasadas
        FROM alarmes_manutencoes am
        JOIN central_alarmes ca ON am.alarme_id = ca.id
        WHERE am.data_hora < DATE_SUB(NOW(), INTERVAL 7 DAY)
          AND am.os_status_id IN (1, 2)
    ");
    $stats['alarmes_atrasadas'] = $result['status'] === 'success' ? (int)($result['data'][0]->atrasadas ?? 0) : 0;

    $result = $db->query("
        SELECT COALESCE(te.nome, 'SEM TIPO') as tipo, COUNT(*) as quantidade
        FROM equipamentos e
        LEFT JOIN tipos_equipamento te ON e.tipo_equipamento_id = te.id
        WHERE e.deleted_at IS NULL
        GROUP BY te.id, te.nome
        ORDER BY quantidade DESC
    ");
    $tipos = $result['status'] === 'success' ? $result['data'] : [];

    $result = $db->query("
        SELECT
            COALESCE(l.nome, CONCAT('EQUIPAMENTO ', e.id)) as camera_nome,
            e.numero_serie as serie_mac,
            m.descricao,
            m.data_hora,
            sec.nome as tecnico_responsavel,
            s.nome as status
        FROM equipamentos_manutencoes m
        JOIN equipamentos e ON m.equipamento_id = e.id
        LEFT JOIN secretarias sec ON m.secretaria_id = sec.id
        LEFT JOIN status s ON e.status_id = s.id
        LEFT JOIN locais l ON e.local_id = l.id
        WHERE e.deleted_at IS NULL
        ORDER BY m.data_hora DESC
        LIMIT 5
    ");
    $manutencoes = $result['status'] === 'success' ? $result['data'] : [];

    $result = $db->query("
        SELECT
            e.id,
            COALESCE(l.nome, CONCAT('EQUIPAMENTO ', e.id)) as nome,
            e.numero_serie as numero_serie,
            l.nome as localizacao,
            s.nome as status,
            DATEDIFF(NOW(), m.data_hora) as dias_atraso,
            m.data_hora as ultima_manutencao
        FROM equipamentos e
        JOIN status s ON e.status_id = s.id
        LEFT JOIN locais l ON e.local_id = l.id
        LEFT JOIN (
            SELECT equipamento_id, MAX(data_hora) as data_hora
            FROM equipamentos_manutencoes
            GROUP BY equipamento_id
        ) m ON m.equipamento_id = e.id
        WHERE e.deleted_at IS NULL
          AND s.nome = 'MANUTENCAO'
          AND (m.data_hora IS NULL OR m.data_hora < DATE_SUB(NOW(), INTERVAL 7 DAY))
        ORDER BY m.data_hora ASC
        LIMIT 5
    ");
    $problemas = $result['status'] === 'success' ? $result['data'] : [];

    $result = $db->query("
        SELECT
            COALESCE(s.nome, 'SEM STATUS') as status,
            COUNT(*) as quantidade
        FROM equipamentos e
        LEFT JOIN status s ON e.status_id = s.id
        WHERE e.deleted_at IS NULL
        GROUP BY s.id, s.nome
        ORDER BY quantidade DESC
    ");
    $statusData = $result['status'] === 'success' ? $result['data'] : [];

    $result = $db->query("
        SELECT
            COALESCE(ca.status, 'SEM STATUS') as status,
            COUNT(*) as quantidade
        FROM central_alarmes ca
        GROUP BY ca.status
        ORDER BY quantidade DESC
    ");
    $alarmStatusData = $result['status'] === 'success' ? $result['data'] : [];

    $result = $db->query("
        SELECT COALESCE(tc.nome, 'SEM TIPO') as tipo, COUNT(*) as quantidade
        FROM equipamentos e
        LEFT JOIN tipo_cameras tc ON e.tipo_camera_id = tc.id
        WHERE e.deleted_at IS NULL
        GROUP BY tc.nome
        ORDER BY quantidade DESC
    ");
    $cameraTipoData = $result['status'] === 'success' ? $result['data'] : [];

    ApiResponse::success([
        'stats' => $stats,
        'tipos' => $tipos,
        'manutencoes' => $manutencoes,
        'problemas' => $problemas,
        'status_data' => $statusData,
        'alarm_status_data' => $alarmStatusData,
        'camera_tipo_data' => $cameraTipoData,
        'user_info' => [
            'nome' => $_SESSION['usuario']->nome ?? $_SESSION['usuario']->usuario ?? 'Usuário',
            'nivel_acesso' => $_SESSION['usuario']->nivel_acesso ?? 'user'
        ],
    ]);

} catch (Throwable $e) {
    error_log('[api_dashboard] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao carregar dashboard.');
}
