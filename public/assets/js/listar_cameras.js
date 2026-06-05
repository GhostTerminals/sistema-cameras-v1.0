(function() {
'use strict';

const listarCamerasConfigEl = document.getElementById('listarCamerasConfig');
const API_URL = listarCamerasConfigEl?.dataset.apiUrl || '';
const CAN_DELETE = listarCamerasConfigEl?.dataset.canDelete === '1';

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function getFiltros() {
    const status = document.getElementById('filtroStatus').value;
    const local = document.getElementById('filtroLocal').value;
    const pesquisa = document.getElementById('filtroPesquisa').value.trim();

    return {
        status,
        local,
        pesquisa
    };
}

function buildQueryParams(page) {
    const filtros = getFiltros();
    const params = new URLSearchParams();
    params.set('page_num', String(page));
    params.set('per_page', String(PER_PAGE));

    if (filtros.status) {
        params.set('status', filtros.status);
    }
    if (filtros.local) {
        params.set('local_id', filtros.local);
    }
    if (filtros.pesquisa) {
        params.set('busca', filtros.pesquisa);
    }

    return params;
}

async function carregarListaCameras(page = 1) {
    try {
        currentPage = page;
        const container = document.getElementById("listaCameras");
        container.innerHTML = `
            <div class="text-center text-muted py-4">
                <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                <p>Carregando câmeras...</p>
            </div>
        `;

        const params = buildQueryParams(page);
        const response = await fetch(`${API_URL}&${params.toString()}`);

        if (!response.ok) {
            throw new Error(`Erro HTTP: ${response.status}`);
        }

        const resultado = await response.json();

        if (resultado.success && Array.isArray(resultado.data)) {
            todasCameras = resultado.data;
            totalRecords = Number(resultado.pagination?.total || 0);
            totalPages = Number(resultado.pagination?.total_pages || 1);
            if (totalPages > 0 && currentPage > totalPages) {
                carregarListaCameras(totalPages);
                return;
            }
            if (todasCameras.length === 0 && totalRecords > 0 && currentPage > 1) {
                carregarListaCameras(currentPage - 1);
                return;
            }
            exibirCameras(todasCameras);
            renderPaginacao();

            showToast(`✅ ${totalRecords} câmeras encontradas`, 'success');
        } else {
            throw new Error(resultado.error || 'Formato de dados inválido');
        }

    } catch (error) {
        console.error('❌ Erro ao carregar câmeras:', error);
        mostrarErro('Erro ao carregar câmeras: ' + error.message);
    }
}

function mostrarDetalhesCamera(cameraId) {
    const camera = todasCameras.find(c => String(c.id) === String(cameraId));
    if (!camera) return;

    const detalhesContent = document.getElementById('detalhesCameraContent');
    const id = escapeHtml(String(camera.id || 'N/A'));
    const descricao = escapeHtml(camera.descricao || 'Não informado');
    const statusNome = escapeHtml(camera.status_nome || 'Não informado');
    const ip = escapeHtml(camera.ip || 'Não informado');
    const serieMac = escapeHtml(camera.serie_mac || 'Não informado');
    const modeloNome = escapeHtml(camera.modelo_nome || 'Não informado');
    const localNome = escapeHtml(camera.local_nome || 'Não informado');
    const secretariaNome = escapeHtml(camera.secretaria_nome || 'Não informado');
    const observacao = camera.observacao ? escapeHtml(camera.observacao) : '';

    let html = `
        <div class="row">
            <div class="col-md-6">
                <h6 class="border-bottom pb-2">Informações Básicas</h6>
                <p><strong>ID:</strong> ${id}</p>
                <p><strong>Descrição:</strong> ${descricao}</p>
                <p><strong>Status:</strong> <span class="badge ${getStatusBadgeClass(camera.status_nome)}">${statusNome}</span></p>
                <p><strong>Data Instalação:</strong> ${camera.data_instalacao ? formatarData(camera.data_instalacao) : 'Não informada'}</p>
            </div>
            <div class="col-md-6">
                <h6 class="border-bottom pb-2">Configurações Técnicas</h6>
                <p><strong>IP:</strong> <code>${ip}</code></p>
                <p><strong>Série/MAC:</strong> ${serieMac}</p>
                <p><strong>Modelo:</strong> ${modeloNome}</p>
                <p><strong>Local:</strong> ${localNome}</p>
                <p><strong>Secretaria:</strong> ${secretariaNome}</p>
            </div>
        </div>
    `;

    if (observacao) {
        html += `
            <div class="row mt-3">
                <div class="col-12">
                    <h6 class="border-bottom pb-2">Observações</h6>
                    <p>${observacao}</p>
                </div>
            </div>
        `;
    }

    detalhesContent.innerHTML = html;
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalhesCamera'));
    modal.show();
}

function formatarData(dataString) {
    if (!dataString) return '';
    const data = new Date(dataString);
    return data.toLocaleDateString('pt-BR') + ' ' + data.toLocaleTimeString('pt-BR');
}

function getStatusBadgeClass(statusNome) {
    const statusLower = statusNome ? statusNome.toLowerCase() : '';
    if (statusLower.includes('ativa') || statusLower.includes('funcionando')) {
        return 'bg-success';
    } else if (statusLower.includes('inativa') || statusLower.includes('desligado')) {
        return 'bg-danger';
    } else if (statusLower.includes('manutenção') || statusLower.includes('manutencao')) {
        return 'bg-warning';
    }
    return 'bg-secondary';
}

function aplicarFiltros() {
    carregarListaCameras(1);
}

function exibirCameras(cameras) {
    const container = document.getElementById('listaCameras');
    const contador = totalRecords || cameras.length;
    document.getElementById('contadorCameras').textContent = contador;

    if (cameras.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted py-4">
                <i class="fas fa-camera-slash fa-2x mb-2"></i>
                <p>Nenhuma câmera encontrada</p>
                <button type="button" class="btn btn-primary mt-2" data-action="limpar-filtros">
                    <i class="fas fa-broom me-1"></i>Limpar Filtros
                </button>
            </div>
        `;
        return;
    }

    let html = `
        <div class="table-responsive" aria-live="polite" aria-busy="false">
            <table class="table table-hover table-striped table-sm">
                <thead class="table-light">
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Descrição</th>
                        <th scope="col">IP</th>
                        <th scope="col">Série/MAC</th>
                        <th scope="col">Local</th>
                        <th scope="col">Secretaria</th>
                        <th scope="col">Status</th>
                        <th scope="col">Modelo</th>
                        <th scope="col">Data Instalação</th>
                        <th scope="col" class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
    `;

    cameras.forEach(camera => {
        const idRaw = String(camera.id || '').trim();
        if (!idRaw) {
            return;
        }
        const id = escapeHtml(idRaw);
        const idAttr = escapeHtml(idRaw);
        const descricaoRaw = camera.descricao || 'Sem descrição';
        const descricao = escapeHtml(descricaoRaw);
        const ip = escapeHtml(camera.ip || 'Não informado');
        const serieMac = escapeHtml(camera.serie_mac || 'Não informado');
        const local = escapeHtml(camera.local_nome || 'Não informado');
        const secretaria = escapeHtml(camera.secretaria_nome || 'Não informado');
        const status = escapeHtml(camera.status_nome || 'Desconhecido');
        const modelo = escapeHtml(camera.modelo_nome || 'Não informado');
        const dataInstalacao = camera.data_instalacao || '';
        const dataFormatada = dataInstalacao ? formatarData(dataInstalacao) : 'Não informada';
        const observacao = camera.observacao ? escapeHtml(camera.observacao.substring(0, 50)) : '';
        const observacaoSufixo = camera.observacao && camera.observacao.length > 50 ? '...' : '';
        const descricaoAttr = escapeHtml(descricaoRaw);
        const statusBadge = getStatusBadgeClass(camera.status_nome);

        html += `
            <tr>
                <td><span class="badge bg-secondary">${id}</span></td>
                <td>
                    <div class="fw-bold">${descricao}</div>
                    ${observacao ? `<small class="text-muted d-block">${observacao}${observacaoSufixo}</small>` : ''}
                </td>
                <td><code class="text-primary">${ip}</code></td>
                <td><small class="text-muted">${serieMac}</small></td>
                <td>${local}</td>
                <td>${secretaria}</td>
                <td><span class="badge ${statusBadge}">${status}</span></td>
                <td>${modelo}</td>
                <td>${dataFormatada}</td>
                <td class="text-center">
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-info py-1 px-2" 
                            data-action="detalhes"
                            data-camera-id="${idAttr}"
                            title="Ver detalhes"
                            data-bs-toggle="tooltip">
                            <i class="fas fa-eye"></i>
                        </button>
                        <a href="?page=editar_cameras&id=${encodeURIComponent(idRaw)}" class="btn btn-primary py-1 px-2"
                            title="Editar câmera" data-bs-toggle="tooltip">
                            <i class="fas fa-edit"></i>
                        </a>
                        ${CAN_DELETE ? `
                        <button class="btn btn-danger py-1 px-2" 
                            data-action="excluir"
                            data-camera-id="${idAttr}"
                            data-camera-descricao="${descricaoAttr}"
                            title="Excluir câmera"
                            data-bs-toggle="tooltip">
                            <i class="fas fa-trash"></i>
                        </button>` : ''}
                    </div>
                </td>
            </tr>
        `;
    });

    html += `
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            <small class="text-muted">Mostrando ${cameras.length} de ${contador} câmera(s)</small>
        </div>
    `;

    container.innerHTML = html;

    if (window._listarCamerasTooltips) {
        window._listarCamerasTooltips.forEach(t => t.dispose());
    }
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    window._listarCamerasTooltips = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

function limparFiltros() {
    document.getElementById('filtroStatus').value = '';
    document.getElementById('filtroLocal').value = '';
    document.getElementById('filtroPesquisa').value = '';
    carregarListaCameras(1);
}

function renderPaginacao() {
    const paginacao = document.getElementById('paginacaoCameras');
    if (!paginacao) return;

    if (totalPages <= 1) {
        paginacao.innerHTML = '';
        return;
    }

    const prevDisabled = currentPage <= 1 ? 'disabled' : '';
    const nextDisabled = currentPage >= totalPages ? 'disabled' : '';

    paginacao.innerHTML = `
        <div class="d-flex justify-content-between align-items-center">
            <button class="btn btn-outline-secondary btn-sm" data-action="pagina-anterior" ${prevDisabled}>
                <i class="fas fa-chevron-left me-1"></i>Anterior
            </button>
            <span class="text-muted small">Página ${currentPage} de ${totalPages}</span>
            <button class="btn btn-outline-secondary btn-sm" data-action="pagina-proxima" ${nextDisabled}>
                Próxima<i class="fas fa-chevron-right ms-1"></i>
            </button>
        </div>
    `;
}

function abrirModalExclusao(cameraId, cameraDescricao) {
    currentCameraId = cameraId;
    document.getElementById('cameraInfo').textContent = `ID: ${cameraId} - ${cameraDescricao}`;
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalConfirmacaoExclusao'));
    modal.show();
}

async function excluirCamera() {
    if (!CAN_DELETE) {
        showToast('Permissao insuficiente para excluir camera.', 'warning');
        return;
    }

    if (!currentCameraId) return;

    try {
        const modal = bootstrap.Modal.getInstance(document.getElementById('modalConfirmacaoExclusao'));
        modal.hide();

        const btnConfirmar = document.getElementById('btnConfirmarExclusao');
        const originalHTML = btnConfirmar.innerHTML;
        btnConfirmar.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Excluindo...';
        btnConfirmar.disabled = true;

        const formData = new FormData();
        formData.append('id', currentCameraId);
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        const response = await fetch(`${window.APP_API_BASE}api_excluir_camera`, {
            method: 'POST',
            headers: {
                'X-CSRF-Token': csrfToken
            },
            body: formData
        });

        const resultado = await response.json();

        if (resultado.success === true) {
            showToast('✅ Câmera excluída com sucesso!', 'success');
            carregarListaCameras(currentPage);
        } else {
            throw new Error(resultado.message || 'Erro ao excluir');
        }

    } catch (error) {
        console.error('❌ Erro ao excluir câmera:', error);
        showToast('❌ Erro ao excluir câmera: ' + error.message, 'danger');
    } finally {
        const btnConfirmar = document.getElementById('btnConfirmarExclusao');
        btnConfirmar.innerHTML = '<i class="fas fa-trash me-1"></i>Excluir';
        btnConfirmar.disabled = false;
        currentCameraId = null;
    }
}

function mostrarErro(mensagem) {
    const container = document.getElementById('listaCameras');
    container.innerHTML = `
        <div class="alert alert-danger" role="alert">
            ${escapeHtml(mensagem)}
        </div>
    `;
}

let currentCameraId = null;
let todasCameras = [];
let totalRecords = 0;
let totalPages = 1;
let currentPage = 1;
const PER_PAGE = 50;
let lastFocusedTrigger = null;

function initListarCameras() {
    if (!API_URL) {
        return;
    }

    document.addEventListener('DOMContentLoaded', function() {
        carregarListaCameras(1);
        document.getElementById('btnAtualizarListaCameras').addEventListener('click', () => carregarListaCameras(currentPage));
        document.getElementById('filtroStatus').addEventListener('change', aplicarFiltros);
        document.getElementById('filtroLocal').addEventListener('change', aplicarFiltros);
        let debounceTimer;
        document.getElementById('filtroPesquisa').addEventListener('keyup', function() {
          clearTimeout(debounceTimer);
          debounceTimer = setTimeout(aplicarFiltros, 300);
        });
        document.getElementById('btnLimparFiltros').addEventListener('click', limparFiltros);

        document.getElementById('btnConfirmarExclusao').addEventListener('click', excluirCamera);
        document.getElementById('listaCameras').addEventListener('click', function(event) {
            const actionButton = event.target.closest('[data-action]');
            if (!actionButton) return;

            const action = actionButton.getAttribute('data-action');
            if (action === 'limpar-filtros') {
                limparFiltros();
                return;
            }

            lastFocusedTrigger = actionButton;
            const cameraId = actionButton.getAttribute('data-camera-id');
            if (!cameraId) return;

            if (action === 'detalhes') {
                mostrarDetalhesCamera(cameraId);
                return;
            }

            if (action === 'excluir') {
                const cameraDescricao = actionButton.getAttribute('data-camera-descricao') || '';
                abrirModalExclusao(cameraId, cameraDescricao);
            }
        });

        window._listarCamerasInterval = setInterval(() => carregarListaCameras(currentPage), 120000);
        window.addEventListener('beforeunload', function() {
            clearInterval(window._listarCamerasInterval);
        });
        document.getElementById('paginacaoCameras').addEventListener('click', function(event) {
            const actionButton = event.target.closest('[data-action]');
            if (!actionButton) return;

            const action = actionButton.getAttribute('data-action');
            if (action === 'pagina-anterior' && currentPage > 1) {
                carregarListaCameras(currentPage - 1);
            }
            if (action === 'pagina-proxima' && currentPage < totalPages) {
                carregarListaCameras(currentPage + 1);
            }
        });

        const detalhesModalEl = document.getElementById('modalDetalhesCamera');
        detalhesModalEl.addEventListener('hide.bs.modal', function() {
            if (detalhesModalEl.contains(document.activeElement)) {
                document.activeElement.blur();
            }
        });
        detalhesModalEl.addEventListener('hidden.bs.modal', function() {
            if (lastFocusedTrigger && typeof lastFocusedTrigger.focus === 'function') {
                lastFocusedTrigger.focus();
            }
        });

        if (window._listarCamerasTooltips) {
            window._listarCamerasTooltips.forEach(t => t.dispose());
        }
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        window._listarCamerasTooltips = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
}

initListarCameras();



})();
