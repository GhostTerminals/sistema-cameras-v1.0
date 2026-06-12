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
        $rateLimitPassed = true;
        if (class_exists('RateLimiter', true)) {
            $rateLimiter = new RateLimiter();
            $rateLimitKey = 'recuperar_senha:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            if (!$rateLimiter->consume($rateLimitKey, 3, 900)) {
                $erro = 'Muitas tentativas. Aguarde 15 minutos antes de tentar novamente.';
                $rateLimitPassed = false;
            }
        }

        if ($rateLimitPassed) {
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

                        $_SESSION['sucesso_recuperar'] = 'Senha temporaria gerada e registrada no sistema. O usuario devera usa-la para fazer login e sera forcado a criar uma nova senha.';
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
}
?>
<style nonce="<?= htmlspecialchars($CSP_NONCE ?? '', ENT_QUOTES, 'UTF-8') ?>">
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
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <div class="mb-3">
            <div class="form-text">
                <i class="fas fa-info-circle me-1"></i>
                A senha temporaria foi registrada com seguranca. O usuario devera usa-la para fazer login e sera forcado a criar uma nova senha no primeiro acesso.
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
                <div class="form-text">Informe o nome do usuario para gerar uma nova senha temporaria segura.</div>
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
