(function () {
  const state = {
    page: 1,
    perPage: 20
  };

  function getApiBase() {
    if (window.APP_API_BASE) return window.APP_API_BASE;
    if (typeof APP_API_BASE !== 'undefined' && APP_API_BASE) return APP_API_BASE;
    const base = window.BASE_URL || '/';
    return `${base}index.php?page=api/`;
  }

  function getBadgeClass(operacao) {
    if (operacao === 'INSERT') return 'bg-success';
    if (operacao === 'UPDATE') return 'bg-warning text-dark';
    if (operacao === 'DELETE') return 'bg-danger';
    return 'bg-secondary';
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function toBrDate(value) {
    if (!value) return '-';
    const d = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return String(value);
    return d.toLocaleString('pt-BR');
  }

  function buildQuery() {
    const form = document.getElementById('formAuditoria');
    const formData = new FormData(form);
    const params = new URLSearchParams();
    params.set('page_num', String(state.page));
    params.set('per_page', String(state.perPage));

    for (const [key, rawValue] of formData.entries()) {
      const value = String(rawValue || '').trim();
      if (value !== '') {
        params.set(key, value);
      }
    }
    return params.toString();
  }

  function renderRows(rows) {
    const tbody = document.getElementById('auditoriaTabelaCorpo');
    if (!rows || rows.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Nenhum registro encontrado.</td></tr>';
      return;
    }

    tbody.innerHTML = rows.map((item) => {
      const usuario = item.usuario_nome || item.usuario_login || 'Sistema';
      const cameraInfo = `${item.codigo_publico || '-'}<br><small class="text-muted">ID ${item.equipamento_id || '-'} | ${item.numero_serie || '-'} | ${item.ip || '-'}</small>`;
      return `
        <tr>
          <td>${escapeHtml(toBrDate(item.created_at))}</td>
          <td>${escapeHtml(usuario)}</td>
          <td><span class="badge badge-op ${getBadgeClass(item.operacao)}">${escapeHtml(item.operacao || '-')}</span></td>
          <td>${cameraInfo}</td>
          <td>${escapeHtml(item.origem || '-')}</td>
          <td>${escapeHtml(item.resumo || '-')}</td>
        </tr>
      `;
    }).join('');
  }

  function renderPagination(pagination) {
    const container = document.getElementById('auditoriaPaginacao');
    const totalPages = Number(pagination?.total_pages || 1);
    const current = Number(pagination?.page || 1);

    if (totalPages <= 1) {
      container.innerHTML = '';
      return;
    }

    const items = [];
    items.push(`<li class="page-item ${current <= 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${current - 1}">Anterior</a></li>`);
    for (let i = 1; i <= totalPages; i += 1) {
      if (i === 1 || i === totalPages || Math.abs(i - current) <= 2) {
        items.push(`<li class="page-item ${i === current ? 'active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`);
      }
    }
    items.push(`<li class="page-item ${current >= totalPages ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${current + 1}">Próxima</a></li>`);
    container.innerHTML = items.join('');

    container.querySelectorAll('a.page-link[data-page]').forEach((el) => {
      el.addEventListener('click', (event) => {
        event.preventDefault();
        const page = Number(el.getAttribute('data-page'));
        if (!Number.isNaN(page) && page >= 1 && page <= totalPages) {
          state.page = page;
          fetchAuditoria();
        }
      });
    });
  }

  async function fetchAuditoria() {
    const totalEl = document.getElementById('totalAuditoria');
    totalEl.textContent = 'Carregando...';

    try {
      const query = buildQuery();
      const response = await fetchWithTimeout(`${getApiBase()}api_auditoria_cameras&${query}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin'
      });
      const payload = await response.json();

      if (!response.ok || !payload.success) {
        throw new Error(payload.error || 'Falha ao carregar auditoria');
      }

      renderRows(payload.data || []);
      renderPagination(payload.pagination || {});

      const total = Number(payload.pagination?.total || 0);
      totalEl.textContent = `Total de registros: ${total}`;
    } catch (error) {
      document.getElementById('auditoriaTabelaCorpo').innerHTML =
        '<tr><td colspan="6" class="text-center text-danger py-4">Erro ao carregar auditoria.</td></tr>';
      document.getElementById('auditoriaPaginacao').innerHTML = '';
      totalEl.textContent = 'Falha ao consultar auditoria.';
      console.error(error);
    }
  }

  function bindEvents() {
    const form = document.getElementById('formAuditoria');
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      state.page = 1;
      fetchAuditoria();
    });

    document.getElementById('btnLimparAuditoria').addEventListener('click', () => {
      form.reset();
      state.page = 1;
      fetchAuditoria();
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    if (!document.body.classList.contains('page-auditoria_cameras')) return;
    bindEvents();
    fetchAuditoria();
  });
})();
