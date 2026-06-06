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

    $db = db();

    $equipamentoId = isset($_POST['equipamento_id']) ? (int)$_POST['equipamento_id'] : null;
    $alarmeId = isset($_POST['alarme_id']) ? (int)$_POST['alarme_id'] : null;
    $manutencaoCameraId = isset($_POST['manutencao_camera_id']) ? (int)$_POST['manutencao_camera_id'] : null;
    $manutencaoAlarmeId = isset($_POST['manutencao_alarme_id']) ? (int)$_POST['manutencao_alarme_id'] : null;
    $tipo = trim($_POST['tipo'] ?? 'foto');
    $descricao = trim($_POST['descricao'] ?? '');

    if (!$equipamentoId && !$alarmeId && !$manutencaoCameraId && !$manutencaoAlarmeId) {
        ApiResponse::error('VALIDATION_ERROR', 'Informe equipamento_id, alarme_id, manutencao_camera_id ou manutencao_alarme_id.');
    }

    $tipoPermitido = ['foto', 'documento', 'anexo'];
    if (!in_array($tipo, $tipoPermitido, true)) {
        ApiResponse::error('VALIDATION_ERROR', 'Tipo inválido. Use: foto, documento, anexo.');
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errorCode = $_FILES['file']['error'] ?? -1;
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'Arquivo excede o limite do servidor.',
            UPLOAD_ERR_FORM_SIZE => 'Arquivo excede o limite do formulário.',
            UPLOAD_ERR_PARTIAL => 'Upload foi feito parcialmente.',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo enviado.',
            UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária ausente.',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever arquivo no disco.',
            UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão.',
        ];
        $msg = $messages[$errorCode] ?? 'Erro desconhecido no upload.';
        ApiResponse::error('BAD_REQUEST', $msg);
    }

    $file = $_FILES['file'];

    $maxSize = 10 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        ApiResponse::error('BAD_REQUEST', 'Arquivo muito grande. Máximo: 10MB.');
    }

    $allowedMimes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($detectedMime, $allowedMimes, true)) {
        ApiResponse::error('VALIDATION_ERROR', 'Tipo de arquivo não permitido. Use imagens, PDF ou documentos Office.');
    }

    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/bmp' => 'bmp',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    ];

    $ext = $extMap[$detectedMime] ?? 'bin';
    $hash = bin2hex(random_bytes(16));
    $nomeArquivo = $hash . '.' . $ext;

    $subdir = 'equipamentos';
    if ($alarmeId) $subdir = 'alarmes';
    if ($manutencaoCameraId) $subdir = 'manutencoes_cameras';
    if ($manutencaoAlarmeId) $subdir = 'manutencoes_alarmes';
    $uploadDir = __DIR__ . '/../../public/uploads/' . $subdir . '/';
    $caminhoAbsoluto = $uploadDir . $nomeArquivo;

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        ApiResponse::internalError('Falha ao criar diretório de upload.');
    }

    if (!move_uploaded_file($file['tmp_name'], $caminhoAbsoluto)) {
        ApiResponse::internalError('Falha ao salvar arquivo no servidor.');
    }

    $caminhoRelativo = 'uploads/' . $subdir . '/' . $nomeArquivo;

    try {
        $result = $db->insert('equipamentos_anexos', [
            'equipamento_id' => $equipamentoId,
            'alarme_id' => $alarmeId,
            'manutencao_camera_id' => $manutencaoCameraId,
            'manutencao_alarme_id' => $manutencaoAlarmeId,
            'tipo' => $tipo,
            'nome_original' => $file['name'],
            'nome_arquivo' => $nomeArquivo,
            'caminho' => $caminhoRelativo,
            'mime_type' => $detectedMime,
            'tamanho' => $file['size'],
            'descricao' => $descricao ?: null,
            'created_by' => currentUserId(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        if ($result['status'] !== 'success') {
            throw new RuntimeException('Falha ao inserir anexo no banco.');
        }

        $anexoId = (int)$db->lastInsertId();
    } catch (Throwable $e) {
        if (is_file($caminhoAbsoluto)) { unlink($caminhoAbsoluto); }
        error_log('Erro ao inserir anexo: ' . $e->getMessage());
        ApiResponse::internalError('Erro ao registrar anexo no banco de dados.');
    }

    $baseUrl = defined('BASE_URL') ? BASE_URL : '';
    ApiResponse::created([
        'message' => 'Arquivo enviado com sucesso.',
        'anexo' => [
            'id' => $anexoId,
            'nome_original' => $file['name'],
            'nome_arquivo' => $nomeArquivo,
            'url' => $baseUrl . '/' . $caminhoRelativo,
            'tamanho' => $file['size'],
            'mime_type' => $detectedMime,
            'tipo' => $tipo,
            'descricao' => $descricao,
        ],
    ], $anexoId);

} catch (Throwable $e) {
    error_log('[api_upload_anexo] ' . $e->getMessage());
    ApiResponse::internalError('Erro ao fazer upload.');
}
