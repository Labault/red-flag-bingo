import { Controller } from '@hotwired/stimulus';
import Chart from 'chart.js/auto';

/**
 * Contrôleur générique pour rendre un graphique Chart.js.
 * Lit la config depuis data-chart-config-value (JSON).
 */
export default class extends Controller {
    static targets = ['canvas'];
    static values = { config: Object };

    connect() {
        if (!this.hasCanvasTarget || !this.configValue) return;

        // Désactive les animations pour la perf et un rendu pro
        const config = {
            ...this.configValue,
            options: {
                ...(this.configValue.options || {}),
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 600 },
            },
        };

        this.chart = new Chart(this.canvasTarget, config);
    }

    disconnect() {
        if (this.chart) {
            this.chart.destroy();
            this.chart = null;
        }
    }
}
