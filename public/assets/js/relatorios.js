$(document).ready(function () {
  // Elementos DOM
  const formFiltros = $('#formFiltros');
  const btnLimpar = $('#btnLimpar');
  const btnExportar = $('#btnExportar');
  const corpoTabela = $('#corpoTabela');
  const totalRegistros = $('#totalRegistros');
  const loadingOverlay = $('#loadingOverlay');
  const tabelaResultados = $('#tabelaResultados');
  const paginacao = $('#paginacao');

  // Configurações
  const apiUrl = `${window.APP_API_BASE}api_relatorios_cameras`;
  const itensPorPagina = 50;
  let paginaAtual = 1;
  let totalItens = 0;
  let dadosAtuais = [];

  // Função para mostrar/ocultar loading
  function mostrarLoading(mostrar) {
    if (mostrar) {
      loadingOverlay.removeClass('is-hidden');
    } else {
      loadingOverlay.addClass('is-hidden');
    }
  }

  // Função para escapar HTML
  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  // Função para formatar data
  function formatarData(dataString) {
    if (!dataString) return '';
    const data = new Date(dataString);
    return data.toLocaleDateString('pt-BR');
  }

  // Função para determinar classe CSS do status
  function getClasseStatus(statusNome) {
    const statusLower = statusNome.toLowerCase();
    if (statusLower.includes('ativo') || statusLower.includes('funcionando')) {
      return 'status-ativo';
    } else if (statusLower.includes('inativo') || statusLower.includes('desligado')) {
      return 'status-inativo';
    } else if (statusLower.includes('manutenção') || statusLower.includes('manutencao')) {
      return 'status-manutencao';
    }
    return '';
  }

  // Função para construir query string com filtros
  function construirQueryString(filtros, pagina = 1) {
    const params = new URLSearchParams();

  if (filtros.pesquisa) params.append('pesquisa', filtros.pesquisa);
  if (filtros.status) params.append('status', filtros.status);
  if (filtros.regiao) params.append('regiao', filtros.regiao);
  if (filtros.local) params.append('local', filtros.local);

  params.append('page_num', pagina);
  params.append('per_page', itensPorPagina);

  return params.toString();
  }

  // Função para buscar dados
  async function buscarDados(pagina = 1) {
    try {
      paginaAtual = pagina;
      mostrarLoading(true);

      // Coletar filtros do formulário
      const filtros = {
        pesquisa: $('#pesquisa').val(),
        status: $('#status').val(),
        regiao: $('#regiao').val(),
        local: $('#local').val()
      };

    const queryString = construirQueryString(filtros, pagina);
    const timestamp = Date.now();
    const url = queryString ? `${apiUrl}&${queryString}&_t=${timestamp}` : `${apiUrl}?_t=${timestamp}`;

    const response = await fetch(url, { cache: 'no-store' });

      if (!response.ok) {
        throw new Error(`Erro HTTP: ${response.status}`);
      }

      const raw = await response.text();
      
      let data = null;
      try {
        data = JSON.parse(raw);
      } catch (jsonError) {
        const isHtmlResponse = /^\s*</.test(raw);
        if (isHtmlResponse) {
          // Sessao expirada/redirecionamento para login retornando HTML.
          if (raw.includes('page=login') || raw.includes('login-title') || raw.includes('<!DOCTYPE html')) {
            window.location.href = `${window.BASE_URL}index.php?page=login`;
            return;
          }
          throw new Error('Servidor retornou HTML em vez de JSON. Verifique sessao/permissoes.');
        }
        throw new Error('Resposta invalida da API (JSON malformado).');
      }

    if (data.success) {
      dadosAtuais = Array.isArray(data.data) ? data.data : [];
      totalItens = data.pagination && data.pagination.total != null ? data.pagination.total : dadosAtuais.length;

      atualizarTotalRegistros(totalItens);
      renderizarTabela(dadosAtuais);
      renderizarPaginacao();

      $('#pesquisa').val('');
    } else {
      throw new Error(data.message || data.error || 'Erro desconhecido');
    }

    } catch (error) {
      console.error('Erro ao buscar dados:', error);
      mostrarErro('Erro ao carregar dados: ' + error.message);
    } finally {
      mostrarLoading(false);
    }
  }

  // Função para atualizar o total de registros
  function atualizarTotalRegistros(total) {
    let texto = '';

    if (total === 0) {
      texto = '<i class="fas fa-exclamation-circle"></i> Nenhuma câmera encontrada com os filtros aplicados';
    } else if (total === 1) {
      texto = `<i class="fas fa-check-circle"></i> ${total} câmera encontrada`;
    } else {
      texto = `<i class="fas fa-check-circle"></i> ${total} câmeras encontradas`;
    }

    totalRegistros.html(texto);
  }

  // Função para renderizar tabela
  function renderizarTabela(dados) {
    if (dados.length === 0) {
      corpoTabela.html(`
                <tr>
                    <td colspan="28" class="no-results">
                        <i class="fas fa-search fa-3x"></i>
                        <h5 class="mt-3">Nenhuma câmera encontrada</h5>
                        <p class="text-muted">Tente ajustar os filtros de busca</p>
                    </td>
                </tr>
            `);
      return;
    }

    let html = '';

    dados.forEach(camera => {
      const classeStatus = getClasseStatus(camera.status_nome);

      html += `
                <tr>
                    <td>${escapeHtml(camera.id)}</td>
                    <td>${escapeHtml(camera.descricao || '')}</td>
                    <td><code>${escapeHtml(camera.ip || '')}</code></td>
                    <td>${escapeHtml(camera.porta || '')}</td>
                    <td>${escapeHtml(camera.patrimonio || '')}</td>
                    <td><small class="text-muted">${escapeHtml(camera.serie_mac || '')}</small></td>
                    <td>${escapeHtml(camera.marca_nome || '')}</td>
                    <td>${escapeHtml(camera.modelo_nome || '')}</td>
                    <td>${escapeHtml(camera.tipo_camera_nome || '')}</td>
                    <td>${escapeHtml(camera.procedimento_nome || '')}</td>
                    <td>${escapeHtml(camera.regiao_nome || '')}</td>
                    <td>${escapeHtml(camera.local_nome || '')}</td>
                    <td>${escapeHtml(camera.secretaria_nome || '')}</td>
                    <td class="${escapeHtml(classeStatus)}">${escapeHtml(camera.status_nome || '')}</td>
                    <td>${escapeHtml(camera.alarme_conta || '')}</td>
                    <td>${escapeHtml(camera.transmissao_nome || '')}</td>
                    <td>${escapeHtml(camera.mosaico || '')}</td>
                    <td>${escapeHtml(camera.coordenadas || '')}</td>
                    <td>${escapeHtml(camera.tipo_logradouro || '')}</td>
                    <td>${escapeHtml(camera.local_logradouro || '')}</td>
                    <td>${escapeHtml(camera.local_numero || '')}</td>
                    <td>${escapeHtml(camera.local_bairro || '')}</td>
                    <td>${escapeHtml(camera.local_cidade || '')}</td>
                    <td>${escapeHtml(camera.local_uf || '')}</td>
                    <td>${escapeHtml(camera.local_cep || '')}</td>
                    <td>${escapeHtml(formatarData(camera.data_instalacao))}</td>
                    <td>${escapeHtml(formatarData(camera.created_at))}</td>
                    <td>
                        ${camera.observacao ? 
                            `<span title="${escapeHtml(camera.observacao)}">${escapeHtml(camera.observacao.substring(0, 30))}${camera.observacao.length > 30 ? '...' : ''}</span>` : 
                            '<span class="text-muted">-</span>'
                        }
                    </td>
                </tr>
            `;
    });

    corpoTabela.html(html);

    setupColumnSelector();
    setupTopScrollbar();
    aplicarVisibilidadeSalva();
  }

  // Função para renderizar paginação (opcional)
  function renderizarPaginacao() {
    if (totalItens <= itensPorPagina) {
      paginacao.addClass('is-hidden');
      return;
    }

    const totalPaginas = Math.ceil(totalItens / itensPorPagina);
    let html = '';

    // Botão anterior
    html += `
            <li class="page-item ${paginaAtual === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-pagina="${paginaAtual - 1}">Anterior</a>
            </li>
        `;

    // Números das páginas
    const maxPaginasVisiveis = 5;
    let inicio = Math.max(1, paginaAtual - Math.floor(maxPaginasVisiveis / 2));
    let fim = Math.min(totalPaginas, inicio + maxPaginasVisiveis - 1);

    if (fim - inicio + 1 < maxPaginasVisiveis) {
      inicio = Math.max(1, fim - maxPaginasVisiveis + 1);
    }

    for (let i = inicio; i <= fim; i++) {
      html += `
                <li class="page-item ${i === paginaAtual ? 'active' : ''}">
                    <a class="page-link" href="#" data-pagina="${i}">${i}</a>
                </li>
            `;
    }

    // Botão próximo
    html += `
            <li class="page-item ${paginaAtual === totalPaginas ? 'disabled' : ''}">
                <a class="page-link" href="#" data-pagina="${paginaAtual + 1}">Próximo</a>
            </li>
        `;

    paginacao.find('ul').html(html);
    paginacao.removeClass('is-hidden');
  }

  // Função para mostrar erro
  function mostrarErro(mensagem) {
    corpoTabela.html(`
            <tr>
                <td colspan="28" class="text-center text-danger py-5">
                    <i class="fas fa-exclamation-triangle fa-2x mb-3"></i><br>
                    <h5>Erro ao carregar dados</h5>
                    <p class="text-muted">${mensagem}</p>
                    <button class="btn btn-sm btn-primary mt-2" onclick="location.reload()">
                        <i class="fas fa-sync-alt"></i> Tentar novamente
                    </button>
                </td>
            </tr>
        `);

    totalRegistros.html(`<i class="fas fa-exclamation-triangle"></i> Erro na busca`);
  }

  // Função para limpar filtros
  function limparFiltros() {
    formFiltros[0].reset();
    buscarDados();
  }

  // Função para exportar dados (somente colunas visiveis)
  function exportarDados() {
    if (dadosAtuais.length === 0) {
      showToast('Nao ha dados para exportar!', 'warning');
      return;
    }

    try {
      const headers = document.querySelectorAll('#tabelaResultados thead th');
      const visibleHeaders = [];
      let csv = '';

      headers.forEach(th => {
        if (!th.classList.contains('column-hidden')) {
          const label = th.textContent.trim();
          visibleHeaders.push(label);
          csv += (csv ? ';' : '') + label;
        }
      });
      csv += '\n';

      dadosAtuais.forEach(item => {
        let row = '';
        visibleHeaders.forEach(label => {
          const accessor = CAMPO_POR_HEADER[label];
          let val = accessor ? accessor(item) : '';
          row += (row ? ';' : '') + '"' + (typeof val === 'string' ? val.replace(/"/g, '""') : val) + '"';
        });
        csv += row + '\n';
      });

      // Criar blob e fazer download
      const blob = new Blob(["\uFEFF" + csv], {
        type: 'text/csv;charset=utf-8;'
      });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');

      const dataAtual = new Date().toISOString().split('T')[0];
      link.href = url;
      link.setAttribute('download', `relatorio_cameras_${dataAtual}.csv`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);

      // Mostrar mensagem de sucesso
      mostrarMensagemSucesso('Exportação iniciada! Verifique seus downloads.');

    } catch (error) {
      console.error('Erro ao exportar:', error);
      showToast('Erro ao exportar dados: ' + error.message, 'danger');
    }
  }

  // Função para mostrar mensagem de sucesso
  function mostrarMensagemSucesso(mensagem) {
    const alertDiv = $(`
            <div class="alert alert-success alert-dismissible fade show position-fixed toast-fixed" 
                 >
                <i class="fas fa-check-circle"></i> ${mensagem}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);

    $('body').append(alertDiv);

    setTimeout(() => {
      alertDiv.alert('close');
    }, 3000);
  }

  // Função para carregar filtros salvos
  function carregarFiltrosSalvos() {
    buscarDados();
  }

  // ---- Seletor de colunas ----
  function setupColumnSelector() {
    const menu = document.getElementById('menuColunas');
    const thead = document.querySelector('#tabelaResultados thead tr');
    if (!menu || !thead) return;

    menu.innerHTML = '<li><div class="dropdown-item py-1 px-2 d-flex gap-2 border-bottom mb-1">'
      + '<a href="#" class="small" id="selecionarTodasColunas">Todas</a>'
      + ' <span class="text-muted">|</span> '
      + '<a href="#" class="small" id="limparTodasColunas">Nenhuma</a>'
      + '</div></li>';

    const headers = thead.querySelectorAll('th');
    headers.forEach((th, index) => {
      const label = th.textContent.trim();
      const saved = JSON.parse(localStorage.getItem('colunasVisiveisCameras') || '{}');
      const checked = saved[label] !== false;
      const item = document.createElement('li');
      item.innerHTML = '<div class="dropdown-item py-1 px-2">'
        + '<div class="form-check">'
        + '<input class="form-check-input toggle-coluna" type="checkbox" data-col-index="' + index + '" ' + (checked ? 'checked' : '') + '>'
        + '<label class="form-check-label small">' + label + '</label>'
        + '</div></div>';
      menu.appendChild(item);
    });
  }

  function toggleColuna(index, show) {
    document.querySelectorAll('#tabelaResultados tr').forEach(row => {
      const cell = row.children[index];
      if (cell) cell.classList.toggle('column-hidden', !show);
    });
  }

  function salvarVisibilidade() {
    const headers = document.querySelectorAll('#tabelaResultados thead th');
    const state = {};
    headers.forEach((th, index) => {
      const label = th.textContent.trim();
      state[label] = !th.classList.contains('column-hidden');
    });
    localStorage.setItem('colunasVisiveisCameras', JSON.stringify(state));
  }

  function aplicarVisibilidadeSalva() {
    const saved = JSON.parse(localStorage.getItem('colunasVisiveisCameras') || '{}');
    if (Object.keys(saved).length === 0) return;
    const headers = document.querySelectorAll('#tabelaResultados thead th');
    headers.forEach((th, index) => {
      const label = th.textContent.trim();
      if (saved[label] === false) {
        toggleColuna(index, false);
      }
    });
  }

  $(document).on('change', '.toggle-coluna', function () {
    const index = parseInt($(this).data('col-index'));
    toggleColuna(index, this.checked);
    salvarVisibilidade();
  });

  $(document).on('click', '#selecionarTodasColunas', function (e) {
    e.preventDefault();
    document.querySelectorAll('.toggle-coluna').forEach(cb => {
      cb.checked = true;
      toggleColuna(parseInt(cb.dataset.colIndex), true);
    });
    salvarVisibilidade();
  });

  $(document).on('click', '#limparTodasColunas', function (e) {
    e.preventDefault();
    document.querySelectorAll('.toggle-coluna').forEach(cb => {
      cb.checked = false;
      toggleColuna(parseInt(cb.dataset.colIndex), false);
    });
    salvarVisibilidade();
  });

  // ---- Barra de rolagem superior ----
  function setupTopScrollbar() {
    const container = document.querySelector('.table-responsive');
    if (!container) return;

    let wrapper = document.querySelector('.scrollbar-top-wrapper');
    if (wrapper) wrapper.remove();

    wrapper = document.createElement('div');
    wrapper.className = 'scrollbar-top-wrapper';

    const inner = document.createElement('div');
    inner.className = 'scrollbar-top-inner';
    wrapper.appendChild(inner);

    container.parentNode.insertBefore(wrapper, container);

    function syncWidth() {
      const tableEl = container.querySelector('table');
      if (tableEl) {
        inner.style.width = tableEl.offsetWidth + 'px';
      }
    }

    syncWidth();

    wrapper.addEventListener('scroll', function () {
      container.scrollLeft = this.scrollLeft;
    });

    container.addEventListener('scroll', function () {
      wrapper.scrollLeft = this.scrollLeft;
    });

    const observer = new ResizeObserver(syncWidth);
    const tableEl = container.querySelector('table');
    if (tableEl) observer.observe(tableEl);
  }

  // ---- Export (somente colunas visiveis) ----
  const CAMPO_POR_HEADER = {
    'ID': d => d.id,
    'Descricao': d => (d.descricao || ''),
    'IP': d => (d.ip || ''),
    'Porta': d => (d.porta || ''),
    'Patrimonio': d => (d.patrimonio || ''),
    'Serie/MAC': d => (d.serie_mac || ''),
    'Marca': d => (d.marca_nome || ''),
    'Modelo': d => (d.modelo_nome || ''),
    'Tipo Camera': d => (d.tipo_camera_nome || ''),
    'Procedimento': d => (d.procedimento_nome || ''),
    'Regiao': d => (d.regiao_nome || ''),
    'Local': d => (d.local_nome || ''),
    'Secretaria': d => (d.secretaria_nome || ''),
    'Status': d => (d.status_nome || ''),
    'Conta Alarme': d => (d.alarme_conta || ''),
    'Transmissao': d => (d.transmissao_nome || ''),
    'Mosaico': d => (d.mosaico || ''),
    'Coordenadas': d => (d.coordenadas || ''),
    'Tipo': d => (d.tipo_logradouro || ''),
    'Logradouro': d => (d.local_logradouro || ''),
    'Numero': d => (d.local_numero || ''),
    'Bairro': d => (d.local_bairro || ''),
    'Cidade': d => (d.local_cidade || ''),
    'UF': d => (d.local_uf || ''),
    'CEP': d => (d.local_cep || ''),
    'Data Instalacao': d => formatarData(d.data_instalacao),
    'Criado em': d => formatarData(d.created_at),
    'Observacao': d => (d.observacao || '').replace(/"/g, '""')
  };

  // Event Listeners

  // Submit do formulário
  formFiltros.on('submit', function (e) {
    e.preventDefault();
    buscarDados();
  });

  // Botão limpar
  btnLimpar.on('click', function () {
    limparFiltros();
  });

  // Botão exportar
  btnExportar.on('click', function () {
    exportarDados();
  });

  // Clique na paginação (se implementada)
  paginacao.on('click', '.page-link', function (e) {
    e.preventDefault();
    const pagina = $(this).data('pagina');
    if (pagina && !$(this).parent().hasClass('disabled')) {
      buscarDados(pagina);
    }
  });

  // Busca automática ao mudar status, região ou local
  $('#status, #regiao, #local').on('change', function () {
    buscarDados();
  });

  // Inicialização
  function inicializar() {
    setupColumnSelector();
    setupTopScrollbar();
    
    // Remover chamada que causa race condition
    buscarDados();
  }

  // Iniciar aplicação
  inicializar();

  // Expor funções para debug (opcional)
  window.relatorioCameras = {
    buscarDados,
    limparFiltros,
    exportarDados
  };
});



