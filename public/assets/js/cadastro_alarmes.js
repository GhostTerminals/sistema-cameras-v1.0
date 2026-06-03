(function () {
  let formDirty = false;
  let savedAlarmeId = null;

  function getApiBase() {
    return window.APP_API_BASE || `${window.BASE_URL || '/'}index.php?page=api/`;
  }

  function showMessage(type, text) {
    const el = document.getElementById('messageContainer');
    if (!el) return;
    el.innerHTML = `<div class="alert alert-${type}" role="alert">${text}</div>`;
    el.classList.remove('is-hidden');
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

  function showPopup(message) {
    const safe = String(message ?? '').replace(/[&<>"']/g, function(m) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
    });
    const overlay = document.createElement('div');
    overlay.className = 'alarme-popup-overlay';
    overlay.innerHTML = `
      <div class="alarme-popup-box">
        <div class="alarme-popup-icon">&#10003;</div>
        <p class="alarme-popup-message">${safe}</p>
      </div>
    `;
    document.body.appendChild(overlay);
    requestAnimationFrame(() => overlay.classList.add('visivel'));
    setTimeout(() => {
      overlay.classList.remove('visivel');
      overlay.addEventListener('transitionend', () => overlay.remove(), { once: true });
    }, 3000);
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

    showPopup('Cadastro finalizado com sucesso!');
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
      showPopup(alarmMsg);
      showMessage('success', alarmMsg + ' — Agora você pode anexar imagens se desejar.');

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
      showMessage('danger', error.message || 'Erro ao cadastrar alarme.');
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
