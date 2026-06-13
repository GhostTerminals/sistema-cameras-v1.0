<?php
require_once __DIR__ . '/../inc/navbar.php';

if (!isset($_SESSION['usuario'])) {
    echo '<div class="alert alert-danger">Sessao expirada.</div>';
    exit;
}

if (!userHasAccess('supervisor')) {
    echo '<div class="alert alert-danger">Acesso negado. Nivel de acesso insuficiente.</div>';
    exit;
}

$db = db();
$dados = [];

try {
    $tabelas = [
        'status' => 'status',
        'procedimentos' => 'procedimentos',
        'regioes' => 'regioes',
        'tipos_equipamento' => 'tipos_equipamento',
        'tipo_cameras' => 'tipo_cameras',
        'transmissoes' => 'transmissoes',
        'origem_links' => 'origem_link',
        'marcas' => 'marcas',
        'locais' => 'locais',
        'secretarias' => 'secretarias',
        'classificacao_enderecos' => 'classificacao_enderecos',
    ];

    foreach ($tabelas as $key => $tabela) {
        $order = $tabela === 'transmissoes' ? 'tipo' : 'nome';
        $result = $db->query("SELECT * FROM $tabela ORDER BY $order");
        $dados[$key] = $result['status'] === 'success' ? $result['data'] : [];
    }
} catch (Exception $e) {
    error_log('Erro ao carregar dados editar_cameras: ' . $e->getMessage());
    echo '<div class="alert alert-danger">Erro ao carregar dados do formulario.</div>';
}
?>

<link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/css/pages/editar_cameras.css?v=<?= filemtime(__DIR__ . '/../public/assets/css/pages/editar_cameras.css') ?>">

<div class="container mt-4 mb-4">
    <div class="editar-camera-wrapper">
        <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-edit me-2"></i>Editar Camera</h4>
                    <div class="page-subtitle">Localize a camera, revise os dados e salve as alteracoes do equipamento.</div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <a href="?page=controle_cameras" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Voltar
                        </a>
                    </div>
                    <div class="camera-search-panel mb-3">
                    <div class="section-title"><i class="fas fa-search"></i><span>Selecionar camera</span></div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="filtroBuscaCamera" class="form-label">Buscar camera</label>
                            <div class="input-group">
                                <input type="text" id="filtroBuscaCamera" class="form-control"
                                    placeholder="Local, IP ou tipo de câmera...">
                                <button type="button" class="btn btn-outline-primary" id="btnBuscarCamera">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <label for="cameraSelector" class="form-label">Selecionar camera</label>
                            <select id="cameraSelector" class="form-select" size="10">
                                <option value="">Carregando cameras...</option>
                            </select>
                        </div>
                    </div>
                    </div>


                    <form id="formEditarCamera" novalidate>
                        <input type="hidden" name="id" id="camera_id">

                        <!-- Dados da Camera -->
                        <div class="card alarme-section-card mb-3">
                            <div class="card-header alarme-section-header d-flex align-items-center gap-2">
                                <i class="fas fa-camera"></i>
                                <span>Dados da Camera</span>
                            </div>
                            <div class="card-body">
                            <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select name="status_id" class="form-select" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($dados['status'] as $item): ?>
                                        <option value="<?= $item->id ?>"><?= htmlspecialchars($item->nome) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Procedimento</label>
                                <select name="procedimento_id" class="form-select" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($dados['procedimentos'] as $item): ?>
                                        <option value="<?= $item->id ?>"><?= htmlspecialchars($item->nome) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Regiao</label>
                                <select name="regiao_id" class="form-select" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($dados['regioes'] as $item): ?>
                                        <option value="<?= $item->id ?>"><?= htmlspecialchars($item->nome) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tipo Dispositivo</label>
                                <select name="tipo_id" class="form-select" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($dados['tipos_equipamento'] as $item): ?>
                                        <option value="<?= $item->id ?>"><?= htmlspecialchars($item->nome) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tipo Câmera</label>
                                <select name="tipo_camera" class="form-select">
                                    <option value="">Selecione...</option>
                                    <?php foreach ($dados['tipo_cameras'] as $tc): ?>
                                        <option value="<?= $tc->id ?>"><?= htmlspecialchars($tc->nome) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Nome/Local</label>
                                <input type="text" name="nome_local" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Descrição do Local de Instalação</label>
                                <input type="text" name="descricao_posicao" class="form-control" maxlength="255"
                                    placeholder="Ex: Corredor Central, Sala 01">
                            </div>
                            </div></div></div>

                        <!-- Marca e Modelo -->
                        <div class="card alarme-section-card mb-3">
                            <div class="card-header alarme-section-header d-flex align-items-center gap-2">
                                <i class="fas fa-tag"></i>
                                <span>Marca e Modelo</span>
                            </div>
                            <div class="card-body">
                            <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label">Marca</label>
                                <select name="marca_id" class="form-select" id="marcaSelect" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($dados['marcas'] as $item): ?>
                                        <option value="<?= $item->id ?>"><?= htmlspecialchars($item->nome) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-center justify-content-center" style="padding-top: 30px;">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" id="toggleModeloExistente" checked>
                                    <label class="form-check-label" for="toggleModeloExistente" style="white-space: nowrap; font-size:0.88rem;">Usar modelo existente</label>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Modelo</label>
                                <div id="modeloContainer">
                                    <select name="modelo_existente" id="modeloExistenteSelect" class="form-select" required>
                                        <option value="">Selecione a marca primeiro...</option>
                                    </select>
                                    <input type="hidden" name="novo_modelo_nome" value="">
                                </div>
                            </div>
                            </div>

                            <!-- Campos específicos LPR -->
                            <div id="secaoEditLPR" style="display:none">
                                <hr>
                                <h6 class="text-primary mb-3"><i class="fas fa-car me-1"></i>Especificações LPR</h6>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Sentido da Via</label>
                                        <input type="text" name="lpr_sentido_via" class="form-control" maxlength="50"
                                            value="<?= htmlspecialchars($formData['lpr_sentido_via'] ?? '') ?>"
                                            placeholder="Ex: CRESCENTE, DECRESCENTE">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Faixa Monitorada</label>
                                        <input type="text" name="lpr_faixa_monitorada" class="form-control" maxlength="50"
                                            value="<?= htmlspecialchars($formData['lpr_faixa_monitorada'] ?? '') ?>"
                                            placeholder="Ex: FAIXA 1, FAIXA 2">
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check mt-4">
                                            <input class="form-check-input" type="checkbox" name="lpr_leitura_noturna" id="editLprLeituraNoturna" value="1"
                                                <?= !empty($formData['lpr_leitura_noturna']) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="editLprLeituraNoturna">Leitura Noturna</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">URL de Acesso</label>
                                        <input type="url" name="lpr_url_acesso" class="form-control" maxlength="2083"
                                            value="<?= htmlspecialchars($formData['lpr_url_acesso'] ?? '') ?>"
                                            placeholder="https://...">
                                    </div>
                                </div>
                            </div>

                            <!-- Campos específicos DVR -->
                            <div id="secaoEditDVR" style="display:none">
                                <hr>
                                <h6 class="text-primary mb-3"><i class="fas fa-hdd me-1"></i>Especificações DVR</h6>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Modelo</label>
                                        <input type="text" name="dvr_modelo" class="form-control" maxlength="80" required
                                            value="<?= htmlspecialchars($formData['dvr_modelo'] ?? '') ?>"
                                            placeholder="Ex: DVR 1104, DS-7104">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Canais</label>
                                        <input type="number" name="dvr_canais" class="form-control" min="1"
                                            value="<?= htmlspecialchars($formData['dvr_canais'] ?? '') ?>"
                                            placeholder="Ex: 4, 8, 16">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Armazenamento (TB)</label>
                                        <input type="number" name="dvr_armazenamento_tb" class="form-control" min="0" step="0.01"
                                            value="<?= htmlspecialchars($formData['dvr_armazenamento_tb'] ?? '') ?>"
                                            placeholder="Ex: 2.00">
                                    </div>
                                </div>
                            </div>

                            <!-- Campos específicos Totem -->
                            <div id="secaoEditTotem" style="display:none">
                                <hr>
                                <h6 class="text-primary mb-3"><i class="fas fa-desktop me-1"></i>Especificações Totem</h6>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Quantidade de Câmeras</label>
                                        <input type="number" name="totem_quantidade_cameras" class="form-control" min="1" required
                                            value="<?= htmlspecialchars($formData['totem_quantidade_cameras'] ?? '') ?>"
                                            placeholder="Ex: 4">
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check mt-4">
                                            <input class="form-check-input" type="checkbox" name="totem_tem_facial" id="editTotemTemFacial" value="1"
                                                <?= !empty($formData['totem_tem_facial']) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="editTotemTemFacial"><i class="fas fa-face-smile me-1"></i>Reconhecimento Facial</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check mt-4">
                                            <input class="form-check-input" type="checkbox" name="totem_tem_lpr" id="editTotemTemLpr" value="1"
                                                <?= !empty($formData['totem_tem_lpr']) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="editTotemTemLpr"><i class="fas fa-car me-1"></i>Leitor de Placa (LPR)</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            </div></div></div>

                        <!-- Localização e Vínculo -->
                        <div class="card alarme-section-card mb-3">
                            <div class="card-header alarme-section-header d-flex align-items-center gap-2">
                                <i class="fas fa-map-pin"></i>
                                <span>Localização e Vínculo</span>
                            </div>
                            <div class="card-body">
                            <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Local</label>
                                <select name="local_id" class="form-select" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($dados['locais'] as $item): ?>
                                        <option value="<?= $item->id ?>"><?= htmlspecialchars($item->nome) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Secretaria</label>
                                <select name="secretaria_id" class="form-select" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($dados['secretarias'] as $item): ?>
                                        <option value="<?= $item->id ?>"><?= htmlspecialchars($item->nome) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Transmissao</label>
                                <select name="transmissao_id" class="form-select">
                                    <option value="">Selecione...</option>
                                    <?php foreach ($dados['transmissoes'] as $item): ?>
                                        <option value="<?= $item->id ?>"><?= htmlspecialchars($item->tipo ?? $item->nome) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Origem do Link</label>
                                <select name="origem_link_id" class="form-select">
                                    <option value="">Selecione...</option>
                                    <?php foreach ($dados['origem_links'] as $item): ?>
                                        <option value="<?= $item->id ?>" data-inscricao="<?= htmlspecialchars($item->inscricao ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item->nome) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Inscricao</label>
                                <input type="text" name="inscricao" class="form-control" maxlength="20">
                            </div>
                            </div>
                            <div class="row g-3 mt-2">
                                <div class="col-12">
                                    <div class="alarme-check-wrapper" id="alarmeWrapper">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="checkbox"
                                                name="tem_alarme" id="temAlarme" value="1">
                                            <label class="form-check-label" for="temAlarme">
                                                <i class="fas fa-bell me-2 text-warning"></i>
                                                Possui central de alarme no local
                                            </label>
                                        </div>
                                        <div id="containerAlarmeConta">
                                            <label class="form-label required mb-1" for="alarmeConta" style="color:#5d4037; font-size:0.88rem;">
                                                Número da conta do alarme
                                            </label>
                                            <input type="text" name="alarme_conta" id="alarmeConta"
                                                class="form-control"
                                                placeholder="Ex: 12345"
                                                maxlength="50"
                                                disabled>
                                            <div class="form-text" style="color:#8d6e63;">Número da conta cadastrada na empresa de monitoramento</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </div></div>

                        <!-- Identificação -->
                        <div class="card alarme-section-card mb-3">
                            <div class="card-header alarme-section-header d-flex align-items-center gap-2">
                                <i class="fas fa-fingerprint"></i>
                                <span>Identificação</span>
                            </div>
                            <div class="card-body">
                            <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">IP</label>
                                <input type="text" name="ip" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mosaico</label>
                                <input type="text" name="mosaico" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Serie/MAC</label>
                                <input type="text" name="serie_mac" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Patrimonio</label>
                                <input type="text" name="patrimonio" class="form-control">
                            </div>
                            </div></div></div>

                        <!-- Endereço -->
                        <div class="card alarme-section-card mb-3">
                            <div class="card-header alarme-section-header d-flex align-items-center gap-2">
                                <i class="fas fa-map-marker-alt"></i>
                                <span>Endereço</span>
                            </div>
                            <div class="card-body">
                            <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">CEP</label>
                                <input type="text" name="cep" class="form-control" maxlength="9" placeholder="00000-000">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">UF</label>
                                <input type="text" name="uf" class="form-control" maxlength="2" placeholder="PR">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Cidade</label>
                                <input type="text" name="cidade" class="form-control" placeholder="Ex: Londrina">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Bairro</label>
                                <input type="text" name="bairro" class="form-control" placeholder="Ex: Centro">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Tipo Logradouro</label>
                                <select name="tipo_logradouro" class="form-select">
                                    <option value="">Selecione...</option>
                                    <?php foreach ($dados['classificacao_enderecos'] as $item): ?>
                                        <option value="<?= htmlspecialchars($item->nome) ?>">
                                            <?= htmlspecialchars($item->nome) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Logradouro</label>
                                <input type="text" name="logradouro" class="form-control" placeholder="Ex: Brasil, XV de Novembro">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Numero</label>
                                <input type="text" name="numero" class="form-control" placeholder="Ex: 150">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Complemento</label>
                                <input type="text" name="complemento" class="form-control" placeholder="Ex: Predio A">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Coordenadas</label>
                                <input type="text" name="coordenadas" class="form-control" placeholder="Latitude, Longitude">
                            </div>
                            </div></div></div>

                        <!-- Datas e Observações -->
                        <div class="card alarme-section-card mb-3">
                            <div class="card-header alarme-section-header d-flex align-items-center gap-2">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Datas e Observações</span>
                            </div>
                            <div class="card-body">
                            <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Data Instalacao</label>
                                <input type="date" name="data_instalacao" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Observacao</label>
                                <textarea name="observacao" class="form-control" rows="3"></textarea>
                            </div>
                            </div></div></div>

                            <div class="col-12 mt-4 form-actions d-flex gap-2 flex-wrap">
                                <button type="submit" class="btn btn-primary" id="btnSubmit">
                                    <i class="fas fa-save me-1"></i>Salvar Alteracoes
                                </button>
                                <a href="?page=controle_cameras" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-1"></i>Voltar
                                </a>
                                <a href="?page=controle_cameras" class="btn btn-secondary">Cancelar</a>
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

<div id="loadingOverlay" class="loading-overlay overlay-fixed overlay-light is-hidden">
    <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
</div>

<script src="<?= BASE_URL ?>/assets/js/utils/uppercase.js?v=<?= filemtime(__DIR__ . '/../public/assets/js/utils/uppercase.js') ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/utils/ui-utils.js?v=<?= filemtime(__DIR__ . '/../public/assets/js/utils/ui-utils.js') ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/utils/file-upload.js?v=<?= filemtime(__DIR__ . '/../public/assets/js/utils/file-upload.js') ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/editar_cameras.js?v=<?= filemtime(__DIR__ . '/../public/assets/js/editar_cameras.js') ?>"></script>

