<?php
require_once __DIR__ . '/../inc/navbar.php';

// Verificar permissões
if (!isset($_SESSION['usuario']) || !in_array($_SESSION['usuario']->nivel_acesso ?? '', ['admin', 'supervisor'], true)) {
    header('Location: index.php?page=nao_autorizado');
    exit;
}

// Carregar dados para selects
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
        'tipos_locais' => 'tipos_locais',
        'secretarias' => 'secretarias',
    ];

    foreach ($tabelas as $key => $tabela) {
        $order = $tabela === 'transmissoes' ? 'tipo' : 'nome';
        $result = $db->query("SELECT * FROM $tabela ORDER BY $order");
        $dados[$key] = $result['status'] === 'success' ? $result['data'] : [];
    }
} catch(Exception $e) {
    error_log('Erro ao carregar dados cadastro_cameras: ' . $e->getMessage());
    echo '<div class="alert alert-danger">Erro ao carregar dados do formulario.</div>';
}

// Verificar se há dados de formulário retornados (em caso de erro)
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);
?>

<link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/css/pages/cadastro_cameras.css?v=<?= filemtime(__DIR__ . '/../public/assets/css/pages/cadastro_cameras.css') ?: 0 ?>">

<div class="container mt-4 mb-5 camera-cadastro-page">
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h3 class="mb-0"><i class="fas fa-camera me-2"></i>Cadastro de C&acirc;mera</h3>
                <p class="text-muted mb-0">Preencha os dados de identifica&ccedil;&atilde;o, rede e localiza&ccedil;&atilde;o do equipamento.</p>
            </div>
            <a href="?page=controle_cameras" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Voltar
            </a>
        </div>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-camera me-2"></i>Informa&ccedil;&otilde;es do Equipamento</h5>
                </div>

                <div class="card-body p-4">
                    <!-- Mensagens de Feedback -->



                    <!-- Formulário -->
                    <form id="formCadastroCamera" class="needs-validation" novalidate>
                        <!-- Seção 1: Informações Básicas -->
                        <div class="card alarme-section-card mb-3">
                            <div class="card-header alarme-section-header d-flex align-items-center gap-2">
                                <i class="fas fa-info-circle"></i>
                                <span>Informações Básicas</span>
                            </div>
                            <div class="card-body">
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <label class="form-label required">Status</label>
                                    <select name="status_id" class="form-select" required>
                                        <option value="">Selecione...</option>
                                        <?php foreach ($dados['status'] as $status): ?>
                                        <option value="<?= $status->id ?>"
                                            <?= isset($formData['status_id']) && $formData['status_id'] == $status->id ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($status->nome) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Selecione o status da câmera</div>
                                </div>

                                <div class="col-md-8">
                                    <label class="form-label required">Nome/Local da Câmera</label>
                                    <input type="text" name="nome_local" class="form-control" maxlength="255" required
                                        value="<?= htmlspecialchars($formData['nome_local'] ?? '') ?>"
                                        placeholder="Ex: Escola Municipal, Praça Central, Cmei, Via Pública">
                                    <div class="invalid-feedback">Informe o nome/local da câmera</div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Descrição do Local de Instalação</label>
                                    <input type="text" name="descricao_posicao" class="form-control" maxlength="255"
                                        value="<?= htmlspecialchars($formData['descricao_posicao'] ?? '') ?>"
                                        placeholder="Ex: Corredor Central, Sala 01">
                                    <div class="form-text">Detalhe onde o equipamento está instalado</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label required">Tipo de Local</label>
                                    <select name="tipo_local_id" class="form-select" required>
                                        <option value="">Selecione...</option>
                                        <?php foreach ($dados['tipos_locais'] as $tipoLocal): ?>
                                        <option value="<?= $tipoLocal->id ?>"
                                            <?= isset($formData['tipo_local_id']) && $formData['tipo_local_id'] == $tipoLocal->id ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($tipoLocal->nome) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Selecione o tipo de local</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label required">Região</label>
                                    <select name="regiao_id" class="form-select" required>
                                        <option value="">Selecione...</option>
                                        <?php foreach ($dados['regioes'] as $regiao): ?>
                                        <option value="<?= $regiao->id ?>"
                                            <?= isset($formData['regiao_id']) && $formData['regiao_id'] == $regiao->id ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($regiao->nome) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Selecione a região</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label required">Procedimento</label>
                                    <select name="procedimento_id" class="form-select" required>
                                        <option value="">Selecione...</option>
                                        <?php foreach ($dados['procedimentos'] as $procedimento): ?>
                                        <option value="<?= $procedimento->id ?>"
                                            <?= isset($formData['procedimento_id']) && $formData['procedimento_id'] == $procedimento->id ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($procedimento->nome) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Selecione o procedimento</div>
                                </div>

                                <!-- Central de Alarme -->
                                <div class="col-12">
                                    <?php
                                        $temAlarme = !empty($formData['tem_alarme']);
                                    ?>
                                    <div class="alarme-check-wrapper <?= $temAlarme ? 'alarme-ativo' : '' ?>" id="alarmeWrapper">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="checkbox"
                                                name="tem_alarme" id="temAlarme" value="1"
                                                <?= $temAlarme ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="temAlarme">
                                                <i class="fas fa-bell me-2 text-warning"></i>
                                                Possui central de alarme no local
                                            </label>
                                        </div>

                                        <div id="containerAlarmeConta" class="<?= $temAlarme ? 'visivel' : '' ?>">
                                            <label class="form-label required mb-1" for="alarmeConta" style="color:#5d4037; font-size:0.88rem;">
                                                Número da conta do alarme
                                            </label>
                                            <input type="text" name="alarme_conta" id="alarmeConta"
                                                class="form-control"
                                                value="<?= htmlspecialchars($formData['alarme_conta'] ?? '') ?>"
                                                placeholder="Ex: 12345"
                                                maxlength="50"
                                                <?= $temAlarme ? '' : 'disabled' ?>>
                                            <div class="form-text" style="color:#8d6e63;">Número da conta cadastrada na empresa de monitoramento</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div></div>

                        <!-- Seção 2: Especificações Técnicas -->
                        <div class="card alarme-section-card mb-3">
                            <div class="card-header alarme-section-header d-flex align-items-center gap-2">
                                <i class="fas fa-cogs"></i>
                                <span>Especificações Técnicas</span>
                            </div>
                            <div class="card-body">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label required">Marca</label>
                                    <select name="marca_id" id="marcaSelect" class="form-select" required>
                                        <option value="">Selecione...</option>
                                        <?php foreach ($dados['marcas'] as $marca): ?>
                                        <option value="<?= $marca->id ?>"
                                            <?= isset($formData['marca_id']) && $formData['marca_id'] == $marca->id ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($marca->nome) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Selecione a marca</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label required">Modelo</label>
                                    <div id="modeloContainer">
                                        <input type="text" name="novo_modelo_nome" class="form-control" required
                                            value="<?= htmlspecialchars($formData['novo_modelo_nome'] ?? '') ?>"
                                            placeholder="Ex: IPC-HDW5842T-ZE, DS-2CD2143G0-I">
                                        <input type="hidden" name="modelo_id" id="modelo_id"
                                            value="<?= $formData['modelo_id'] ?? '' ?>">
                                    </div>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="toggleModeloExistente">
                                        <label class="form-check-label" for="toggleModeloExistente">
                                            Usar modelo existente
                                        </label>
                                    </div>
                                    <div class="invalid-feedback">Informe o modelo da câmera</div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label required">Tipo Dispositivo</label>
                                    <select name="tipo_id" class="form-select" required>
                                        <option value="">Selecione...</option>
                                        <?php foreach ($dados['tipos_equipamento'] as $tipo): ?>
                                        <option value="<?= $tipo->id ?>"
                                            <?= isset($formData['tipo_id']) && $formData['tipo_id'] == $tipo->id ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($tipo->nome) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Selecione o tipo de dispositivo</div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Tipo Câmera</label>
                                    <select name="tipo_camera" class="form-select">
                                        <option value="">Selecione...</option>
                                        <?php foreach ($dados['tipo_cameras'] as $tc): ?>
                                        <option value="<?= $tc->id ?>"
                                            <?= isset($formData['tipo_camera']) && $formData['tipo_camera'] == $tc->id ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($tc->nome) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Transmissão</label>
                                    <select name="transmissao_id" class="form-select">
                                        <option value="">Selecione...</option>
                                        <?php foreach ($dados['transmissoes'] as $trans): ?>
                                        <option value="<?= $trans->id ?>"
                                            <?= isset($formData['transmissao_id']) && $formData['transmissao_id'] == $trans->id ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($trans->tipo ?? $trans->nome) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Origem do Link</label>
                                    <select name="origem_link_id" class="form-select">
                                        <option value="">Selecione...</option>
                                        <?php foreach ($dados['origem_links'] as $origem): ?>
                                        <option value="<?= $origem->id ?>"
                                            data-inscricao="<?= htmlspecialchars($origem->inscricao ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                            <?= isset($formData['origem_link_id']) && $formData['origem_link_id'] == $origem->id ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($origem->nome) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Inscrição</label>
                                    <input type="text" name="inscricao" class="form-control"
                                        value="<?= htmlspecialchars($formData['inscricao'] ?? '') ?>"
                                        placeholder="Identificador da inscrição do link"
                                        maxlength="20">
                                    <div class="form-text">Inscrição do link</div>
                                </div>
                            </div>
                        </div></div>

                        <!-- Seção 3: Identificação -->
                        <div class="card alarme-section-card mb-3">
                            <div class="card-header alarme-section-header d-flex align-items-center gap-2">
                                <i class="fas fa-fingerprint"></i>
                                <span>Identificação</span>
                            </div>
                            <div class="card-body">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label">Endereço IP</label>
                                    <input type="text" name="ip" class="form-control"
                                        value="<?= htmlspecialchars($formData['ip'] ?? '') ?>"
                                        placeholder="192.168.1.100">
                                    <div class="form-text">Endereço IP da câmera na rede</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Série/MAC Address</label>
                                    <input type="text" name="serie_mac" class="form-control"
                                        value="<?= htmlspecialchars($formData['serie_mac'] ?? '') ?>"
                                        placeholder="00:1A:2B:3C:4D:5E ou serial number">
                                    <div class="form-text">Número de série ou endereço MAC</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Patrimônio</label>
                                    <input type="text" name="patrimonio" class="form-control"
                                        value="<?= htmlspecialchars($formData['patrimonio'] ?? '') ?>"
                                        placeholder="Número do patrimônio">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Mosaico</label>
                                    <input type="text" name="mosaico" class="form-control"
                                        value="<?= htmlspecialchars($formData['mosaico'] ?? '') ?>"
                                        placeholder="Posição no mosaico (ex: CAM-01)">
                                    <div class="form-text">Identificação no mosaico de câmeras</div>
                                </div>
                            </div>
                        </div></div>

                        <!-- Seção 3.5: Campos Específicos por Tipo de Dispositivo -->
                        <div class="card alarme-section-card mb-3" id="secaoEspecificaLPR" style="display:none">
                            <div class="card-header alarme-section-header d-flex align-items-center gap-2">
                                <i class="fas fa-car"></i>
                                <span>Especificações LPR (Leitura de Placas)</span>
                            </div>
                            <div class="card-body">
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <label class="form-label">Sentido da Via</label>
                                    <input type="text" name="lpr_sentido_via" class="form-control"
                                        value="<?= htmlspecialchars($formData['lpr_sentido_via'] ?? '') ?>"
                                        maxlength="50" placeholder="Ex: CRESCENTE, DECRESCENTE">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Faixa Monitorada</label>
                                    <input type="text" name="lpr_faixa_monitorada" class="form-control"
                                        value="<?= htmlspecialchars($formData['lpr_faixa_monitorada'] ?? '') ?>"
                                        maxlength="50" placeholder="Ex: FAIXA 1, FAIXA 2">
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" name="lpr_leitura_noturna" id="lprLeituraNoturna" value="1"
                                            <?= !empty($formData['lpr_leitura_noturna']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="lprLeituraNoturna">
                                            Leitura Noturna
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div></div>

                        <div class="card alarme-section-card mb-3" id="secaoEspecificaDVR" style="display:none">
                            <div class="card-header alarme-section-header d-flex align-items-center gap-2">
                                <i class="fas fa-hdd"></i>
                                <span>Especificações DVR (Gravador)</span>
                            </div>
                            <div class="card-body">
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <label class="form-label required">Modelo</label>
                                    <input type="text" name="dvr_modelo" class="form-control" required
                                        value="<?= htmlspecialchars($formData['dvr_modelo'] ?? '') ?>"
                                        maxlength="80" placeholder="Ex: DVR 1104, DS-7104">
                                    <div class="invalid-feedback">Informe o modelo do DVR</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Canais</label>
                                    <input type="number" name="dvr_canais" class="form-control"
                                        value="<?= htmlspecialchars($formData['dvr_canais'] ?? '') ?>"
                                        min="1" placeholder="Ex: 4, 8, 16">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Armazenamento (TB)</label>
                                    <input type="number" name="dvr_armazenamento_tb" class="form-control"
                                        value="<?= htmlspecialchars($formData['dvr_armazenamento_tb'] ?? '') ?>"
                                        min="0" step="0.01" placeholder="Ex: 2.00">
                                </div>
                            </div>
                        </div></div>

                        <div class="card alarme-section-card mb-3" id="secaoEspecificaTotem" style="display:none">
                            <div class="card-header alarme-section-header d-flex align-items-center gap-2">
                                <i class="fas fa-desktop"></i>
                                <span>Especificações Totem</span>
                            </div>
                            <div class="card-body">
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <label class="form-label required">Quantidade de Câmeras</label>
                                    <input type="number" name="totem_quantidade_cameras" class="form-control" required
                                        value="<?= htmlspecialchars($formData['totem_quantidade_cameras'] ?? '') ?>"
                                        min="1" placeholder="Ex: 4">
                                    <div class="invalid-feedback">Informe a quantidade de câmeras</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" name="totem_tem_facial" id="totemTemFacial" value="1"
                                            <?= !empty($formData['totem_tem_facial']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="totemTemFacial">
                                            <i class="fas fa-face-smile me-1"></i>Reconhecimento Facial
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" name="totem_tem_lpr" id="totemTemLpr" value="1"
                                            <?= !empty($formData['totem_tem_lpr']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="totemTemLpr">
                                            <i class="fas fa-car me-1"></i>Leitor de Placa (LPR)
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div></div>

                        <!-- Seção 4: Localização -->
                        <div class="card alarme-section-card mb-3">
                            <div class="card-header alarme-section-header d-flex align-items-center gap-2">
                                <i class="fas fa-map-marker-alt"></i>
                                <span>Localização</span>
                            </div>
                            <div class="card-body">
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <label class="form-label">CEP</label>
                                    <div class="input-group">
                                        <input type="text" name="cep" id="cep" class="form-control"
                                            value="<?= htmlspecialchars($formData['cep'] ?? '') ?>"
                                            placeholder="00000-000" maxlength="9">
                                        <button type="button" class="btn btn-outline-primary" id="btnBuscarCep">
                                            Buscar
                                        </button>
                                    </div>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label required">UF</label>
                                    <input type="text" name="uf" id="uf" class="form-control" required maxlength="2"
                                        value="<?= htmlspecialchars($formData['uf'] ?? '') ?>" placeholder="PR">
                                    <div class="invalid-feedback">Informe a UF</div>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label required">Cidade</label>
                                    <input type="text" name="cidade" id="cidade" class="form-control" required
                                        value="<?= htmlspecialchars($formData['cidade'] ?? '') ?>"
                                        placeholder="Ex: Londrina">
                                    <div class="invalid-feedback">Informe a cidade</div>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label required">Bairro</label>
                                    <input type="text" name="bairro" id="bairro" class="form-control" required
                                        value="<?= htmlspecialchars($formData['bairro'] ?? '') ?>"
                                        placeholder="Ex: Centro">
                                    <div class="invalid-feedback">Informe o bairro</div>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Tipo Logradouro</label>
                                    <select name="tipo_logradouro" id="tipo_logradouro" class="form-select">
                                        <option value="">Selecione...</option>
                                        <option value="RUA"
                                            <?= ($formData['tipo_logradouro'] ?? '') === 'RUA' ? 'selected' : '' ?>>Rua
                                        </option>
                                        <option value="AVENIDA"
                                            <?= ($formData['tipo_logradouro'] ?? '') === 'AVENIDA' ? 'selected' : '' ?>>
                                            Avenida</option>
                                        <option value="ALAMEDA"
                                            <?= ($formData['tipo_logradouro'] ?? '') === 'ALAMEDA' ? 'selected' : '' ?>>
                                            Alameda</option>
                                        <option value="TRAVESSA"
                                            <?= ($formData['tipo_logradouro'] ?? '') === 'TRAVESSA' ? 'selected' : '' ?>>
                                            Travessa</option>
                                        <option value="RODOVIA"
                                            <?= ($formData['tipo_logradouro'] ?? '') === 'RODOVIA' ? 'selected' : '' ?>>
                                            Rodovia</option>
                                        <option value="ESTRADA"
                                            <?= ($formData['tipo_logradouro'] ?? '') === 'ESTRADA' ? 'selected' : '' ?>>
                                            Estrada</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label required">Logradouro</label>
                                    <input type="text" name="logradouro" id="logradouro" class="form-control" required
                                        value="<?= htmlspecialchars($formData['logradouro'] ?? '') ?>"
                                        placeholder="Ex: Brasil, das Flores, XV de Novembro">
                                    <div class="invalid-feedback">Informe o nome da via</div>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label required">Número</label>
                                    <input type="text" name="numero" id="numero" class="form-control" required
                                        value="<?= htmlspecialchars($formData['numero'] ?? '') ?>"
                                        placeholder="Ex: 150">
                                    <div class="invalid-feedback">Informe o número</div>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Complemento</label>
                                    <input type="text" name="complemento" id="complemento" class="form-control"
                                        value="<?= htmlspecialchars($formData['complemento'] ?? '') ?>"
                                        placeholder="Ex: Prédio A">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Coordenadas</label>
                                    <div class="input-group">
                                        <input type="text" name="coordenadas" id="coordenadas" class="form-control"
                                            value="<?= htmlspecialchars($formData['coordenadas'] ?? '') ?>"
                                            placeholder="Latitude, Longitude">
                                        <button type="button" class="btn btn-outline-primary" id="btnBuscarCoordenadas">
                                            Buscar
                                        </button>
                                    </div>
                                    <div class="form-text">Formato: -23.550520, -46.633308</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label required">Secretaria</label>
                                    <select name="secretaria_id" class="form-select" required>
                                        <option value="">Selecione...</option>
                                        <?php foreach ($dados['secretarias'] as $sec): ?>
                                        <option value="<?= $sec->id ?>"
                                            <?= isset($formData['secretaria_id']) && $formData['secretaria_id'] == $sec->id ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($sec->nome) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Selecione a secretaria</div>
                                </div>

                                <input type="hidden" name="numero_ruas"
                                    value="<?= htmlspecialchars($formData['numero_ruas'] ?? '') ?>">
                            </div>
                        </div></div>

                        <!-- Seção 5: Datas e Observações -->
                        <div class="card alarme-section-card mb-3">
                            <div class="card-header alarme-section-header d-flex align-items-center gap-2">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Datas e Observações</span>
                            </div>
                            <div class="card-body">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label">Data de Instalação</label>
                                    <input type="date" name="data_instalacao" class="form-control"
                                        value="<?= htmlspecialchars($formData['data_instalacao'] ?? date('Y-m-d')) ?>">
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Observações</label>
                                    <textarea name="observacao" class="form-control" rows="4" maxlength="500"
                                        placeholder="Observações adicionais sobre a câmera..."><?= htmlspecialchars($formData['observacao'] ?? '') ?></textarea>
                                    <div class="form-text">Máximo 500 caracteres</div>
                                </div>
                            </div>
                        </div></div>

                        <!-- Botões -->
                        <div class="form-actions d-flex justify-content-between align-items-center mt-4 flex-wrap gap-2">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary px-5 py-2" id="btnSubmit">
                                    <i class="fas fa-save me-2"></i>Salvar
                                </button>
                                <button type="button" class="btn btn-success px-5 py-2 d-none" id="btnFinalizar">
                                    <i class="fas fa-check me-2"></i>Finalizar Cadastro
                                </button>
                            </div>
                            <a href="?page=controle_cameras" class="btn btn-secondary px-5 py-2">
                                <i class="fas fa-times me-2"></i>Cancelar
                            </a>
                        </div>
                    </form>

                    <!-- Anexos (fotos / documentos) - aparece apos salvar -->
                    <div class="anexos-section-pending card alarme-section-card mb-3 mt-4 anexos-editable" id="anexosSection">
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
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay overlay-fixed overlay-strong is-hidden">
    <div class="text-center">
        <div class="spinner-border text-primary mb-2 spinner-lg" role="status">
            <span class="visually-hidden">Carregando...</span>
        </div>
        <div class="text-muted">Processando...</div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/utils/uppercase.js?v=<?= filemtime(__DIR__ . '/../public/assets/js/utils/uppercase.js') ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/utils/ui-utils.js?v=<?= filemtime(__DIR__ . '/../public/assets/js/utils/ui-utils.js') ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/utils/file-upload.js?v=<?= filemtime(__DIR__ . '/../public/assets/js/utils/file-upload.js') ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/cadastro_cameras.js?v=<?= filemtime(__DIR__ . '/../public/assets/js/cadastro_cameras.js') ?>"></script>



