<?php
require_once __DIR__ . '/../inc/navbar.php';
requererAcesso('admin');

// Get statistics
$db = db();

// Count users
$totalUsers = 0;
$r = $db->query("SELECT COUNT(*) as total FROM usuarios");
if ($r['status'] === 'success') $totalUsers = (int)($r['data'][0]->total ?? 0);

// Count escolas
$totalEscolas = 0;
$r = $db->query("SELECT COUNT(*) as total FROM locais WHERE nome LIKE '%ESCOLA%'");
if ($r['status'] === 'success') $totalEscolas = (int)($r['data'][0]->total ?? 0);

// Count CMEIs
$totalCmeis = 0;
$r = $db->query("SELECT COUNT(*) as total FROM locais WHERE nome LIKE '%CMEI%'");
if ($r['status'] === 'success') $totalCmeis = (int)($r['data'][0]->total ?? 0);

// Count operadoras
$totalOperadoras = 0;
$r = $db->query("SELECT COUNT(*) as total FROM origem_link");
if ($r['status'] === 'success') $totalOperadoras = (int)($r['data'][0]->total ?? 0);

// Count proprios publicos
$totalProprios = 0;
$r = $db->query("SELECT COUNT(*) as total FROM locais WHERE tipo_local_id = (SELECT id FROM tipos_locais WHERE nome = 'PREDIO PUBLICO')");
if ($r['status'] === 'success') $totalProprios = (int)($r['data'][0]->total ?? 0);
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">
                        <i class="fas fa-cog me-2"></i>Administração do Sistema
                    </h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h5><i class="fas fa-tools me-2"></i>Ferramentas administrativas</h5>
                        <p class="mb-0">Gerencie usuários, escolas, CMEIs, operadoras de link e próprios públicos cadastrados no sistema.</p>
                    </div>

                    <!-- Stats row -->
                    <div class="row mb-4">
                        <div class="col-md-2 mb-2">
                            <div class="card bg-primary text-white text-center h-100">
                                <div class="card-body py-3">
                                    <h3 class="mb-0"><?= $totalUsers ?></h3>
                                    <small>Usuários</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 mb-2">
                            <div class="card bg-success text-white text-center h-100">
                                <div class="card-body py-3">
                                    <h3 class="mb-0"><?= $totalEscolas ?></h3>
                                    <small>Escolas</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 mb-2">
                            <div class="card bg-info text-white text-center h-100">
                                <div class="card-body py-3">
                                    <h3 class="mb-0"><?= $totalCmeis ?></h3>
                                    <small>CMEIs</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 mb-2">
                            <div class="card bg-warning text-white text-center h-100">
                                <div class="card-body py-3">
                                    <h3 class="mb-0"><?= $totalOperadoras ?></h3>
                                    <small>Operadoras</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 mb-2">
                            <div class="card bg-secondary text-white text-center h-100">
                                <div class="card-body py-3">
                                    <h3 class="mb-0"><?= $totalProprios ?></h3>
                                    <small>Próprios</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action cards row -->
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="card h-100 border-primary">
                                <div class="card-body text-center">
                                    <i class="fas fa-users fa-3x text-primary mb-3"></i>
                                    <h5>Usuários</h5>
                                    <p>Gerenciar contas de acesso ao sistema</p>
                                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                                        <a href="?page=listarUsuario" class="btn btn-primary">Listar</a>
                                        <a href="?page=cadastroUsuario" class="btn btn-outline-primary">Novo</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100 border-success">
                                <div class="card-body text-center">
                                    <i class="fas fa-school fa-3x text-success mb-3"></i>
                                    <h5>Escolas</h5>
                                    <p>Relação de escolas municipais cadastradas</p>
                                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                                        <a href="?page=listar_escolas" class="btn btn-success">Listar</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100 border-info">
                                <div class="card-body text-center">
                                    <i class="fas fa-child fa-3x text-info mb-3"></i>
                                    <h5>CMEIs</h5>
                                    <p>Centros Municipais de Educação Infantil</p>
                                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                                        <a href="?page=listar_cmeis" class="btn btn-info text-white">Listar</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="card h-100 border-warning">
                                <div class="card-body text-center">
                                    <i class="fas fa-network-wired fa-3x text-warning mb-3"></i>
                                    <h5>Links / Operadoras</h5>
                                    <p>Operadoras de link cadastradas</p>
                                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                                        <a href="?page=listar_operadoras" class="btn btn-warning text-dark">Listar</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100 border-secondary">
                                <div class="card-body text-center">
                                    <i class="fas fa-building fa-3x text-secondary mb-3"></i>
                                    <h5>Próprios Públicos</h5>
                                    <p>Prédios públicos municipais</p>
                                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                                        <a href="?page=listar_proprios" class="btn btn-secondary">Listar</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100 border-dark">
                                <div class="card-body text-center">
                                    <i class="fas fa-file-alt fa-3x text-dark mb-3"></i>
                                    <h5>Auditoria</h5>
                                    <p>Acompanhar alterações no sistema</p>
                                    <div class="d-flex gap-2 justify-content-center flex-wrap">
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
</div>
