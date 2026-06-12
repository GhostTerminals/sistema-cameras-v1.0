window.DashboardCore = class DashboardCore {
    constructor() {
        this.data = null;
        this.chart = null;
        this.updateInterval = null;
    }
    async fetchData(endpoint) {
        try {
            const response = await fetchWithTimeout(endpoint);
            return await response.json();
        } catch (error) {
            console.error('Erro ao buscar dados:', error);
            throw error;
        }
    }
    animateCounter(element, start, end, duration = 1000) {
        let startTimestamp = null;
        const step = (timestamp) => {
            if (!startTimestamp) startTimestamp = timestamp;
            const progress = Math.min((timestamp - startTimestamp) / duration, 1);
            const value = Math.floor(progress * (end - start) + start);
            element.textContent = value;
            if (progress < 1) {
                window.requestAnimationFrame(step);
            }
        };
        window.requestAnimationFrame(step);
    }
};