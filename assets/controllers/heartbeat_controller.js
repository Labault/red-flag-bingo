import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        url: String,
        interval: { type: Number, default: 5000 },
    };

    connect() {
        // viewerId unique par onglet, persisté pour la durée de la session
        this.viewerId = sessionStorage.getItem('rfb_viewer_id');
        if (!this.viewerId) {
            this.viewerId = crypto.randomUUID();
            sessionStorage.setItem('rfb_viewer_id', this.viewerId);
        }

        // Premier ping immédiat puis toutes les N ms
        this.sendHeartbeat();
        this.timer = setInterval(() => this.sendHeartbeat(), this.intervalValue);
    }

    disconnect() {
        if (this.timer) {
            clearInterval(this.timer);
        }
    }

    sendHeartbeat() {
        const formData = new FormData();
        formData.append('viewerId', this.viewerId);

        fetch(this.urlValue, {
            method: 'POST',
            body: formData,
        }).catch((err) => console.error('Heartbeat failed:', err));
    }
}
