import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['tab', 'panel'];

    select(event) {
        const key = event.currentTarget.dataset.themeKey;

        this.tabTargets.forEach((tab) => {
            const active = tab.dataset.themeKey === key;
            tab.classList.toggle('theme-tab-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        this.panelTargets.forEach((panel) => {
            const active = panel.dataset.themeKey === key;
            panel.classList.toggle('hidden', !active);
        });
    }
}
