<?php
require_once __DIR__ . '/../inc/navbar.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

try {
    $db = db();
    $resultRegioes = $db->query("SELECT id, nome FROM regioes ORDER BY nome");
    $regioesList = $resultRegioes['status'] === 'success' ? $resultRegioes['data'] : [];
} catch (Exception $e) {
    error_log("Erro ao carregar regioes para relatorio de alarmes: " . $e->getMessage());
    $regioesList = [];
}
?>

<link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/css/pages/relatorios_alarmes.css?v=<?= @filemtime(__DIR__ . '/../public/assets/css/pages/relatorios_alarmes.css') ?>">

<div class="container-fluid mt-4 mb-4 relatorio-alarmes-page">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h2 class="mb-1"><i class="fas fa-bell me-2"></i>Relatorio de Alarmes</h2>
                <p class="text-muted mb-0">Consulte e filtre os alarmes cadastrados.</p>
            </div>
            <a href="?page=controle_alarmes" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Voltar
            </a>
        </div>
    </div>

    <div class="filtros-container mb-3">
        <h4 class="mb-4"><i class="fas fa-filter me-2"></i>Filtros</h4>

        <form id="formFiltros">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="conta" class="form-label">Conta <span class="text-muted fw-normal">(principal)</span></label>
                    <input type="number" class="form-control" id="conta" name="conta" min="1" placeholder="Número da conta">
                </div>

                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        <option value="ATIVO">ATIVO</option>
                        <option value="INATIVO">INATIVO</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="regiao" class="form-label">Regiao</label>
                    <select class="form-select" id="regiao" name="regiao">
                        <option value="">Todas</option>
                        <?php foreach ($regioesList as $regiao): ?>
                        <option value="<?= htmlspecialchars($regiao->nome, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($regiao->nome, ENT_QUOTES, 'UTF-8') ?></option>
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
                        <th scope="col">Conta</th>
                        <th scope="col">Status</th>
                        <th scope="col">Regiao</th>
                        <th scope="col">Local</th>
                        <th scope="col">Endereco</th>
                        <th scope="col">Numero</th>
                        <th scope="col">IP</th>
                        <th scope="col">PGM1</th>
                        <th scope="col">PGM2</th>
                        <th scope="col">MAC</th>
                        <th scope="col">Central</th>
                        <th scope="col">Qtd Repetidor</th>
                        <th scope="col">Qtd Sensores</th>
                        <th scope="col">IP DVR</th>
                        <th scope="col">Cameras DVR</th>
                        <th scope="col">Camera GM</th>
                        <th scope="col">Qtd Camera GM</th>
                        <th scope="col">Integracao</th>
                        <th scope="col">Documentacao</th>
                        <th scope="col">Monitorada</th>
                        <th scope="col">Numero SEI</th>
                        <th scope="col">Data Atualizacao</th>
                        <th scope="col">Criado em</th>
                        <th scope="col">Observacao</th>
                    </tr>
                </thead>
                <tbody id="corpoTabela">
                    <tr>
                        <td colspan="25" class="text-center text-muted py-5">
                            <i class="fas fa-search fa-2x mb-3"></i><br>
                            Use os filtros acima para buscar alarmes
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
    <div class="spinner-border text-warning" role="status" aria-hidden="true"></div>
    <div class="mt-3">Carregando dados...</div>
</div>

<script src="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/js/relatorios_alarmes.js?v=<?= @filemtime(__DIR__ . '/../public/assets/js/relatorios_alarmes.js') ?>"></script>


