<?php
require_once __DIR__ . '/../inc/navbar.php';

if (!isset($_SESSION['usuario'])) {
    header('Location: index.php?page=login');
    exit;
}

$api_url = BASE_URL . '/index.php?page=api/api_alarmes';
$canDelete = isset($_SESSION['usuario']->nivel_acesso)
    && in_array($_SESSION['usuario']->nivel_acesso, ['admin', 'supervisor'], true);
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-danger text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="fas fa-bell me-2"></i>Lista de Alarmes Cadastrados
                        </h4>
                        <div>
                            <a href="?page=controle_alarmes" class="btn btn-outline-light btn-sm py-1 me-2"
                                title="Voltar ao painel" data-bs-toggle="tooltip">
                                <i class="fas fa-arrow-left me-1"></i>Voltar
                            </a>
                            <button class="btn btn-light btn-sm py-1 me-2" id="btnAtualizarListaAlarmes"
                                title="Atualizar lista" data-bs-toggle="tooltip">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <a href="?page=cadastro_alarmes" class="btn btn-success btn-sm py-1"
                                title="Cadastrar novo alarme" data-bs-toggle="tooltip">
                                <i class="fas fa-plus me-1"></i>Novo Alarme
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Filtros -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label for="filtroStatus" class="form-label">Status</label>
                            <select class="form-select form-select-sm" id="filtroStatus">
                                <option value="">Todos</option>
                                <option value="ATIVO">ATIVO</option>
                                <option value="INATIVO">INATIVO</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="filtroRegiao" class="form-label">Regiao</label>
                            <input type="text" class="form-control form-control-sm" id="filtroRegiao" placeholder="Ex: CENTRO, NORTE">
                        </div>
                        <div class="col-md-6">
                            <label for="filtroPesquisa" class="form-label">Pesquisar</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control" id="filtroPesquisa"
                                    placeholder="Local, endereco, IP, SEI...">
                                <button class="btn btn-outline-secondary" type="button" id="btnLimparFiltros">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Lista de Alarmes -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card border-danger">
                                <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <i class="fas fa-bell me-2"></i>Alarmes Cadastrados
                                    </h5>
                                    <span class="badge bg-light text-dark" id="contadorAlarmes">0</span>
                                </div>
                                <div class="card-body">
                                    <div id="listaAlarmes">
                                        <div class="text-center text-muted py-4">
                                            <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                                            <p>Carregando alarmes...</p>
                                        </div>
                                    </div>
                                    <div id="paginacaoAlarmes" class="mt-3"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Detalhes do Alarme -->
<div class="modal fade" id="modalDetalhesAlarme" tabindex="-1" aria-labelledby="modalDetalhesAlarmeLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="modalDetalhesAlarmeLabel">
                    <i class="fas fa-info-circle me-2"></i>Detalhes do Alarme
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="detalhesAlarmeContent">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<div aria-live="polite" aria-atomic="true" class="position-relative">
    <div class="toast-container position-fixed top-0 end-0 p-3"></div>
</div>

<div id="listarAlarmesConfig" style="display:none;" data-api-url="<?= htmlspecialchars($api_url, ENT_QUOTES, 'UTF-8') ?>"></div>
<script src="<?= BASE_URL ?>/assets/js/listar_alarmes.js?v=<?= @filemtime(__DIR__ . '/../public/assets/js/listar_alarmes.js') ?>"></script>
