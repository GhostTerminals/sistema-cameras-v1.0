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
$search = trim($_GET['busca'] ?? '');

$where = '';
$params = [];
if ($search !== '') {
    $where = "WHERE o.nome LIKE ? OR o.inscricao LIKE ?";
    $like = "%$search%";
    $params = [$like, $like];
}

$result = $db->query(
    "SELECT o.id, o.nome, o.inscricao,
            (SELECT COUNT(*) FROM equipamentos e WHERE e.origem_link_id = o.id) as total_equipamentos
     FROM origem_link o
     $where
     ORDER BY o.nome",
    $params
);
$operadoras = ($result['status'] === 'success') ? $result['data'] : [];
?>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-warning text-dark">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas fa-network-wired me-2"></i>Lista de Operadoras / Links</h4>
                <a href="?page=administracao" class="btn btn-outline-dark btn-sm">
                    <i class="fas fa-arrow-left me-1"></i>Voltar
                </a>
            </div>
        </div>
        <div class="card-body">
            <form method="get" class="row g-2 mb-3">
                <input type="hidden" name="page" value="listar_operadoras">
                <div class="col-md-4">
                    <input type="text" name="busca" class="form-control form-control-sm"
                        placeholder="Buscar por nome ou inscrição..."
                        value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="fas fa-search me-1"></i>Buscar
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="?page=listar_operadoras" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="fas fa-times me-1"></i>Limpar
                    </a>
                </div>
            </form>

            <p class="text-muted small">Total: <strong><?= count($operadoras) ?></strong> operadoras cadastradas</p>

            <?php if (!empty($operadoras)): ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>Operadora</th>
                            <th>Inscrição</th>
                            <th>Equipamentos Vinculados</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($operadoras as $o): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($o->nome ?? '') ?></strong></td>
                            <td><code><?= htmlspecialchars($o->inscricao ?? '') ?></code></td>
                            <td>
                                <span class="badge bg-primary"><?= (int)($o->total_equipamentos ?? 0) ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="alert alert-info text-center py-4">
                <i class="fas fa-network-wired fa-2x mb-3"></i><br>
                Nenhuma operadora encontrada.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
