const STORAGE_KEY = 'sellMode';

function remember(active) {
    try {
        sessionStorage.setItem(STORAGE_KEY, active ? '1' : '0');
    } catch (e) {
        // ohne Storage gilt der Modus nur bis zum Seitenwechsel
    }
}

function recall() {
    try {
        return sessionStorage.getItem(STORAGE_KEY) === '1';
    } catch (e) {
        return false;
    }
}

export default ({ sold, details }) => ({
    active: recall(),
    sold,
    details,
    changed: {},
    busy: {},
    message: '',

    setActive(active) {
        this.active = active;
        this.message = '';
        remember(active);
    },

    async tap(event, id, url) {
        if (!this.active) {
            return;
        }
        event.preventDefault();

        if (this.busy[id]) {
            return;
        }

        const target = !this.sold[id];
        if (!target && this.details[id] && !confirm('Käufer, Verkaufspreis und Versanddaten dieses Artikels werden gelöscht. Trotzdem als verfügbar markieren?')) {
            return;
        }

        this.busy[id] = true;
        this.sold[id] = target;
        this.changed[id] = true;

        try {
            const response = await fetch(url, {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ sold: target }),
            });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            this.details[id] = false;
            this.message = target ? 'Als verkauft markiert.' : 'Wieder verfügbar.';
        } catch (e) {
            this.sold[id] = !target;
            this.message = 'Speichern fehlgeschlagen – bitte erneut tippen.';
        } finally {
            this.busy[id] = false;
        }
    },
});
