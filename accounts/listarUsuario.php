<?php
// Include necessary files
require_once __DIR__ . '/../inc/navbar.php';
require_once __DIR__ . '/../config/database.php';

// ⭐⭐ VERIFICAÇÃO DE ACESSO ADMIN ⭐⭐
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']->nivel_acesso !== 'admin') {
    header('Location: index.php?page=nao_autorizado');
    exit;
}

// Inicializa a conexão com o banco usando a classe database
$db = db();

function listarUsuarios($dbConn) {
    try {
        $sql = "SELECT u.id, u.nome, u.usuario, n.nome as nivel_acesso, u.ativo, u.created_at AS data FROM usuarios u LEFT JOIN niveis_acesso n ON u.nivel_acesso_id = n.id ORDER BY u.id DESC";
        $result = $dbConn->query($sql);
        
        if ($result['status'] === 'success') {
            return $result['data'];
        }
        return [];
    } catch (Exception $e) {
        error_log('Erro ao buscar usuários: ' . $e->getMessage());
        return [];
    }
}

$listUsuarios = listarUsuarios($db);
?>

<div class="container mt-4">
    <!-- Cabeçalho com título e botão -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Lista de Usuários</h2>
        <div>
            <a href="?page=administracao" class="btn btn-outline-primary" title="Voltar para o painel de administração">
                <i class="fas fa-arrow-left"></i> Voltar para Administração
            </a>
        </div>
    </div>
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
    <?php if (!empty($listUsuarios)): ?>
        <div class="table-responsive shadow-sm rounded">
            <table class="table table-hover table-striped mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Usuário</th>
                        <th>Nível de Acesso</th>
                        <th>Status</th>
                        <th>Data de Cadastro</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($listUsuarios as $usuario): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($usuario->id ?? ''); ?></td>
                            <td><?= htmlspecialchars($usuario->nome ?? ''); ?></td>
                            <td><?= htmlspecialchars($usuario->usuario ?? ''); ?></td>
                            <td>
                                <span class="badge <?= ($usuario->nivel_acesso === 'admin') ? 'bg-danger' : 'bg-primary' ?>">
                                    <?= htmlspecialchars($usuario->nivel_acesso ?? 'user'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= ($usuario->ativo == 1) ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= ($usuario->ativo == 1) ? 'Ativo' : 'Inativo' ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($usuario->data ?? ''); ?></td>
                            <td>
                                <div class="d-flex justify-content-center gap-2">
                                    <!-- Botão Editar com tooltip -->
                                    <a href="?page=editarUsuario&id=<?= $usuario->id; ?>" 
                                    class="btn btn-sm btn-outline-warning" 
                                    title="Editar usuário"
                                    data-bs-toggle="tooltip">
                                    <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <!-- Botão Bloquear/Ativar com tooltip -->
                                    <?php if ($usuario->ativo == 1): ?>
                                        <form method="post" action="?page=bloquearUsuario" class="d-inline">
                                            <input type="hidden" name="id" value="<?= (int)$usuario->id; ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="submit"
                                                class="btn btn-sm btn-outline-secondary"
                                                title="Bloquear usuário"
                                                data-bs-toggle="tooltip"
                                                onclick="return confirm('Tem certeza que deseja bloquear este usuário?')">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" action="?page=ativarUsuario" class="d-inline">
                                            <input type="hidden" name="id" value="<?= (int)$usuario->id; ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="submit"
                                                class="btn btn-sm btn-outline-success"
                                                title="Ativar usuário"
                                                data-bs-toggle="tooltip"
                                                onclick="return confirm('Tem certeza que deseja ativar este usuário?')">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Botão Excluir com tooltip -->
                                    <form method="post" action="?page=deletarUsuario" class="d-inline">
                                        <input type="hidden" name="id" value="<?= (int)$usuario->id; ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <button type="submit"
                                            class="btn btn-sm btn-outline-danger"
                                            title="Excluir usuário permanentemente"
                                            data-bs-toggle="tooltip"
                                            onclick="return confirm('Tem certeza que deseja excluir permanentemente este usuário?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info text-center py-4">
            <i class="fas fa-users fa-2x mb-3"></i><br>
            Nenhum usuário encontrado.
        </div>
    <?php endif; ?>    
    <!-- Botão adicional no rodapé com tooltip -->
    <div class="mt-4 text-center">
        <a href="?page=administracao" 
        class="btn btn-primary" 
        title="Voltar para o painel de administração">
        <i class="fas fa-arrow-left"></i> Voltar para Administração
        </a>
    </div>
</div>
<!-- Script para inicializar os tooltips do Bootstrap -->
<script nonce="<?= htmlspecialchars($CSP_NONCE ?? '', ENT_QUOTES, 'UTF-8') ?>">
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
