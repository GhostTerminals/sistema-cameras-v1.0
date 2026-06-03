class HomeDashboard extends window.DashboardCore {
    constructor() {
        super();
        this.themeManager = new window.ThemeManager();
        this.chartManager = new window.ChartManager();
        this.init();
    }

    getApiBase() {
        if (window.APP_API_BASE) {
            return window.APP_API_BASE;
        }
        if (typeof APP_API_BASE !== 'undefined' && APP_API_BASE) {
            return APP_API_BASE;
        }
        const base = window.BASE_URL || (typeof BASE_URL !== 'undefined' ? BASE_URL : '/');
        return `${base}index.php?page=api/`;
    }

    async init() {
        try {
            this.themeManager.init();
            await this.loadData();
            this.setupAutoRefresh();
            this.setupEventListeners();
            this.bootstrapTooltips();
        } catch (error) {
            console.error('Erro na inicializacao do HomeDashboard:', error);
            this.setupEventListeners();
        }
    }

    setLoading(loading) {
        const cards = document.querySelectorAll('.stat-card');
        cards.forEach(card => {
            card.classList.toggle('is-loading', loading);
        });
    }

    async loadData() {
        if (this._loading) return;
        this._loading = true;
        try {
            this.setLoading(true);
            const endpoint = `${this.getApiBase()}api_dashboard`;
            const payload = await this.fetchData(endpoint);
            this.data = payload && payload.success ? payload : null;
            this.updateUI();
        } catch (error) {
            console.error('Falha ao carregar dados:', error);
        } finally {
            this._loading = false;
            this.setLoading(false);
        }
    }

    updateUI() {
        if (!this.data) return;
        const data = this.data.data || this.data;
        const stats = data.stats || {};

        const totalEl = document.getElementById('total-cameras');
        if (totalEl && stats.total !== undefined) {
            this.animateCounter(totalEl, 0, Number(stats.total) || 0, 1000);
        }

        const activeEl = document.getElementById('active-cameras');
        if (activeEl && stats.ativas !== undefined) {
            this.animateCounter(activeEl, 0, Number(stats.ativas) || 0, 1000);
        }

        const manutencaoEl = document.getElementById('manutencao-cameras');
        if (manutencaoEl && stats.manutencao !== undefined) {
            this.animateCounter(manutencaoEl, 0, Number(stats.manutencao) || 0, 1000);
        }

        const desativadasEl = document.getElementById('desativadas-cameras');
        if (desativadasEl && stats.desativadas !== undefined) {
            this.animateCounter(desativadasEl, 0, Number(stats.desativadas) || 0, 1000);
        }

        const alertsEl = document.getElementById('alerts-count');
        if (alertsEl && stats.atrasada !== undefined) {
            this.animateCounter(alertsEl, 0, Number(stats.atrasada) || 0, 1000);
        }

        const uptime = Number(stats.uptime) || 0;
        const uptimeEl = document.getElementById('uptime-percentual');
        if (uptimeEl) {
            uptimeEl.textContent = uptime + '%';
        }

        const progressLabel = document.getElementById('progress-label');
        if (progressLabel) progressLabel.textContent = uptime + '%';

        const progressBar = document.getElementById('progress-bar');
        if (progressBar) {
            progressBar.style.width = uptime + '%';
            progressBar.setAttribute('aria-valuenow', uptime);
        }

        const progressCameras = document.getElementById('progress-cameras');
        if (progressCameras) progressCameras.textContent = Number(stats.ativas) || 0;
        const progressTotal = document.getElementById('progress-total');
        if (progressTotal) progressTotal.textContent = Number(stats.total) || 0;

        const statusAtivas = document.getElementById('status-ativas');
        if (statusAtivas) statusAtivas.textContent = Number(stats.ativas) || 0;
        const statusManutencao = document.getElementById('status-manutencao');
        if (statusManutencao) statusManutencao.textContent = Number(stats.manutencao) || 0;
        const statusDesativadas = document.getElementById('status-desativadas');
        if (statusDesativadas) statusDesativadas.textContent = Number(stats.desativadas) || 0;

        const alertaAtraso = document.getElementById('alertaAtraso');
        if (alertaAtraso) {
            if (Number(stats.atrasada) > 0) {
                alertaAtraso.classList.remove('d-none');
                const countEl = document.getElementById('alertaAtrasoCount');
                if (countEl) countEl.textContent = stats.atrasada;
            } else {
                alertaAtraso.classList.add('d-none');
            }
        }

        if (this.chartManager && data.status_data) {
            this.chartManager.updateCameraStatusChart(data.status_data);
        }

        if (this.chartManager && data.camera_tipo_data) {
            this.chartManager.updateCameraTypeChart(data.camera_tipo_data);
        }

        if (this.chartManager && data.alarm_status_data) {
            this.chartManager.updateAlarmStatusChart(data.alarm_status_data);
        }

        this.renderRecentActivities(data.manutencoes);
    }

    renderRecentActivities(manutencoes) {
        const container = document.getElementById('recent-activities-body');
        if (!container) return;

        if (!manutencoes || manutencoes.length === 0) {
            container.innerHTML = `
                <div class="text-center text-muted py-4">
                    <i class="fas fa-clock fa-3x mb-3"></i>
                    <h5>Nenhuma manuten&ccedil;&atilde;o recente encontrada</h5>
                    <p>Registre manuten&ccedil;&otilde;es para ver as atualiza&ccedil;&otilde;es aqui.</p>
                </div>`;
            return;
        }

        const rows = manutencoes.map(m => {
            const data = m.data_hora ? new Date(m.data_hora + ' UTC').toLocaleString('pt-BR') : '-';
            const nome = m.camera_nome || 'ID ' + (m.equipamento_id || '-');
            const desc = m.descricao ? (m.descricao.length > 100 ? m.descricao.substring(0, 100) + '...' : m.descricao) : '-';
            return `<tr>
                <td>${this.escapeHtml(data)}</td>
                <td>${this.escapeHtml(nome)}</td>
                <td>${this.escapeHtml(m.status || '-')}</td>
                <td>${this.escapeHtml(m.serie_mac || '-')}</td>
                <td>${this.escapeHtml(m.tecnico_responsavel || '-')}</td>
                <td>${this.escapeHtml(desc)}</td>
            </tr>`;
        }).join('');

        container.innerHTML = `
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Equipamento</th>
                            <th>Status</th>
                            <th>S&eacute;rie/MAC</th>
                            <th>T&eacute;cnico</th>
                            <th>Resumo</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>`;
    }

    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    bootstrapTooltips() {
        if (window._homeTooltips) {
            window._homeTooltips.forEach(t => t.dispose());
        }
        const triggers = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        window._homeTooltips = Array.from(triggers).map(el => new bootstrap.Tooltip(el));
    }

    setupAutoRefresh() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
        }
        this.updateInterval = setInterval(() => this.loadData(), 30000);
    }

    setupEventListeners() {
        document.addEventListener('click', (e) => {
            if (e.target.matches('#logout') || e.target.closest('#logout')) {
                e.preventDefault();
                this.logout();
            }

            if (e.target.matches('#refresh-dashboard') || e.target.closest('#refresh-dashboard')) {
                e.preventDefault();
                this.loadData();
            }

            if (e.target.matches('#settings') || e.target.closest('#settings')) {
                e.preventDefault();
                window.location.href = (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + '/index.php?page=settings';
            }
        });

        const menuToggle = document.getElementById('menu-toggle');
        if (menuToggle) {
            menuToggle.addEventListener('click', () => {
                document.body.classList.toggle('sidebar-collapsed');
            });
        }
    }

    logout() {
        const base = (typeof BASE_URL !== 'undefined' ? BASE_URL : '/').replace(/\/?$/, '/');
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `${base}index.php?page=logout`;
        form.style.display = 'none';

        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = 'csrf_token';
        csrf.value = window.CSRF_TOKEN || '';
        form.appendChild(csrf);

        document.body.appendChild(form);
        form.submit();
    }

    destroy() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
        }
        if (window._homeTooltips) {
            window._homeTooltips.forEach(t => t.dispose());
            window._homeTooltips = null;
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.body.classList.add('page-home');

    if (!document.body.classList.contains('page-home')) return;

    try {
        window.homeDashboard = new HomeDashboard();
    } catch (e) {
        console.error('Falha ao instanciar HomeDashboard', e);
    }
});

window.addEventListener('beforeunload', () => {
    if (window.homeDashboard && typeof window.homeDashboard.destroy === 'function') {
        window.homeDashboard.destroy();
    }
});
