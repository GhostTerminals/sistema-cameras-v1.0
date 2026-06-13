<?php
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['usuario'])) {
    header('Location: index.php?page=login');
    exit;
}
if ($_SESSION['usuario']->nivel_acesso !== 'admin' && $_SESSION['usuario']->nivel_acesso !== 'supervisor') {
    header('Location: index.php?page=nao_autorizado');
    exit;
}

$db = db();

// PRG: flash success from session (cleared after redirect)
$mensagem_sucesso = $_SESSION['mensagem_sucesso_editar_local'] ?? '';
unset($_SESSION['mensagem_sucesso_editar_local']);

$id_local = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id_local) {
    $_SESSION['erro'] = 'ID do local invalido.';
    header('Location: index.php?page=listar_locais');
    exit;
}

$result = $db->query("SELECT l.*, s.sigla, tl.nome as tipo_local_nome FROM locais l
    LEFT JOIN secretarias s ON l.secretaria_id = s.id
    LEFT JOIN tipos_locais tl ON l.tipo_local_id = tl.id
    WHERE l.id = :id", [':id' => $id_local]);
if ($result['status'] !== 'success' || count($result['data']) === 0) {
    $_SESSION['erro'] = 'Local nao encontrado.';
    header('Location: index.php?page=listar_locais');
    exit;
}
$local = $result['data'][0];

// Carrega dados para os selects
$resultTipos = $db->query("SELECT id, nome FROM tipos_locais ORDER BY nome");
$tiposLocais = ($resultTipos['status'] === 'success') ? $resultTipos['data'] : [];

$resultSec = $db->query("SELECT id, nome, sigla FROM secretarias ORDER BY nome");
$secretarias = ($resultSec['status'] === 'success') ? $resultSec['data'] : [];

$resultClass = $db->query("SELECT id, nome FROM classificacao_enderecos ORDER BY nome");
$classificacoes = ($resultClass['status'] === 'success') ? $resultClass['data'] : [];

$resultRegioes = $db->query("SELECT id, nome FROM regioes ORDER BY nome");
$regioes = ($resultRegioes['status'] === 'success') ? $resultRegioes['data'] : [];

$ufs = [
    'AC','AL','AP','AM','BA','CE','DF','ES','GO',
    'MA','MT','MS','MG','PA','PB','PR','PE','PI',
    'RJ','RN','RS','RO','RR','SC','SP','SE','TO'
];

$beforeAudit = [
    'nome' => $local->nome ?? null,
    'tipo_local_id' => $local->tipo_local_id ?? null,
    'regiao_id' => $local->regiao_id ?? null,
    'secretaria_id' => $local->secretaria_id ?? null,
    'horario_funcionamento' => $local->horario_funcionamento ?? null,
    'tem_alarme' => $local->tem_alarme ?? null,
];

$mensagem_erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $csrfToken = $_POST['csrf_token'] ?? null;
    if (!validateCsrfToken($csrfToken)) {
        $mensagem_erro = 'Falha de validacao CSRF.';
    } else {
        $tipo_local_id = $_POST['tipo_local_id'] ?? null;
        $nome = mb_strtoupper(trim($_POST['nome'] ?? ''));
        $regiao_id = $_POST['regiao_id'] ?? null;
        $secretaria_id = $_POST['secretaria_id'] ?? null;
        $horario_funcionamento = mb_strtoupper(trim($_POST['horario_funcionamento'] ?? ''));
        $tem_alarme = isset($_POST['tem_alarme']) ? 1 : 0;
        $cep = preg_replace('/\D+/', '', (string)($_POST['cep'] ?? ''));
        $uf = strtoupper(trim($_POST['uf'] ?? ''));
        $cidade = mb_strtoupper(trim($_POST['cidade'] ?? ''));
        $bairro = mb_strtoupper(trim($_POST['bairro'] ?? ''));
        $classificacao_endereco_id = $_POST['classificacao_endereco_id'] ?? null;
        $logradouro = mb_strtoupper(trim($_POST['logradouro'] ?? ''));
        $numero = trim($_POST['numero'] ?? '');
        $descricao_posicao = mb_strtoupper(trim($_POST['descricao_posicao'] ?? ''));
        $latitude = trim($_POST['latitude'] ?? '');
        $longitude = trim($_POST['longitude'] ?? '');

        if (empty($nome)) {
            $mensagem_erro = 'O campo Nome do Local e obrigatorio.';
        } elseif (empty($tipo_local_id)) {
            $mensagem_erro = 'Selecione o Tipo de Local.';
        } else {
            $sql = "UPDATE locais SET
                        tipo_local_id = :tipo_local_id,
                        nome = :nome,
                        regiao_id = :regiao_id,
                        secretaria_id = :secretaria_id,
                        horario_funcionamento = :horario_funcionamento,
                        tem_alarme = :tem_alarme,
                        cep = :cep,
                        uf = :uf,
                        cidade = :cidade,
                        bairro = :bairro,
                        classificacao_endereco_id = :classificacao_endereco_id,
                        logradouro = :logradouro,
                        numero = :numero,
                        descricao_posicao = :descricao_posicao,
                        latitude = :latitude,
                        longitude = :longitude
                    WHERE id = :id";
            $params = [
                ':tipo_local_id' => $tipo_local_id ? (int)$tipo_local_id : null,
                ':nome' => $nome,
                ':regiao_id' => $regiao_id ? (int)$regiao_id : null,
                ':secretaria_id' => $secretaria_id ? (int)$secretaria_id : null,
                ':horario_funcionamento' => $horario_funcionamento ?: null,
                ':tem_alarme' => $tem_alarme,
                ':cep' => $cep ?: null,
                ':uf' => $uf ?: null,
                ':cidade' => $cidade ?: null,
                ':bairro' => $bairro ?: null,
                ':classificacao_endereco_id' => $classificacao_endereco_id ? (int)$classificacao_endereco_id : null,
                ':logradouro' => $logradouro ?: null,
                ':numero' => $numero ?: null,
                ':descricao_posicao' => $descricao_posicao ?: null,
                ':latitude' => $latitude !== '' ? (float)$latitude : null,
                ':longitude' => $longitude !== '' ? (float)$longitude : null,
                ':id' => $id_local,
            ];

            $result = $db->query($sql, $params);

            if ($result['status'] === 'success') {
                auditEvent($db, 'locais', $id_local, 'UPDATE', $beforeAudit, [
                    'id' => $id_local,
                    'nome' => $nome,
                    'tipo_local_id' => $tipo_local_id,
                    'regiao_id' => $regiao_id,
                    'secretaria_id' => $secretaria_id,
                    'horario_funcionamento' => $horario_funcionamento,
                    'tem_alarme' => $tem_alarme,
                ], 'web');
                $_SESSION['mensagem_sucesso_editar_local'] = 'Local atualizado com sucesso.';
                header('Location: index.php?page=editar_locais&id=' . $id_local);
                exit;
            } else {
                $mensagem_erro = 'Erro ao atualizar local.';
            }
        }
    }

    // Se houve erro, sobrescreve $local com os dados submetidos para exibir no formulario
    if ($mensagem_erro) {
        $local->tipo_local_id = $tipo_local_id;
        $local->nome = $nome;
        $local->regiao_id = $regiao_id;
        $local->secretaria_id = $secretaria_id;
        $local->horario_funcionamento = $horario_funcionamento;
        $local->tem_alarme = $tem_alarme;
        $local->cep = $cep;
        $local->uf = $uf;
        $local->cidade = $cidade;
        $local->bairro = $bairro;
        $local->classificacao_endereco_id = $classificacao_endereco_id;
        $local->logradouro = $logradouro;
        $local->numero = $numero;
        $local->descricao_posicao = $descricao_posicao;
        $local->latitude = $latitude;
        $local->longitude = $longitude;
    }
}

require_once __DIR__ . '/../inc/navbar.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-xl-10 col-lg-12">
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="fas fa-pen me-2"></i>Editar Local</h4>
                    <a href="?page=listar_locais" class="btn btn-outline-dark btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>Voltar
                    </a>
                </div>
                <div class="card-body">
                    <?php if ($mensagem_erro): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= htmlspecialchars($mensagem_erro, ENT_QUOTES, 'UTF-8') ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($mensagem_sucesso): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?= htmlspecialchars($mensagem_sucesso, ENT_QUOTES, 'UTF-8') ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="needs-validation" novalidate autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">

                        <h5 class="border-bottom pb-2 mb-3"><i class="fas fa-tag me-2"></i>Identificacao</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-2">
                                <label for="tipo_local_id" class="form-label">Tipo de Local *</label>
                                <select class="form-select form-select-sm" id="tipo_local_id" name="tipo_local_id" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($tiposLocais as $t): ?>
                                    <option value="<?= (int)$t->id ?>" <?= ((int)$local->tipo_local_id === (int)$t->id) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($t->nome, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label for="nome" class="form-label">Nome do Local *</label>
                                <input type="text" class="form-control form-control-sm" id="nome" name="nome" required
                                    value="<?= htmlspecialchars($local->nome ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    placeholder="Ex: ESCOLA MUNICIPAL PROF JOÃO">
                            </div>
                            <div class="col-md-2">
                                <label for="regiao_id" class="form-label">Regiao</label>
                                <select class="form-select form-select-sm" id="regiao_id" name="regiao_id">
                                    <option value="">Selecione...</option>
                                    <?php foreach ($regioes as $r): ?>
                                    <option value="<?= (int)$r->id ?>" <?= ((int)$local->regiao_id === (int)$r->id) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($r->nome, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="tem_alarme" name="tem_alarme" value="1"
                                        <?= ($local->tem_alarme ?? 0) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="tem_alarme">
                                        Local possui alarme
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="secretaria_id" class="form-label">Secretaria</label>
                                <select class="form-select form-select-sm" id="secretaria_id" name="secretaria_id">
                                    <option value="">Nenhuma</option>
                                    <?php foreach ($secretarias as $s): ?>
                                    <option value="<?= (int)$s->id ?>" <?= ((int)$local->secretaria_id === (int)$s->id) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s->sigla . ' - ' . $s->nome, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="horario_funcionamento" class="form-label">Horario de Funcionamento</label>
                                <input type="text" class="form-control form-control-sm" id="horario_funcionamento" name="horario_funcionamento"
                                    value="<?= htmlspecialchars($local->horario_funcionamento ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    placeholder="Ex: Seg a Sex 08:00-18:00">
                            </div>
                        </div>

                        <h5 class="border-bottom pb-2 mb-3"><i class="fas fa-address-card me-2"></i>Endereco</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <label for="cep" class="form-label">CEP</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control form-control-sm" id="cep" name="cep" maxlength="9"
                                        value="<?= htmlspecialchars($local->cep ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                        placeholder="00000-000">
                                    <button type="button" class="btn btn-outline-primary" id="btnBuscarCep">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label for="uf" class="form-label">UF</label>
                                <select class="form-select form-select-sm" id="uf" name="uf">
                                    <option value="">Selecione</option>
                                    <?php foreach ($ufs as $uf): ?>
                                    <option value="<?= $uf ?>" <?= (($local->uf ?? '') === $uf) ? 'selected' : '' ?>><?= $uf ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="cidade" class="form-label">Cidade</label>
                                <input type="text" class="form-control form-control-sm" id="cidade" name="cidade"
                                    value="<?= htmlspecialchars($local->cidade ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    placeholder="Londrina">
                            </div>
                            <div class="col-md-3">
                                <label for="bairro" class="form-label">Bairro</label>
                                <input type="text" class="form-control form-control-sm" id="bairro" name="bairro"
                                    value="<?= htmlspecialchars($local->bairro ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    placeholder="Centro">
                            </div>
                            <div class="col-md-3">
                                <label for="classificacao_endereco_id" class="form-label">Tipo de Logradouro</label>
                                <select class="form-select form-select-sm" id="classificacao_endereco_id" name="classificacao_endereco_id">
                                    <option value="">Selecione</option>
                                    <?php foreach ($classificacoes as $c): ?>
                                    <option value="<?= (int)$c->id ?>" <?= ((int)($local->classificacao_endereco_id ?? 0) === (int)$c->id) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c->nome, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="logradouro" class="form-label">Logradouro</label>
                                <input type="text" class="form-control form-control-sm" id="logradouro" name="logradouro"
                                    value="<?= htmlspecialchars($local->logradouro ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    placeholder="Rua das Flores">
                            </div>
                            <div class="col-md-3">
                                <label for="numero" class="form-label">Numero</label>
                                <input type="text" class="form-control form-control-sm" id="numero" name="numero"
                                    value="<?= htmlspecialchars($local->numero ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    placeholder="123">
                            </div>
                            <div class="col-12">
                                <label for="descricao_posicao" class="form-label">Complemento / Referencia</label>
                                <input type="text" class="form-control form-control-sm" id="descricao_posicao" name="descricao_posicao"
                                    value="<?= htmlspecialchars($local->descricao_posicao ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    placeholder="Proximo ao mercado, sala 2">
                            </div>
                        </div>

                        <h5 class="border-bottom pb-2 mb-3"><i class="fas fa-globe me-2"></i>Geolocalizacao <small class="text-muted">(opcional)</small></h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-5">
                                <label for="latitude" class="form-label">Latitude</label>
                                <input type="text" class="form-control form-control-sm" id="latitude" name="latitude"
                                    value="<?= htmlspecialchars($local->latitude ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    placeholder="-23.3103">
                            </div>
                            <div class="col-md-5">
                                <label for="longitude" class="form-label">Longitude</label>
                                <input type="text" class="form-control form-control-sm" id="longitude" name="longitude"
                                    value="<?= htmlspecialchars($local->longitude ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    placeholder="-51.1628">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" id="btnBuscarCoordenadas" class="btn btn-outline-info btn-sm w-100" title="Buscar coordenadas pelo endereço preenchido">
                                    <i class="fas fa-map-pin me-1"></i>Geo
                                </button>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col d-flex justify-content-between">
                                <a href="?page=listar_locais" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-1"></i>Cancelar
                                </a>
                                <button type="submit" name="submit" class="btn btn-warning px-4">
                                    <i class="fas fa-save me-1"></i>Salvar Alteracoes
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/js/utils/uppercase.js?v=<?= filemtime(__DIR__ . '/../public/assets/js/utils/uppercase.js') ?>"></script>
<script nonce="<?= htmlspecialchars($CSP_NONCE ?? '', ENT_QUOTES, 'UTF-8') ?>">
document.addEventListener('DOMContentLoaded', function() {
    aplicarUppercaseUniversal(document.querySelector('form'));

    var API_BASE = window.APP_API_BASE || (window.BASE_URL || '/') + 'index.php?page=api/';

    // --- Helper: set select option by text ---
    function setSelectByText(selectId, text) {
        var sel = document.getElementById(selectId);
        if (!sel || !text) return;
        var upper = text.toUpperCase().trim();
        for (var i = 0; i < sel.options.length; i++) {
            if (sel.options[i].text.toUpperCase().trim() === upper) {
                sel.selectedIndex = i;
                return;
            }
        }
    }

    // --- CEP Mask + Lookup ---
    var cepInput = document.getElementById('cep');
    cepInput.addEventListener('input', function(e) {
        var value = e.target.value.replace(/\D/g, '').substring(0, 8);
        e.target.value = value.length > 5 ? value.substring(0, 5) + '-' + value.substring(5) : value;

        // Se o CEP foi alterado (digitando), limpa endereco anterior
        var raw = value;
        if (raw.length === 0 || raw.length < 8) {
            document.getElementById('logradouro').value = '';
            document.getElementById('bairro').value = '';
            document.getElementById('cidade').value = '';
        }
    });

    function buscarCep() {
        var cep = cepInput.value.replace(/\D/g, '');
        if (cep.length !== 8) {
            if (typeof showToast === 'function') showToast('Informe um CEP valido com 8 digitos.', 'warning');
            cepInput.focus();
            return;
        }

        fetch(API_BASE + 'api_cep_lookup&cep=' + cep)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.data) {
                    var d = data.data;

                    var logradouroCompleto = (d.logradouro || '').trim();
                    var partes = logradouroCompleto.split(/\s+/);
                    var tipo = partes.length > 1 ? partes[0].replace('.', '').toUpperCase() : '';
                    var via = partes.length > 1 ? partes.slice(1).join(' ') : logradouroCompleto;

                    if (tipo) setSelectByText('classificacao_endereco_id', tipo);
                    document.getElementById('logradouro').value = (via || '').toUpperCase();
                    document.getElementById('bairro').value = (d.bairro || '').toUpperCase();
                    document.getElementById('cidade').value = (d.cidade || '').toUpperCase();

                    var ufSelect = document.getElementById('uf');
                    if (d.uf) {
                        for (var i = 0; i < ufSelect.options.length; i++) {
                            if (ufSelect.options[i].value === d.uf) {
                                ufSelect.selectedIndex = i;
                                break;
                            }
                        }
                    }

                    if (typeof showToast === 'function') showToast('Endereco preenchido a partir do CEP.', 'success');
                } else {
                    if (typeof showToast === 'function') showToast('CEP nao encontrado.', 'warning');
                }
            })
            .catch(function(err) {
                console.error('CEP lookup failed:', err);
                if (typeof showToast === 'function') showToast('Erro ao consultar CEP.', 'danger');
            });
    }

    document.getElementById('btnBuscarCep').addEventListener('click', buscarCep);
    cepInput.addEventListener('blur', buscarCep);

    // --- Geocode (Buscar Coordenadas) ---
    var btnGeo = document.getElementById('btnBuscarCoordenadas');
    btnGeo.addEventListener('click', function() {
        var getVal = function(id) { return (document.getElementById(id) || {}).value || ''; };

        var tipoLog = getVal('classificacao_endereco_id');
        var tipoText = '';
        var sel = document.getElementById('classificacao_endereco_id');
        if (sel) {
            for (var i = 0; i < sel.options.length; i++) {
                if (sel.options[i].value === tipoLog) {
                    tipoText = sel.options[i].text;
                    break;
                }
            }
        }

        var logradouro = getVal('logradouro');
        var numero = getVal('numero');
        var bairro = getVal('bairro');
        var cidade = getVal('cidade');
        var uf = getVal('uf');

        if (!logradouro || !bairro || !cidade || !uf) {
            if (typeof showToast === 'function') {
                showToast('Preencha logradouro, bairro, cidade e UF antes de buscar coordenadas.', 'warning');
            }
            return;
        }

        var query = [tipoText, logradouro, numero, bairro, cidade, uf, 'Brasil'].filter(Boolean).join(', ');

        btnGeo.disabled = true;
        btnGeo.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        fetch(API_BASE + 'api_geocode&q=' + encodeURIComponent(query))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.data && data.data.lat && data.data.lon) {
                    document.getElementById('latitude').value = Number(data.data.lat).toFixed(6);
                    document.getElementById('longitude').value = Number(data.data.lon).toFixed(6);
                    if (typeof showToast === 'function') {
                        showToast('Coordenadas preenchidas automaticamente.', 'success');
                    }
                } else {
                    if (typeof showToast === 'function') {
                        showToast('Endereco nao encontrado para geolocalizacao.', 'warning');
                    }
                }
            })
            .catch(function(err) {
                console.error('Geocode failed:', err);
                if (typeof showToast === 'function') {
                    showToast('Erro ao buscar coordenadas.', 'danger');
                }
            })
            .finally(function() {
                btnGeo.disabled = false;
                btnGeo.innerHTML = '<i class="fas fa-map-pin me-1"></i>Geo';
            });
    });
});
</script>
