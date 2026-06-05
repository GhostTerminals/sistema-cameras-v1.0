<?php
require_once __DIR__ . '/../inc/navbar.php';
require_once __DIR__ . '/../config/database.php';

try {
    $db = db();
    $resultStatus = $db->query("SELECT id, nome FROM status ORDER BY nome");
    $statusList = $resultStatus['status'] === 'success' ? $resultStatus['data'] : [];

    $resultLocais = $db->query("SELECT id, nome FROM locais ORDER BY nome");
    $locaisList = $resultLocais['status'] === 'success' ? $resultLocais['data'] : [];

    $resultRegioes = $db->query("SELECT id, nome FROM regioes ORDER BY nome");
    $regioesList = $resultRegioes['status'] === 'success' ? $resultRegioes['data'] : [];
} catch (Exception $e) {
    error_log("Erro ao carregar dados para relatorio de cameras: " . $e->getMessage());
    $statusList = [];
    $locaisList = [];
    $regioesList = [];
}
?>

<link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/css/pages/relatorios_cameras.css?v=<?= @filemtime(__DIR__ . '/../public/assets/css/pages/relatorios_cameras.css') ?>">

<div class="container-fluid mt-4 mb-4 relatorio-cameras-page">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h2 class="mb-1"><i class="fas fa-camera me-2"></i>Relat&oacute;rio de C&acirc;meras</h2>
                <p class="text-muted mb-0">Consulte e filtre as c&acirc;meras do sistema.</p>
            </div>
            <a href="?page=controle_cameras" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Voltar
            </a>
        </div>
    </div>

    <div class="filtros-container mb-3">
        <h4 class="mb-4"><i class="fas fa-filter me-2"></i>Filtros</h4>

        <form id="formFiltros">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="pesquisa" class="form-label">Busca principal</label>
                    <input type="text" class="form-control" id="pesquisa" name="pesquisa" placeholder="IP do equipamento">
                </div>

                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        <?php foreach ($statusList as $status): ?>
                        <option value="<?= (int)$status->id ?>"><?= htmlspecialchars($status->nome, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="regiao" class="form-label">Região</label>
                    <select class="form-select" id="regiao" name="regiao">
                        <option value="">Todas</option>
                        <?php foreach ($regioesList as $regiao): ?>
                        <option value="<?= (int)$regiao->id ?>"><?= htmlspecialchars($regiao->nome, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="local" class="form-label">Local</label>
                    <select class="form-select" id="local" name="local">
                        <option value="">Todos</option>
                        <?php foreach ($locaisList as $local): ?>
                        <option value="<?= (int)$local->id ?>"><?= htmlspecialchars($local->nome, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-12 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Buscar
                    </button>
                    <button type="button" id="btnLimpar" class="btn btn-secondary">
                        <i class="fas fa-broom me-1"></i>Limpar Filtros
                    </button>
                    <button type="button" id="btnExportar" class="btn btn-success">
                        <i class="fas fa-file-export me-1"></i>Exportar
                    </button>
                    <div class="btn-group">
                        <button type="button" id="btnColunas" class="btn btn-info dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-columns me-1"></i>Colunas
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end p-2" id="menuColunas" style="min-width:200px;max-height:300px;overflow-y:auto">
                        </ul>
                    </div>
                    <a href="?page=home" class="btn btn-outline-danger ms-auto">
                        <i class="fas fa-sign-out-alt me-1"></i>Sair
                    </a>
                </div>
            </div>
        </form>
    </div>

    <div class="total-registros" id="totalRegistros">
        <i class="fas fa-info-circle me-1"></i>Aguardando busca...
    </div>

    <div class="table-container">
        <div class="table-responsive" aria-live="polite" aria-busy="false">
            <table class="table table-hover" id="tabelaResultados">
                <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Descricao</th>
                        <th scope="col">IP</th>
                        <th scope="col">Porta</th>
                        <th scope="col">Patrimonio</th>
                        <th scope="col">Serie/MAC</th>
                        <th scope="col">Marca</th>
                        <th scope="col">Modelo</th>
                        <th scope="col">Tipo Camera</th>
                        <th scope="col">Procedimento</th>
                        <th scope="col">Regiao</th>
                        <th scope="col">Local</th>
                        <th scope="col">Secretaria</th>
                        <th scope="col">Status</th>
                        <th scope="col">Conta Alarme</th>
                        <th scope="col">Transmissao</th>
                        <th scope="col">Mosaico</th>
                        <th scope="col">Coordenadas</th>
                        <th scope="col">Tipo</th>
                        <th scope="col">Logradouro</th>
                        <th scope="col">Numero</th>
                        <th scope="col">Bairro</th>
                        <th scope="col">Cidade</th>
                        <th scope="col">UF</th>
                        <th scope="col">CEP</th>
                        <th scope="col">Data Instalacao</th>
                        <th scope="col">Criado em</th>
                        <th scope="col">Observacao</th>
                    </tr>
                </thead>
                <tbody id="corpoTabela">
                    <tr>
                        <td colspan="28" class="text-center text-muted py-5">
                            <i class="fas fa-search fa-2x mb-3"></i><br>
                            Use os filtros acima para buscar cameras
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav id="paginacao" class="is-hidden">
            <ul class="pagination justify-content-center"></ul>
        </nav>
    </div>
</div>

<div class="loading-overlay is-hidden" id="loadingOverlay">
    <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
    <div class="mt-3">Carregando dados...</div>
</div>

<script src="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/js/relatorios.js?v=<?= @filemtime(__DIR__ . '/../public/assets/js/relatorios.js') ?>"></script>



