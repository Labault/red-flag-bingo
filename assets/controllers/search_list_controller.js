import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'list', 'item', 'empty'];

    filter() {
        const query = this.inputTarget.value.trim().toLowerCase();
        let visibleCount = 0;

        this.itemTargets.forEach((item) => {
            const text = item.dataset.searchText || '';
            const matches = query === '' || text.includes(query);

            item.style.display = matches ? '' : 'none';
            if (matches) visibleCount++;
        });

        // Affichage du message "aucun résultat"
        if (this.hasEmptyTarget) {
            if (visibleCount === 0 && this.itemTargets.length > 0) {
                this.emptyTarget.classList.remove('hidden');
                this.listTarget.classList.add('hidden');
            } else {
                this.emptyTarget.classList.add('hidden');
                this.listTarget.classList.remove('hidden');
            }
        }
    }
}
