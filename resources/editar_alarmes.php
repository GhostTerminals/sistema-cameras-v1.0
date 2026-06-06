<?php
require_once __DIR__ . '/../inc/navbar.php';
requererAcesso('supervisor');
require_once __DIR__ . '/../config/database.php';

$db = db();
$statusOptions = $db->query("SELECT id, nome FROM status ORDER BY nome ASC");
$statusList = $statusOptions['status'] === 'success' ? array_map(fn($r) => (array)$r, $statusOptions['data']) : [];
$regiaoOptions = $db->query("SELECT id, nome FROM regioes ORDER BY nome ASC");
$regiaoList = $regiaoOptions['status'] === 'success' ? array_map(fn($r) => (array)$r, $regiaoOptions['data']) : [];
?>

<link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/css/pages/editar_alarmes.css?v=<?= filemtime(__DIR__ . '/../public/assets/css/pages/editar_alarmes.css') ?>">

<div class="container mt-4 mb-4">
    <div class="editar-alarme-wrapper">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="?page=controle_alarmes">Alarmes</a></li>
            <li class="breadcrumb-item active">Editar</li>
        </ol>
    </nav>

    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0"><i class="fas fa-edit me-2"></i>Editar Alarmes</h4>
        </div>
        <div class="card-body">
            <div class="card alarme-section-card mb-3">
                <div class="card-header alarme-section-header d-flex align-items-center gap-2">
                    <i class="fas fa-search"></i> Buscar e Selecionar Alarme
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Nome/Local</label>
                            <input type="text" id="filtroNome" class="form-control" placeholder="Nome do local do alarme...">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Conta</label>
                            <input type="number" id="filtroConta" class="form-control" placeholder="Conta">
                        </div>
                        <div class="col-md-2 d-grid align-self-end">
                            <button type="button" class="btn btn-outline-primary" id="btnBuscar"><i class="fas fa-search me-1"></i>Buscar</button>
                        </div>
                        <div class="col-md-4 d-flex justify-content-end align-items-end gap-2">
                            <a href="?page=cadastro_alarmes" class="btn btn-success"><i class="fas fa-plus me-1"></i>Novo alarme</a>
                            <a href="?page=controle_alarmes" class="btn btn-secondary">Voltar</a>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Selecionar alarme</label>
                        <select id="alarmeSelector" class="form-select">
                            <option value="">Carregando alarmes...</option>
                        </select>
                    </div>
                </div>
            </div>

            <form id="formEditarAlarme" class="needs-validation" novalidate>
                <input type="hidden" name="id" id="alarme_id">

                <div class="card alarme-section-card mb-3">
                    <div class="card-header alarme-section-header d-flex align-items-center gap-2">
                        <i class="fas fa-tag"></i> Identificação e Localização
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">Conta <span class="text-danger">*</span></label>
                                <input type="number" name="conta" class="form-control" min="1" required>
                                <div class="invalid-feedback">Informe a conta.</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status <span class="text-danger">*</span></label>
                                <select name="status" class="form-select" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($statusList as $s): ?>
                                        <option value="<?= htmlspecialchars($s['nome']) ?>"><?= htmlspecialchars($s['nome']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Selecione o status.</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Regiao</label>
                                <select name="regiao" class="form-select">
                                    <option value="">Selecione...</option>
                                    <?php foreach ($regiaoList as $r): ?>
                                        <option value="<?= htmlspecialchars($r['nome']) ?>"><?= htmlspecialchars($r['nome']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Data atualizacao <span class="text-danger">*</span></label>
                                <input type="date" name="data_atualizacao" class="form-control" required>
                                <div class="invalid-feedback">Informe a data de atualizacao.</div>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Local <span class="text-danger">*</span></label>
                                <input type="text" name="local" class="form-control" maxlength="255" required>
                                <div class="invalid-feedback">Informe o local.</div>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Endereco</label>
                                <input type="text" name="endereco" class="form-control" maxlength="255">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Numero</label>
                                <input type="text" name="numero" class="form-control" maxlength="20">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card alarme-section-card mb-3">
                    <div class="card-header alarme-section-header d-flex align-items-center gap-2">
                        <i class="fas fa-wifi"></i> Conexão e Integração
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">PGM1</label>
                                <input type="text" name="pgm1" class="form-control" maxlength="100">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">PGM2</label>
                                <input type="text" name="pgm2" class="form-control" maxlength="100">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">MAC</label>
                                <input type="text" name="mac" class="form-control" maxlength="50">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">IP</label>
                                <input type="text" name="ip" class="form-control" maxlength="50">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Integracao</label>
                                <input type="date" name="integracao" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">IP DVR</label>
                                <input type="text" name="ip_dvr" class="form-control" maxlength="50">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Cameras DVR</label>
                                <input type="number" name="cameras_dvr" class="form-control" min="0">
                            </div>
                            <div class="col-md-3">
                                <div class="form-check mt-4">
                                    <input type="checkbox" name="camera_gm" class="form-check-input" value="1" id="cameraGm">
                                    <label class="form-check-label" for="cameraGm">Camera GM</label>
                                </div>
                                <input type="number" name="quant_camera_gm" class="form-control mt-2" min="0" placeholder="Qtd. cameras GM">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card alarme-section-card mb-3">
                    <div class="card-header alarme-section-header d-flex align-items-center gap-2">
                        <i class="fas fa-cogs"></i> Configuração e Documentação
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Modelo da Central</label>
                                <select name="modelo_alarme_id" class="form-select">
                                    <option value="">Selecione um modelo...</option>
                                    <?php
                                    $dbModels = db();
                                    $modelosResult = $dbModels->query("SELECT id, nome FROM catalogo_modelos_alarmes ORDER BY nome ASC");
                                    if ($modelosResult['status'] === 'success') {
                                        foreach ($modelosResult['data'] as $m) {
                                            echo '<option value="' . $m->id . '">' . htmlspecialchars($m->nome, ENT_QUOTES, 'UTF-8') . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Qtd. Repetidor</label>
                                <input type="number" name="quant_repetidor" class="form-control" min="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Qtd. Sensores</label>
                                <input type="number" name="qtde_sensores" class="form-control" min="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Documentacao</label>
                                <input type="text" name="documentacao" class="form-control" maxlength="50">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Monitorada</label>
                                <input type="text" name="monitorada" class="form-control" maxlength="50">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Numero SEI</label>
                                <input type="text" name="numero_sei" class="form-control" maxlength="50">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Observacao</label>
                                <textarea name="observacao" class="form-control char-counter" rows="3" maxlength="4000" data-target="#obsCounter"></textarea>
                                <small class="text-muted"><span id="obsCounter">0</span>/4000 caracteres</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary" id="btnSubmit"><i class="fas fa-save me-1"></i>Salvar alteracoes</button>
                    <a href="?page=controle_alarmes" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>

            <!-- Anexos (fotos / documentos) -->
            <div class="card alarme-section-card mb-3 mt-3 anexos-editable" id="anexosSection">
                <div class="card-header alarme-section-header d-flex align-items-center gap-2">
                    <i class="fas fa-paperclip"></i>
                    <span>Anexos</span>
                </div>
                <div class="card-body">
                    <div class="anexos-dropzone text-center p-4 border border-dashed rounded mb-3">
                        <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-2"></i>
                        <p class="mb-1">Arraste arquivos aqui ou clique para selecionar</p>
                        <small class="text-muted">Imagens, PDF, Word, Excel (max 10MB)</small>
                        <input type="file" class="anexos-file-input d-none" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
                    </div>
                    <div class="anexos-progress-container d-none mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <small class="anexos-progress-text">Enviando...</small>
                        </div>
                        <div class="progress" style="height:6px;">
                            <div class="anexos-progress progress-bar" role="progressbar" style="width:0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                    <div class="anexos-list" data-empty-msg="Nenhum anexo cadastrado."></div>
                </div>
            </div>
        </div>
    </div>
    </div>
</div>

<script src="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/js/utils/uppercase.js?v=<?= filemtime(__DIR__ . '/../public/assets/js/utils/uppercase.js') ?>"></script>
<script src="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/js/utils/ui-utils.js?v=<?= filemtime(__DIR__ . '/../public/assets/js/utils/ui-utils.js') ?>"></script>
<script src="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/js/utils/file-upload.js?v=<?= filemtime(__DIR__ . '/../public/assets/js/utils/file-upload.js') ?>"></script>
<script src="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/js/editar_alarmes.js?v=<?= filemtime(__DIR__ . '/../public/assets/js/editar_alarmes.js') ?>"></script>
