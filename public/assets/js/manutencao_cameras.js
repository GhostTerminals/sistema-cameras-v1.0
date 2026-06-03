(function () {
  const state = {
    cameraSelecionada: '',
    historico: [],
    pendingOrders: [],
    executingOrders: [],
    currentOsId: null,
    currentOsProblemas: '',
    currentOsNumeroOs: '',
    historicoBusca: '',
    historicoPage: 1,
    historicoPerPage: 20,
    historicoSortBy: 'data_hora',
    historicoSortDir: 'desc',
    dataInicial: '',
    dataFinal: '',
    listsLoaded: false,
    cameraBusca: '',
    cameraOptions: [],
    selectedHistoricoIds: new Set(),
    selectedHistoricoRows: {},
    currentPagination: {},
    defaultProcedimentoId: ''
  };

  function getApiBase() {
    if (window.APP_API_BASE) return window.APP_API_BASE;
    const base = window.BASE_URL || '/';
    return `${base}index.php?page=api/`;
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
    const dt = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(dt.getTime())) return String(value);
    return dt.toLocaleString('pt-BR');
  }

  function toDateTimeLocalNow() {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
  }

  function toIsoDateStamp() {
    const d = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}${pad(d.getMonth() + 1)}${pad(d.getDate())}_${pad(d.getHours())}${pad(d.getMinutes())}`;
  }

  function csvEscape(value) {
    return `"${String(value ?? '').replace(/"/g, '""')}"`;
  }

  function showMessage(type, text) {
    window.showToast(text, type);
  }

  function clearMessage() {
    // no-op: toasts auto-hide
  }

  function renderSelectOptions(selectId, data, includeEmptyLabel) {
    const select = document.getElementById(selectId);
    if (!select) return;
    const baseOption = includeEmptyLabel ? `<option value="">${escapeHtml(includeEmptyLabel)}</option>` : '';
    const options = (data || []).map((item) => {
      return `<option value="${escapeHtml(item.id)}">${escapeHtml(item.nome)}</option>`;
    }).join('');
    select.innerHTML = baseOption + options;
  }

  function hasSelectableOptions(selectId) {
    const select = document.getElementById(selectId);
    if (!select) return false;
    return select.options.length > 1;
  }

  async function ensureMaintenanceListsLoaded(force = false) {
    if (!force && hasSelectableOptions('procedimento_id') && hasSelectableOptions('status_id')) {
      return;
    }

    const params = new URLSearchParams();
    params.set('page_num', '1');
    params.set('per_page', '10');
    params.set('include_lists', '1');

    const response = await fetch(`${getApiBase()}api_manutencao_cameras&${params.toString()}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
      cache: 'no-store'
    });

    const payload = await response.json();
    if (!response.ok || !payload.success) {
      throw new Error(payload.error || 'Falha ao carregar listas de manutenção.');
    }

    const data = payload.data || {};
    if (Array.isArray(data.procedimento_options)) {
      renderSelectOptions('procedimento_id', data.procedimento_options, 'Selecione...');
    }
    if (Array.isArray(data.status_options)) {
      renderSelectOptions('status_id', data.status_options, 'Manter status atual');
    }
    state.defaultProcedimentoId = String(data?.defaults?.procedimento_id || state.defaultProcedimentoId || '');
    state.listsLoaded = true;
  }

  function syncHistoricoTopScroll() {
    const top = document.getElementById('historicoTopScroll');
    const topContent = document.getElementById('historicoTopScrollContent');
    const bottom = document.querySelector('.historico-table-wrap');
    const table = bottom?.querySelector('table');
    if (!top || !topContent || !bottom || !table) return;

    const totalWidth = table.scrollWidth;
    const visibleWidth = bottom.clientWidth;
    topContent.style.width = `${totalWidth}px`;
    top.style.display = totalWidth > visibleWidth ? 'block' : 'none';
    top.scrollLeft = bottom.scrollLeft;
  }

  function setupHistoricoDualScroll() {
    const top = document.getElementById('historicoTopScroll');
    const bottom = document.querySelector('.historico-table-wrap');
    if (!top || !bottom) return;

    if (!top.dataset.bound) {
      top.addEventListener('scroll', () => {
        bottom.scrollLeft = top.scrollLeft;
      });
      bottom.addEventListener('scroll', () => {
        top.scrollLeft = bottom.scrollLeft;
      });
      window.addEventListener('resize', syncHistoricoTopScroll);
      top.dataset.bound = '1';
    }

    syncHistoricoTopScroll();
  }

  function renderCameras(cameras) {
    state.cameraOptions = Array.isArray(cameras) ? cameras : [];
    const selectIds = ['equipamento_id', 'equipamento_id_os'];
    const options = (cameras || []).map((camera) => {
      const localName = camera.local_nome || camera.descricao || `EQUIPAMENTO ${camera.id}`;
      const tipoLabel = camera.tipo_equipamento_nome ? ` [${camera.tipo_equipamento_nome}]` : '';
      const manutBadge = camera.em_manutencao ? ' (Em manutenção)' : '';
      const label = camera.ip ? `${localName} - ${camera.ip}${tipoLabel}${manutBadge}` : `${localName}${tipoLabel}${manutBadge}`;
      return `<option value="${escapeHtml(camera.id)}"${camera.em_manutencao ? ' data-em-manutencao="1"' : ''}>${escapeHtml(label)}</option>`;
    }).join('');

    selectIds.forEach((selectId) => {
      const select = document.getElementById(selectId);
      if (!select) return;
      select.innerHTML = '<option value="">Selecione uma câmera...</option>' + options;
    });
  }

  function ensureSelectOption(select, value, label) {
    if (!select || !value) return;
    const existing = Array.from(select.options).find((option) => option.value === value);
    if (existing) {
      select.value = value;
      return;
    }

    const option = new Option(label || value, value, true, true);
    select.add(option);
    select.value = value;
  }

  async function loadCameraOptions(term = '') {
    const select = document.getElementById('equipamento_id');
    const selectOs = document.getElementById('equipamento_id_os');
    if (!select) return;
      select.innerHTML = '<option value="">Carregando câmeras...</option>';
    if (selectOs) {
      selectOs.innerHTML = '<option value="">Carregando câmeras...</option>';
    }

    try {
      const qs = new URLSearchParams();
      qs.set('page_num', '1');
      qs.set('per_page', '50');
      qs.set('excluir_manutencao', '1');
      if (term) {
        qs.set('busca', term);
      }

      const response = await fetch(`${getApiBase()}api_cameras&${qs.toString()}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        cache: 'no-store'
      });
      const payload = await response.json();
      if (!response.ok || !payload.success) {
        throw new Error(payload.error || 'Erro ao carregar cameras');
      }

      const list = Array.isArray(payload.data) ? payload.data : [];
      renderCameras(list);

      if (state.cameraSelecionada) {
        const equipamentoSelect = document.getElementById('equipamento_id');
        const equipamentoOsSelect = document.getElementById('equipamento_id_os');
        ensureSelectOption(equipamentoSelect, String(state.cameraSelecionada), `Câmera ${state.cameraSelecionada}`);
        ensureSelectOption(equipamentoOsSelect, String(state.cameraSelecionada), `Câmera ${state.cameraSelecionada}`);
      }
    } catch (e) {
      console.error('Erro ao carregar cameras:', e);
      select.innerHTML = '<option value="">Erro ao carregar cameras</option>';
      if (selectOs) {
        selectOs.innerHTML = '<option value="">Erro ao carregar cameras</option>';
      }
    }
  }

  function updateSelectedCount() {
    const el = document.getElementById('resumoSelecaoHistorico');
    if (!el) return;
    el.textContent = `Selecionados: ${state.selectedHistoricoIds.size}`;
  }

  function updateSelectAllCheckbox() {
    const selectAll = document.getElementById('selectAllHistorico');
    if (!selectAll) return;
    const visibleIds = state.historico.map((item) => String(item.id || ''));
    if (visibleIds.length === 0) {
      selectAll.checked = false;
      selectAll.indeterminate = false;
      return;
    }
    const selectedVisible = visibleIds.filter((id) => state.selectedHistoricoIds.has(id));
    selectAll.checked = selectedVisible.length === visibleIds.length;
    selectAll.indeterminate = selectedVisible.length > 0 && selectedVisible.length < visibleIds.length;
  }

  function renderHistorico(historico, pagination) {
    const tbody = document.getElementById('historicoManutencaoBody');
    const resumo = document.getElementById('resumoHistorico');
    if (!tbody || !resumo) return;

    state.historico = Array.isArray(historico) ? historico : [];
    state.currentPagination = pagination || {};
    const total = Number(pagination?.total || 0);
    const page = Number(pagination?.page || state.historicoPage);
    const totalPages = Number(pagination?.total_pages || 1);
    resumo.textContent = `Total: ${total} | Pagina ${page} de ${totalPages}`;

    if (state.historico.length === 0) {
      tbody.innerHTML = '<tr><td colspan="15" class="text-center text-muted py-4">Nenhuma manutenção registrada.</td></tr>';
      updateSelectedCount();
      updateSelectAllCheckbox();
      return;
    }

    const formatDateOnly = (value) => {
      if (!value) return '-';
      const dt = new Date(String(value).replace(' ', 'T'));
      return Number.isNaN(dt.getTime()) ? String(value) : dt.toLocaleDateString('pt-BR');
    };

    const formatTimeOnly = (value) => {
      if (!value) return '-';
      const dt = new Date(String(value).replace(' ', 'T'));
      return Number.isNaN(dt.getTime()) ? String(value) : dt.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    };

    tbody.innerHTML = state.historico.map((item) => {
      const itemId = String(item.id || '');
      const selected = state.selectedHistoricoIds.has(itemId);
      const dateValue = item.data_execucao || item.data_hora || item.created_at;
      return `
      <tr>
        <td class="align-middle text-center">
          <input type="checkbox" class="form-check-input historico-select-checkbox" data-id="${escapeHtml(itemId)}" ${selected ? 'checked' : ''}>
        </td>
        <td>${escapeHtml(item.numero_os || '-')}</td>
        <td><small>${escapeHtml(formatDateOnly(dateValue))}</small></td>
        <td><small>${escapeHtml(formatTimeOnly(dateValue))}</small></td>
        <td>
          <strong>${escapeHtml(item.tipo_equipamento_nome || '-')}</strong>
          ${item.camera_nome ? `<br><small class="text-muted">${escapeHtml(item.camera_nome)}</small>` : ''}
        </td>
        <td>${escapeHtml(item.ip || '-')}</td>
        <td>${escapeHtml(item.modelo_nome || '-')}</td>
        <td>${escapeHtml(item.local_servico || '-')}</td>
        <td>${escapeHtml(item.endereco_servico || '-')}</td>
        <td>${escapeHtml(item.procedimento_nome || '-')}</td>
        <td>${escapeHtml(item.status_nome || '-')}</td>
        <td>${escapeHtml(item.tecnico || '-')}</td>
        <td class="td-min-320">${escapeHtml(item.descricao || '-')}</td>
        <td>${escapeHtml(item.pecas_previstas || '-')}</td>
        <td>${escapeHtml(item.usuario_nome || 'Sistema')}</td>
      </tr>
    `;
    }).join('');

    tbody.querySelectorAll('.historico-select-checkbox').forEach((checkbox) => {
      checkbox.addEventListener('change', () => {
        const id = String(checkbox.getAttribute('data-id') || '');
        if (!id) return;
        const item = state.historico.find((row) => String(row.id || '') === id);
        if (checkbox.checked) {
          state.selectedHistoricoIds.add(id);
          if (item) state.selectedHistoricoRows[id] = item;
        } else {
          state.selectedHistoricoIds.delete(id);
          delete state.selectedHistoricoRows[id];
        }
        updateSelectedCount();
        updateSelectAllCheckbox();
      });
    });

    updateSelectedCount();
    updateSelectAllCheckbox();
  }

  function renderPendingOrders(orders) {
    const tbody = document.getElementById('ordensCadastradasBody');
    if (!tbody) return;
    state.pendingOrders = Array.isArray(orders) ? orders : [];

    if (state.pendingOrders.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Nenhuma ordem de serviço cadastrada.</td></tr>';
      return;
    }

    tbody.innerHTML = state.pendingOrders.map((item) => {
      const itemId = String(item.id || '');
      return `
      <tr>
        <td>${escapeHtml(item.numero_os || '-')}</td>
        <td>
          ${escapeHtml(item.tipo_equipamento_nome || '-')}
          ${item.camera_nome ? `<br><small class="text-muted">${escapeHtml(item.camera_nome)}</small>` : ''}
        </td>
        <td>${escapeHtml(item.ip || '-')}</td>
        <td>${escapeHtml(toBrDate(item.data_hora || item.created_at))}</td>
        <td>${escapeHtml(item.problemas || '-')}</td>
        <td>
          <button type="button" class="btn btn-sm btn-outline-primary btn-start-os" data-id="${escapeHtml(itemId)}">
            <i class="fas fa-play me-1"></i>Iniciar execução
          </button>
        </td>
      </tr>
    `;
    }).join('');

    tbody.querySelectorAll('.btn-start-os').forEach((button) => {
      button.addEventListener('click', async () => {
        const id = String(button.getAttribute('data-id') || '');
        if (!id) return;
        await iniciarExecucaoOs(id);
      });
    });
  }

  function renderExecutingOrders(orders) {
    const tbody = document.getElementById('ordensExecutandoBody');
    if (!tbody) return;
    state.executingOrders = Array.isArray(orders) ? orders : [];

    if (state.executingOrders.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Nenhuma ordem de serviço em execução.</td></tr>';
      return;
    }

    tbody.innerHTML = state.executingOrders.map((item) => {
      const itemId = String(item.id || '');
      return `
      <tr>
        <td>${escapeHtml(item.numero_os || '-')}</td>
        <td>
          ${escapeHtml(item.tipo_equipamento_nome || '-')}
          ${item.camera_nome ? `<br><small class="text-muted">${escapeHtml(item.camera_nome)}</small>` : ''}
        </td>
        <td>${escapeHtml(item.ip || '-')}</td>
        <td>${escapeHtml(toBrDate(item.data_hora || item.created_at))}</td>
        <td>${escapeHtml(item.problemas || '-')}</td>
        <td>
          <button type="button" class="btn btn-sm btn-outline-success btn-finalizar-os" data-id="${escapeHtml(itemId)}">
            <i class="fas fa-flag-checkered me-1"></i>Finalizar
          </button>
        </td>
      </tr>
    `;
    }).join('');

    tbody.querySelectorAll('.btn-finalizar-os').forEach((button) => {
      button.addEventListener('click', async () => {
        const id = String(button.getAttribute('data-id') || '');
        if (!id) return;
        await selectOrdemServico(id);
      });
    });
  }

  async function selectOrdemServico(osId) {
    const ordem = state.executingOrders.find((item) => String(item.id || '') === osId);
    if (!ordem) return;
    await ensureMaintenanceListsLoaded();
    state.currentOsId = osId;
    state.currentOsProblemas = ordem.problemas || '';
    state.currentOsNumeroOs = ordem.numero_os || '';
    state.cameraSelecionada = String(ordem.equipamento_id || '');

    const equipamentoSelect = document.getElementById('equipamento_id');
    const equipamentoOsSelect = document.getElementById('equipamento_id_os');
    const cameraLabel = ordem.camera_nome || `Câmera ${state.cameraSelecionada}`;
    ensureSelectOption(equipamentoSelect, state.cameraSelecionada, cameraLabel);
    ensureSelectOption(equipamentoOsSelect, state.cameraSelecionada, cameraLabel);

    document.getElementById('os_id').value = state.currentOsId;
    const cameraIpEl = document.getElementById('camera_ip');
    if (cameraIpEl) {
      cameraIpEl.value = ordem.ip || '';
    }
    document.getElementById('numero_os').value = state.currentOsNumeroOs;
    document.getElementById('local_servico').value = ordem.local_servico || '';
    document.getElementById('endereco_servico').value = ordem.endereco_servico || '';
    document.getElementById('ordemNumeroOs').textContent = state.currentOsNumeroOs || '-';
    document.getElementById('ordemProblemas').textContent = state.currentOsProblemas;
    document.getElementById('ordemSelecionadaContainer').classList.remove('d-none');
    document.getElementById('btnLimparSelecaoOs').classList.remove('d-none');
    document.getElementById('data_hora').value = toDateTimeLocalNow();
    const procedimentoSelect = document.getElementById('procedimento_id');
    const procedimentoToSet = String(ordem.procedimento_id || state.defaultProcedimentoId || '');
    if (procedimentoSelect && procedimentoToSet) {
      procedimentoSelect.value = procedimentoToSet;
      procedimentoSelect.classList.add('auto-filled-highlight');
      window.setTimeout(() => {
        procedimentoSelect.classList.remove('auto-filled-highlight');
      }, 1400);
    }
    document.querySelector('#formManutencaoCamera button[type="submit"]').textContent = 'Finalizar Ordem de Serviço';
  }

  async function iniciarExecucaoOs(osId) {
    const ordem = state.pendingOrders.find((item) => String(item.id || '') === osId);
    if (!ordem) return;

    clearMessage();
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || window.CSRF_TOKEN || '';

    const payload = {
      action: 'start_os',
      os_id: String(ordem.id || '').trim(),
      equipamento_id: String(ordem.equipamento_id || '').trim()
    };

    try {
      const response = await fetch(`${getApiBase()}api_manutencao_cameras`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken,
          Accept: 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
      });
      const result = await response.json();
      if (!response.ok || !result.success) {
        throw new Error(result.error || 'Falha ao iniciar execução da OS.');
      }

      window.showToast(result.message || 'Ordem de serviço em execução.', 'success');
      state.cameraSelecionada = '';
      await fetchDados();
    } catch (error) {
      console.error(error);
      window.showToast(error.message || 'Erro ao iniciar execução da OS.', 'danger');
    }
  }

  function clearOrdemSelecao() {
    state.currentOsId = null;
    state.currentOsProblemas = '';
    state.currentOsNumeroOs = '';
    state.cameraSelecionada = '';
    document.getElementById('os_id').value = '';
    document.getElementById('equipamento_id').value = '';
    const cameraIpEl = document.getElementById('camera_ip');
    if (cameraIpEl) {
      cameraIpEl.value = '';
    }
    document.getElementById('numero_os').value = '';
    document.getElementById('ordemNumeroOs').textContent = '';
    document.getElementById('ordemProblemas').textContent = '';
    document.getElementById('ordemSelecionadaContainer').classList.add('d-none');
    document.getElementById('btnLimparSelecaoOs').classList.add('d-none');
    document.getElementById('formManutencaoCamera').reset();
    document.getElementById('data_hora').value = toDateTimeLocalNow();
    document.querySelector('#formManutencaoCamera button[type="submit"]').textContent = 'Registrar Manutenção';
    document.getElementById('btnSalvarManutencao').disabled = false;
    var btnFinalizar = document.getElementById('btnFinalizarManutencao');
    if (btnFinalizar) btnFinalizar.classList.add('d-none');
    var secaoAnexos = document.getElementById('secaoAnexosManutencao');
    if (secaoAnexos) { secaoAnexos.classList.add('d-none'); secaoAnexos.classList.remove('anexos-section-visible'); }
    clearMessage();
  }

  function renderPaginacao(pagination) {
    const container = document.getElementById('historicoPaginacao');
    if (!container) return;

    const totalPages = Number(pagination?.total_pages || 1);
    const current = Number(pagination?.page || 1);

    if (totalPages <= 1) {
      container.innerHTML = '';
      return;
    }

    const itens = [];
    itens.push(`<li class="page-item ${current <= 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${current - 1}">Anterior</a></li>`);
    for (let i = 1; i <= totalPages; i += 1) {
      if (i === 1 || i === totalPages || Math.abs(i - current) <= 2) {
        itens.push(`<li class="page-item ${i === current ? 'active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`);
      }
    }
    itens.push(`<li class="page-item ${current >= totalPages ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${current + 1}">Próxima</a></li>`);
    container.innerHTML = itens.join('');

    container.querySelectorAll('a.page-link[data-page]').forEach((el) => {
      el.addEventListener('click', async (event) => {
        event.preventDefault();
        const page = Number(el.getAttribute('data-page'));
        if (Number.isNaN(page) || page < 1 || page > totalPages) return;
        state.historicoPage = page;
        await fetchDados();
      });
    });
  }

  function renderSortIndicators() {
    document.querySelectorAll('[data-sort]').forEach((btn) => {
      const key = btn.getAttribute('data-sort');
      const active = key === state.historicoSortBy;
      btn.classList.toggle('is-active', active);
    });

    document.querySelectorAll('[data-sort-indicator]').forEach((el) => {
      const key = el.getAttribute('data-sort-indicator');
      if (key === state.historicoSortBy) {
        el.textContent = state.historicoSortDir === 'asc' ? '↑' : '↓';
      } else {
        el.textContent = '↕';
      }
    });
  }

  async function fetchDados() {
    const params = new URLSearchParams();
    params.set('page_num', String(state.historicoPage));
    params.set('per_page', String(state.historicoPerPage));
    if (state.cameraSelecionada) {
      params.set('equipamento_id', String(state.cameraSelecionada));
    }
    if (state.historicoBusca) {
      params.set('busca', state.historicoBusca);
    }
    if (state.dataInicial) {
      params.set('data_inicial', state.dataInicial);
    }
    if (state.dataFinal) {
      params.set('data_final', state.dataFinal);
    }
    params.set('include_lists', state.listsLoaded ? '0' : '1');
    params.set('sort_by', state.historicoSortBy);
    params.set('sort_dir', state.historicoSortDir);
    params.set('_t', String(Date.now()));

    const response = await fetch(`${getApiBase()}api_manutencao_cameras&${params.toString()}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
      cache: 'no-store'
    });

    const payload = await response.json();
    if (!response.ok || !payload.success) {
      throw new Error(payload.error || 'Falha ao carregar dados de manutenção.');
    }
    const data = payload.data || {};
    console.log('fetchDados payload:', { success: payload.success, historicoCount: data.historico?.length, historicoIds: data.historico?.map(h => h.id), pendingIds: data.pending_orders?.map(p => p.id) });
    if (Array.isArray(data.cameras)) {
      renderCameras(data.cameras);
    }
    if (Array.isArray(data.procedimento_options)) {
      renderSelectOptions('procedimento_id', data.procedimento_options, 'Selecione...');
    }
    if (Array.isArray(data.status_options)) {
      renderSelectOptions('status_id', data.status_options, 'Manter status atual');
    }
    state.defaultProcedimentoId = String(data?.defaults?.procedimento_id || state.defaultProcedimentoId || '');
    if (Array.isArray(data.cameras) || Array.isArray(data.procedimento_options) || Array.isArray(data.status_options)) {
      state.listsLoaded = true;
    }

    const selectedCamera = document.getElementById('equipamento_id')?.value || '';
    const selectedProcedimento = document.getElementById('procedimento_id')?.value || '';
    const selectedStatus = document.getElementById('status_id')?.value || '';
    const cameraToSet = state.cameraSelecionada || selectedCamera;
    if (cameraToSet) {
      document.getElementById('equipamento_id').value = cameraToSet;
      document.getElementById('equipamento_id_os').value = cameraToSet;
    }
    if (selectedProcedimento) {
      document.getElementById('procedimento_id').value = selectedProcedimento;
    }
    if (selectedStatus) {
      document.getElementById('status_id').value = selectedStatus;
    }

    renderPendingOrders(data.pending_orders || []);
    renderExecutingOrders(data.executing_orders || []);
    renderHistorico(data.historico || [], data.pagination || {});
    renderPaginacao(data.pagination || {});
    renderSortIndicators();
    setupHistoricoDualScroll();
  }

  async function fetchHistoricoCompleto() {
    const allRows = [];
    const perPage = 100;
    let page = 1;
    let totalPages = 1;

    do {
      const params = new URLSearchParams();
      params.set('page_num', String(page));
      params.set('per_page', String(perPage));
      if (state.cameraSelecionada) {
        params.set('equipamento_id', String(state.cameraSelecionada));
      }
      if (state.historicoBusca) {
        params.set('busca', state.historicoBusca);
      }
      if (state.dataInicial) {
        params.set('data_inicial', state.dataInicial);
      }
      if (state.dataFinal) {
        params.set('data_final', state.dataFinal);
      }
      params.set('include_lists', '0');
      params.set('sort_by', state.historicoSortBy);
      params.set('sort_dir', state.historicoSortDir);
      params.set('_t', String(Date.now()));

      const response = await fetch(`${getApiBase()}api_manutencao_cameras&${params.toString()}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        cache: 'no-store'
      });
      const payload = await response.json();
      if (!response.ok || !payload.success) {
        throw new Error(payload.error || 'Falha ao carregar histórico completo.');
      }
      const data = payload.data || {};
      const rows = Array.isArray(data.historico) ? data.historico : [];
      allRows.push(...rows);
      const pagination = data.pagination || {};
      totalPages = Number(pagination.total_pages || 1);
      page += 1;

    } while (page <= totalPages);

    return allRows;
  }

  function getSelectedOrAllHistoricoRows() {
    const selectedRows = Object.values(state.selectedHistoricoRows || {});
    if (selectedRows.length > 0) {
      return selectedRows;
    }
    return null;
  }

  async function exportarCsvHistorico() {
    let rows = getSelectedOrAllHistoricoRows();
    if (!rows) {
      rows = await fetchHistoricoCompleto();
    }
    if (rows.length === 0) {
      showMessage('warning', 'Não há dados no histórico para exportar.');
      return;
    }

    let csv = 'Data/Hora;OS;Tipo;Câmera;IP;Série;Modelo;Local Serviço;Endereço Serviço;Procedimento;Status;Técnico;Descrição;Peças;Usuário\n';
    rows.forEach((item) => {
      csv += [
        csvEscape(toBrDate(item.data_execucao || item.data_hora || item.created_at)),
        csvEscape(item.numero_os || ''),
        csvEscape(item.tipo_equipamento_nome || ''),
        csvEscape(item.camera_nome || ''),
        csvEscape(item.ip || ''),
        csvEscape(item.numero_serie || ''),
        csvEscape(item.modelo_nome || ''),
        csvEscape(item.local_servico || ''),
        csvEscape(item.endereco_servico || ''),
        csvEscape(item.procedimento_nome || ''),
        csvEscape(item.status_nome || ''),
        csvEscape(item.tecnico || ''),
        csvEscape(item.descricao || ''),
        csvEscape(item.pecas_previstas || ''),
        csvEscape(item.usuario_nome || 'Sistema')
      ].join(';') + '\n';
    });

    const blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `manutencao_cameras_${toIsoDateStamp()}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
    showMessage('success', 'Exportação CSV iniciada.');
  }

  async function exportarPdfHistorico() {
    let rows = getSelectedOrAllHistoricoRows();
    if (!rows) {
      rows = await fetchHistoricoCompleto();
    }
    if (rows.length === 0) {
      showMessage('warning', 'Não há dados no histórico para exportar.');
      return;
    }

    const htmlRows = rows.map((item) => `
      <tr>
        <td>${escapeHtml(toBrDate(item.data_execucao || item.data_hora || item.created_at))}</td>
        <td>${escapeHtml(item.numero_os || '-')}</td>
        <td>${escapeHtml(item.tipo_equipamento_nome || '-')}</td>
        <td>${escapeHtml(item.camera_nome || '-')}</td>
        <td>${escapeHtml(item.ip || '-')}</td>
        <td>${escapeHtml(item.modelo_nome || '-')}</td>
        <td>${escapeHtml(item.local_servico || '-')}</td>
        <td>${escapeHtml(item.endereco_servico || '-')}</td>
        <td>${escapeHtml(item.procedimento_nome || '-')}</td>
        <td>${escapeHtml(item.status_nome || '-')}</td>
        <td>${escapeHtml(item.tecnico || '-')}</td>
        <td>${escapeHtml(item.descricao || '-')}</td>
        <td>${escapeHtml(item.pecas_previstas || '-')}</td>
        <td>${escapeHtml(item.usuario_nome || 'Sistema')}</td>
      </tr>
    `).join('');

    const reportHtml = `<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Relatório Manutenção Câmeras</title>
  <style>
    body { font-family: Arial, sans-serif; font-size: 12px; margin: 24px; }
    h1 { margin: 0 0 8px; font-size: 18px; }
    .meta { margin-bottom: 16px; color: #444; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #ccc; padding: 6px; vertical-align: top; }
    th { background: #f1f1f1; text-align: left; }
    @media print { body { margin: 8mm; } }
  </style>
</head>
<body>
  <h1>Relatório de Manutenção de Câmeras</h1>
  <div class="meta">Gerado em: ${escapeHtml(new Date().toLocaleString('pt-BR'))} | Total: ${rows.length}</div>
  <table>
    <thead>
      <tr>
        <th>Data/Hora</th><th>OS</th><th>Tipo</th><th>Câmera</th><th>IP</th>
        <th>Modelo</th><th>Local</th><th>Endereço</th><th>Procedimento</th>
        <th>Status</th><th>Técnico</th><th>Descrição</th><th>Peças</th><th>Usuário</th>
      </tr>
    </thead>
    <tbody>${htmlRows}</tbody>
  </table>
  <script>window.onload=function(){window.print();};</script>
</body>
</html>`;

    const printWindow = window.open('', '_blank');
    if (!printWindow) {
      showMessage('danger', 'Não foi possível abrir a janela de impressão. Verifique o bloqueador de pop-up.');
      return;
    }
    printWindow.document.open();
    printWindow.document.write(reportHtml);
    printWindow.document.close();
  }

  async function salvarOrdemServico(event) {
    event.preventDefault();
    clearMessage();

    const form = document.getElementById('formCriarOs');
    const button = document.getElementById('btnSalvarOs');
    const formData = new FormData(form);

    const payload = {
      action: 'create_os',
      equipamento_id: String(formData.get('equipamento_id_os') || '').trim(),
      data_hora: String(formData.get('data_hora_os') || '').trim(),
      numero_os: String(formData.get('numero_os_os') || '').trim(),
      local_servico: String(formData.get('local_servico_os') || '').trim(),
      endereco_servico: String(formData.get('endereco_servico_os') || '').trim(),
      problemas: String(formData.get('problemas') || '').trim()
    };

    if (!payload.equipamento_id) {
      showMessage('warning', 'Selecione uma câmera para criar a OS.');
      return;
    }
    if (payload.problemas.length < 5) {
      showMessage('warning', 'Descreva os problemas na ordem de serviço.');
      return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || window.CSRF_TOKEN || '';

    try {
      button.disabled = true;
      button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Criando...';

      const response = await fetch(`${getApiBase()}api_manutencao_cameras`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken,
          Accept: 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
      });
      const result = await response.json();
      if (!response.ok || !result.success) {
        throw new Error(result.error || 'Falha ao criar ordem de serviço.');
      }

      showMessage('success', result.message || 'Ordem de serviço cadastrada com sucesso.');
      form.reset();
      document.getElementById('data_hora_os').value = toDateTimeLocalNow();
      state.historicoPage = 1;
      state.cameraSelecionada = '';
      await fetchDados();
    } catch (error) {
      console.error(error);
      showMessage('danger', error.message || 'Erro ao cadastrar ordem de serviço.');
    } finally {
      button.disabled = false;
      button.innerHTML = '<i class="fas fa-save me-1"></i>Criar Ordem de Serviço';
    }
  }

  async function salvarManutencao(event) {
    event.preventDefault();
    clearMessage();

    if (!state.currentOsId) {
      showMessage('warning', 'Selecione uma ordem de serviço em execução para finalizar.');
      return;
    }

    const form = document.getElementById('formManutencaoCamera');
    const button = document.getElementById('btnSalvarManutencao');
    const formData = new FormData(form);

    const payload = {
      action: 'finalize_os',
      os_id: String(formData.get('os_id') || '').trim(),
      equipamento_id: String(formData.get('equipamento_id') || '').trim(),
      data_hora: String(formData.get('data_hora') || '').trim(),
      procedimento_id: String(formData.get('procedimento_id') || '').trim(),
      status_id: String(formData.get('status_id') || '').trim(),
      tecnico: String(formData.get('tecnico') || '').trim(),
      numero_os: String(formData.get('numero_os') || '').trim(),
      local_servico: String(formData.get('local_servico') || '').trim(),
      endereco_servico: String(formData.get('endereco_servico') || '').trim(),
      pecas_previstas: String(formData.get('pecas_previstas') || '').trim(),
      descricao: String(formData.get('descricao') || '').trim()
    };

    if (!payload.equipamento_id) {
      showMessage('warning', 'Selecione uma câmera.');
      return;
    }
    if (payload.descricao.length < 5) {
      showMessage('warning', 'Descrição precisa ter ao menos 5 caracteres.');
      return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || window.CSRF_TOKEN || '';

    try {
      button.disabled = true;
      button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Salvando...';

      const response = await fetch(`${getApiBase()}api_manutencao_cameras`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken,
          Accept: 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
      });
      const result = await response.json();
      if (!response.ok || !result.success) {
        throw new Error(result.error || 'Falha ao registrar manutenção.');
      }

      showMessage('success', result.message || 'Manutenção registrada com sucesso.');
      var manutencaoId = document.getElementById('os_id').value;
      if (manutencaoId) {
        var secao = document.getElementById('secaoAnexosManutencao');
        var listEl = secao && secao.querySelector('.anexos-list');
        if (listEl) {
          listEl.setAttribute('data-manutencao-camera-id', manutencaoId);
          secao.classList.remove('d-none');
          secao.classList.add('anexos-section-visible');
          if (typeof window.initAnexoSection === 'function') window.initAnexoSection(secao);
          secao.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      }
      document.getElementById('btnFinalizarManutencao').classList.remove('d-none');
      state.historicoPage = 1;
      state.cameraSelecionada = '';
      await fetchDados();
    } catch (error) {
      console.error(error);
      showMessage('danger', error.message || 'Erro ao registrar manutenção.');
    } finally {
      button.disabled = false;
      button.innerHTML = '<i class="fas fa-save me-1"></i>Registrar Manutenção';
    }
  }

  function bindEvents() {
    document.getElementById('formCriarOs')?.addEventListener('submit', salvarOrdemServico);
    document.getElementById('formManutencaoCamera').addEventListener('submit', salvarManutencao);

    const btnBuscarCameraLista = document.getElementById('btnBuscarCameraLista');
    const filtroCamera = document.getElementById('filtroCamera');
    if (btnBuscarCameraLista && filtroCamera) {
      let debounceTimer;

      const searchCameras = async (term) => {
        clearMessage();
        state.cameraBusca = term;
        await loadCameraOptions(term);

        const select = document.getElementById('equipamento_id_os');
        if (!select) return;
        const options = Array.from(select.options).filter(o => o.value !== '');
        if (options.length === 0 && term) {
          showMessage('warning', `Nenhuma câmera encontrada para "${term}".`);
        } else if (options.length === 1) {
          select.value = options[0].value;
          select.dispatchEvent(new Event('change'));
        }
      };

      btnBuscarCameraLista.addEventListener('click', async () => {
        const term = String(filtroCamera.value || '').trim();
        clearTimeout(debounceTimer);
        await searchCameras(term);
      });
      filtroCamera.addEventListener('keydown', async (event) => {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        clearTimeout(debounceTimer);
        await searchCameras(String(filtroCamera.value || '').trim());
      });
      filtroCamera.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const term = String(filtroCamera.value || '').trim();
        debounceTimer = setTimeout(() => searchCameras(term), 300);
      });
    }


    document.getElementById('btnLimparFormulario').addEventListener('click', () => {
      document.getElementById('formManutencaoCamera').reset();
      document.getElementById('data_hora').value = toDateTimeLocalNow();
      clearMessage();
      clearOrdemSelecao();
    });

    document.getElementById('btnFinalizarManutencao')?.addEventListener('click', function () {
      clearOrdemSelecao();
    });

    document.getElementById('btnLimparOs')?.addEventListener('click', () => {
      const form = document.getElementById('formCriarOs');
      if (!form) return;
      form.reset();
      document.getElementById('data_hora_os').value = toDateTimeLocalNow();
      clearMessage();
    });

    const equipamentoOsSelect = document.getElementById('equipamento_id_os');
    const localServicoOsEl = document.getElementById('local_servico_os');
    const enderecoServicoOsEl = document.getElementById('endereco_servico_os');
    equipamentoOsSelect?.addEventListener('change', () => {
      const cameraId = String(equipamentoOsSelect.value || '');
      const camera = state.cameraOptions.find((item) => String(item.id || '') === cameraId);
      if (camera?.em_manutencao) {
        const nome = camera.local_nome || camera.descricao || `ID ${camera.id}`;
        const modal = new bootstrap.Modal(document.getElementById('modalManutencaoAtiva'));
        document.getElementById('modalManutencaoNome').textContent = nome;
        document.getElementById('modalManutencaoOk').onclick = () => {
          modal.hide();
          equipamentoOsSelect.value = '';
          if (localServicoOsEl) localServicoOsEl.value = '';
          if (enderecoServicoOsEl) enderecoServicoOsEl.value = '';
        };
        modal.show();
        return;
      }
      if (localServicoOsEl) {
        localServicoOsEl.value = camera?.local_nome || '';
      }
      if (enderecoServicoOsEl) {
        const parts = [
          camera?.tipo_logradouro || '',
          camera?.local_logradouro || '',
          camera?.local_numero || ''
        ].filter(Boolean);
        enderecoServicoOsEl.value = parts.join(' ');
      }
    });

    document.getElementById('btnLimparSelecaoOs')?.addEventListener('click', () => {
      clearOrdemSelecao();
    });

    document.getElementById('btnFiltrarHistorico').addEventListener('click', async () => {
      const cameraId = document.getElementById('equipamento_id').value;
      if (!cameraId) {
        showMessage('warning', 'Selecione uma câmera para filtrar o histórico.');
        return;
      }
      state.cameraSelecionada = cameraId;
      state.historicoPage = 1;
      await fetchDados();
    });

    document.getElementById('btnLimparFiltroHistorico').addEventListener('click', async () => {
      state.cameraSelecionada = '';
      state.historicoBusca = '';
      state.dataInicial = '';
      state.dataFinal = '';
      document.getElementById('filtroBuscaHistorico').value = '';
      document.getElementById('dataInicial').value = '';
      document.getElementById('dataFinal').value = '';
      state.historicoPage = 1;
      await fetchDados();
    });

    function readHistoricoFilters() {
      state.historicoBusca = String(document.getElementById('filtroBuscaHistorico').value || '').trim();
      state.dataInicial = document.getElementById('dataInicial').value || '';
      state.dataFinal = document.getElementById('dataFinal').value || '';
    }

    document.getElementById('btnBuscarHistorico').addEventListener('click', async () => {
      readHistoricoFilters();
      state.historicoPage = 1;
      await fetchDados();
    });

    document.getElementById('btnLimparBuscaHistorico').addEventListener('click', async () => {
      state.historicoBusca = '';
      state.dataInicial = '';
      state.dataFinal = '';
      document.getElementById('filtroBuscaHistorico').value = '';
      document.getElementById('dataInicial').value = '';
      document.getElementById('dataFinal').value = '';
      state.historicoPage = 1;
      await fetchDados();
    });

    document.getElementById('filtroBuscaHistorico').addEventListener('keydown', async (event) => {
      if (event.key !== 'Enter') return;
      event.preventDefault();
      readHistoricoFilters();
      state.historicoPage = 1;
      await fetchDados();
    });

    document.getElementById('dataInicial').addEventListener('change', async () => {
      readHistoricoFilters();
      state.historicoPage = 1;
      await fetchDados();
    });

    document.getElementById('dataFinal').addEventListener('change', async () => {
      readHistoricoFilters();
      state.historicoPage = 1;
      await fetchDados();
    });

    document.getElementById('historicoPerPage').addEventListener('change', async (event) => {
      const value = Number(event.target.value || 20);
      state.historicoPerPage = Number.isNaN(value) ? 20 : value;
      state.historicoPage = 1;
      await fetchDados();
    });

    document.getElementById('btnExportarCsvHistorico')?.addEventListener('click', async () => {
      try {
        await exportarCsvHistorico();
      } catch (error) {
        console.error(error);
        showMessage('danger', error.message || 'Falha ao exportar CSV.');
      }
    });

    document.getElementById('btnSelecionarTodosHistorico')?.addEventListener('click', () => {
      state.historico.forEach((item) => {
        const itemId = String(item.id || '');
        if (!itemId) return;
        state.selectedHistoricoIds.add(itemId);
        state.selectedHistoricoRows[itemId] = item;
      });
      renderHistorico(state.historico, state.currentPagination);
    });

    document.getElementById('btnLimparSelecaoHistorico')?.addEventListener('click', () => {
      state.selectedHistoricoIds.clear();
      state.selectedHistoricoRows = {};
      renderHistorico(state.historico, state.currentPagination);
    });

    document.getElementById('selectAllHistorico')?.addEventListener('change', (event) => {
      const checked = event.target.checked;
      document.querySelectorAll('.historico-select-checkbox').forEach((checkbox) => {
        checkbox.checked = checked;
        const id = String(checkbox.getAttribute('data-id') || '');
        const item = state.historico.find((row) => String(row.id || '') === id);
        if (!id) return;
        if (checked) {
          state.selectedHistoricoIds.add(id);
          if (item) state.selectedHistoricoRows[id] = item;
        } else {
          state.selectedHistoricoIds.delete(id);
          delete state.selectedHistoricoRows[id];
        }
      });
      updateSelectedCount();
      updateSelectAllCheckbox();
    });

    document.getElementById('btnExportarRapidoHistorico')?.addEventListener('click', async () => {
      try {
        await exportarPdfHistorico();
      } catch (error) {
        console.error(error);
        showMessage('danger', error.message || 'Falha ao exportar relatório.');
      }
    });

    document.getElementById('btnExportarPdfHistorico')?.addEventListener('click', async () => {
      try {
        await exportarPdfHistorico();
      } catch (error) {
        console.error(error);
        showMessage('danger', error.message || 'Falha ao exportar PDF.');
      }
    });

    document.querySelector('.historico-table-wrap table thead')?.addEventListener('click', async (event) => {
      const button = event.target.closest('[data-sort]');
      if (!button) return;

      const sortKey = button.getAttribute('data-sort');
      if (!sortKey) return;

      if (state.historicoSortBy === sortKey) {
        state.historicoSortDir = state.historicoSortDir === 'asc' ? 'desc' : 'asc';
      } else {
        state.historicoSortBy = sortKey;
        state.historicoSortDir = 'asc';
      }

      state.historicoPage = 1;
      await fetchDados();
    });
  }

  async function init() {
    if (!document.body.classList.contains('page-manutencao_cameras')) return;
    document.getElementById('data_hora').value = toDateTimeLocalNow();
    document.getElementById('data_hora_os') && (document.getElementById('data_hora_os').value = toDateTimeLocalNow());
    bindEvents();

    try {
      await fetchDados();
    } catch (error) {
      console.error(error);
      showMessage('danger', error.message || 'Erro ao carregar dados da manutenção.');
      document.getElementById('historicoManutencaoBody').innerHTML =
        '<tr><td colspan="15" class="text-center text-danger py-4">Falha ao carregar histórico.</td></tr>';
    }
  }

  document.addEventListener('DOMContentLoaded', init);
})();
