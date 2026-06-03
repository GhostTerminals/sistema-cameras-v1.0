window.ThemeManager = class ThemeManager {
    constructor() {
        this.theme = localStorage.getItem('theme') || 'light';
        this.init();
    }
    init() {
        this.applyTheme();
        this.setupToggle();
    }
    applyTheme() {
        document.documentElement.setAttribute('data-theme', this.theme);
    }
    setupToggle() {
        const toggle = document.getElementById('themeToggle');
        if (toggle) {
            toggle.addEventListener('click', () => {
                this.theme = this.theme === 'dark' ? 'light' : 'dark';
                this.applyTheme();
                localStorage.setItem('theme', this.theme);
                const icon = toggle.querySelector('i');
                if (icon) {
                    icon.className = this.theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
                }
            });
        }
    }
};