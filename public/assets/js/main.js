/*
<!-- Modal de confirmacao sera carregado no footer.php ou header.php -->
Inclua este modal em inc/footer.php:
<div class="modal fade" id="modalConfirmacaoAcao" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Confirmacao</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" id="btnCancelarAcao" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btnConfirmarAcao">Confirmar</button>
      </div>
    </div>
  </div>
</div>
*/

function confirmModal(message) {
  return new Promise((resolve) => {
    const modalEl = document.getElementById('modalConfirmacaoAcao');
    if (!modalEl) {
      resolve(confirm(message));
      return;
    }
    const modalBody = modalEl.querySelector('.modal-body');
    modalBody.textContent = message;
    const btnConfirmar = document.getElementById('btnConfirmarAcao');
    const btnCancelar = document.getElementById('btnCancelarAcao');
    const modal = new bootstrap.Modal(modalEl);

    function cleanup() {
      btnConfirmar.removeEventListener('click', onConfirm);
      btnCancelar.removeEventListener('click', onCancel);
      modalEl.removeEventListener('hidden.bs.modal', onHide);
    }
    function onConfirm() { cleanup(); modal.hide(); resolve(true); }
    function onCancel() { cleanup(); modal.hide(); resolve(false); }
    function onHide() { cleanup(); resolve(false); }

    btnConfirmar.addEventListener('click', onConfirm);
    btnCancelar.addEventListener('click', onCancel);
    modalEl.addEventListener('hidden.bs.modal', onHide);
    modal.show();
  });
}

function promptModal(message) {
  return new Promise((resolve) => {
    const modalEl = document.getElementById('modalPromptAcao');
    if (!modalEl) {
      resolve(prompt(message));
      return;
    }
    const modalBody = modalEl.querySelector('.modal-body');
    modalBody.textContent = message;
    const input = modalEl.querySelector('.modal-prompt-input');
    const btnConfirmar = document.getElementById('btnConfirmarPrompt');
    const modal = new bootstrap.Modal(modalEl);

    function cleanup() {
      btnConfirmar.removeEventListener('click', onConfirm);
      modalEl.removeEventListener('hidden.bs.modal', onHide);
    }
    function onConfirm() { cleanup(); modal.hide(); resolve(input.value); }
    function onHide() { cleanup(); resolve(null); }

    btnConfirmar.addEventListener('click', onConfirm);
    modalEl.addEventListener('hidden.bs.modal', onHide);
    modal.show();
    if (input) input.focus();
  });
}

class ControlesManutencao {
  constructor() {
    this.statusCache = null;
    this.init();
  }

  init() {
    this.configurarEventListeners();
  }

  configurarEventListeners() {
    document.addEventListener('click', (event) => {
      const button = event.target.closest('[data-action="manutencao-rapida"]');
      if (button) {
        this.acaoRapida(button);
      }
    });
  }

  async acaoRapida(botao) {
    const acao = botao.getAttribute('data-tipo');
    const cameraId = botao.getAttribute('data-camera-id');
    const cameraNome = botao.getAttribute('data-camera-nome') || `ID ${cameraId}`;

    const confirmacoes = {
      manutencao: `Colocar a camera "${cameraNome}" em manutencao?`,
      ativar: `Reativar a camera "${cameraNome}"?`,
      desativar: `Desativar a camera "${cameraNome}"?`,
      'registrar-manutencao': `Registrar manutencao preventiva em "${cameraNome}"?`
    };

    if (!(await confirmModal(confirmacoes[acao] || 'Confirmar acao?'))) {
      return;
    }

    try {
      await executarAcaoManutencao(cameraId, acao);
      safeToast('Acao executada com sucesso.', 'success');
      refreshManutencao();
    } catch (error) {
      console.error('Erro na acao rapida:', error);
      safeToast(error.message || 'Erro ao executar acao.', 'error');
    }
  }
}

async function colocarEmManutencao(id) {
  if (await confirmModal('Colocar esta camera em manutencao?')) {
    await executarAcaoComFeedback(id, 'manutencao');
  }
}

async function registrarManutencao(id) {
  const observacoes = await promptModal('Observacoes da manutencao:');
  if (observacoes !== null && observacoes.trim() !== '') {
    await executarAcaoComFeedback(id, 'registrar-manutencao', observacoes);
  }
}

async function ativarCamera(id) {
  if (await confirmModal('Reativar esta camera?')) {
    await executarAcaoComFeedback(id, 'ativar');
  }
}

async function desativarCamera(id) {
  if (await confirmModal('Desativar esta camera?')) {
    await executarAcaoComFeedback(id, 'desativar');
  }
}

async function executarAcaoComFeedback(id, acao, descricao = null) {
  try {
    await executarAcaoManutencao(id, acao, descricao);
    safeToast('Acao executada com sucesso.', 'success');
    refreshManutencao();
  } catch (error) {
    console.error('Erro ao executar acao:', error);
    safeToast(error.message || 'Erro ao executar acao.', 'error');
  }
}

async function executarAcaoManutencao(id, acao, descricao = null) {
  const equipamentoId = Number.parseInt(id, 10);
  if (!Number.isInteger(equipamentoId) || equipamentoId <= 0) {
    throw new Error('Camera invalida.');
  }

  const payload = {
    equipamento_id: equipamentoId,
    descricao: descricao || descricaoPadrao(acao)
  };

  const statusName = statusParaAcao(acao);
  if (statusName) {
    payload.status_id = await buscarStatusId(statusName);
  }

  const response = await fetch(apiUrl('api_manutencao_cameras'), {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': window.CSRF_TOKEN || ''
    },
    body: JSON.stringify(payload)
  });

  const data = await response.json().catch(() => ({}));
  if (!response.ok || !data.success) {
    throw new Error(data.error || data.message || 'Falha ao processar manutencao.');
  }

  return data;
}

function descricaoPadrao(acao) {
  const descricoes = {
    manutencao: 'Camera colocada em manutencao por acao rapida.',
    ativar: 'Camera reativada por acao rapida.',
    desativar: 'Camera desativada por acao rapida.',
    'registrar-manutencao': 'Manutencao preventiva registrada por acao rapida.'
  };

  return descricoes[acao] || 'Acao rapida de manutencao registrada.';
}

function statusParaAcao(acao) {
  const statusMap = {
    manutencao: 'MANUTENCAO',
    ativar: 'FUNCIONANDO',
    desativar: 'DESATIVADA'
  };

  return statusMap[acao] || null;
}

async function buscarStatusId(nome) {
  if (!window.__statusManutencaoCache) {
    const response = await fetch(apiUrl('api_status'), { credentials: 'same-origin' });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || !data.success) {
      throw new Error('Nao foi possivel carregar os status.');
    }
    window.__statusManutencaoCache = Array.isArray(data.data) ? data.data : (data.data?.data || []);
  }

  const status = window.__statusManutencaoCache.find((item) => {
    return String(item.nome || '').toUpperCase() === nome;
  });

  if (!status) {
    throw new Error(`Status ${nome} nao encontrado.`);
  }

  return Number.parseInt(status.id, 10);
}

function apiUrl(endpoint) {
  if (window.APP_API_BASE) {
    return `${window.APP_API_BASE}${endpoint}`;
  }
  const base = (window.BASE_URL || '').replace(/\/?$/, '/');
  return `${base}index.php?page=api/${endpoint}`;
}

function refreshManutencao() {
  if (window.manutencao && typeof window.manutencao.carregarRelatorio === 'function') {
    window.manutencao.carregarRelatorio(true);
  }
}

function showToast(mensagem, tipo) {
  if (!tipo) tipo = 'success';
  var iconMap = { success: 'fa-check-circle text-success', danger: 'fa-exclamation-circle text-danger', warning: 'fa-exclamation-triangle text-warning', info: 'fa-info-circle text-info' };
  var icon = iconMap[tipo] || iconMap.info;
  var bgMap = { success: 'bg-success-subtle border-success', danger: 'bg-danger-subtle border-danger', warning: 'bg-warning-subtle border-warning', info: 'bg-info-subtle border-info' };
  var bgClass = bgMap[tipo] || bgMap.info;

  var container = document.querySelector('.toast-container');
  if (!container) {
    container = document.createElement('div');
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
  }

  var id = 'toast-' + Date.now() + '-' + Math.random().toString(36).substr(2, 5);
  var safeMsg = String(mensagem ?? '').replace(/[&<>"']/g, function(m) {
    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
  });

  var html = '<div id="' + id + '" class="toast align-items-center border-2 ' + bgClass + '" role="alert" aria-live="assertive" aria-atomic="true">';
  html += '<div class="d-flex">';
  html += '<div class="toast-body"><i class="fas ' + icon + ' me-2"></i>' + safeMsg + '</div>';
  html += '<button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>';
  html += '</div></div>';

  container.insertAdjacentHTML('beforeend', html);
  var el = document.getElementById(id);
  if (el && typeof bootstrap !== 'undefined' && bootstrap.Toast) {
    var toast = new bootstrap.Toast(el, { delay: 3000 });
    toast.show();
    el.addEventListener('hidden.bs.toast', function () { el.remove(); });
  } else {
    setTimeout(function () { if (el) el.remove(); }, 3500);
  }
}

function safeToast(msg, type = 'info') {
  if (typeof window.showToast === 'function') {
    window.showToast(msg, type);
  }
}

if (typeof window !== 'undefined') {
  window.showToast = window.showToast || showToast;
  window.safeToast = window.safeToast || safeToast;
}

if (typeof document !== 'undefined') {
  document.addEventListener('DOMContentLoaded', () => {
    window.controlesManutencao = new ControlesManutencao();
  });
}
