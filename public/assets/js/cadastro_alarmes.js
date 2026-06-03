(function () {
  let formDirty = false;
  let savedAlarmeId = null;

  function getApiBase() {
    return window.APP_API_BASE || `${window.BASE_URL || '/'}index.php?page=api/`;
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

  function resetForm() {
    const form = document.getElementById('formCadastroAlarme');
    if (!form) return;
    form.reset();
    form.classList.remove('was-validated');
    formDirty = false;

    const btn = document.getElementById('btnSubmit');
    const btnFinalizar = document.getElementById('btnFinalizar');
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-save me-1"></i>Salvar';
    }
    if (btnFinalizar) {
      btnFinalizar.classList.add('d-none');
    }

    const anexosSection = document.getElementById('anexosSection');
    if (anexosSection) {
      anexosSection.classList.remove('anexos-section-visible');
      const list = anexosSection.querySelector('.anexos-list');
      if (list) {
        list.removeAttribute('data-equipamento-id');
        list.removeAttribute('data-alarme-id');
      }
    }

    savedAlarmeId = null;

    window.showToast('Cadastro finalizado com sucesso!', 'success');
  }

  async function handleSubmit(event) {
    event.preventDefault();
    const form = event.currentTarget;
    if (!form.checkValidity()) {
      form.classList.add('was-validated');
      return;
    }

    const btn = document.getElementById('btnSubmit');
    const payload = formToPayload(form);
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || window.CSRF_TOKEN || '';

    try {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Salvando...';
      const response = await fetch(`${getApiBase()}api_cadastrar_alarmes`, {
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
        throw new Error(result.message || 'Falha ao cadastrar alarme.');
      }

      formDirty = false;
      savedAlarmeId = result.data?.resource?.alarme_id;

      var alarmMsg = result.data?.resource?.message || 'Alarme cadastrado com sucesso!';
      window.showToast(alarmMsg + ' — Agora você pode anexar imagens se desejar.', 'success');

      if (savedAlarmeId) {
        const anexosSection = document.getElementById('anexosSection');
        if (anexosSection) {
          anexosSection.classList.add('anexos-section-visible');
          const list = anexosSection.querySelector('.anexos-list');
          if (list) list.setAttribute('data-alarme-id', savedAlarmeId);
          if (window.initAnexoSection) {
            initAnexoSection(anexosSection);
          }
          anexosSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      }

      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-check-circle me-1"></i>Salvo';

      const btnFinalizar = document.getElementById('btnFinalizar');
      if (btnFinalizar) {
        btnFinalizar.classList.remove('d-none');
      }
    } catch (error) {
      window.showToast(error.message || 'Erro ao cadastrar alarme.', 'danger');
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-save me-1"></i>Salvar';
    }
  }

  function init() {
    if (!document.body.classList.contains('page-cadastro_alarmes')) return;
    const form = document.getElementById('formCadastroAlarme');
    if (!form) return;
    aplicarUppercaseUniversal(form);
    setupCharCounters();
    setupDirtyTracking(form);
    setupBeforeUnload();
    form.addEventListener('submit', handleSubmit);
    document.getElementById('btnFinalizar')?.addEventListener('click', resetForm);
  }

  document.addEventListener('DOMContentLoaded', init);
})();
