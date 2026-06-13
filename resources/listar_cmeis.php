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
$page = max(1, (int)($_GET['page_num'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;
$search = trim($_GET['busca'] ?? '');

$where = "WHERE l.nome LIKE '%CMEI%'";
$params = [];
if ($search !== '') {
    $where .= " AND (l.nome LIKE ? OR l.logradouro LIKE ? OR l.bairro LIKE ? OR l.cidade LIKE ?)";
    $like = "%$search%";
    $params = [$like, $like, $like, $like];
}

$countResult = $db->query("SELECT COUNT(*) as total FROM locais l $where", $params);
$total = (int)($countResult['status'] === 'success' ? ($countResult['data'][0]->total ?? 0) : 0);
$totalPages = max(1, ceil($total / $perPage));

$result = $db->query(
    "SELECT l.id, l.nome, l.logradouro, l.bairro, l.cidade, l.uf, l.numero,
            s.nome as secretaria, s.sigla, tl.nome as tipo_local
     FROM locais l
     LEFT JOIN secretarias s ON l.secretaria_id = s.id
     LEFT JOIN tipos_locais tl ON l.tipo_local_id = tl.id
     $where
     ORDER BY l.nome
     LIMIT $perPage OFFSET $offset",
    $params
);
$locais = ($result['status'] === 'success') ? $result['data'] : [];
?>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-info text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas fa-child me-2"></i>Lista de CMEIs</h4>
                <a href="?page=administracao" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-arrow-left me-1"></i>Voltar
                </a>
            </div>
        </div>
        <div class="card-body">
            <form method="get" class="row g-2 mb-3">
                <input type="hidden" name="page" value="listar_cmeis">
                <div class="col-md-4">
                    <input type="text" name="busca" class="form-control form-control-sm"
                        placeholder="Buscar por nome, logradouro, bairro..."
                        value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="fas fa-search me-1"></i>Buscar
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="?page=listar_cmeis" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="fas fa-times me-1"></i>Limpar
                    </a>
                </div>
            </form>

            <p class="text-muted small">Total: <strong><?= $total ?></strong> CMEIs encontrados</p>

            <?php if (!empty($locais)): ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>Nome</th>
                            <th>Logradouro</th>
                            <th>Bairro</th>
                            <th>Cidade</th>
                            <th>Secretaria</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($locais as $l): ?>
                        <tr>
                            <td><?= htmlspecialchars($l->nome ?? '') ?></td>
                            <td><?= htmlspecialchars(($l->logradouro ?? '') . ($l->numero ? ', ' . $l->numero : '')) ?></td>
                            <td><?= htmlspecialchars($l->bairro ?? '') ?></td>
                            <td><?= htmlspecialchars($l->cidade ?? '') ?></td>
                            <td><span class="badge bg-info"><?= htmlspecialchars($l->sigla ?? $l->secretaria ?? '-') ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <nav>
                <ul class="pagination pagination-sm justify-content-center">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=listar_cmeis&page_num=<?= $page - 1 ?>&busca=<?= urlencode($search) ?>">Anterior</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=listar_cmeis&page_num=<?= $i ?>&busca=<?= urlencode($search) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=listar_cmeis&page_num=<?= $page + 1 ?>&busca=<?= urlencode($search) ?>">Próximo</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>

            <?php else: ?>
            <div class="alert alert-info text-center py-4">
                <i class="fas fa-child fa-2x mb-3"></i><br>
                Nenhum CMEI encontrado.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
