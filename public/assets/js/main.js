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

function showSuccessModal(title, message) {
  try {
    var modalEl = document.getElementById('modalSucessoCadastro');
    if (!modalEl) {
      var div = document.createElement('div');
      div.innerHTML = [
        '<div class="modal fade" id="modalSucessoCadastro" tabindex="-1" data-bs-backdrop="static">',
        '<div class="modal-dialog modal-dialog-centered">',
        '<div class="modal-content border-0">',
        '<div class="modal-body py-4">',
        '<div class="d-flex align-items-center gap-3">',
        '<div><span class="success-icon"><i class="fas fa-check-circle fa-3x text-success"></i></span></div>',
        '<div class="flex-grow-1">',
        '<h5 class="fw-bold mb-1" id="sucessoModalTitle">Cadastro Realizado!</h5>',
        '<p class="text-muted mb-0" id="sucessoModalMessage">O registro foi concluído com sucesso.</p>',
        '</div>',
        '<button type="button" class="btn btn-success px-3 flex-shrink-0" data-bs-dismiss="modal"><i class="fas fa-check me-2"></i>OK</button>',
        '</div></div></div></div></div>'
      ].join('\n');
      var firstChild = div.firstElementChild;
      if (firstChild) document.body.appendChild(firstChild);
      modalEl = document.getElementById('modalSucessoCadastro');
    }
    var titleEl = document.getElementById('sucessoModalTitle');
    var msgEl = document.getElementById('sucessoModalMessage');
    if (titleEl) titleEl.textContent = title || 'Cadastro Realizado!';
    if (msgEl) msgEl.textContent = message || 'O registro foi concluído com sucesso.';
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal && modalEl) {
      var modal = new bootstrap.Modal(modalEl);
      modal.show();
    }
  } catch (e) {
    console.warn('showSuccessModal fallback:', e);
    if (typeof window.showToast === 'function') {
      window.showToast(message || title || 'Cadastro realizado com sucesso!', 'success');
    }
  }
}

if (typeof window !== 'undefined') {
  window.showSuccessModal = showSuccessModal;
}
