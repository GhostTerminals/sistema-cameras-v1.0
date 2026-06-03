// Versão Otimizada do JavaScript para Manutenção de Alarmes
// Versão 2.0 com módulos centralizados e performance melhorada

(function () {
  // Importar módulos centralizados (já são instâncias globais)
  const { debounce, throttle } = window;
  const errorHandler = window.ErrorHandler;
  errorHandler.options.enableAlerts = false; // v2 usa showMessage próprio
  const loadingManager = window.LoadingManager;

  const state = {
    alarmeSelecionada: '',
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
    alarmeBusca: '',
    alarmeOptions: [],
    selectedHistoricoIds: new Set(),
    selectedHistoricoRows: {},
    currentPagination: {},
    defaultProcedimentoId: '',
  };

  // Funções utilitárias
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

  function showMessage(type, text, autoHide = true) {
    window.showToast(text, type);
  }

  const debouncedFetchDados = debounce(async () => {
    try {
      await fetchDados();
    } catch (error) {
      errorHandler.handle(error, 'Debounced fetch failed', 'warning');
      showMessage('danger', 'Erro ao carregar dados: ' + error.message);
    }
  }, 300);

  // Funções principais
  async function loadAlarmeOptions(term = '', targetSelectId = null) {
    const selectIds = targetSelectId ? [targetSelectId] : ['equipamento_id', 'equipamento_id_os'];
    const firstSelect = document.getElementById(selectIds[0]);
    if (!firstSelect) return;

    // Mostrar loading no primeiro select
    const loading = loadingManager.showElement(firstSelect, 'Carregando alarmes...');

    selectIds.forEach((id) => {
      const sel = document.getElementById(id);
      if (sel) sel.innerHTML = '<option value="">Carregando alarmes...</option>';
    });

    try {
      const qs = new URLSearchParams();
      qs.set('page_num', '1');
      qs.set('per_page', '50');
      if (term) {
        qs.set('busca', term);
      }

      const response = await fetch(`${getApiBase()}api_alarmes&${qs.toString()}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        cache: 'no-store'
      });
      
      const payload = await response.json();
      if (!response.ok || !payload.success) {
        throw new Error(payload.error || 'Erro ao carregar alarmes');
      }

      const list = Array.isArray(payload.data) ? payload.data : [];
      renderAlarmes(list, selectIds);

      if (state.alarmeSelecionada && !targetSelectId) {
        selectIds.forEach((id) => {
          const sel = document.getElementById(id);
          if (sel) ensureSelectOption(sel, String(state.alarmeSelecionada), `Alarme ${state.alarmeSelecionada}`);
        });
      }
    } catch (e) {
      console.error('Erro ao carregar alarmes:', e);
      selectIds.forEach((id) => {
        const sel = document.getElementById(id);
        if (sel) sel.innerHTML = '<option value="">Erro ao carregar alarmes</option>';
      });
      errorHandler.handle(e, 'Failed to load alarm options', 'error');
    } finally {
      loading?.hide();
    }
  }

  function renderAlarmes(alarmes, targetSelectIds = null) {
    state.alarmeOptions = Array.isArray(alarmes) ? alarmes : [];
    const selectIds = targetSelectIds || ['equipamento_id', 'equipamento_id_os'];
    const options = (alarmes || []).map((alarme) => {
      const alarmeId = alarme.equipamento_id || alarme.id;
      const localName = alarme.local_nome || alarme.descricao || alarme.local || `ALARME ${alarmeId}`;
      const label = alarme.ip ? `${localName} - ${alarme.ip}` : localName;
      return `<option value="${escapeHtml(alarmeId)}">${escapeHtml(label)}</option>`;
    }).join('');

    selectIds.forEach((selectId) => {
      const select = document.getElementById(selectId);
      if (!select) return;
      select.innerHTML = '<option value="">Selecione um alarme...</option>' + options;
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

  // Funções de interface
  function showLoading(message = 'Carregando...') {
    const loadingEl = document.getElementById('globalLoadingAlert');
    if (loadingEl) {
      const messageEl = loadingEl.querySelector('#loadingMessage');
      messageEl.textContent = message;
      loadingEl.classList.remove('d-none');
    }
  }

  function hideLoading() {
    const loadingEl = document.getElementById('globalLoadingAlert');
    if (loadingEl) {
      loadingEl.classList.add('d-none');
    }
  }

  // Event handlers otimizados
  function bindEvents() {
    // Formulários
    document.getElementById('formCriarOs')?.addEventListener('submit', criarOs);
    document.getElementById('formManutencaoAlarme')?.addEventListener('submit', salvarManutencao);

    // Busca de alarme para nova OS (popula apenas o select da OS)
    document.getElementById('filtroAlarmeOs')?.addEventListener('input', debounce(async (e) => {
      try {
        await loadAlarmeOptions(e.target.value, 'equipamento_id_os');
      } catch (error) {
        showMessage('danger', 'Erro ao buscar alarmes: ' + error.message);
      }
    }, 300));

    document.getElementById('btnBuscarAlarmeLista')?.addEventListener('click', () => {
      const term = document.getElementById('filtroAlarmeOs')?.value || '';
      loadAlarmeOptions(term, 'equipamento_id_os');
    });

    // Filtros de data do histórico
    document.getElementById('dataInicialHist')?.addEventListener('change', debouncedFetchDados);
    document.getElementById('dataFinalHist')?.addEventListener('change', debouncedFetchDados);
    document.getElementById('historicoPerPageHist')?.addEventListener('change', debouncedFetchDados);

    // Busca no histórico
    document.getElementById('filtroBuscaHistorico')?.addEventListener('input', (e) => {
      state.historicoBusca = e.target.value;
      debouncedFetchDados();
    });
    document.getElementById('btnFiltrarHistorico')?.addEventListener('click', () => {
      const id = document.getElementById('equipamento_id')?.value;
      if (!id) return showMessage('warning', 'Selecione um alarme para filtrar.');
      state.alarmeSelecionada = id;
      state.historicoPage = 1;
      fetchDados();
    });
    document.getElementById('btnLimparFiltroHistorico')?.addEventListener('click', () => {
      state.alarmeSelecionada = '';
      state.historicoBusca = '';
      state.dataInicial = '';
      state.dataFinal = '';
      const bEl = document.getElementById('filtroBuscaHistorico');
      if (bEl) bEl.value = '';
      document.getElementById('dataInicialHist').value = '';
      document.getElementById('dataFinalHist').value = '';
      state.historicoPage = 1;
      fetchDados();
    });

    // Select do histórico
    document.getElementById('equipamento_id')?.addEventListener('change', (event) => {
      state.alarmeSelecionada = String(event.target.value || '');
      state.historicoPage = 1;
      debouncedFetchDados();
    });

    // Select de nova OS — apenas popula campos locais, NÃO recarrega página
    document.getElementById('equipamento_id_os')?.addEventListener('change', (event) => {
      const id = String(event.target.value || '');
      const alarme = state.alarmeOptions.find((a) => String(a.equipamento_id || a.id) === id);
      document.getElementById('local_servico_os').value = alarme?.local || '';
      document.getElementById('endereco_servico_os').value = alarme?.endereco || '';
    });

    // Botões de limpeza
    document.getElementById('btnLimparOs')?.addEventListener('click', () => {
      document.getElementById('formCriarOs').reset();
      document.getElementById('local_servico_os').value = '';
      document.getElementById('endereco_servico_os').value = '';
      document.getElementById('data_hora_os').value = toDateTimeLocalNow();
      showMessage('info', 'Formulário limpo');
    });

    document.getElementById('btnFinalizarManutencao')?.addEventListener('click', () => {
      clearOrdemSelecao();
      showMessage('success', 'Ordem de serviço finalizada.');
    });

    document.getElementById('btnLimparFormulario')?.addEventListener('click', () => {
      clearOrdemSelecao();
      showMessage('info', 'Seleção limpa');
    });

    // Exportação e seleção no histórico
    document.getElementById('btnExportarCsvHistorico')?.addEventListener('click', async () => {
      try {
        await exportarCsvHistorico();
      } catch (error) {
        showMessage('danger', error.message || 'Falha ao exportar CSV.');
      }
    });

    document.getElementById('btnExportarPdfHistorico')?.addEventListener('click', async () => {
      try {
        await exportarPdfHistorico();
      } catch (error) {
        showMessage('danger', error.message || 'Falha ao exportar PDF.');
      }
    });

    document.getElementById('btnExportarRapidoHistorico')?.addEventListener('click', async () => {
      try {
        await exportarPdfHistorico();
      } catch (error) {
        showMessage('danger', error.message || 'Falha ao gerar relatório.');
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
  }

  // Funções principais de operação
  async function criarOs(event) {
    event.preventDefault();
    clearMessage();

    const form = event.currentTarget;
    const btn = document.getElementById('btnSalvarOs');
    const formData = new FormData(form);
    const payload = {
      action: 'create_os',
      alarme_id: String(formData.get('equipamento_id_os') || '').trim(),
      problemas: String(formData.get('problemas') || '').trim(),
      data_hora: String(formData.get('data_hora_os') || '').trim(),
      numero_os: String(formData.get('numero_os_os') || '').trim(),
      local_servico: String(formData.get('local_servico_os') || '').trim(),
      endereco_servico: String(formData.get('endereco_servico_os') || '').trim()
    };

    if (!payload.alarme_id) return showMessage('warning', 'Selecione um alarme.');
    if (payload.problemas.length < 5) return showMessage('warning', 'Problemas deve ter ao menos 5 caracteres.');

    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || window.CSRF_TOKEN || '';

    try {
      btn.disabled = true;
      showLoading('Criando ordem de serviço...');

      const response = await fetch(`${getApiBase()}api_manutencao_alarmes`, {
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
      if (!response.ok || !result.success) throw new Error(result.error || 'Erro ao criar ordem de serviço.');

      showMessage('success', 'Ordem de serviço cadastrada com sucesso.');
      document.getElementById('formCriarOs').reset();
      document.getElementById('local_servico_os').value = '';
      document.getElementById('endereco_servico_os').value = '';
      document.getElementById('data_hora_os').value = toDateTimeLocalNow();
      await fetchDados();
    } catch (error) {
      errorHandler.handle(error, 'Failed to create OS', 'error');
      showMessage('danger', error.message || 'Erro ao criar ordem de serviço.');
    } finally {
      btn.disabled = false;
      hideLoading();
    }
  }

  async function fetchDados() {
    showLoading('Carregando dados...');

    try {
      // Ler filtros do DOM
      state.historicoBusca = document.getElementById('filtroBuscaHistorico')?.value || '';
      state.dataInicial = document.getElementById('dataInicialHist')?.value || '';
      state.dataFinal = document.getElementById('dataFinalHist')?.value || '';

      const params = new URLSearchParams();
      params.set('page_num', String(state.historicoPage));
      params.set('per_page', String(state.historicoPerPage));
      if (state.alarmeSelecionada) {
        params.set('equipamento_id', String(state.alarmeSelecionada));
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

      const response = await fetch(`${getApiBase()}api_manutencao_alarmes&${params.toString()}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        cache: 'no-store'
      });

      const payload = await response.json();
      if (!response.ok || !payload.success) {
        throw new Error(payload.error || 'Falha ao carregar dados de manutenção.');
      }

      const data = payload.data || {};
      if (Array.isArray(data.alarme) && data.alarme.length > 0) {
        renderAlarmes(data.alarme);
      }
      if (Array.isArray(data.procedimento_options) && data.procedimento_options.length > 0) {
        renderSelectOptions('procedimento_id', data.procedimento_options, 'Selecione...');
      }
      if (Array.isArray(data.status_options) && data.status_options.length > 0) {
        renderSelectOptions('status_id', data.status_options, 'Manter status atual');
      }
      state.defaultProcedimentoId = String(data?.defaults?.procedimento_id || state.defaultProcedimentoId || '');
      
      if (Array.isArray(data.alarme) || Array.isArray(data.procedimento_options) || Array.isArray(data.status_options)) {
        state.listsLoaded = true;
      }

      renderPendingOrders(data.pending_orders || []);
      renderExecutingOrders(data.executing_orders || []);
      renderHistorico(data.historico || [], data.pagination || {});
      renderPaginacao(data.pagination || {});
      renderSortIndicators();
      setupHistoricoDualScroll();

    } catch (error) {
      errorHandler.handle(error, 'Failed to fetch data', 'error');
      throw error;
    } finally {
      hideLoading();
    }
  }

  // Funções de renderização (mantendo as versões otimizadas)
  function renderSelectOptions(selectId, data, includeEmptyLabel) {
    const select = document.getElementById(selectId);
    if (!select) return;
    const baseOption = includeEmptyLabel ? `<option value="">${escapeHtml(includeEmptyLabel)}</option>` : '';
    const options = (data || []).map((item) => {
      return `<option value="${escapeHtml(item.id)}">${escapeHtml(item.nome)}</option>`;
    }).join('');
    select.innerHTML = baseOption + options;
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
        <td>${escapeHtml(item.alarme_nome || `ID ${item.alarme_id || '-'}`)}</td>
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
        <td>${escapeHtml(item.alarme_nome || `ID ${item.alarme_id || '-'}`)}</td>
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
        try {
          await selectOrdemServico(id);
        } catch (error) {
          showMessage('danger', 'Erro ao selecionar OS: ' + (error.message || 'erro desconhecido'));
        }
      });
    });
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
    resumo.textContent = `Total: ${total} | Página ${page} de ${totalPages}`;

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
        <td>${escapeHtml(item.conta || item.alarme_nome || `ID ${item.alarme_id || '-'}`)}</td>
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

  // Funções auxiliares
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

  // Funções restantes (simplificadas para manter o escopo)
  async function iniciarExecucaoOs(osId) {
    // Implementação similar à original, com tratamento de erros
    const ordem = state.pendingOrders.find((item) => String(item.id || '') === osId);
    if (!ordem) return;

    clearMessage();
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || window.CSRF_TOKEN || '';

    const payload = {
      action: 'start_os',
      os_id: String(ordem.id || '').trim(),
      alarme_id: String(ordem.alarme_id || '').trim()
    };

    try {
      showLoading('Iniciando execução...');
      const response = await fetch(`${getApiBase()}api_manutencao_alarmes`, {
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
      if (!response.ok || !result.success) throw new Error(result.error || 'Erro ao iniciar execução da OS.');

      showMessage('success', 'Ordem de serviço em execução.');
      await fetchDados();
    } catch (error) {
      errorHandler.handle(error, 'Failed to start OS execution', 'error');
      showMessage('danger', error.message || 'Erro ao iniciar execução da OS.');
    } finally {
      hideLoading();
    }
  }

  async function salvarManutencao(event) {
    // Implementação similar à original, com tratamento de erros
    event.preventDefault();
    clearMessage();

    if (!state.currentOsId) return showMessage('warning', 'Selecione uma ordem de serviço em execução para finalizar.');

    const form = event.currentTarget;
    const btn = document.getElementById('btnSalvarManutencao');
    const formData = new FormData(form);
    const payload = {
      action: 'finalize_os',
      os_id: String(formData.get('os_id') || '').trim(),
      alarme_id: String(formData.get('equipamento_id') || '').trim(),
      data_hora: String(formData.get('data_hora') || '').trim(),
      procedimento_id: String(formData.get('procedimento_id') || '').trim(),
      status_id: String(formData.get('status_id') || '').trim(),
      tecnico: String(formData.get('tecnico') || '').trim(),
      numero_os: String(formData.get('numero_os') || '').trim(),
      local_servico: String(formData.get('local_servico') || '').trim(),
      endereco_servico: String(formData.get('endereco_servico') || '').trim(),
      descricao: String(formData.get('descricao') || '').trim(),
      pecas_previstas: String(formData.get('pecas_previstas') || '').trim()
    };

    if (!payload.alarme_id) return showMessage('warning', 'Selecione um alarme.');
    if (payload.descricao.length < 5) return showMessage('warning', 'Descrição deve ter ao menos 5 caracteres.');

    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || window.CSRF_TOKEN || '';

    try {
      btn.disabled = true;
      showLoading('Finalizando ordem de serviço...');

      const response = await fetch(`${getApiBase()}api_manutencao_alarmes`, {
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
      if (!response.ok || !result.success) throw new Error(result.error || 'Erro ao registrar manutenção.');

      showMessage('success', 'Manutenção registrada com sucesso.');
      document.getElementById('btnSalvarManutencao').disabled = true;
      document.getElementById('btnFinalizarManutencao')?.classList.remove('d-none');
      state.alarmeSelecionada = '';
      state.historicoPage = 1;
      console.warn('[FINALIZE] page:', state.historicoPage);
      await fetchDados();
      console.warn('[FINALIZE] fetchDados concluido');
    } catch (error) {
      errorHandler.handle(error, 'Failed to save maintenance', 'error');
      showMessage('danger', error.message || 'Erro ao salvar manutenção.');
    } finally {
      btn.disabled = false;
      hideLoading();
    }
  }

  function clearOrdemSelecao() {
    state.currentOsId = null;
    state.currentOsProblemas = '';
    state.currentOsNumeroOs = '';
    state.alarmeSelecionada = '';
    document.getElementById('os_id').value = '';
    document.getElementById('equipamento_id').value = '';
    document.getElementById('numero_os').value = '';
    document.getElementById('ordemNumeroOs').textContent = '';
    document.getElementById('ordemProblemas').textContent = '';
    document.getElementById('ordemSelecionadaContainer').classList.add('d-none');
    document.getElementById('formManutencaoAlarme').reset();
    document.getElementById('data_hora').value = toDateTimeLocalNow();
    document.querySelector('#formManutencaoAlarme button[type="submit"]').textContent = 'Registrar Manutenção';
    document.getElementById('btnSalvarManutencao').disabled = false;
    document.getElementById('btnFinalizarManutencao')?.classList.add('d-none');
    clearMessage();
  }

  function clearMessage() {
    // no-op: toasts auto-hide
  }

  // Funções de renderização restantes (simplificadas)
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

  let _historicoScrollStyle = null;

  function _setHistoricoScrollWidth(width) {
    if (!_historicoScrollStyle) {
      _historicoScrollStyle = document.createElement('style');
      if (window._CSP_NONCE) _historicoScrollStyle.setAttribute('nonce', window._CSP_NONCE);
      document.head.appendChild(_historicoScrollStyle);
    }
    _historicoScrollStyle.textContent = `#historicoTopScrollContent { width: ${width}px !important; }`;
  }

  function syncHistoricoTopScroll() {
    const top = document.getElementById('historicoTopScroll');
    const topContent = document.getElementById('historicoTopScrollContent');
    const bottom = document.querySelector('.historico-table-wrap');
    const table = bottom?.querySelector('table');
    if (!top || !topContent || !bottom || !table) return;

    const totalWidth = table.scrollWidth;
    const visibleWidth = bottom.clientWidth;
    _setHistoricoScrollWidth(totalWidth);
    top.classList.toggle('d-none', totalWidth <= visibleWidth);
    top.scrollLeft = bottom.scrollLeft;
  }

  // Funções restantes (simplificadas para manter o escopo)
  async function selectOrdemServico(osId) {
    const ordem = state.executingOrders.find((item) => String(item.id || '') === osId);
    if (!ordem) return;
    await ensureMaintenanceListsLoaded();
    state.currentOsId = osId;
    state.currentOsProblemas = ordem.problemas || '';
    state.currentOsNumeroOs = ordem.numero_os || '';
    state.alarmeSelecionada = String(ordem.alarme_id || '');

    const equipamentoSelect = document.getElementById('equipamento_id');
    const equipamentoOsSelect = document.getElementById('equipamento_id_os');
    const alarmeLabel = ordem.alarme_nome || `Alarme ${state.alarmeSelecionada}`;
    ensureSelectOption(equipamentoSelect, state.alarmeSelecionada, alarmeLabel);
    ensureSelectOption(equipamentoOsSelect, state.alarmeSelecionada, alarmeLabel);

    // Popular dados do alarme nos campos de serviço
    const alarme = state.alarmeOptions.find((a) => String(a.equipamento_id || a.id) === state.alarmeSelecionada);
    document.getElementById('os_id').value = state.currentOsId;
    document.getElementById('numero_os').value = state.currentOsNumeroOs;
    document.getElementById('local_servico').value = ordem.local_servico || alarme?.local || '';
    document.getElementById('endereco_servico').value = ordem.endereco_servico || alarme?.endereco || '';
    document.getElementById('ordemNumeroOs').textContent = state.currentOsNumeroOs || '-';
    document.getElementById('ordemProblemas').textContent = state.currentOsProblemas;
    document.getElementById('ordemSelecionadaContainer').classList.remove('d-none');
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
    clearMessage();
    document.querySelector('#formManutencaoAlarme button[type="submit"]').textContent = 'Finalizar Ordem de Serviço';
  }

  async function ensureMaintenanceListsLoaded(force = false) {
    if (!force && hasSelectableOptions('procedimento_id') && hasSelectableOptions('status_id')) {
      return;
    }

    const params = new URLSearchParams();
    params.set('page_num', '1');
    params.set('per_page', '10');
    params.set('include_lists', '1');

    const response = await fetch(`${getApiBase()}api_manutencao_alarmes&${params.toString()}`, {
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

  function hasSelectableOptions(selectId) {
    const select = document.getElementById(selectId);
    if (!select) return false;
    return select.options.length > 1;
  }

  // Funções de exportação
  function csvEscape(value) {
    const str = String(value ?? '');
    if (str.includes(';') || str.includes('"') || str.includes('\n')) {
      return `"${str.replace(/"/g, '""')}"`;
    }
    return str;
  }

  function toIsoDateStamp() {
    return new Date().toISOString().slice(0, 10);
  }

  function getSelectedOrAllHistoricoRows() {
    if (state.selectedHistoricoIds.size > 0) {
      return state.historico.filter((item) => state.selectedHistoricoIds.has(String(item.id || '')));
    }
    return null;
  }

  async function fetchHistoricoCompleto() {
    const params = new URLSearchParams();
    params.set('page_num', '1');
    params.set('per_page', '99999');
    if (state.alarmeSelecionada) params.set('equipamento_id', String(state.alarmeSelecionada));
    if (state.historicoBusca) params.set('busca', state.historicoBusca);
    if (state.dataInicial) params.set('data_inicial', state.dataInicial);
    if (state.dataFinal) params.set('data_final', state.dataFinal);
    params.set('sort_by', state.historicoSortBy);
    params.set('sort_dir', state.historicoSortDir);
    params.set('_t', String(Date.now()));

    const response = await fetch(`${getApiBase()}api_manutencao_alarmes&${params.toString()}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
      cache: 'no-store'
    });
    const payload = await response.json();
    if (!response.ok || !payload.success) {
      throw new Error(payload.error || 'Falha ao carregar histórico completo.');
    }
    const data = payload.data || {};
    return Array.isArray(data.historico) ? data.historico : [];
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

    let csv = 'Data/Hora;OS;Conta;IP;Local;Endereço;Procedimento;Status;Técnico;Descrição;Peças;Usuário\n';
    rows.forEach((item) => {
      csv += [
        csvEscape(toBrDate(item.data_execucao || item.data_hora || item.created_at)),
        csvEscape(item.numero_os || ''),
        csvEscape(item.conta || item.alarme_nome || ''),
        csvEscape(item.ip || ''),
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
    link.setAttribute('download', `manutencao_alarmes_${toIsoDateStamp()}.csv`);
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
        <td>${escapeHtml(item.conta || item.alarme_nome || '-')}</td>
        <td>${escapeHtml(item.ip || '-')}</td>
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
  <title>Relatório Manutenção Alarmes</title>
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
  <h1>Relatório de Manutenção de Alarmes</h1>
  <div class="meta">Gerado em: ${escapeHtml(new Date().toLocaleString('pt-BR'))} | Total: ${rows.length}</div>
  <table>
    <thead>
      <tr>
        <th>Data/Hora</th><th>OS</th><th>Alarme</th><th>IP</th>
        <th>Local</th><th>Endereço</th><th>Procedimento</th>
        <th>Status</th><th>Técnico</th><th>Descrição</th><th>Peças</th><th>Usuário</th>
      </tr>
    </thead>
    <tbody>${htmlRows}</tbody>
  </table>
</body>
</html>`;

    const win = window.open('', '_blank');
    if (!win) {
      showMessage('warning', 'Permita pop-ups para gerar o relatório.');
      return;
    }
    win.document.write(reportHtml);
    win.document.close();
    win.focus();
    win.print();
    showMessage('success', 'Relatório gerado com sucesso.');
  }

  // Inicialização
  async function init() {
    if (!document.body.classList.contains('page-manutencao_alarmes')) return;
    
    // Configurar valores iniciais
    document.getElementById('data_hora_os').value = toDateTimeLocalNow();
    document.getElementById('data_hora').value = toDateTimeLocalNow();
    
    // Bind eventos
    bindEvents();
    
    // Carregar dados iniciais
    try {
      showLoading('Inicializando sistema...');
      await fetchDados();
    } catch (error) {
      errorHandler.handle(error, 'Failed to initialize system', 'error');
      showMessage('danger', 'Falha ao carregar dados de manutenção.');
      document.getElementById('historicoManutencaoBody').innerHTML = '<tr><td colspan="15" class="text-center text-danger py-4">Falha ao carregar histórico.</td></tr>';
    } finally {
      hideLoading();
    }
  }

  // Iniciar quando DOM estiver pronto
  document.addEventListener('DOMContentLoaded', init);
})();