<?php
require_once __DIR__ . '/../inc/navbar.php';

// Verificar se usuario esta logado
if (!isset($_SESSION['usuario'])) {
    header('Location: index.php?page=login');
    exit;
}
?>

<link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/css/pages/home.css?v=<?= filemtime(__DIR__ . '/../public/assets/css/pages/home.css') ?>">

<div class="container-fluid mt-3">
    <!-- Boas-vindas do usuario -->
    <div class="user-welcome">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1><i class="fas fa-home me-2"></i>Dashboard do Sistema</h1>
                <p class="mb-0">Bem-vindo,
                    <strong><?= htmlspecialchars($_SESSION['usuario']->nome ?? 'Usuário') ?></strong>!
                </p>
            </div>
            <div>
                <span class="badge bg-light text-dark">
                    <i class="fas fa-user-shield me-1"></i>
                    <?= htmlspecialchars(strtoupper($_SESSION['usuario']->nivel_acesso ?? 'USER')) ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Alertas Urgentes -->
    <div class="alert alert-danger alert-urgente d-flex align-items-center d-none" role="alert" id="alertaAtraso">
        <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
        <div class="flex-grow-1">
            <h5 class="alert-heading mb-1">Alerta Cr&iacute;tico!</h5>
            <p class="mb-0">Existem <strong><span id="alertaAtrasoCount">0</span> c&acirc;mera(s)</strong> com manuten&ccedil;&atilde;o atrasada ha
                mais de 7 dias.</p>
        </div>
        <a href="?page=manutencao_cameras" class="btn btn-outline-light">Verificar</a>
    </div>

    <!-- Estat&iacute;sticas em Tempo Real -->
    <div class="row mb-4">
        <div class="col-md-2 col-6">
            <div class="stat-card bg-primary text-white">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0" id="total-cameras">0</h3>
                            <small>Total de C&acirc;meras</small>
                        </div>
                        <i class="fas fa-camera fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-2 col-6">
            <div class="stat-card bg-success text-white">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0" id="active-cameras">0</h3>
                            <small>C&acirc;meras Ativas</small>
                        </div>
                        <i class="fas fa-check-circle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-2 col-6">
            <div class="stat-card bg-warning text-white">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0" id="manutencao-cameras">0</h3>
                            <small>Em Manuten&ccedil;&atilde;o</small>
                        </div>
                        <i class="fas fa-tools fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-2 col-6">
            <div class="stat-card bg-secondary text-white">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0" id="desativadas-cameras">0</h3>
                            <small>Desativadas</small>
                        </div>
                        <i class="fas fa-power-off fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-2 col-6">
            <div class="stat-card bg-danger text-white">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0" id="alerts-count">0</h3>
                            <small>Manuten&ccedil;&atilde;o Atrasada</small>
                        </div>
                        <i class="fas fa-clock fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-2 col-6">
            <div class="stat-card bg-info text-white">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0" id="uptime-percentual">0%</h3>
                            <small>Uptime do Sistema</small>
                        </div>
                        <i class="fas fa-chart-line fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Barra de Progresso do Uptime -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="system-status">
                <h5><i class="fas fa-chart-bar me-2"></i>Status do Sistema</h5>
                <div class="d-flex justify-content-between mb-1">
                    <small>Disponibilidade do Sistema: <span id="progress-label">0%</span></small>
                    <small><span id="progress-cameras">0</span>/<span id="progress-total">0</span> c&acirc;meras operacionais</small>
                </div>
                <div class="progress progress-sm">
                    <div class="progress-bar progress-bar-custom" id="progress-bar" role="progressbar" aria-valuenow="0"
                        aria-valuemin="0" aria-valuemax="100" style="width: 0%;">
                    </div>
                </div>
                <div class="mt-2">
                    <small class="text-muted">
                        <span class="status-indicator status-online"></span> Operacional: <span id="status-ativas">0</span>
                        <span class="ms-3 status-indicator status-warning"></span> Manuten&ccedil;&atilde;o:
                        <span id="status-manutencao">0</span>
                        <span class="ms-3 status-indicator status-offline"></span> Desativadas:
                        <span id="status-desativadas">0</span>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- A&ccedil;&otilde;es R&aacute;pidas -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="chart-container mb-3">
                <h5><i class="fas fa-chart-pie me-2 text-primary"></i>C&acirc;meras: Status</h5>
                <div class="chart-wrapper">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
            <div class="chart-container mb-3">
                <h5><i class="fas fa-camera me-2 text-success"></i>Tipos de C&acirc;meras</h5>
                <div class="chart-wrapper">
                    <canvas id="typeChart"></canvas>
                </div>
            </div>
            <div class="chart-container">
                <h5><i class="fas fa-bell me-2 text-danger"></i>Alarmes: Status</h5>
                <div class="chart-wrapper">
                    <canvas id="alarmChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="row">
                <!-- M&oacute;dulos do Sistema -->
                <div class="col-lg-6 col-12 mb-3">
                    <h5 class="mb-3"><i class="fas fa-video me-2 text-primary"></i>C&acirc;meras</h5>

                    <div class="dashboard-card">
                        <div class="card-body text-center p-4">
                            <div class="text-primary card-icon">
                                <i class="fas fa-gauge-high"></i>
                            </div>
                            <h5 class="card-title">Painel de C&acirc;meras</h5>
                            <p class="card-text">Vis&atilde;o geral de todas as c&acirc;meras, status, localiza&ccedil;&atilde;o, cadastro, manuten&ccedil;&atilde;o e relat&oacute;rios</p>
                            <a href="?page=controle_cameras" class="btn btn-primary w-100">
                                <i class="fas fa-gauge-high me-2"></i>Acessar Painel
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 col-12 mb-3">
                    <h5 class="mb-3"><i class="fas fa-bell me-2 text-danger"></i>Alarmes</h5>

                    <div class="dashboard-card">
                        <div class="card-body text-center p-4">
                            <div class="text-danger card-icon">
                                <i class="fas fa-gauge-high"></i>
                            </div>
                            <h5 class="card-title">Painel de Alarmes</h5>
                            <p class="card-text">Vis&atilde;o geral dos alarmes, status, cadastro, manuten&ccedil;&atilde;o e relat&oacute;rios</p>
                            <a href="?page=controle_alarmes" class="btn btn-danger w-100">
                                <i class="fas fa-gauge-high me-2"></i>Acessar Painel
                            </a>
                        </div>
                    </div>

                    <?php if (($_SESSION['usuario']->nivel_acesso ?? '') === 'admin'): ?>
                    <div class="dashboard-card">
                        <div class="card-body text-center p-4">
                            <div class="text-secondary card-icon">
                                <i class="fas fa-cog"></i>
                            </div>
                            <h5 class="card-title">Administra&ccedil;&atilde;o do Sistema</h5>
                            <p class="card-text">Gerencie usu&aacute;rios, permiss&otilde;es e configura&ccedil;&otilde;es do sistema</p>
                            <a href="?page=administracao" class="btn btn-secondary w-100">
                                <i class="fas fa-users-cog me-2"></i>Administrar
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>&Uacute;ltimas Manuten&ccedil;&otilde;es</h5>
                </div>
                <div class="card-body" id="recent-activities-body">
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-clock fa-3x mb-3"></i>
                        <h5>Carregando dados...</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js" integrity="sha384-FcQlsUOd0TJjROrBxhJdUhXTUgNJQxTMcxZe6nHbaEfFL1zjQ+bq/uRoBQxb0KMo" crossorigin="anonymous"></script>
<script src="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/js/core/dashboard-core.js?v=<?= filemtime(__DIR__ . '/../public/assets/js/core/dashboard-core.js') ?>"></script>
<script src="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/js/modules/dashboard-theme.js?v=<?= filemtime(__DIR__ . '/../public/assets/js/modules/dashboard-theme.js') ?>"></script>
<script src="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/js/modules/dashboard-charts.js?v=<?= filemtime(__DIR__ . '/../public/assets/js/modules/dashboard-charts.js') ?>"></script>
<script src="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/js/pages/home-dashboard.js?v=<?= filemtime(__DIR__ . '/../public/assets/js/pages/home-dashboard.js') ?>"></script>
