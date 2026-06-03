<?php
require_once __DIR__ . '/../inc/navbar.php';

if (!isset($_SESSION['usuario'])) {
    header('Location: index.php?page=login');
    exit;
}

if (($_SESSION['usuario']->nivel_acesso ?? 'user') !== 'admin' && ($_SESSION['usuario']->nivel_acesso ?? 'user') !== 'supervisor') {
    header('Location: index.php?page=nao_autorizado');
    exit;
}
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">
                        <i class="fas fa-camera me-2"></i>Controle de C&acirc;meras
                    </h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h5><i class="fas fa-check-circle me-2"></i>M&oacute;dulo de c&acirc;meras habilitado</h5>
                        <p class="mb-0">Use os atalhos abaixo para cadastrar, editar, listar e registrar manuten&ccedil;&atilde;o das c&acirc;meras.</p>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h1 class="mb-0">-</h1>
                                    <p class="mb-0">C&acirc;meras Totais</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h1 class="mb-0">-</h1>
                                    <p class="mb-0">Ativas</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <h1 class="mb-0">-</h1>
                                    <p class="mb-0">Em Manuten&ccedil;&atilde;o</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-secondary text-white">
                                <div class="card-body text-center">
                                    <h1 class="mb-0">-</h1>
                                    <p class="mb-0">Desativadas</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-plus-circle fa-3x text-primary mb-3"></i>
                                    <h5>Cadastro</h5>
                                    <p>Nova c&acirc;mera no sistema</p>
                                    <a href="?page=cadastro_cameras" class="btn btn-primary">Cadastrar</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-list fa-3x text-success mb-3"></i>
                                    <h5>Listagem</h5>
                                    <p>Visualizar e gerenciar c&acirc;meras</p>
                                    <a href="?page=listar_cameras" class="btn btn-success">Listar</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-pen-to-square fa-3x text-warning mb-3"></i>
                                    <h5>Edi&ccedil;&atilde;o</h5>
                                    <p>Alterar dados das c&acirc;meras cadastradas</p>
                                    <a href="?page=editar_cameras" class="btn btn-warning text-dark">Editar</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-tools fa-3x text-info mb-3"></i>
                                    <h5>Manuten&ccedil;&atilde;o</h5>
                                    <p>Registrar manuten&ccedil;&atilde;o e acompanhar hist&oacute;rico</p>
                                    <a href="?page=manutencao_cameras" class="btn btn-info text-white">Manuten&ccedil;&atilde;o</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-chart-bar fa-3x text-secondary mb-3"></i>
                                    <h5>Relat&oacute;rios</h5>
                                    <p>Relat&oacute;rios e exporta&ccedil;&atilde;o</p>
                                    <a href="?page=relatorios_cameras" class="btn btn-secondary">Relat&oacute;rios</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-clipboard-check fa-3x text-dark mb-3"></i>
                                    <h5>Auditoria</h5>
                                    <p>Acompanhar altera&ccedil;&otilde;es no cadastro</p>
                                    <a href="?page=auditoria_cameras" class="btn btn-dark">Auditoria</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>