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
                $novaSenha = generateTemporaryPassword(12);
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
.page-login {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    padding: 24px;
    background: url('<?= BASE_URL ?>/images/tela_fundo.png') no-repeat center center fixed;
    background-color: #0f1e2f;
    background-size: min(720px, 85vw) auto;
    box-shadow:
        inset 0 0 120px rgba(0, 0, 0, 0.6),
        inset 0 0 0 6px rgba(255, 255, 255, 0.08);
}

.page-login .login-wrapper {
    width: min(420px, 100%);
}

.page-login .login-card {
    background: rgba(255, 255, 255, 0.18);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.25);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.35);
    color: #132238;
    padding: 32px 28px;
}

.page-login .pass-box {
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

<div class="login-wrapper">
    <div class="login-card">
        <h3 class="login-title text-center fw-bold mb-3">Recuperar Senha</h3>
        <hr>

        <?php if ($sucesso): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>Senha temporaria gerada com sucesso!
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">Sua nova senha temporaria:</label>
            <div class="pass-box"><?= htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="form-text mt-2">
                <i class="fas fa-info-circle me-1"></i>
                Copie esta senha agora. Por seguranca, ela nao sera exibida novamente.
                Use-a para fazer login e voce sera forcado a criar uma senha permanente.
            </div>
        </div>
        <a href="?page=login" class="btn login-btn w-100 text-white" style="background:#1f3c60;border:none;font-weight:500">
            <i class="fas fa-arrow-left me-1"></i>Voltar ao Login
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
                <div class="form-text">Informe seu nome de usuario para gerar uma nova senha temporaria.</div>
            </div>
            <div class="d-grid gap-2">
                <button type="submit" name="submit" class="btn login-btn text-white" style="background:#1f3c60;border:none;font-weight:500">
                    <i class="fas fa-key me-1"></i>Gerar Nova Senha
                </button>
                <a href="?page=login" class="btn btn-outline-secondary">Voltar ao Login</a>
            </div>
        </form>

        <?php endif; ?>
    </div>
</div>
