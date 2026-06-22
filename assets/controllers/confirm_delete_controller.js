import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['modal', 'name', 'active', 'archived', 'cards', 'form', 'token'];

    open(event) {
        const params = event.params;

        this.nameTarget.textContent = params.name;
        this.activeTarget.textContent = params.active;
        this.archivedTarget.textContent = params.archived;
        this.cardsTarget.textContent = params.cards;
        this.formTarget.action = params.action;
        this.tokenTarget.value = params.token;

        this.modalTarget.classList.remove('hidden');

        // Ferme avec Escape
        this._escapeHandler = (e) => {
            if (e.key === 'Escape') this.close();
        };
        document.addEventListener('keydown', this._escapeHandler);
    }

    close() {
        this.modalTarget.classList.add('hidden');
        if (this._escapeHandler) {
            document.removeEventListener('keydown', this._escapeHandler);
            this._escapeHandler = null;
        }
    }
}
