<?php
require_once __DIR__ . '/../inc/navbar.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header bg-danger text-white">
                    <h3 class="mb-0">
                        <i class="fas fa-bell me-2"></i>Controle de Alarmes
                    </h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-success">
                        <h5><i class="fas fa-check-circle me-2"></i>Modulo de alarmes habilitado</h5>
                        <p class="mb-0">Use os atalhos abaixo para cadastrar, editar e registrar manutencao dos alarmes.</p>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <h1 class="mb-0">-</h1>
                                    <p class="mb-0">Alarmes Ativos</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h1 class="mb-0">-</h1>
                                    <p class="mb-0">Resolvidos Hoje</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-danger text-white">
                                <div class="card-body text-center">
                                    <h1 class="mb-0">-</h1>
                                    <p class="mb-0">Criticos</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h1 class="mb-0">-</h1>
                                    <p class="mb-0">Em Analise</p>
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
                                    <p>Novo alarme no sistema</p>
                                    <a href="?page=cadastro_alarmes" class="btn btn-primary">Cadastrar</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-list fa-3x text-success mb-3"></i>
                                    <h5>Listagem</h5>
                                    <p>Visualizar e gerenciar alarmes</p>
                                    <a href="?page=listar_alarmes" class="btn btn-success">Listar</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-pen-to-square fa-3x text-warning mb-3"></i>
                                    <h5>Edicao</h5>
                                    <p>Alterar dados dos alarmes cadastrados</p>
                                    <a href="?page=editar_alarmes" class="btn btn-warning text-dark">Editar</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-tools fa-3x text-success mb-3"></i>
                                    <h5>Manutencao</h5>
                                    <p>Registrar manutencao e acompanhar historico</p>
                                    <a href="?page=manutencao_alarmes" class="btn btn-success">Manutencao</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-chart-bar fa-3x text-secondary mb-3"></i>
                                    <h5>Relat&oacute;rios</h5>
                                    <p>Relat&oacute;rios e exporta&ccedil;&atilde;o</p>
                                    <a href="?page=relatorios_alarmes" class="btn btn-secondary">Relat&oacute;rios</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
