<?php
require_once __DIR__ . '/../inc/navbar.php';

if (!isset($_SESSION['usuario'])) {
    header('Location: index.php?page=login');
    exit;
}
if ($_SESSION['usuario']->nivel_acesso !== 'admin' && $_SESSION['usuario']->nivel_acesso !== 'supervisor') {
    header('Location: index.php?page=nao_autorizado');
    exit;
}

$db = db();

// Carrega filtros
$search = trim($_GET['busca'] ?? '');
$filtro_tipo = $_GET['tipo_local_id'] ?? '';
$filtro_camera = $_GET['camera'] ?? '';
$page = max(1, (int)($_GET['page_num'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$where = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $where .= " AND (l.nome LIKE :busca OR l.logradouro LIKE :busca2 OR l.bairro LIKE :busca3 OR l.cidade LIKE :busca4)";
    $like = "%$search%";
    $params[':busca'] = $like;
    $params[':busca2'] = $like;
    $params[':busca3'] = $like;
    $params[':busca4'] = $like;
}

if ($filtro_tipo !== '') {
    $where .= " AND l.tipo_local_id = :tipo_local_id";
    $params[':tipo_local_id'] = (int)$filtro_tipo;
}

// Carrega tipos para o select de filtro
$resultTipos = $db->query("SELECT id, nome FROM tipos_locais ORDER BY nome");
$tiposLocais = ($resultTipos['status'] === 'success') ? $resultTipos['data'] : [];

// Filtro por camera
if ($filtro_camera === 'sim') {
    $where .= " AND (SELECT COUNT(*) FROM equipamentos e WHERE e.local_id = l.id AND e.deleted_at IS NULL AND e.tipo_equipamento_id IN (1,2)) > 0";
} elseif ($filtro_camera === 'nao') {
    $where .= " AND (SELECT COUNT(*) FROM equipamentos e WHERE e.local_id = l.id AND e.deleted_at IS NULL AND e.tipo_equipamento_id IN (1,2)) = 0";
}

$selectColumns = "l.*, s.sigla, tl.nome as tipo_local_nome, r.nome as regiao_nome,
    (SELECT COUNT(*) FROM equipamentos e
        WHERE e.local_id = l.id AND e.deleted_at IS NULL
        AND e.tipo_equipamento_id IN (1,2)) as total_cameras,
    (SELECT COUNT(*) FROM equipamentos e
        WHERE e.local_id = l.id AND e.deleted_at IS NULL
        AND e.tipo_equipamento_id IN (3,4)) as total_outros";
$fromClause = "FROM locais l
    LEFT JOIN secretarias s ON l.secretaria_id = s.id
    LEFT JOIN tipos_locais tl ON l.tipo_local_id = tl.id
    LEFT JOIN regioes r ON l.regiao_id = r.id";

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $allResult = $db->query("SELECT $selectColumns $fromClause $where ORDER BY l.nome", $params);
    $allLocais = ($allResult['status'] === 'success') ? $allResult['data'] : [];

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="locais_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, ['Nome', 'Tipo', 'Regiao', 'Logradouro', 'Numero', 'Bairro', 'Cidade', 'UF', 'CEP', 'Secretaria', 'Tem Alarme', 'Cameras', 'Outros Equip.', 'Horario Funcionamento']);
    foreach ($allLocais as $l) {
        fputcsv($output, [
            $l->nome ?? '',
            $l->tipo_local_nome ?? '',
            $l->regiao_nome ?? '',
            $l->logradouro ?? '',
            $l->numero ?? '',
            $l->bairro ?? '',
            $l->cidade ?? '',
            $l->uf ?? '',
            $l->cep ?? '',
            $l->sigla ?? '',
            ($l->tem_alarme ?? 0) ? 'Sim' : 'Nao',
            (int)($l->total_cameras ?? 0),
            (int)($l->total_outros ?? 0),
            $l->horario_funcionamento ?? '',
        ]);
    }
    fclose($output);
    exit;
}

$countQuery = "SELECT COUNT(*) as total FROM locais l $where";
$countResult = $db->query($countQuery, $params);
$total = (int)($countResult['status'] === 'success' ? ($countResult['data'][0]->total ?? 0) : 0);
$totalPages = max(1, ceil($total / $perPage));

$dataQuery = "SELECT $selectColumns $fromClause $where ORDER BY l.nome LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
$result = $db->query($dataQuery, $params);
$locais = ($result['status'] === 'success') ? $result['data'] : [];
?>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Locais Cadastrados</h4>
            <div>
                <a href="?page=cadastro_locais" class="btn btn-outline-light btn-sm me-1">
                    <i class="fas fa-plus me-1"></i>Novo Local
                </a>
                <a href="?page=administracao" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-arrow-left me-1"></i>Voltar
                </a>
            </div>
        </div>
        <div class="card-body">
            <?php if (isset($_SESSION['sucesso'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['sucesso'], ENT_QUOTES, 'UTF-8') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <?php unset($_SESSION['sucesso']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['erro'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['erro'], ENT_QUOTES, 'UTF-8') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <?php unset($_SESSION['erro']); ?>
                </div>
            <?php endif; ?>

            <form method="get" class="row g-2 mb-3">
                <input type="hidden" name="page" value="listar_locais">
                <div class="col-md-3">
                    <input type="text" name="busca" class="form-control form-control-sm"
                        placeholder="Buscar por nome, logradouro, bairro..."
                        value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <select name="tipo_local_id" class="form-select form-select-sm">
                        <option value="">Todos os tipos</option>
                        <?php foreach ($tiposLocais as $t): ?>
                        <option value="<?= (int)$t->id ?>" <?= $filtro_tipo !== '' && (int)$filtro_tipo === (int)$t->id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t->nome, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="camera" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <option value="sim" <?= $filtro_camera === 'sim' ? 'selected' : '' ?>>Com camera</option>
                        <option value="nao" <?= $filtro_camera === 'nao' ? 'selected' : '' ?>>Sem camera</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="fas fa-search me-1"></i>Filtrar
                    </button>
                </div>
                <div class="col-md-1">
                    <a href="?page=listar_locais" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
                <div class="col-md-2">
                    <a href="?page=listar_locais&export=csv<?= $search ? '&busca=' . urlencode($search) : '' ?><?= $filtro_tipo !== '' ? '&tipo_local_id=' . urlencode($filtro_tipo) : '' ?><?= $filtro_camera !== '' ? '&camera=' . urlencode($filtro_camera) : '' ?>"
                       class="btn btn-success btn-sm w-100">
                        <i class="fas fa-file-csv me-1"></i>Exportar CSV
                    </a>
                </div>
            </form>

            <p class="text-muted small">Total: <strong><?= $total ?></strong> locais encontrados</p>

            <?php if (!empty($locais)): ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Regiao</th>
                            <th>Endereco</th>
                            <th>Bairro</th>
                            <th>Horario</th>
                            <th>Sec.</th>
                            <th class="text-center">Cameras</th>
                            <th class="text-center">Alarme</th>
                            <th class="text-center">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($locais as $l): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($l->nome ?? '', ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($l->tipo_local_nome ?? '-', ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><span class="badge" style="background-color:#6f42c1;"><?= htmlspecialchars($l->regiao_nome ?? '-', ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= htmlspecialchars(
                                trim(($l->logradouro ?? '') . ($l->numero ? ', ' . $l->numero : '')),
                                ENT_QUOTES, 'UTF-8'
                            ) ?: '-' ?></td>
                            <td><?= htmlspecialchars($l->bairro ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><small><?= htmlspecialchars($l->horario_funcionamento ?? '-', ENT_QUOTES, 'UTF-8') ?></small></td>
                            <td><span class="badge bg-info"><?= htmlspecialchars($l->sigla ?? '-', ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td class="text-center">
                                <?php $totalCam = (int)($l->total_cameras ?? 0); ?>
                                <span class="badge <?= $totalCam > 0 ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= $totalCam ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($l->tem_alarme ?? 0): ?>
                                    <span class="badge bg-success">Sim</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Nao</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <a href="?page=editar_locais&id=<?= (int)$l->id ?>"
                                    class="btn btn-sm btn-outline-warning" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <nav>
                <ul class="pagination pagination-sm justify-content-center">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=listar_locais&page_num=<?= $page - 1 ?>&busca=<?= urlencode($search) ?>&tipo_local_id=<?= urlencode($filtro_tipo) ?>&camera=<?= urlencode($filtro_camera) ?>">Anterior</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=listar_locais&page_num=<?= $i ?>&busca=<?= urlencode($search) ?>&tipo_local_id=<?= urlencode($filtro_tipo) ?>&camera=<?= urlencode($filtro_camera) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=listar_locais&page_num=<?= $page + 1 ?>&busca=<?= urlencode($search) ?>&tipo_local_id=<?= urlencode($filtro_tipo) ?>&camera=<?= urlencode($filtro_camera) ?>">Proximo</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>

            <?php else: ?>
            <div class="alert alert-info text-center py-4">
                <i class="fas fa-map-marker-alt fa-2x mb-3"></i><br>
                Nenhum local encontrado.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
