<?php
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['usuario']) || $_SESSION['usuario']->nivel_acesso !== 'admin') {
    header('Location: index.php?page=nao_autorizado');
    exit;
}

$db = db();
$mensagem_erro = '';
$mensagem_sucesso = '';

$id_usuario = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if (!$id_usuario) {
    header('Location: index.php?page=listarUsuario');
    exit;
}

$sql = "SELECT u.id, u.usuario, u.nome, n.nome as nivel_acesso, u.ativo, u.senha_temporaria FROM usuarios u LEFT JOIN niveis_acesso n ON u.nivel_acesso_id = n.id WHERE u.id = :id";
$params = [':id' => $id_usuario];
$result = $db->query($sql, $params);

if ($result['status'] !== 'success' || count($result['data']) === 0) {
    $_SESSION['erro'] = 'Usuario nao encontrado.';
    header('Location: index.php?page=listarUsuario');
    exit;
}

$usuario = $result['data'][0];
$beforeAudit = [
    'nome' => $usuario->nome ?? null,
    'usuario' => $usuario->usuario ?? null,
    'nivel_acesso' => $usuario->nivel_acesso ?? null,
    'ativo' => $usuario->ativo ?? null,
    'senha_temporaria' => $usuario->senha_temporaria ?? null
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!validateCsrfToken($csrfToken)) {
        $mensagem_erro = 'Falha de validacao CSRF.';
    } else {
        $nome = trim($_POST['nome'] ?? '');
        $usuario_novo = trim($_POST['usuario'] ?? '');
        $nivel_acesso = $_POST['nivel_acesso'] ?? 'user';
        if (!in_array($nivel_acesso, ['admin', 'supervisor', 'user'], true)) {
            $nivel_acesso = 'user';
        }
        $nivel_acesso_id = 3;
        if ($nivel_acesso === 'admin') {
            $nivel_acesso_id = 1;
        } elseif ($nivel_acesso === 'supervisor') {
            $nivel_acesso_id = 2;
        }
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $redefinir_senha = isset($_POST['redefinir_senha']);

        if ($nome === '' || $usuario_novo === '') {
            $mensagem_erro = 'Nome e usuario sao obrigatorios.';
        } else {
            $sql_verifica = "SELECT id FROM usuarios WHERE usuario = :usuario AND id != :id";
            $params_verifica = [
                ':usuario' => $usuario_novo,
                ':id' => $id_usuario
            ];
            $result_verifica = $db->query($sql_verifica, $params_verifica);

            if (($result_verifica['status'] ?? 'error') !== 'success') {
                $mensagem_erro = 'Erro ao validar usuario.';
            } elseif (count($result_verifica['data']) > 0) {
                $mensagem_erro = 'Este nome de usuario ja esta em uso.';
            } else {
                $senhaTemporariaPlain = null;

                if ($redefinir_senha) {
                    $senhaTemporariaPlain = generateTemporaryPassword(12);
                    $senhaHash = hashPassword($senhaTemporariaPlain);
                    $sql_update = "UPDATE usuarios SET nome = :nome, usuario = :usuario, nivel_acesso_id = :nivel_acesso_id, ativo = :ativo, senha = :senha, senha_temporaria = 1 WHERE id = :id";
                    $params_update = [
                        ':nome' => $nome,
                        ':usuario' => $usuario_novo,
                        ':nivel_acesso_id' => $nivel_acesso_id,
                        ':ativo' => $ativo,
                        ':senha' => $senhaHash,
                        ':id' => $id_usuario
                    ];
                } else {
                    $sql_update = "UPDATE usuarios SET nome = :nome, usuario = :usuario, nivel_acesso_id = :nivel_acesso_id, ativo = :ativo WHERE id = :id";
                    $params_update = [
                        ':nome' => $nome,
                        ':usuario' => $usuario_novo,
                        ':nivel_acesso_id' => $nivel_acesso_id,
                        ':ativo' => $ativo,
                        ':id' => $id_usuario
                    ];
                }

                $result_update = $db->query($sql_update, $params_update);

                if (($result_update['status'] ?? 'error') === 'success') {
                    $mensagem_sucesso = 'Usuario atualizado com sucesso.';
                    $usuario->nome = $nome;
                    $usuario->usuario = $usuario_novo;
                    $usuario->nivel_acesso = $nivel_acesso;
                    $usuario->ativo = $ativo;
                    $usuario->senha_temporaria = $redefinir_senha ? 1 : ($usuario->senha_temporaria ?? 0);

                    $senhaTemporariaExibida = null;
                    if ($senhaTemporariaPlain !== null) {
                        $senhaTemporariaExibida = $senhaTemporariaPlain;
                        $mensagem_sucesso .= ' Senha temporaria gerada com sucesso!';
                    }

                    auditEvent($db, 'usuarios', (int)$id_usuario, 'UPDATE', $beforeAudit, [
                        'nome' => $usuario->nome,
                        'usuario' => $usuario->usuario,
                        'nivel_acesso' => $usuario->nivel_acesso,
                        'ativo' => $usuario->ativo,
                        'senha_temporaria' => $usuario->senha_temporaria
                    ], 'web');
                } else {
                    $mensagem_erro = 'Erro ao atualizar usuario.';
                }
            }
        }
    }
}

require_once __DIR__ . '/../inc/navbar.php';
?>
<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-user-edit me-2"></i>Editar Usuario
                    </h4>
                </div>
                <div class="card-body">
                    <?php if ($mensagem_erro): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= htmlspecialchars($mensagem_erro, ENT_QUOTES, 'UTF-8') ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($mensagem_sucesso): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?= htmlspecialchars($mensagem_sucesso, ENT_QUOTES, 'UTF-8') ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php if (isset($senhaTemporariaExibida)): ?>
                        <div class="alert alert-warning" role="alert">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas fa-exclamation-triangle fa-lg"></i>
                                <strong>Nova senha temporaria gerada:</strong>
                            </div>
                            <div class="mt-2 p-3 bg-dark text-warning rounded text-center" style="font-size:1.4rem;font-weight:bold;letter-spacing:2px;font-family:monospace">
                                <?= htmlspecialchars($senhaTemporariaExibida, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <div class="mt-2 small text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Copie esta senha agora. Por seguranca, ela nao sera exibida novamente apos recarregar a pagina.
                                O usuario sera forcado a trocar a senha no proximo login.
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="nome" class="form-label">Nome Completo *</label>
                                    <input type="text" class="form-control" id="nome" name="nome" value="<?= htmlspecialchars($usuario->nome, ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="usuario" class="form-label">Nome de Usuario *</label>
                                    <input type="text" class="form-control" id="usuario" name="usuario" value="<?= htmlspecialchars($usuario->usuario, ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="nivel_acesso" class="form-label">Nivel de Acesso</label>
                                    <select class="form-select" id="nivel_acesso" name="nivel_acesso"
                                        onchange="return confirmarNivelAcesso(this)">
                                        <option value="user" <?= $usuario->nivel_acesso === 'user' ? 'selected' : '' ?>>Usuario</option>
                                        <option value="supervisor" <?= $usuario->nivel_acesso === 'supervisor' ? 'selected' : '' ?>>Supervisor</option>
                                        <option value="admin" <?= $usuario->nivel_acesso === 'admin' ? 'selected' : '' ?>>Administrador</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" id="ativo" name="ativo" value="1" <?= $usuario->ativo ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="ativo">
                                            Usuario ativo
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="redefinir_senha" name="redefinir_senha">
                                <label class="form-check-label" for="redefinir_senha">
                                    <strong>Redefinir senha para temporaria</strong>
                                </label>
                                <div class="form-text">
                                    Marque esta opcao para gerar uma nova senha temporaria segura.
                                    O usuario sera obrigado a trocar a senha no proximo login.
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <a href="?page=listarUsuario" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Voltar
                            </a>
                            <button type="submit" name="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Salvar Alteracoes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
<div class="modal fade" id="modalConfirmarRebaixar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-warning">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirmar Alteracao</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p><strong>Tem certeza que deseja rebaixar este administrador?</strong></p>
                <p class="text-muted mb-0">Esta acao pode ser irreversivel. O usuario perdera acesso administrativo ao sistema.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" id="btnConfirmarRebaixar">
                    <i class="fas fa-arrow-down me-1"></i>Rebaixar Nivel
                </button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= htmlspecialchars($CSP_NONCE ?? '', ENT_QUOTES, 'UTF-8') ?>">
(function() {
    var valorOriginal = '<?= htmlspecialchars($usuario->nivel_acesso ?? 'user', ENT_QUOTES, 'UTF-8') ?>';
    var selectEl = document.getElementById('nivel_acesso');
    var modalConfirm = new bootstrap.Modal(document.getElementById('modalConfirmarRebaixar'));
    var confirmBtn = document.getElementById('btnConfirmarRebaixar');
    var pendingValue = null;

    window.confirmarNivelAcesso = function(select) {
        if (valorOriginal === 'admin' && select.value !== 'admin') {
            pendingValue = select.value;
            modalConfirm.show();
            return false;
        }
        return true;
    };

    confirmBtn.addEventListener('click', function() {
        if (pendingValue) {
            selectEl.value = pendingValue;
        }
        modalConfirm.hide();
    });

    selectEl.addEventListener('change', function() {
        if (valorOriginal === 'admin' && this.value !== 'admin') {
            pendingValue = this.value;
            modalConfirm.show();
            this.value = valorOriginal;
        }
    });
})();
</script>
            <div class="card mt-4 border-warning">
                <div class="card-header bg-warning text-dark">
                    <h6 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>Informacoes Importantes
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li>Campos marcados com * sao obrigatorios</li>
                        <li>Ao redefinir a senha, o usuario sera forcado a troca-la no proximo login</li>
                        <li>Usuarios inativos nao poderao fazer login no sistema</li>
                        <li><strong>Supervisor:</strong> Acesso a relatorios, sem administracao de usuarios</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
