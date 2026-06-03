<?php
require_once __DIR__ . '/../inc/navbar.php';
requererAcesso('supervisor');
?>

<link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/css/pages/manutencao_alarmes_v2.css?v=<?= @filemtime(__DIR__ . '/../public/assets/css/pages/manutencao_alarmes_v2.css') ?>">

<div class="container-fluid mt-4 mb-4 manutencao-page">
    <!-- Header com título e ações -->
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h2 class="mb-1"><i class="fas fa-tools me-2"></i>Manutenção de Alarmes</h2>
                <p class="text-muted mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    Registre manutenções e acompanhe o histórico completo por equipamento
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="?page=controle_alarmes" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Voltar
                </a>
            </div>
        </div>
    </div>

    <!-- Alerta de loading global -->
    <div id="globalLoadingAlert" class="alert alert-info alert-dismissible fade show d-none" role="alert">
        <i class="fas fa-spinner fa-spin me-2"></i>
        <span id="loadingMessage">Carregando dados...</span>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>

    <!-- Container de mensagens -->
    <div id="manutencaoMensagem" class="mb-3 is-hidden"></div>

    <!-- Seção de Nova Ordem de Serviço -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-warning-subtle">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Nova Ordem de Serviço</h5>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="toggleNovaOs">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
        </div>
        <div class="card-body" id="novaOsBody">
            <form id="formCriarOs" class="row g-3" novalidate>
                <div class="col-md-6">
                    <label for="equipamento_id_os" class="form-label">Alarme Conta</label>
                    <div class="input-group mb-2">
                        <input type="text" id="filtroAlarmeOs" class="form-control" placeholder="Buscar alarme...">
                        <button type="button" class="btn btn-outline-primary" id="btnBuscarAlarmeLista">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                    <select id="equipamento_id_os" name="equipamento_id_os" class="form-select" required>
                        <option value="">Selecione um alarme...</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="local_servico_os" class="form-label">Local do alarme</label>
                    <input type="text" id="local_servico_os" name="local_servico_os" class="form-control" maxlength="255" readonly>
                </div>
                <div class="col-md-3">
                    <label for="endereco_servico_os" class="form-label">Endereço do alarme</label>
                    <input type="text" id="endereco_servico_os" name="endereco_servico_os" class="form-control" maxlength="255" readonly>
                </div>
                <div class="col-md-4">
                    <label for="data_hora_os" class="form-label">Data/Hora de abertura</label>
                    <input type="datetime-local" id="data_hora_os" name="data_hora_os" class="form-control">
                </div>
                <div class="col-md-4">
                    <label for="numero_os_os" class="form-label">Número da OS</label>
                    <input type="text" id="numero_os_os" name="numero_os_os" class="form-control" maxlength="50" placeholder="Número da OS">
                </div>
                <div class="col-12">
                    <label for="problemas" class="form-label">Problemas relatados</label>
                    <textarea id="problemas" name="problemas" class="form-control" rows="3" maxlength="2000" required placeholder="Descreva os problemas identificados no equipamento..."></textarea>
                    <div class="form-text">Mínimo de 5 caracteres</div>
                </div>
                <div class="col-12 d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-primary" id="btnSalvarOs">
                        <i class="fas fa-save me-1"></i>Criar Ordem de Serviço
                    </button>
                    <button type="button" class="btn btn-secondary" id="btnLimparOs">
                        <i class="fas fa-eraser me-1"></i>Limpar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-info-subtle">
            <h5 class="mb-0"><i class="fas fa-tools me-2"></i>Finalizar Ordem de Serviço</h5>
        </div>
        <div class="card-body">
            <div id="ordemSelecionadaContainer" class="alert alert-secondary d-none">
                <div><strong>OS selecionada:</strong> <span id="ordemNumeroOs"></span></div>
                <div><strong>Problemas:</strong> <span id="ordemProblemas"></span></div>
                <button type="button" class="btn btn-link btn-sm p-0 mt-2" id="btnLimparSelecaoOs">Limpar seleção</button>
            </div>
            <form id="formManutencaoAlarme" class="row g-3" novalidate>
                <input type="hidden" id="os_id" name="os_id" value="">
                <div class="col-md-6">
                    <label for="equipamento_id" class="form-label">Alarme</label>
                    <select id="equipamento_id" name="equipamento_id" class="form-select" required>
                        <option value="">Selecione um alarme...</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="data_hora" class="form-label">Data/Hora de execução</label>
                    <input type="datetime-local" id="data_hora" name="data_hora" class="form-control">
                </div>
                <div class="col-md-3">
                    <label for="procedimento_id" class="form-label">Procedimento</label>
                    <select id="procedimento_id" name="procedimento_id" class="form-select">
                        <option value="">Selecione...</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="status_id" class="form-label">Status após manutenção</label>
                    <select id="status_id" name="status_id" class="form-select">
                        <option value="">Manter status atual</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="tecnico" class="form-label">Técnico</label>
                    <input type="text" id="tecnico" name="tecnico" class="form-control" maxlength="255" placeholder="Nome do técnico que executou o serviço">
                </div>
                <div class="col-md-4">
                    <label for="numero_os" class="form-label">Número da OS</label>
                    <input type="text" id="numero_os" name="numero_os" class="form-control" maxlength="50" placeholder="Ordem de serviço">
                </div>
                <div class="col-md-4">
                    <label for="local_servico" class="form-label">Local do serviço</label>
                    <input type="text" id="local_servico" name="local_servico" class="form-control" maxlength="255" placeholder="Local onde foi atendido">
                </div>
                <div class="col-md-6">
                    <label for="endereco_servico" class="form-label">Endereço do serviço</label>
                    <input type="text" id="endereco_servico" name="endereco_servico" class="form-control" maxlength="255" placeholder="Endereço ou referência">
                </div>
                <div class="col-md-8">
                    <label for="descricao" class="form-label">Descrição do serviço realizado</label>
                    <textarea id="descricao" name="descricao" class="form-control" rows="3" maxlength="2000" required placeholder="Descreva tudo o que foi feito na manutenção..."></textarea>
                </div>
                <div class="col-md-4">
                    <label for="pecas_previstas" class="form-label">Peças utilizadas/previstas</label>
                    <textarea id="pecas_previstas" name="pecas_previstas" class="form-control" rows="3" maxlength="2000" placeholder="Bateria, sensor, teclado, cabeamento..."></textarea>
                </div>
                <div class="col-12 d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-primary" id="btnSalvarManutencao">
                        <i class="fas fa-save me-1"></i>Registrar Manutenção
                    </button>
                    <button type="button" class="btn btn-success d-none" id="btnFinalizarManutencao">
                        <i class="fas fa-check me-1"></i>Finalizar
                    </button>
                    <button type="button" class="btn btn-secondary" id="btnLimparFormulario">
                        <i class="fas fa-eraser me-1"></i>Limpar
                    </button>
                </div>
            </form>

            <div id="secaoAnexosManutencao" class="anexos-section-pending d-none mt-4">
                <hr>
                <h6><i class="fas fa-paperclip me-2"></i>Anexos da Manutenção</h6>
                <p class="text-muted small mb-3">Anexe fotos de peças trocadas ou estado atual do equipamento.</p>
                <div class="anexos-editable">
                    <div class="anexos-dropzone text-center p-4 border border-dashed rounded">
                        <i class="fas fa-cloud-upload-alt fa-2x mb-2 d-block text-primary"></i>
                        <p class="mb-1">Arraste arquivos aqui ou clique para selecionar</p>
                        <small class="text-muted">Imagens, PDF, DOC, XLS — Máx. 10MB cada</small>
                        <input type="file" class="anexos-file-input d-none" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
                    </div>
                    <div class="anexos-progress-container d-none mt-2">
                        <div class="progress" style="height:6px">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <small class="anexos-progress-text text-muted mt-1 d-block"></small>
                    </div>
                    <div class="anexos-error-msg text-danger small mt-1"></div>
                    <div class="anexos-list mt-2" data-manutencao-alarme-id="" data-empty-msg="Nenhum anexo cadastrado para esta manutenção.">
                        <p class="text-muted text-center mb-0">Nenhum anexo cadastrado.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-list-check me-2"></i>Ordens de Serviço Cadastradas</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead>
                        <tr>
                            <th>OS</th>
                            <th>Alarme</th>
                            <th>IP</th>
                            <th>Data de abertura</th>
                            <th>Problemas</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="ordensCadastradasBody">
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Carregando ordens cadastradas...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-person-digging me-2"></i>Ordens em Execução</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead>
                        <tr>
                            <th>OS</th>
                            <th>Alarme</th>
                            <th>IP</th>
                            <th>Data de abertura</th>
                            <th>Problemas</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="ordensExecutandoBody">
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Carregando ordens em execução...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0"><i class="fas fa-history me-2"></i>Ordens de Serviço Realizadas</h5>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm" id="btnFiltrarHistorico">
                    <i class="fas fa-filter me-1"></i>Filtrar pelo alarme selecionado
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnLimparFiltroHistorico">
                    <i class="fas fa-list me-1"></i>Exibir todos
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-2 mb-3">
                <div class="col-md-5">
                    <label for="filtroBuscaHistorico" class="form-label">Busca no histórico</label>
                    <input type="text" id="filtroBuscaHistorico" class="form-control"
                        placeholder="Buscar por descrição, técnico, conta, alarme, IP, série, usuário, procedimento...">
                </div>
                <div class="col-md-3">
                    <label for="dataInicialHist" class="form-label">Data inicial</label>
                    <input type="date" id="dataInicialHist" class="form-control">
                </div>
                <div class="col-md-3">
                    <label for="dataFinalHist" class="form-label">Data final</label>
                    <input type="date" id="dataFinalHist" class="form-control">
                </div>
                <div class="col-md-1">
                    <label for="historicoPerPageHist" class="form-label">Registros/pág</label>
                    <select id="historicoPerPageHist" class="form-select">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-12">
                    <button type="button" class="btn btn-primary" id="btnBuscarHistorico">
                        <i class="fas fa-search me-1"></i>Buscar
                    </button>
                    <button type="button" class="btn btn-outline-secondary ms-2" id="btnLimparBuscaHistorico">
                        <i class="fas fa-eraser me-1"></i>Limpar
                    </button>
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <small class="text-muted" id="resumoHistorico">Aguardando carregamento...</small>
                <div class="d-flex flex-wrap align-items-center gap-1">
                    <small class="text-muted fw-semibold me-2">Exportação</small>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnSelecionarTodosHistorico">
                        <i class="fas fa-check-double me-1"></i>Selecionar todos
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnLimparSelecaoHistorico">
                        <i class="fas fa-trash-can me-1"></i>Limpar seleção
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" id="btnExportarRapidoHistorico">
                        <i class="fas fa-file-export me-1"></i>Gerar Relatório
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm" id="btnExportarCsvHistorico">
                        <i class="fas fa-file-csv me-1"></i>Exportar CSV
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="btnExportarPdfHistorico">
                        <i class="fas fa-file-pdf me-1"></i>Exportar PDF
                    </button>
                    <small class="text-muted ms-1" id="resumoSelecaoHistorico">Selecionados: 0</small>
                </div>
            </div>
            <div class="historico-top-scroll" id="historicoTopScroll">
                <div class="historico-top-scroll-content" id="historicoTopScrollContent"></div>
            </div>
            <div class="table-responsive historico-table-wrap">
                <table class="table table-hover table-striped table-bordered align-middle">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 40px; background-color: #f8f9fa;">
                                <input type="checkbox" id="selectAllHistorico" class="form-check-input">
                            </th>
                            <th style="min-width: 100px; background-color: #f8f9fa;">
                                <button type="button" class="sort-header-btn" data-sort="numero_os">
                                    Nº OS <span data-sort-indicator="numero_os">↕</span>
                                </button>
                            </th>
                            <th style="min-width: 150px; background-color: #f8f9fa;">
                                <button type="button" class="sort-header-btn" data-sort="data_hora">
                                    Data <span data-sort-indicator="data_hora">↕</span>
                                </button>
                            </th>
                            <th style="min-width: 140px; background-color: #f8f9fa;">
                                Hora
                            </th>
                            <th style="min-width: 120px; background-color: #f8f9fa;">
                                <button type="button" class="sort-header-btn" data-sort="alarme">
                                    Conta <span data-sort-indicator="alarme">↕</span>
                                </button>
                            </th>
                            <th style="min-width: 130px; background-color: #f8f9fa;">IP</th>
                            <th style="min-width: 120px; background-color: #f8f9fa;">Modelo</th>
                            <th style="min-width: 150px; background-color: #f8f9fa;">Local</th>
                            <th style="min-width: 140px; background-color: #f8f9fa;">Endereço</th>
                            <th style="min-width: 140px; background-color: #f8f9fa;">
                                <button type="button" class="sort-header-btn" data-sort="procedimento">
                                    Procedimento <span data-sort-indicator="procedimento">↕</span>
                                </button>
                            </th>
                            <th style="min-width: 100px; background-color: #f8f9fa;">
                                <button type="button" class="sort-header-btn" data-sort="status">
                                    Status <span data-sort-indicator="status">↕</span>
                                </button>
                            </th>
                            <th style="min-width: 120px; background-color: #f8f9fa;">
                                <button type="button" class="sort-header-btn" data-sort="tecnico">
                                    Técnico <span data-sort-indicator="tecnico">↕</span>
                                </button>
                            </th>
                            <th style="min-width: 200px; background-color: #f8f9fa;">
                                <button type="button" class="sort-header-btn" data-sort="descricao">
                                    Descrição <span data-sort-indicator="descricao">↕</span>
                                </button>
                            </th>
                            <th style="min-width: 200px; background-color: #f8f9fa;">
                                <button type="button" class="sort-header-btn" data-sort="pecas_previstas">
                                    Peças Utilizadas <span data-sort-indicator="pecas_previstas">↕</span>
                                </button>
                            </th>
                            <th style="min-width: 120px; background-color: #f8f9fa;">
                                <button type="button" class="sort-header-btn" data-sort="usuario">
                                    Usuário <span data-sort-indicator="usuario">↕</span>
                                </button>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="historicoManutencaoBody">
                        <tr>
                            <td colspan="15" class="text-center text-muted py-5">
                                Carregando histórico...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav class="mt-4">
                <ul class="pagination justify-content-center mb-0" id="historicoPaginacao"></ul>
            </nav>
        </div>
    </div>
</div>

<script nonce="<?= htmlspecialchars($CSP_NONCE ?? '', ENT_QUOTES, 'UTF-8') ?>">
window._CSP_NONCE = '<?= htmlspecialchars($CSP_NONCE ?? '', ENT_QUOTES, 'UTF-8') ?>';
</script>
<script src="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/js/utils/ui-utils.js?v=<?= @filemtime(__DIR__ . '/../public/assets/js/utils/ui-utils.js') ?>"></script>
<script src="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/js/utils/file-upload.js?v=<?= @filemtime(__DIR__ . '/../public/assets/js/utils/file-upload.js') ?>"></script>
<script src="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/js/utils/ui/ErrorHandler.js?v=<?= @filemtime(__DIR__ . '/../public/assets/js/utils/ui/ErrorHandler.js') ?>"></script>
<script src="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/js/utils/ui/LoadingManager.js?v=<?= @filemtime(__DIR__ . '/../public/assets/js/utils/ui/LoadingManager.js') ?>"></script>
<script src="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/js/manutencao_alarmes_v2.js?v=<?= @filemtime(__DIR__ . '/../public/assets/js/manutencao_alarmes_v2.js') ?>"></script>
