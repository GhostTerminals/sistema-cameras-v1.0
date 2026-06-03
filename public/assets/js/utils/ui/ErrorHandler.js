// Módulo Centralizado de Tratamento de Erros
// utils/ui/ErrorHandler.js

class ErrorHandler {
    constructor(options = {}) {
        this.options = {
            maxLogSize: 100,
            enableConsole: true,
            enableAlerts: true,
            customHandlers: {},
            ...options
        };
        
        this.errorLog = [];
        this.init();
    }
    
    init() {
        // Capturar erros globais
        window.addEventListener('error', (event) => {
            this.handleGlobalError(event.error, event.filename, event.lineno);
        });
        
        // Captitar promises rejeitadas
        window.addEventListener('unhandledrejection', (event) => {
            this.handlePromiseError(event.reason);
        });
        
        // Criar elemento de notificação
        this.createNotificationContainer();
    }
    
    createNotificationContainer() {
        const existingContainer = document.getElementById('error-notification-container');
        if (existingContainer) return;
        
        const container = document.createElement('div');
        container.id = 'error-notification-container';
        container.className = 'error-notification-container';
        container.innerHTML = `
            <div class="error-notification-wrapper"></div>
        `;
        
        document.body.appendChild(container);
    }
    
    // Método principal para tratamento de erros
    handle(error, context = '', severity = 'error') {
        const errorInfo = {
            error: error,
            context: context,
            severity: severity,
            timestamp: new Date().toISOString(),
            stack: error?.stack || null,
            type: error?.constructor?.name || 'UnknownError'
        };
        
        // Adicionar ao log
        this.addToLog(errorInfo);
        
        // Log no console
        if (this.options.enableConsole) {
            this.logToConsole(errorInfo);
        }
        
        // Mostrar notificação
        if (this.options.enableAlerts) {
            this.showNotification(errorInfo);
        }
        
        // Executar handler customizado
        this.executeCustomHandler(errorInfo);
        
        return errorInfo;
    }
    
    // Tratamento de erros globais
    handleGlobalError(error, filename, lineno) {
        const errorInfo = {
            error: error,
            context: `Global error at ${filename}:${lineno}`,
            severity: 'error',
            timestamp: new Date().toISOString(),
            stack: error?.stack || null,
            type: 'GlobalError'
        };
        
        this.addToLog(errorInfo);
        this.logToConsole(errorInfo);
        this.showNotification(errorInfo);
    }
    
    // Tratamento de promise rejeitadas
    handlePromiseError(reason) {
        const errorInfo = {
            error: reason,
            context: 'Unhandled promise rejection',
            severity: 'warning',
            timestamp: new Date().toISOString(),
            stack: reason?.stack || null,
            type: 'PromiseRejection'
        };
        
        this.addToLog(errorInfo);
        this.logToConsole(errorInfo);
        this.showNotification(errorInfo);
    }
    
    // Adicionar ao log interno
    addToLog(errorInfo) {
        this.errorLog.unshift(errorInfo);
        
        // Limitar tamanho do log
        if (this.errorLog.length > this.options.maxLogSize) {
            this.errorLog = this.errorLog.slice(0, this.options.maxLogSize);
        }
    }
    
    // Log no console
    logToConsole(errorInfo) {
        const { error, context, severity, timestamp } = errorInfo;
        
        const logMethod = severity === 'warning' ? 'warn' : 'error';
        const prefix = `[${new Date(timestamp).toLocaleTimeString()}] [${severity.toUpperCase()}]`;
        
        console[logMethod](`${prefix} ${context}`, error);
        
        if (errorInfo.stack) {
            console[logMethod](`Stack:`, errorInfo.stack);
        }
    }
    
    // Mostrar notificação na interface
    showNotification(errorInfo) {
        const wrapper = document.querySelector('.error-notification-wrapper');
        if (!wrapper) return;
        
        const notification = this.createNotificationElement(errorInfo);
        wrapper.appendChild(notification);
        
        // Animação de entrada
        setTimeout(() => {
            notification.classList.add('show');
        }, 10);
        
        // Auto remover após 5 segundos
        setTimeout(() => {
            this.removeNotification(notification);
        }, 5000);
        
        // Limpar notificações antigas
        const notifications = wrapper.querySelectorAll('.error-notification');
        if (notifications.length > 5) {
            for (let i = notifications.length - 1; i >= 5; i--) {
                this.removeNotification(notifications[i]);
            }
        }
    }
    
    // Criar elemento de notificação
    createNotificationElement(errorInfo) {
        const { error, context, severity, timestamp } = errorInfo;
        
        const severityClasses = {
            error: 'bg-danger',
            warning: 'bg-warning',
            info: 'bg-info',
            success: 'bg-success'
        };
        
        const severityIcons = {
            error: 'fas fa-exclamation-triangle',
            warning: 'fas fa-exclamation-circle',
            info: 'fas fa-info-circle',
            success: 'fas fa-check-circle'
        };
        
        const notification = document.createElement('div');
        notification.className = `error-notification ${severityClasses[severity] || 'bg-info'}`;
        notification.innerHTML = `
            <div class="error-notification-content">
                <div class="error-notification-header">
                    <i class="${severityIcons[severity] || 'fas fa-info-circle'} me-2"></i>
                    <strong>${severity.toUpperCase()}</strong>
                    <button class="btn-close ms-2" type="button"></button>
                </div>
                <div class="error-notification-body">
                    <div class="error-message">${this.formatErrorMessage(error)}</div>
                    <div class="error-context text-muted small">${context}</div>
                    <div class="error-timestamp text-muted small">
                        ${new Date(timestamp).toLocaleString()}
                    </div>
                </div>
            </div>
        `;
        
        // Evento para fechar
        const closeBtn = notification.querySelector('.btn-close');
        closeBtn.addEventListener('click', () => {
            this.removeNotification(notification);
        });
        
        return notification;
    }
    
    // Remover notificação
    removeNotification(notification) {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }
    
    // Formatar mensagem de erro
    formatErrorMessage(error) {
        if (typeof error === 'string') {
            return error;
        }
        
        if (error?.message) {
            return error.message;
        }
        
        return 'Ocorreu um erro desconhecido';
    }
    
    // Executar handler customizado
    executeCustomHandler(errorInfo) {
        const { type, severity } = errorInfo;
        const handlerKey = `${type}_${severity}`;
        
        if (this.options.customHandlers[handlerKey]) {
            try {
                this.options.customHandlers[handlerKey](errorInfo);
            } catch (handlerError) {
                console.error('Custom error handler failed:', handlerError);
            }
        }
    }
    
    // Métodos públicos
    addCustomHandler(type, handler) {
        this.options.customHandlers[type] = handler;
    }
    
    removeCustomHandler(type) {
        delete this.options.customHandlers[type];
    }
    
    getErrorLog() {
        return [...this.errorLog];
    }
    
    clearErrorLog() {
        this.errorLog = [];
    }
    
    // Métodos para tipos específicos de erro
    handleApiError(error, endpoint = '') {
        return this.handle(error, `API Error: ${endpoint}`, 'error');
    }
    
    handleValidationError(error, field = '') {
        return this.handle(error, `Validation Error: ${field}`, 'warning');
    }
    
    handleNetworkError(error, url = '') {
        return this.handle(error, `Network Error: ${url}`, 'error');
    }
    
    handleTimeoutError(error, operation = '') {
        return this.handle(error, `Timeout Error: ${operation}`, 'warning');
    }
    
    // Método utilitário para obter mensagem amigável
    getUserMessage(error, context = '') {
        const errorMap = {
            'network': 'Erro de conexão. Verifique sua internet.',
            'timeout': 'A requisição demorou demais. Tente novamente.',
            'validation': 'Dados inválidos. Verifique os campos.',
            'authorization': 'Você não tem permissão para esta ação.',
            'not_found': 'Registro não encontrado.',
            'duplicate': 'Registro já existe.',
            'server_error': 'Erro interno do servidor. Tente novamente mais tarde.'
        };
        
        const errorType = this.getErrorType(error);
        return errorMap[errorType] || 'Ocorreu um erro inesperado.';
    }
    
    // Identificar tipo de erro
    getErrorType(error) {
        if (error instanceof TypeError) return 'validation';
        if (error instanceof ReferenceError) return 'validation';
        if (error instanceof SyntaxError) return 'validation';
        
        const errorMessage = (error.message || '').toLowerCase();
        
        if (errorMessage.includes('network') || errorMessage.includes('fetch')) {
            return 'network';
        }
        
        if (errorMessage.includes('timeout')) {
            return 'timeout';
        }
        
        if (errorMessage.includes('unauthorized') || errorMessage.includes('forbidden')) {
            return 'authorization';
        }
        
        if (errorMessage.includes('not found')) {
            return 'not_found';
        }
        
        if (errorMessage.includes('duplicate') || errorMessage.includes('already exists')) {
            return 'duplicate';
        }
        
        return 'server_error';
    }
}

// Criar instância global
const errorHandler = new ErrorHandler();

// Exportar instância global
window.ErrorHandler = errorHandler;

// Exportar para ambiente CommonJS (se aplicável)
if (typeof module !== 'undefined' && module.exports) {
  module.exports = errorHandler;
}