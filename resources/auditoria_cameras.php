<?php
require_once __DIR__ . '/../inc/navbar.php';
requererAcesso('admin');
?>

<link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/css/pages/auditoria_cameras.css?v=<?= filemtime(__DIR__ . '/../public/assets/css/pages/auditoria_cameras.css') ?>">

<div class="container-fluid mt-4 mb-4 auditoria-page">
    <div class="row mb-3">
        <div class="col-12">
            <div class="mb-3">
                <a href="?page=controle_cameras" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Voltar
                </a>
            </div>
            <h2 class="mb-1"><i class="fas fa-clipboard-list me-2"></i>Auditoria de Câmeras</h2>
            <p class="text-muted mb-0">Ações dos usuários nos registros das câmeras (cadastro, edição e exclusão).</p>
        </div>
    </div>

    <div class="card-box mb-3">
        <form id="formAuditoria" class="row g-3">
            <div class="col-md-2">
                <label for="data_inicial" class="form-label">Data inicial</label>
                <input type="date" id="data_inicial" name="data_inicial" class="form-control">
            </div>
            <div class="col-md-2">
                <label for="data_final" class="form-label">Data final</label>
                <input type="date" id="data_final" name="data_final" class="form-control">
            </div>
            <div class="col-md-2">
                <label for="operacao" class="form-label">Ação</label>
                <select id="operacao" name="operacao" class="form-select">
                    <option value="">Todas</option>
                    <option value="INSERT">Cadastro</option>
                    <option value="UPDATE">Edição</option>
                    <option value="DELETE">Exclusão</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="equipamento_id" class="form-label">ID câmera</label>
                <input type="number" id="equipamento_id" name="equipamento_id" class="form-control" min="1" placeholder="Ex: 12">
            </div>
            <div class="col-md-4">
                <label for="busca" class="form-label">Busca</label>
                <input type="text" id="busca" name="busca" class="form-control" placeholder="Código, série/MAC, IP, usuário...">
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i>Buscar
                </button>
                <button type="button" id="btnLimparAuditoria" class="btn btn-secondary">
                    <i class="fas fa-broom me-1"></i>Limpar
                </button>
            </div>
        </form>
    </div>

    <div class="card-box">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Registros</h5>
            <small class="text-muted" id="totalAuditoria">Aguardando busca...</small>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Usuário</th>
                        <th>Ação</th>
                        <th>Câmera</th>
                        <th>Origem</th>
                        <th>Resumo</th>
                    </tr>
                </thead>
                <tbody id="auditoriaTabelaCorpo">
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Use os filtros para consultar a auditoria.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <nav class="mt-3">
            <ul class="pagination justify-content-center mb-0" id="auditoriaPaginacao"></ul>
        </nav>
    </div>
</div>

<script src="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/js/auditoria-cameras.js?v=<?= filemtime(__DIR__ . '/../public/assets/js/auditoria-cameras.js') ?>"></script>

