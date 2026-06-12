$(document).ready(function () {
  // Elementos DOM
  const formFiltros = $('#formFiltros');
  const btnLimpar = $('#btnLimpar');
  const btnExportar = $('#btnExportar');
  const corpoTabela = $('#corpoTabela');
  const totalRegistros = $('#totalRegistros');
  const loadingOverlay = $('#loadingOverlay');
  const paginacao = $('#paginacao');

  // Configuracoes
  const apiUrl = `${window.APP_API_BASE}api_alarmes`;
  const itensPorPagina = 50;
  let paginaAtual = 1;
  let totalItens = 0;
  let dadosAtuais = [];

  function mostrarLoading(mostrar) {
    if (mostrar) {
      loadingOverlay.removeClass('is-hidden');
    } else {
      loadingOverlay.addClass('is-hidden');
    }
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function formatarData(dataString) {
    if (!dataString) return '';
    const data = new Date(dataString);
    return data.toLocaleDateString('pt-BR');
  }

  function getClasseStatus(statusNome) {
    const statusLower = String(statusNome || '').toLowerCase();
    if (statusLower.includes('ativo') || statusLower.includes('operante')) {
      return 'status-ativo';
    } else if (statusLower.includes('inativo') || statusLower.includes('desligado')) {
      return 'status-inativo';
    } else if (statusLower.includes('manutencao') || statusLower.includes('manutenção')) {
      return 'status-manutencao';
    }
    return '';
  }

  function construirQueryString(filtros, pagina = 1) {
    const params = new URLSearchParams();

    if (filtros.status) params.append('status', filtros.status);
    if (filtros.regiao) params.append('regiao', filtros.regiao);
    if (filtros.conta) params.append('conta', filtros.conta);

    params.append('page_num', String(pagina));
    params.append('per_page', String(itensPorPagina));

    return params.toString();
  }

  async function buscarDados(pagina = 1) {
    try {
      paginaAtual = pagina;
      mostrarLoading(true);

      const filtros = {
        conta: $('#conta').val(),
        status: $('#status').val(),
        regiao: $('#regiao').val()
      };

      const queryString = construirQueryString(filtros, pagina);
      const url = queryString ? `${apiUrl}&${queryString}` : apiUrl;

      const response = await fetchWithTimeout(url, {
        credentials: 'same-origin',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

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
        totalItens = Number(data.pagination?.total || 0);

        atualizarTotalRegistros(totalItens);
        renderizarTabela(dadosAtuais);
        renderizarPaginacao();

      } else {
        throw new Error(data.error || 'Erro desconhecido');
      }
    } catch (error) {
      console.error('Erro ao buscar dados:', error);
      mostrarErro('Erro ao carregar dados: ' + error.message);
    } finally {
      mostrarLoading(false);
    }
  }

  function atualizarTotalRegistros(total) {
    let texto = '';

    if (total === 0) {
      texto = '<i class="fas fa-exclamation-circle"></i> Nenhum alarme encontrado com os filtros aplicados';
    } else if (total === 1) {
      texto = `<i class="fas fa-check-circle"></i> ${total} alarme encontrado`;
    } else {
      texto = `<i class="fas fa-check-circle"></i> ${total} alarmes encontrados`;
    }

    totalRegistros.html(texto);
  }

  function renderizarTabela(dados) {
    if (dados.length === 0) {
      corpoTabela.html(`
                <tr>
                    <td colspan="25" class="no-results">
                        <i class="fas fa-search fa-3x"></i>
                        <h5 class="mt-3">Nenhum alarme encontrado</h5>
                        <p class="text-muted">Tente ajustar os filtros de busca</p>
                    </td>
                </tr>
            `);
      return;
    }

    let html = '';

    dados.forEach(alarme => {
      const classeStatus = getClasseStatus(alarme.status);
      const cameraGm = alarme.camera_gm == 1 ? 'Sim' : (alarme.camera_gm == 0 ? 'Nao' : '');

      html += `
                <tr>
                    <td>${escapeHtml(alarme.id)}</td>
                    <td>${escapeHtml(alarme.conta || '')}</td>
                    <td class="${escapeHtml(classeStatus)}">${escapeHtml(alarme.status || '')}</td>
                    <td>${escapeHtml(alarme.regiao || '')}</td>
                    <td>${escapeHtml(alarme.local || '')}</td>
                    <td>${escapeHtml(alarme.endereco || '')}</td>
                    <td>${escapeHtml(alarme.numero || '')}</td>
                    <td><code>${escapeHtml(alarme.ip || '')}</code></td>
                    <td>${escapeHtml(alarme.pgm1 || '')}</td>
                    <td>${escapeHtml(alarme.pgm2 || '')}</td>
                    <td><small class="text-muted">${escapeHtml(alarme.mac || '')}</small></td>
                    <td>${escapeHtml(alarme.modelo_central || '')}</td>
                    <td>${escapeHtml(alarme.quant_repetidor || '')}</td>
                    <td>${escapeHtml(alarme.qtde_sensores || '')}</td>
                    <td><code>${escapeHtml(alarme.ip_dvr || '')}</code></td>
                    <td>${escapeHtml(alarme.cameras_dvr || '')}</td>
                    <td>${escapeHtml(cameraGm)}</td>
                    <td>${escapeHtml(alarme.quant_camera_gm || '')}</td>
                    <td>${escapeHtml(formatarData(alarme.integracao))}</td>
                    <td>${escapeHtml(alarme.documentacao || '')}</td>
                    <td>${escapeHtml(alarme.monitorada || '')}</td>
                    <td>${escapeHtml(alarme.numero_sei || '')}</td>
                    <td>${escapeHtml(formatarData(alarme.data_atualizacao))}</td>
                    <td>${escapeHtml(formatarData(alarme.created_at))}</td>
                    <td>
                        ${alarme.observacao ?
                            `<span title="${escapeHtml(alarme.observacao)}">${escapeHtml(alarme.observacao.substring(0, 30))}${alarme.observacao.length > 30 ? '...' : ''}</span>` :
                            '<span class="text-muted">-</span>'
                        }
                    </td>
                </tr>
            `;
    });

    corpoTabela.html(html);

    setupTopScrollbar();
    setupColumnSelector();
    aplicarVisibilidadeSalva();
  }

  function renderizarPaginacao() {
    if (totalItens <= itensPorPagina) {
      paginacao.addClass('is-hidden');
      return;
    }

    const totalPaginas = Math.ceil(totalItens / itensPorPagina);
    let html = '';

    html += `
            <li class="page-item ${paginaAtual === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-pagina="${paginaAtual - 1}">Anterior</a>
            </li>
        `;

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

    html += `
            <li class="page-item ${paginaAtual === totalPaginas ? 'disabled' : ''}">
                <a class="page-link" href="#" data-pagina="${paginaAtual + 1}">Proximo</a>
            </li>
        `;

    paginacao.find('ul').html(html);
    paginacao.removeClass('is-hidden');
  }

  function mostrarErro(mensagem) {
    corpoTabela.html(`
            <tr>
                <td colspan="25" class="text-center text-danger py-5">
                    <i class="fas fa-exclamation-triangle fa-2x mb-3"></i><br>
                    <h5>Erro ao carregar dados</h5>
                    <p class="text-muted">${mensagem}</p>
                    <button class="btn btn-sm btn-primary mt-2" onclick="location.reload()">
                        <i class="fas fa-sync-alt"></i> Tentar novamente
                    </button>
                </td>
            </tr>
        `);

    totalRegistros.html('<i class="fas fa-exclamation-triangle"></i> Erro na busca');
  }

  function limparFiltros() {
    formFiltros[0].reset();
    buscarDados();
  }

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

      const blob = new Blob(["\uFEFF" + csv], {
        type: 'text/csv;charset=utf-8;'
      });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');

      const dataAtual = new Date().toISOString().split('T')[0];
      link.href = url;
      link.setAttribute('download', `relatorio_alarmes_${dataAtual}.csv`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);

      mostrarMensagemSucesso('Exportacao iniciada! Verifique seus downloads.');
    } catch (error) {
      console.error('Erro ao exportar:', error);
      showToast('Erro ao exportar dados: ' + error.message, 'danger');
    }
  }

  function mostrarMensagemSucesso(mensagem) {
    window.showToast(mensagem, 'success');
  }

  // Função vazia intencional (placeholder)
  function carregarFiltrosSalvos() {
    }
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
      const saved = JSON.parse(localStorage.getItem('colunasVisiveisAlarmes') || '{}');
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
    localStorage.setItem('colunasVisiveisAlarmes', JSON.stringify(state));
  }

  function aplicarVisibilidadeSalva() {
    const saved = JSON.parse(localStorage.getItem('colunasVisiveisAlarmes') || '{}');
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
    'Conta': d => (d.conta || ''),
    'Status': d => (d.status || ''),
    'Regiao': d => (d.regiao || ''),
    'Local': d => (d.local || ''),
    'Endereco': d => (d.endereco || ''),
    'Numero': d => (d.numero || ''),
    'IP': d => (d.ip || ''),
    'PGM1': d => (d.pgm1 || ''),
    'PGM2': d => (d.pgm2 || ''),
    'MAC': d => (d.mac || ''),
    'Modelo Central': d => (d.modelo_central || ''),
    'Qtd Repetidor': d => (d.quant_repetidor || ''),
    'Qtd Sensores': d => (d.qtde_sensores || ''),
    'IP DVR': d => (d.ip_dvr || ''),
    'Cameras DVR': d => (d.cameras_dvr || ''),
    'Camera GM': d => (d.camera_gm == 1 ? 'Sim' : (d.camera_gm == 0 ? 'Nao' : '')),
    'Qtd Camera GM': d => (d.quant_camera_gm || ''),
    'Integracao': d => formatarData(d.integracao),
    'Documentacao': d => (d.documentacao || ''),
    'Monitorada': d => (d.monitorada || ''),
    'Numero SEI': d => (d.numero_sei || ''),
    'Data Atualizacao': d => formatarData(d.data_atualizacao),
    'Criado em': d => formatarData(d.created_at),
    'Observacao': d => (d.observacao || '').replace(/"/g, '""')
  };

  formFiltros.on('submit', function (e) {
    e.preventDefault();
    buscarDados();
  });

  btnLimpar.on('click', function () {
    limparFiltros();
  });

  btnExportar.on('click', function () {
    exportarDados();
  });

  paginacao.on('click', '.page-link', function (e) {
    e.preventDefault();
    const pagina = $(this).data('pagina');
    if (pagina && !$(this).parent().hasClass('disabled')) {
      buscarDados(pagina);
    }
  });

  $('#status, #regiao').on('change', function () {
    buscarDados();
  });

  function inicializar() {
    setupColumnSelector();
    setupTopScrollbar();


  }

  inicializar();


});

