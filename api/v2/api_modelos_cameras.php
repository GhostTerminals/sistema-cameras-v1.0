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
        'marca_id' => 'required|numeric|min:1',
        'tipo_id' => 'numeric|min:1',
        'tipo_equipamento_id' => 'numeric|min:1',
    ]);

    if ($validator->fails()) {
        ApiResponse::validationError($validator->errors());
    }

    $marcaId = (int)($_GET['marca_id'] ?? 0);
    $tipoId = (int)($_GET['tipo_id'] ?? $_GET['tipo_equipamento_id'] ?? 0);

    $db = db();
    $sql = "SELECT id, nome FROM catalogo_modelos WHERE marca_id = :marca_id";
    $params = [':marca_id' => $marcaId];

    if ($tipoId > 0) {
        $sql .= " AND tipo_equipamento_id = :tipo_id";
        $params[':tipo_id'] = $tipoId;
    }

    $sql .= " ORDER BY nome";

    $result = $db->query($sql, $params);

    if ($result['status'] === 'success') {
        ApiResponse::success(['modelos' => $result['data'] ?? []]);
    }

    ApiResponse::internalError('Erro ao buscar modelos.');

} catch (Throwable $e) {
    error_log('[api_modelos_cameras] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao buscar modelos.');
}
