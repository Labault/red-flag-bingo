import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        url:      { type: String, default: '' },
        feedback: { type: String, default: 'Copié !' },
        duration: { type: Number, default: 1500 },
    };

    async copy(event) {
        event.preventDefault();

        const url = this.urlValue || window.location.href;

        try {
            await navigator.clipboard.writeText(url);
        } catch (err) {
            console.error('Clipboard write failed:', err);
            return;
        }

        const original = this.element.textContent;
        this.element.textContent = this.feedbackValue;

        if (this.timer) {
            clearTimeout(this.timer);
        }
        this.timer = setTimeout(() => {
            this.element.textContent = original;
        }, this.durationValue);
    }

    disconnect() {
        if (this.timer) {
            clearTimeout(this.timer);
        }
    }
}
