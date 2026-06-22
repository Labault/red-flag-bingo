import { Controller } from '@hotwired/stimulus';

// Garde un élément "ancre" visuellement stable malgré les Turbo Streams
// qui modifient le layout autour. Concrètement : on capture la position
// de l'ancre avant le rendu, puis on ajuste window.scrollY après pour
// que l'ancre reste au même endroit à l'écran.
export default class extends Controller {
    static targets = ['anchor'];

    connect() {
        this.boundHandler = this.beforeStreamRender.bind(this);
        document.addEventListener('turbo:before-stream-render', this.boundHandler);
    }

    disconnect() {
        document.removeEventListener('turbo:before-stream-render', this.boundHandler);
    }

    beforeStreamRender(event) {
        if (!this.hasAnchorTarget) {
            return;
        }

        const anchor    = this.anchorTarget;
        const beforeTop = anchor.getBoundingClientRect().top;

        const originalRender = event.detail.render;
        event.detail.render = async (streamElement) => {
            await originalRender(streamElement);

            const delta = anchor.getBoundingClientRect().top - beforeTop;
            if (0 !== delta) {
                window.scrollBy(0, delta);
            }
        };
    }
}
