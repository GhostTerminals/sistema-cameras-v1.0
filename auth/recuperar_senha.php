<?php
$erro = $_SESSION['erro_recuperar'] ?? null;
unset($_SESSION['erro_recuperar']);
$sucesso = $_SESSION['sucesso_recuperar'] ?? null;
unset($_SESSION['sucesso_recuperar']);

$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $csrfToken = $_POST['csrf_token'] ?? null;
    if (!validateCsrfToken($csrfToken)) {
        $erro = 'Falha de validacao CSRF.';
    } else {
        $usuario = trim($_POST['text_usuario'] ?? '');

        if ($usuario === '') {
            $erro = 'Informe o nome de usuario.';
        } else {
            $result = $db->query(
                "SELECT id, nome FROM usuarios WHERE usuario = :usuario AND ativo = 1",
                [':usuario' => $usuario]
            );

            if ($result['status'] === 'success' && !empty($result['data'])) {
                $userData = $result['data'][0];
                $novaSenha = generateTemporaryPassword();
                $senhaHash = hashPassword($novaSenha);

                $update = $db->query(
                    "UPDATE usuarios SET senha = :senha, senha_temporaria = 1 WHERE id = :id",
                    [':senha' => $senhaHash, ':id' => $userData->id]
                );

                if ($update['status'] === 'success') {
                    auditEvent($db, 'usuarios', (int)$userData->id, 'UPDATE', [
                        'senha_temporaria' => 0
                    ], [
                        'senha_temporaria' => 1,
                        'motivo' => 'recuperacao_de_senha'
                    ], 'web');

                    $_SESSION['sucesso_recuperar'] = $novaSenha;
                    header('Location: ?page=recuperar_senha');
                    exit;
                } else {
                    $erro = 'Erro ao redefinir a senha. Tente novamente.';
                }
            } else {
                $erro = 'Usuario nao encontrado ou inativo.';
            }
        }
    }
}
?>
<style nonce="<?= htmlspecialchars($CSP_NONCE ?? '', ENT_QUOTES, 'UTF-8') ?>">
.pass-box {
    background: #1a1a2e;
    color: #f0e68c;
    font-family: monospace;
    font-size: 1.3rem;
    font-weight: bold;
    letter-spacing: 2px;
    padding: 12px 16px;
    border-radius: 8px;
    text-align: center;
    user-select: all;
}
</style>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header">
                    <h4 class="mb-0"><i class="fas fa-key me-2"></i>Recuperar Senha</h4>
                </div>
                <div class="card-body">

        <?php if ($sucesso): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>Senha temporaria gerada com sucesso!
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">Nova senha temporaria do usuario:</label>
            <div class="pass-box"><?= htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="form-text mt-2">
                <i class="fas fa-info-circle me-1"></i>
                Copie esta senha agora. Por seguranca, ela nao sera exibida novamente.
                O usuario devera usar esta senha para fazer login e sera forcado a criar uma nova.
            </div>
        </div>
        <a href="?page=recuperar_senha" class="btn btn-primary">
            <i class="fas fa-redo me-1"></i>Recuperar outra senha
        </a>
        <a href="?page=listarUsuario" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i>Voltar
        </a>

        <?php else: ?>

        <?php if ($erro): ?>
        <div class="alert alert-danger p-2 text-center">
            <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>

        <form action="?page=recuperar_senha" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="mb-3">
                <label for="text_usuario" class="form-label">Nome de Usuario</label>
                <input type="text" name="text_usuario" id="text_usuario" class="form-control" required autofocus>
                <div class="form-text">Informe o nome do usuario para gerar uma nova senha temporaria de 6 digitos numericos.</div>
            </div>
            <div class="d-grid gap-2">
                <button type="submit" name="submit" class="btn btn-primary">
                    <i class="fas fa-key me-1"></i>Gerar Nova Senha
                </button>
                <a href="?page=listarUsuario" class="btn btn-outline-secondary">Voltar</a>
            </div>
        </form>

        <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
