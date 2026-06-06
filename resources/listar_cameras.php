<?php
require_once __DIR__ . '/../inc/navbar.php';

// Verificar se usu�rio tem acesso
if (!isset($_SESSION['usuario'])) {
    header('Location: index.php?page=login');
    exit;
}

// Configurar o caminho da API
$api_url = BASE_URL . '/index.php?page=api/api_cameras';
$canDelete = isset($_SESSION['usuario']->nivel_acesso)
    && in_array($_SESSION['usuario']->nivel_acesso, ['admin', 'supervisor'], true);
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="fas fa-camera me-2"></i>Lista de C�meras Cadastradas
                        </h4>
                        <div>
                            <a href="?page=controle_cameras" class="btn btn-outline-light btn-sm py-1 me-2"
                                title="Voltar ao painel" data-bs-toggle="tooltip">
                                <i class="fas fa-arrow-left me-1"></i>Voltar
                            </a>
                            <button class="btn btn-light btn-sm py-1 me-2" id="btnAtualizarListaCameras"
                                title="Atualizar lista" data-bs-toggle="tooltip">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <a href="?page=cadastro_cameras" class="btn btn-success btn-sm py-1"
                                title="Cadastrar nova c�mera" data-bs-toggle="tooltip">
                                <i class="fas fa-plus me-1"></i>Nova C�mera
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
                                <?php
                                // Buscar status do banco
                                require_once __DIR__ . '/../config/database.php';
                                $db = db();
                                $result = $db->query("SELECT id, nome FROM status ORDER BY nome");
                                if ($result['status'] === 'success') {
                                    foreach ($result['data'] as $status) {
                                        echo '<option value="' . $status->id . '">' . htmlspecialchars($status->nome) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="filtroLocal" class="form-label">Local</label>
                            <select class="form-select form-select-sm" id="filtroLocal">
                                <option value="">Todos</option>
                                <?php
                                // Buscar locais do banco
                                $result = $db->query("SELECT id, nome FROM locais ORDER BY nome");
                                if ($result['status'] === 'success') {
                                    foreach ($result['data'] as $local) {
                                        echo '<option value="' . $local->id . '">' . htmlspecialchars($local->nome) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="filtroPesquisa" class="form-label">Pesquisar</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control" id="filtroPesquisa"
                                    placeholder="Digite para pesquisar...">
                                <button class="btn btn-outline-secondary" type="button" id="btnLimparFiltros">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Lista de C�meras -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card border-info">
                                <div
                                    class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <i class="fas fa-video me-2"></i>C�meras Cadastradas
                                    </h5>
                                    <span class="badge bg-light text-dark" id="contadorCameras">0</span>
                                </div>
                                <div class="card-body">
                                    <div id="listaCameras">
                                        <div class="text-center text-muted py-4">
                                            <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                                            <p>Carregando c�meras...</p>
                                        </div>
                                    </div>
                                    <div id="paginacaoCameras" class="mt-3"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Confirma��o de Exclus�o -->
<div class="modal fade" id="modalConfirmacaoExclusao" tabindex="-1" aria-labelledby="modalConfirmacaoExclusaoLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="modalConfirmacaoExclusaoLabel">
                    <i class="fas fa-trash me-2"></i>Confirmar Exclus�o
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div class="mb-3">
                    <i class="fas fa-exclamation-triangle fa-3x text-danger"></i>
                </div>
                <h5 class="mb-3">Confirmar exclus�o da c�mera?</h5>
                <p class="text-muted mb-1">Voc� est� prestes a excluir a c�mera:</p>
                <p class="h6 text-primary mb-3" id="cameraInfo"></p>
                <div class="alert alert-warning small">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Esta a��o n�o pode ser desfeita.
                </div>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancelar
                </button>
                <button type="button" class="btn btn-danger px-4" id="btnConfirmarExclusao">
                    <i class="fas fa-trash me-1"></i>Excluir
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Detalhes da C�mera -->
<div class="modal fade" id="modalDetalhesCamera" tabindex="-1" aria-labelledby="modalDetalhesCameraLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="modalDetalhesCameraLabel">
                    <i class="fas fa-info-circle me-2"></i>Detalhes da C�mera
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="detalhesCameraContent">
                <!-- Conte�do ser� preenchido via JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Container para toasts -->
<div aria-live="polite" aria-atomic="true" class="position-relative">
    <div class="toast-container position-fixed top-0 end-0 p-3"></div>
</div>

<div id="listarCamerasConfig" style="display:none;" data-api-url="<?= htmlspecialchars($api_url, ENT_QUOTES, 'UTF-8') ?>" data-can-delete="<?= $canDelete ? '1' : '0' ?>"></div>
<script src="<?= BASE_URL ?>/assets/js/listar_cameras.js?v=<?= filemtime(__DIR__ . '/../public/assets/js/listar_cameras.js') ?>"></script>
