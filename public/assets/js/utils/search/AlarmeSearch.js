// Módulo Centralizado de Busca para Sistema de Alarmes
// utils/search/AlarmeSearch.js

class AlarmeSearch {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        this.options = {
            debounceTime: 300,
            minChars: 2,
            maxResults: 50,
            showSuggestions: true,
            autoSelectFirst: false,
            ...options
        };
        
        this.state = {
            isSearching: false,
            currentQuery: '',
            results: [],
            selectedIndex: -1,
            history: []
        };
        
        this.initialize();
    }
    
    initialize() {
        if (!this.container) {
            console.error(`Container ${this.containerId} not found`);
            return;
        }
        
        this.setupElements();
        this.bindEvents();
        this.setupKeyboardNavigation();
    }
    
    setupElements() {
        // Criar estrutura HTML do componente
        this.container.innerHTML = `
            <div class="alarme-search-container">
                <div class="search-input-wrapper">
                    <input type="text" 
                           class="search-input" 
                           placeholder="Buscar alarmes..."
                           autocomplete="off">
                    <div class="search-spinner d-none">
                        <div class="spinner-border spinner-border-sm" role="status">
                            <span class="visually-hidden">Buscando...</span>
                        </div>
                    </div>
                    <button class="search-clear d-none" type="button">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="search-suggestions d-none">
                    <div class="suggestions-header">
                        <strong>Resultados</strong>
                        <small class="suggestions-count"></small>
                    </div>
                    <div class="suggestions-list"></div>
                    <div class="suggestions-footer">
                        <button class="btn btn-sm btn-outline-primary search-more-btn">
                            <i class="fas fa-search-plus me-1"></i>Buscar mais resultados
                        </button>
                    </div>
                </div>
                <div class="search-history">
                    <div class="history-header">
                        <strong>Buscas recentes</strong>
                        <button class="btn btn-sm btn-link btn-sm clear-history">
                            <i class="fas fa-trash me-1"></i>Limpar
                        </button>
                    </div>
                    <div class="history-list"></div>
                </div>
            </div>
        `;
        
        // Obter referências aos elementos
        this.elements = {
            input: this.container.querySelector('.search-input'),
            spinner: this.container.querySelector('.search-spinner'),
            clearBtn: this.container.querySelector('.search-clear'),
            suggestions: this.container.querySelector('.search-suggestions'),
            suggestionsList: this.container.querySelector('.suggestions-list'),
            suggestionsCount: this.container.querySelector('.suggestions-count'),
            moreBtn: this.container.querySelector('.search-more-btn'),
            historyList: this.container.querySelector('.history-list'),
            clearHistoryBtn: this.container.querySelector('.clear-history')
        };
    }
    
    bindEvents() {
        // Input events
        this.elements.input.addEventListener('input', this.handleInput.bind(this));
        this.elements.input.addEventListener('focus', this.handleFocus.bind(this));
        this.elements.input.addEventListener('blur', this.handleBlur.bind(this));
        this.elements.input.addEventListener('keydown', this.handleKeydown.bind(this));
        
        // Button events
        this.elements.clearBtn.addEventListener('click', this.clearSearch.bind(this));
        this.elements.moreBtn.addEventListener('click', this.loadMoreResults.bind(this));
        this.elements.clearHistoryBtn.addEventListener('click', this.clearHistory.bind(this));
        
        // Click outside
        document.addEventListener('click', this.handleClickOutside.bind(this));
    }
    
    setupKeyboardNavigation() {
        const keys = ['ArrowUp', 'ArrowDown', 'Enter', 'Escape'];
        
        keys.forEach(key => {
            document.addEventListener('keydown', (e) => {
                if (e.target === this.elements.input && key === e.key) {
                    this.handleKeydown(e);
                }
            });
        });
    }
    
    async handleInput(event) {
        const query = event.target.value.trim();
        
        // Debounce
        clearTimeout(this.debounceTimeout);
        
        if (query.length < this.options.minChars) {
            this.hideSuggestions();
            return;
        }
        
        this.debounceTimeout = setTimeout(() => {
            this.performSearch(query);
        }, this.options.debounceTime);
    }
    
    async performSearch(query, page = 1) {
        if (this.state.isSearching) return;
        
        this.state.isSearching = true;
        this.state.currentQuery = query;
        
        // Mostrar spinner
        this.showSpinner();
        
        try {
            const params = new URLSearchParams({
                busca: query,
                page_num: page.toString(),
                per_page: this.options.maxResults.toString()
            });
            
            const response = await fetch(`${this.getApiBase()}api_alarmes&${params.toString()}`, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            });
            
            const payload = await response.json();
            
            if (!response.ok || !payload.success) {
                throw new Error(payload.error || 'Erro ao buscar alarmes');
            }
            
            const results = payload.data || [];
            
            // Adicionar metadata
            const searchResult = {
                query,
                page,
                results,
                pagination: payload.pagination,
                timestamp: new Date().toISOString()
            };
            
            this.state.results = page === 1 ? results : [...this.state.results, ...results];
            this.state.selectedIndex = -1;
            
            // Adicionar ao histórico
            this.addToHistory(searchResult);
            
            // Exibir resultados
            this.showSuggestions(results, payload.pagination);
            
        } catch (error) {
            console.error('Search error:', error);
            this.showError(error.message);
        } finally {
            this.state.isSearching = false;
            this.hideSpinner();
        }
    }
    
    showSuggestions(results, pagination) {
        if (results.length === 0) {
            this.showNoResults();
            return;
        }
        
        // Atualizar contador
        this.elements.suggestionsCount.textContent = `${results.length} resultado${results.length !== 1 ? 's' : ''}`;
        
        // Renderizar sugestões
        const suggestionsHtml = results.map((item, index) => `
            <div class="suggestion-item ${index === this.state.selectedIndex ? 'active' : ''}" 
                 data-id="${item.id}"
                 data-index="${index}">
                <div class="suggestion-main">
                    <strong>#${item.id}</strong>
                    <span class="suggestion-local">${item.local || 'Sem local'}</span>
                </div>
                <div class="suggestion-details">
                    <span class="badge bg-secondary">${item.conta || 'Sem conta'}</span>
                    <span class="badge bg-info">${item.status || 'Sem status'}</span>
                    ${item.ip ? `<small class="text-muted">${item.ip}</small>` : ''}
                </div>
            </div>
        `).join('');
        
        this.elements.suggestionsList.innerHTML = suggestionsHtml;
        
        // Mostrar botão "mais resultados" se houver página seguinte
        if (pagination && pagination.total_pages > pagination.page) {
            this.elements.moreBtn.classList.remove('d-none');
        } else {
            this.elements.moreBtn.classList.add('d-none');
        }
        
        // Mostrar sugestões
        this.elements.suggestions.classList.remove('d-none');
        
        // Auto selecionar primeiro resultado
        if (this.options.autoSelectFirst && results.length > 0) {
            this.state.selectedIndex = 0;
            this.updateActiveSuggestion();
        }
        
        // Bind events aos itens
        this.elements.suggestionsList.querySelectorAll('.suggestion-item').forEach((item, index) => {
            item.addEventListener('click', () => this.selectSuggestion(index));
            item.addEventListener('mouseenter', () => {
                this.state.selectedIndex = index;
                this.updateActiveSuggestion();
            });
        });
    }
    
    updateActiveSuggestion() {
        const items = this.elements.suggestionsList.querySelectorAll('.suggestion-item');
        items.forEach((item, index) => {
            item.classList.toggle('active', index === this.state.selectedIndex);
        });
    }
    
    selectSuggestion(index) {
        const suggestion = this.state.results[index];
        if (!suggestion) return;
        
        // Disparar evento de seleção
        const event = new CustomEvent('suggestionSelected', {
            detail: { suggestion, index }
        });
        this.container.dispatchEvent(event);
        
        // Esconder sugestões
        this.hideSuggestions();
        
        // Limpar input (opcional)
        // this.elements.input.value = '';
    }
    
    showNoResults() {
        this.elements.suggestionsList.innerHTML = `
            <div class="no-results">
                <i class="fas fa-search fa-2x text-muted mb-2"></i>
                <p class="text-muted mb-0">Nenhum resultado encontrado para "${this.state.currentQuery}"</p>
            </div>
        `;
        this.elements.suggestionsCount.textContent = '';
        this.elements.moreBtn.classList.add('d-none');
        this.elements.suggestions.classList.remove('d-none');
    }
    
    showError(message) {
        this.elements.suggestionsList.innerHTML = `
            <div class="search-error">
                <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                <p class="text-danger mb-0">${message}</p>
            </div>
        `;
        this.elements.suggestionsCount.textContent = '';
        this.elements.moreBtn.classList.add('d-none');
        this.elements.suggestions.classList.remove('d-none');
    }
    
    showSpinner() {
        this.elements.spinner.classList.remove('d-none');
        this.elements.clearBtn.classList.add('d-none');
    }
    
    hideSpinner() {
        this.elements.spinner.classList.add('d-none');
        this.elements.clearBtn.classList.remove('d-none');
    }
    
    clearSearch() {
        this.elements.input.value = '';
        this.hideSuggestions();
        this.state.currentQuery = '';
        this.state.results = [];
        this.state.selectedIndex = -1;
    }
    
    hideSuggestions() {
        this.elements.suggestions.classList.add('d-none');
        this.elements.suggestionsList.innerHTML = '';
        this.state.selectedIndex = -1;
    }
    
    handleFocus() {
        if (this.state.history.length > 0) {
            this.showHistory();
        }
    }
    
    handleBlur() {
        // Dar tempo para clique em sugestões
        setTimeout(() => {
            this.hideHistory();
        }, 200);
    }
    
    handleKeydown(event) {
        const key = event.key;
        
        switch (key) {
            case 'ArrowDown':
                event.preventDefault();
                this.navigateSuggestions(1);
                break;
            case 'ArrowUp':
                event.preventDefault();
                this.navigateSuggestions(-1);
                break;
            case 'Enter':
                event.preventDefault();
                if (this.state.selectedIndex >= 0) {
                    this.selectSuggestion(this.state.selectedIndex);
                }
                break;
            case 'Escape':
                event.preventDefault();
                this.hideSuggestions();
                break;
        }
    }
    
    navigateSuggestions(direction) {
        const items = this.elements.suggestionsList.querySelectorAll('.suggestion-item');
        if (items.length === 0) return;
        
        this.state.selectedIndex += direction;
        
        // Loop nos resultados
        if (this.state.selectedIndex < 0) {
            this.state.selectedIndex = items.length - 1;
        } else if (this.state.selectedIndex >= items.length) {
            this.state.selectedIndex = 0;
        }
        
        this.updateActiveSuggestion();
    }
    
    addToHistory(searchResult) {
        // Remover buscas duplicadas
        this.state.history = this.state.history.filter(h => h.query !== searchResult.query);
        
        // Adicionar no início
        this.state.history.unshift(searchResult);
        
        // Limitar histórico
        if (this.state.history.length > 10) {
            this.state.history = this.state.history.slice(0, 10);
        }
        
        this.updateHistoryDisplay();
    }
    
    updateHistoryDisplay() {
        if (this.state.history.length === 0) {
            this.elements.historyList.innerHTML = '<p class="text-muted small">Nenhuma busca recente</p>';
            return;
        }
        
        const historyHtml = this.state.history.map((item, index) => `
            <div class="history-item" data-index="${index}">
                <div class="history-query">
                    <i class="fas fa-history me-2 text-muted"></i>
                    <span class="query-text">${item.query}</span>
                    <small class="text-muted ms-2">${new Date(item.timestamp).toLocaleDateString()}</small>
                </div>
                <div class="history-results">
                    <small class="text-muted">${item.results.length} resultado${item.results.length !== 1 ? 's' : ''}</small>
                </div>
            </div>
        `).join('');
        
        this.elements.historyList.innerHTML = historyHtml;
        
        // Bind events
        this.elements.historyList.querySelectorAll('.history-item').forEach((item, index) => {
            item.addEventListener('click', () => {
                const historyItem = this.state.history[index];
                this.elements.input.value = historyItem.query;
                this.performSearch(historyItem.query);
            });
        });
    }
    
    showHistory() {
        const historyContainer = this.container.querySelector('.search-history');
        if (historyContainer) {
            historyContainer.style.display = 'block';
        }
    }
    
    hideHistory() {
        const historyContainer = this.container.querySelector('.search-history');
        if (historyContainer) {
            historyContainer.style.display = 'none';
        }
    }
    
    clearHistory() {
        this.state.history = [];
        this.updateHistoryDisplay();
    }
    
    handleClickOutside(event) {
        if (!this.container.contains(event.target)) {
            this.hideSuggestions();
            this.hideHistory();
        }
    }
    
    loadMoreResults() {
        const pagination = this.state.results.pagination;
        if (!pagination || pagination.page >= pagination.total_pages) return;
        
        this.performSearch(this.state.currentQuery, pagination.page + 1);
    }
    
    getApiBase() {
        return window.APP_API_BASE || `${window.BASE_URL || '/'}index.php?page=api/`;
    }
    
    // Métodos públicos
    getValue() {
        return this.elements.input.value;
    }
    
    setValue(value) {
        this.elements.input.value = value;
    }
    
    focus() {
        this.elements.input.focus();
    }
    
    blur() {
        this.elements.input.blur();
    }
    
    destroy() {
        // Limpar eventos
        clearTimeout(this.debounceTimeout);
        
        // Remover elemento
        if (this.container && this.container.parentNode) {
            this.container.parentNode.removeChild(this.container);
        }
    }
}

// Disponibilizar globalmente
window.AlarmeSearch = AlarmeSearch;