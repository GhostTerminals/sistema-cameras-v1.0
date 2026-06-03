<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="?page=home">
            <i class="fas fa-camera me-2"></i>
            Controle de Cameras
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="?page=home">
                        <i class="fas fa-home me-1"></i>Dashboard
                    </a>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-video me-1"></i>Cameras
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="?page=controle_cameras"><i class="fas fa-gauge-high me-2"></i>Painel de Cameras</a></li>
                        <li><a class="dropdown-item" href="?page=cadastro_cameras"><i class="fas fa-plus me-2"></i>Cadastro de Cameras</a></li>
                        <li><a class="dropdown-item" href="?page=listar_cameras"><i class="fas fa-list me-2"></i>Listar Cameras</a></li>
                        <li><a class="dropdown-item" href="?page=editar_cameras"><i class="fas fa-pen-to-square me-2"></i>Editar Cameras</a></li>
                        <li><a class="dropdown-item" href="?page=manutencao_cameras"><i class="fas fa-tools me-2"></i>Manutencao de Cameras</a></li>
                    </ul>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-bell me-1"></i>Alarmes
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="?page=controle_alarmes"><i class="fas fa-gauge-high me-2"></i>Painel de Alarmes</a></li>
                        <li><a class="dropdown-item" href="?page=cadastro_alarmes"><i class="fas fa-plus me-2"></i>Cadastro de Alarmes</a></li>
                        <li><a class="dropdown-item" href="?page=editar_alarmes"><i class="fas fa-pen-to-square me-2"></i>Editar Alarmes</a></li>
                        <li><a class="dropdown-item" href="?page=manutencao_alarmes"><i class="fas fa-tools me-2"></i>Manutencao de Alarmes</a></li>
                    </ul>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-chart-column me-1"></i>Relatorios
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="?page=relatorios_cameras"><i class="fas fa-camera me-2"></i>Relatorios de Cameras</a></li>
                        <li><a class="dropdown-item" href="?page=relatorios_alarmes"><i class="fas fa-bell me-2"></i>Relatorios de Alarmes</a></li>
                    </ul>
                </li>

                <?php if (isset($_SESSION['usuario']) && isset($_SESSION['usuario']->nivel_acesso) && $_SESSION['usuario']->nivel_acesso === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="?page=administracao">
                        <i class="fas fa-cog me-1"></i>Administracao
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <div class="navbar-nav">
                <!-- Dark Mode Toggle -->
                <button 
                  id="themeToggle" 
                  class="btn btn-outline-light ms-2" 
                  aria-label="Alternar modo escuro/claro"
                  title="Modo escuro"
                  type="button">
                  <i class="fas fa-moon"></i>
                </button>

                <?php if (isset($_SESSION['usuario'])): ?>
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i>
                        <strong><?= htmlspecialchars($_SESSION['usuario']->usuario ?? '') ?></strong>
                        <?php if (isset($_SESSION['usuario']->senha_temporaria) && $_SESSION['usuario']->senha_temporaria == 1): ?>
                        <span class="badge bg-warning ms-1">Trocar Senha</span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <span class="dropdown-item-text text-muted small">
                                <i class="fas fa-user me-1"></i>
                                <?= htmlspecialchars($_SESSION['usuario']->nome ?? $_SESSION['usuario']->usuario) ?>
                            </span>
                        </li>
                        <li>
                            <span class="dropdown-item-text text-muted small">
                                <i class="fas fa-shield-alt me-1"></i>
                                <?= htmlspecialchars($_SESSION['usuario']->nivel_acesso ?? 'user') ?>
                                <?php if (($_SESSION['usuario']->nivel_acesso ?? '') === 'supervisor'): ?>
                                <span class="badge bg-info ms-1">Supervisor</span>
                                <?php endif; ?>
                            </span>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <?php if (isset($_SESSION['usuario']->senha_temporaria) && $_SESSION['usuario']->senha_temporaria == 1): ?>
                        <li>
                            <a class="dropdown-item text-warning" href="?page=trocar_senha">
                                <i class="fas fa-exclamation-triangle me-1"></i>Trocar Senha
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        <li>
                            <form method="post" action="?page=logout" class="px-2 py-1">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="dropdown-item">
                                    <i class="fas fa-sign-out-alt me-1"></i>Sair
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
                <?php else: ?>
                <a class="nav-link" href="?page=login">
                    <i class="fas fa-sign-in-alt me-1"></i>Login
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<?php if (isset($_SESSION['usuario']) && isset($_SESSION['usuario']->senha_temporaria) && $_SESSION['usuario']->senha_temporaria == 1): ?>
<div class="alert alert-warning alert-dismissible fade show mb-0 text-center" role="alert">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <strong>Alerta de Seguranca:</strong> Voce esta usando uma senha temporaria.
    <a href="?page=trocar_senha" class="alert-link">Clique aqui para definir sua senha permanente</a>.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>
