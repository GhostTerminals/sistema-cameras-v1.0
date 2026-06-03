<?php
require_once __DIR__ . '/../inc/navbar.php';
requererAcesso('admin');
?>

<style nonce="<?= htmlspecialchars($CSP_NONCE ?? '', ENT_QUOTES, 'UTF-8') ?>">
.admin-page .btn-admin-strong {
    background: linear-gradient(135deg, #0f4c81, #0c3a63);
    color: #fff;
    border: none;
    box-shadow: 0 8px 18px rgba(12, 58, 99, 0.25);
}

.admin-page .btn-admin-strong:hover {
    color: #fff;
    background: linear-gradient(135deg, #0c3a63, #0a304f);
}

.admin-page .u-admin-logo-side {
    max-height: 280px;
    object-fit: contain;
    filter: drop-shadow(0 10px 20px rgba(0, 0, 0, 0.18));
}

.admin-page .admin-tools .btn {
    font-weight: 600;
}

</style>

<section class="admin-page bg-body-tertiary d-flex align-items-center">
    <div class="container">
        <div class="mb-3">
            <a href="?page=home" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Voltar
            </a>
        </div>
        <div class="row justify-content-center align-items-center">
            <div class="col-md-6 col-lg-5">
                <div class="card border-0 shadow-lg rounded-4">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <h3 class="fw-bold mb-1">Cadastrar Usuario</h3>
                            <p class="text-muted small mb-0">Gerencie os acessos do sistema</p>
                        </div>

                        <?php if (!empty($_SESSION['nome_existe'])): ?>
                        <div class="alert alert-danger text-center small">O nome escolhido ja existe.</div>
                        <?php unset($_SESSION['nome_existe']); endif; ?>

                        <?php if (!empty($_SESSION['usuario_existe'])): ?>
                        <div class="alert alert-danger text-center small">O usuario escolhido ja existe.</div>
                        <?php unset($_SESSION['usuario_existe']); endif; ?>

                        <?php if (!empty($_SESSION['status_cadastro'])): ?>
                        <div class="alert alert-success text-center small">Usuario cadastrado com sucesso.</div>
                        <?php unset($_SESSION['status_cadastro']); endif; ?>

                        <form action="?page=cadastroUsuario" method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">

                            <div class="form-floating mb-3">
                                <input type="text" id="text_nome" name="text_nome" class="form-control" placeholder="Nome" required>
                                <label for="text_nome">Nome</label>
                            </div>

                            <div class="form-floating mb-3">
                                <input type="text" id="text_usuario" name="text_usuario" class="form-control" placeholder="Usuario" required>
                                <label for="text_usuario">Usuario</label>
                            </div>

                            <div class="form-floating mb-3">
                                <input type="password" id="text_senha" name="text_senha" class="form-control" placeholder="Senha" required>
                                <label for="text_senha">Senha</label>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold" for="text_nivel_acesso">Nivel de Acesso</label>
                                <select id="text_nivel_acesso" name="text_nivel_acesso" class="form-select" required>
                                    <option value="user">Usuario</option>
                                    <option value="supervisor">Supervisor</option>
                                    <option value="admin">Administrador</option>
                                </select>
                            </div>

                            <button type="submit" name="submit" class="btn btn-admin-strong w-100 py-2 fw-semibold">
                                Cadastrar
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-5 d-none d-md-block text-center">
                <img src="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/images/logo.png" alt="Cadastro de usuario" class="img-fluid u-admin-logo-side" loading="lazy">
            </div>
        </div>

        <div class="row mt-5">
            <div class="col-12 text-center">
                <hr class="my-4">
                <div class="row g-3 justify-content-center admin-tools">
                    <div class="col-12">
                        <h5 class="fw-semibold mb-4">Ferramentas Administrativas</h5>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <a href="?page=listarUsuario" class="btn btn-primary w-100">
                            <i class="fas fa-users me-1"></i> Listar Usuarios
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <a href="?page=auditoria_cameras" class="btn btn-success w-100">
                            <i class="fas fa-file-alt me-1"></i> Relatorio de Auditoria
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

