(function () {
  let alarmes = [];
  let formDirty = false;

  function getApiBase() {
    return window.APP_API_BASE || `${window.BASE_URL || '/'}index.php?page=api/`;
  }

  function showMessage(type, text) {
    const el = document.getElementById('messageContainer');
    if (!el) return;
    el.innerHTML = `<div class="alert alert-${type}" role="alert">${text}</div>`;
    el.classList.remove('is-hidden');
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function formToPayload(form) {
    const data = {};
    const fd = new FormData(form);
    for (const [k, v] of fd.entries()) {
      const value = String(v || '').trim();
      if (value === '') continue;
      data[k] = value;
    }
    return data;
  }

  function fillForm(alarme) {
    const form = document.getElementById('formEditarAlarme');
    if (!form || !alarme) return;

    const fields = form.querySelectorAll('[name]');
    fields.forEach((field) => {
      const name = field.getAttribute('name');
      if (!name) return;
      const value = alarme[name] ?? '';
      if (field.tagName === 'SELECT') {
        const option = Array.from(field.options).find((o) => String(o.value) === String(value));
        field.value = option ? String(value) : '';
      } else {
        field.value = value;
      }
    });

    const idField = document.getElementById('alarme_id');
    if (idField) idField.value = alarme.id || '';

    var anexosSection = document.getElementById('anexosSection');
    if (anexosSection) {
        var anexoslist = anexosSection.querySelector('.anexos-list');
        if (anexoslist) {
            anexoslist.setAttribute('data-alarme-id', alarme.id || '');
        }
        if (alarme.id) {
            if (window.initAnexoSection) {
                initAnexoSection(anexosSection);
            }
        } else {
            if (anexoslist) anexoslist.innerHTML = '';
        }
    }

    formDirty = false;
  }

  function setupCharCounters() {
    document.querySelectorAll('.char-counter').forEach((el) => {
      const target = document.querySelector(el.getAttribute('data-target'));
      if (!target) return;
      const update = () => { target.textContent = el.value.length; };
      el.addEventListener('input', update);
      update();
    });
  }

  function setupDirtyTracking(form) {
    form.querySelectorAll('input, select, textarea').forEach((el) => {
      el.addEventListener('change', () => { formDirty = true; });
      el.addEventListener('input', () => { formDirty = true; });
    });
  }

  function setupBeforeUnload() {
    window.addEventListener('beforeunload', (e) => {
      if (!formDirty) return;
      e.preventDefault();
      e.returnValue = '';
    });
  }

  function renderSelector(rows) {
    const select = document.getElementById('alarmeSelector');
    if (!select) return;

    select.innerHTML = '<option value="">Selecione...</option>' + rows.map((row) => {
      const label = `${row.id} - CONTA ${row.conta || '-'} - ${row.local || 'SEM LOCAL'} (${row.status || 'SEM STATUS'})`;
      return `<option value="${escapeHtml(row.id)}">${escapeHtml(label)}</option>`;
    }).join('');
  }

  async function loadAlarmes(extraParams = '') {
    const select = document.getElementById('alarmeSelector');
    if (select) select.innerHTML = '<option value="">Carregando alarmes...</option>';

    const response = await fetch(`${getApiBase()}api_alarmes&per_page=100${extraParams}`, { credentials: 'same-origin' });
    const payload = await response.json();
    if (!response.ok || !payload.success) {
      throw new Error(payload.error || 'Erro ao carregar alarmes.');
    }
    alarmes = Array.isArray(payload.data) ? payload.data : [];
    renderSelector(alarmes);
  }

  async function handleSave(event) {
    event.preventDefault();
    const form = event.currentTarget;
    if (!form.checkValidity()) {
      form.classList.add('was-validated');
      return;
    }

    const id = document.getElementById('alarme_id')?.value;
    if (!id) {
      showMessage('warning', 'Selecione um alarme para editar.');
      return;
    }

    const payload = formToPayload(form);
    payload.id = id;

    const btn = document.getElementById('btnSubmit');
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || window.CSRF_TOKEN || '';
    const originalHtml = btn.innerHTML;

    try {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Salvando...';
      const response = await fetch(`${getApiBase()}api_editar_alarme`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': token,
          Accept: 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
      });
      const result = await response.json();
      if (!response.ok || !result.success) {
        throw new Error(result.message || 'Falha ao atualizar alarme.');
      }

      formDirty = false;
      showMessage('success', result.message || 'Alarme atualizado com sucesso.');
    } catch (error) {
      showMessage('danger', error.message || 'Erro ao atualizar alarme.');
    } finally {
      btn.disabled = false;
      btn.innerHTML = originalHtml;
    }
  }

  async function init() {
    if (!document.body.classList.contains('page-editar_alarmes')) return;

    const form = document.getElementById('formEditarAlarme');
    const select = document.getElementById('alarmeSelector');
    const btnBuscar = document.getElementById('btnBuscar');
    const filtroNome = document.getElementById('filtroNome');
    const filtroConta = document.getElementById('filtroConta');

    aplicarUppercaseUniversal(form);
    setupCharCounters();
    setupDirtyTracking(form);
    setupBeforeUnload();

    try {
      await loadAlarmes();

      const urlId = new URLSearchParams(window.location.search).get('id');
      if (urlId) {
        select.value = urlId;
        const found = alarmes.find((a) => String(a.id) === String(urlId));
        if (found) fillForm(found);
      }
    } catch (error) {
      showMessage('danger', error.message || 'Falha ao carregar alarmes.');
    }

    select?.addEventListener('change', () => {
      const id = select.value;
      const found = alarmes.find((a) => String(a.id) === String(id));
      fillForm(found || null);
    });

    btnBuscar?.addEventListener('click', async () => {
      const nome = String(filtroNome?.value || '').trim();
      const conta = String(filtroConta?.value || '').trim();
      const qs = new URLSearchParams();
      if (nome) qs.set('nome', nome);
      if (conta) qs.set('conta', conta);
      const params = qs.toString() ? `&${qs.toString()}` : '';
      try {
        await loadAlarmes(params);
      } catch (error) {
        showMessage('danger', error.message || 'Erro ao buscar alarmes.');
      }
    });

    form?.addEventListener('submit', handleSave);
  }

  document.addEventListener('DOMContentLoaded', init);
})();
