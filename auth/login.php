<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$erro = $_SESSION['error'] ?? null;
unset($_SESSION['error']);

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

.page-login .login-title {
    font-weight: 600;
    text-align: center;
    letter-spacing: 1px;
    margin-bottom: 12px;
}

.page-login .login-input {
    background: rgba(255, 255, 255, 0.92);
    border-radius: 8px;
    border: 1px solid rgba(0, 0, 0, 0.1);
}

.page-login .login-btn {
    background-color: #1f3c60;
    border: none;
    font-weight: 500;
    color: #fff;
}

.page-login .login-btn:hover {
    background-color: #152a45;
}
</style>

<div class="login-wrapper">
    <div class="login-card">
        <h3 class="login-title">Login</h3>
        <hr>
        <form action="?page=login_submit" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="mb-3">
                <label for="text_usuario">Usuario</label>
                <input type="text" name="text_usuario" class="form-control login-input" required>
            </div>
            <div class="mb-3">
                <label for="text_senha">Senha</label>
                <input type="password" name="text_senha" class="form-control login-input" required>
            </div>
            <div class="d-grid gap-2">
                <input type="submit" name="submit" value="Entrar" class="btn login-btn w-100">
            </div>
        </form>
        <?php if (!empty($erro)) : ?>
        <div class="alert alert-danger mt-3 p-2 text-center">
            <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>
        <div class="text-center mt-2">
            <small class="text-muted">
                <i class="fas fa-info-circle me-1"></i>Esqueceu a senha? Contacte o administrador.
            </small>
        </div>
    </div>
</div>
