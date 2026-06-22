import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['zone', 'input', 'placeholder', 'filename'];

    openFileDialog(event) {
        // Évite le double déclenchement quand on clique sur l'input lui-même
        if (event.target === this.inputTarget) return;
        this.inputTarget.click();
    }

    highlight(event) {
        event.preventDefault();
        this.zoneTarget.classList.add('is-dragover');
    }

    unhighlight(event) {
        event.preventDefault();
        this.zoneTarget.classList.remove('is-dragover');
    }

    drop(event) {
        event.preventDefault();
        this.zoneTarget.classList.remove('is-dragover');

        const files = event.dataTransfer.files;
        if (files.length === 0) return;

        // Assigne les fichiers droppés à l'input file
        this.inputTarget.files = files;
        this.fileSelected();
    }

    fileSelected() {
        const file = this.inputTarget.files[0];
        if (!file) return;

        this.placeholderTarget.classList.add('hidden');
        this.filenameTarget.classList.remove('hidden');
        this.filenameTarget.textContent = '✓ ' + file.name;
        this.zoneTarget.classList.add('has-file');
    }
}
