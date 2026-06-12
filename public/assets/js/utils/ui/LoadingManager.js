// Módulo de Debounce e Loading States
// utils/ui/LoadingManager.js

class LoadingManager {
    constructor(options = {}) {
        this.options = {
            defaultTimeout: 10000, // 10 segundos
            showSpinner: true,
            backdrop: false,
            ...options
        };
        
        this.state = {
            isLoading: false,
            currentRequests: new Map(),
            globalLoading: false,
            globalMessage: '',
            backdropVisible: false
        };
        
        this.init();
    }
    
    init() {
        this.createGlobalLoader();
        this.setupRequestInterceptor();
    }
    
    createGlobalLoader() {
        const existingLoader = document.getElementById('global-loader');
        if (existingLoader) return;
        
        const loader = document.createElement('div');
        loader.id = 'global-loader';
        loader.className = 'global-loader';
        loader.innerHTML = `
            <div class="global-loader-backdrop"></div>
            <div class="global-loader-content">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
                <div class="global-loader-message">Carregando...</div>
            </div>
        `;
        
        document.body.appendChild(loader);
    }
    
    setupRequestInterceptor() {
        const fetchFn = window.fetchWithTimeout || window.fetch;
        const self = this;

        window.fetchWithTimeout = async function (...args) {
            const [resource, config = {}] = args;

            const requestId = self.generateRequestId(resource, config);

            self.startLoading(requestId, config.metadata || {});

            try {
                const response = await (args.length > 2
                    ? fetchFn(args[0], args[1], args[2])
                    : fetchFn(args[0], args[1]));

                self.finishLoading(requestId);

                return response;
            } catch (error) {
                self.finishLoading(requestId);
                throw error;
            }
        };
    }
    
    generateRequestId(resource, config) {
        const url = typeof resource === 'string' ? resource : resource.url;
        const method = config.method || 'GET';
        const timestamp = Date.now();
        
        return `${method}_${url}_${timestamp}`;
    }
    
    // Métodos individuais
    startLoading(id, metadata = {}) {
        this.state.currentRequests.set(id, {
            metadata,
            startTime: Date.now()
        });
        
        this.updateGlobalState();
    }
    
    finishLoading(id) {
        this.state.currentRequests.delete(id);
        this.updateGlobalState();
    }
    
    updateGlobalState() {
        const requestCount = this.state.currentRequests.size;
        
        this.state.globalLoading = requestCount > 0;
        this.state.backdropVisible = requestCount > 0 && this.options.backdrop;
        
        const loader = document.getElementById('global-loader');
        if (loader) {
            loader.classList.toggle('show', this.state.globalLoading);
            loader.classList.toggle('backdrop', this.state.backdropVisible);
            
            // Atualizar mensagem
            const messageEl = loader.querySelector('.global-loader-message');
            if (messageEl) {
                messageEl.textContent = this.state.globalMessage || 'Carregando...';
            }
        }
    }
    
    // Métodos públicos
    show(message = '', options = {}) {
        this.state.globalLoading = true;
        this.state.globalMessage = message;
        this.state.backdropVisible = options.backdrop || this.options.backdrop;
        
        this.updateGlobalState();
        
        // Auto esconder após timeout
        if (options.timeout) {
            setTimeout(() => {
                this.hide();
            }, options.timeout);
        }
    }
    
    hide() {
        this.state.globalLoading = false;
        this.state.globalMessage = '';
        this.state.backdropVisible = false;
        
        this.updateGlobalState();
    }
    
    withLoading(promise, id, metadata = {}) {
        this.startLoading(id, metadata);
        
        return promise
            .finally(() => {
                this.finishLoading(id);
            });
    }
    
    // Método para elementos específicos
    showElement(element, message = '') {
        if (!element) return;
        
        const loader = document.createElement('div');
        loader.className = 'element-loader';
        loader.innerHTML = `
            <div class="element-loader-backdrop"></div>
            <div class="element-loader-content">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
                ${message ? `<div class="element-loader-message">${message}</div>` : ''}
            </div>
        `;
        
        element.style.position = 'relative';
        element.appendChild(loader);
        
        return {
            hide: () => {
                if (loader.parentNode) {
                    loader.parentNode.removeChild(loader);
                }
            }
        };
    }
}

// Função utilitária de debounce
function debounce(func, wait) {
    let timeout;
    
    const debounced = function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            timeout = null;
            func.apply(this, args);
        }, wait);
    };
    
    debounced.cancel = function() {
        if (timeout) {
            clearTimeout(timeout);
            timeout = null;
        }
    };
    
    debounced.flush = function() {
        if (timeout) {
            clearTimeout(timeout);
            timeout = null;
            func.apply(this, arguments);
        }
    };
    
    return debounced;
}

// Função utilitária de throttle
function throttle(func, limit) {
    let inThrottle;
    let lastFunc;
    let lastRan;
    
    return function(...args) {
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            lastRan = Date.now();
            inThrottle = true;
        } else {
            clearTimeout(lastFunc);
            lastFunc = setTimeout(function() {
                if ((Date.now() - lastRan) >= limit) {
                    func.apply(context, args);
                    lastRan = Date.now();
                }
            }, limit - (Date.now() - lastRan));
        }
    };
}

// Criar instância global
const loadingManager = new LoadingManager();

// Exportar instância global
window.LoadingManager = loadingManager;

// Exportar funções utilitárias
window.debounce = debounce;
window.throttle = throttle;

// Exportar para módulos (caso usado como módulo)
if (typeof module !== 'undefined' && module.exports) {
  module.exports = loadingManager;
}