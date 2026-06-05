/**
 * Dark Mode Toggle Manager
 * Gerencia tema escuro/claro com persistência em localStorage
 */
class ThemeManager {
  constructor() {
    this.STORAGE_KEY = 'app_theme_preference';
    this.DARK_MODE_CLASS = 'dark-mode';
    this.init();
  }

  init() {
    this.detectSystemPreference();
    this.setupToggle();
    this.setupListeners();
  }

  detectSystemPreference() {
    // Verificar preferência salva
    const savedTheme = localStorage.getItem(this.STORAGE_KEY);
    if (savedTheme) {
      this.setTheme(savedTheme);
      return;
    }

    // Verificar preferência do sistema
    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
      this.setTheme('dark');
    } else {
      this.setTheme('light');
    }
  }

  setTheme(theme) {
    const isDark = theme === 'dark';
    const html = document.documentElement;
    const body = document.body;

    if (isDark) {
      body.classList.add(this.DARK_MODE_CLASS);
      html.setAttribute('data-theme', 'dark');
    } else {
      body.classList.remove(this.DARK_MODE_CLASS);
      html.setAttribute('data-theme', 'light');
    }

    localStorage.setItem(this.STORAGE_KEY, theme);
    this.updateToggleButton(isDark);
  }

  setupToggle() {
    const toggle = document.getElementById('themeToggle');
    if (toggle) {
      toggle.addEventListener('click', () => {
        const currentTheme = localStorage.getItem(this.STORAGE_KEY) || 'light';
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        this.setTheme(newTheme);
      });
    }
  }

  setupListeners() {
    // Detectar mudanças no sistema operacional
    if (window.matchMedia) {
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
        const newTheme = e.matches ? 'dark' : 'light';
        this.setTheme(newTheme);
      });
    }
  }

  updateToggleButton(isDark) {
    const toggle = document.getElementById('themeToggle');
    if (toggle) {
      const icon = toggle.querySelector('i');
      if (icon) {
        icon.classList.toggle('fa-moon', !isDark);
        icon.classList.toggle('fa-sun', isDark);
      }
      toggle.setAttribute('aria-label', isDark ? 'Ativar modo claro' : 'Ativar modo escuro');
      toggle.title = isDark ? 'Modo claro' : 'Modo escuro';
    }
  }

  getCurrentTheme() {
    return localStorage.getItem(this.STORAGE_KEY) || 'light';
  }
}

/**
 * Skeleton Loading Manager
 * Gerencia exibição de skeleton loaders durante carregamento
 */
class SkeletonLoader {
  constructor() {
    this.loaders = new Map();
  }

  /**
   * Criar skeleton loader
   * @param {string} elementId - ID do elemento
   * @param {Object} options - Opções
   * @param {number} options.lines - Número de linhas (padrão: 3)
   * @param {string} options.type - Tipo: 'text', 'card', 'avatar', 'list'
   */
  create(elementId, options = {}) {
    const element = document.getElementById(elementId);
    if (!element) {
      console.warn(`Element ${elementId} not found`);
      return;
    }

    const { lines = 3, type = 'text' } = options;
    const skeleton = this.buildSkeleton(type, lines);
    
    element.innerHTML = skeleton;
    element.classList.add('skeleton-container', 'loading');
    this.loaders.set(elementId, { element, skeleton });
  }

  /**
   * Construir skeleton HTML
   */
  buildSkeleton(type, lines) {
    switch (type) {
      case 'card':
        return `
          <div class="skeleton-card">
            <div class="skeleton skeleton-heading"></div>
            ${Array(lines).fill().map(() => '<div class="skeleton skeleton-text"></div>').join('')}
          </div>
        `;
      
      case 'avatar':
        return `
          <div style="display: flex; gap: 1rem;">
            <div class="skeleton skeleton-avatar"></div>
            <div style="flex: 1;">
              <div class="skeleton skeleton-heading"></div>
              <div class="skeleton skeleton-text"></div>
            </div>
          </div>
        `;
      
      case 'list':
        return `
          <div class="skeleton-card">
            ${Array(lines).fill().map(() => `
              <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                <div class="skeleton" style="width: 40px; height: 40px; border-radius: 50%;"></div>
                <div style="flex: 1;">
                  <div class="skeleton skeleton-text" style="width: 60%;"></div>
                  <div class="skeleton skeleton-text" style="width: 40%;"></div>
                </div>
              </div>
            `).join('')}
          </div>
        `;
      
      case 'text':
      default:
        return `
          <div class="skeleton-card">
            ${Array(lines).fill().map(() => '<div class="skeleton skeleton-text"></div>').join('')}
          </div>
        `;
    }
  }

  /**
   * Remover skeleton loader
   */
  remove(elementId) {
    const loader = this.loaders.get(elementId);
    if (loader) {
      loader.element.innerHTML = '';
      loader.element.classList.remove('skeleton-container', 'loading');
      this.loaders.delete(elementId);
    }
  }

  /**
   * Mostrar skeleton por tempo determinado (útil para testes)
   */
  async show(elementId, duration = 2000, options = {}) {
    this.create(elementId, options);
    return new Promise((resolve) => {
      setTimeout(() => {
        this.remove(elementId);
        resolve();
      }, duration);
    });
  }
}

/**
 * Accessibility Helper
 * Melhorias de acessibilidade
 */
class AccessibilityHelper {
  static init() {
    this.setupAriaLabels();
    this.setupKeyboardNavigation();
    this.setupLiveRegions();
  }

  static setupAriaLabels() {
    // Adicionar role e aria-labels automáticos
    document.querySelectorAll('[data-aria-label]').forEach(el => {
      el.setAttribute('aria-label', el.getAttribute('data-aria-label'));
    });
  }

  static setupKeyboardNavigation() {
    // Permitir navegação por teclado em modais
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        const modal = document.querySelector('.modal.show');
        if (modal) {
          const closeBtn = modal.querySelector('[data-bs-dismiss="modal"]');
          if (closeBtn) closeBtn.click();
        }
      }
    });
  }

  static setupLiveRegions() {
    // Criar live region para anúncios
    const liveRegion = document.createElement('div');
    liveRegion.id = 'aria-live-region';
    liveRegion.setAttribute('aria-live', 'polite');
    liveRegion.setAttribute('aria-atomic', 'true');
    liveRegion.className = 'visually-hidden';
    document.body.appendChild(liveRegion);
  }

  static announce(message) {
    const liveRegion = document.getElementById('aria-live-region');
    if (liveRegion) {
      liveRegion.textContent = message;
    }
  }
}

// Inicializar quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
  // Theme Manager
  new ThemeManager();

  // Skeleton Loader (instância mantida para side effects, se necessário)
  new SkeletonLoader();

  // Accessibility
  AccessibilityHelper.init();
});

// Exportar para uso em outros módulos (se usando módulos ES6)
if (typeof module !== 'undefined' && module.exports) {
  module.exports = { ThemeManager, SkeletonLoader, AccessibilityHelper };
}
