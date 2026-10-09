const STORAGE_KEY = 'sellMode';
const SELECTION_KEY = 'batchSelection';

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

// Auswahl übersteht das Live-Filtern (das die Komponente neu aufbaut) und Seitenwechsel.
function rememberSelection(selecting, selected) {
    try {
        sessionStorage.setItem(SELECTION_KEY, JSON.stringify({ selecting, ids: Object.keys(selected).filter((id) => selected[id]) }));
    } catch (e) {
        // ohne Storage nur bis zum nächsten Neuaufbau
    }
}

function recallSelection() {
    try {
        const { selecting = false, ids = [] } = JSON.parse(sessionStorage.getItem(SELECTION_KEY) ?? '{}');
        return { selecting, selected: Object.fromEntries(ids.map((id) => [id, true])) };
    } catch (e) {
        return { selecting: false, selected: {} };
    }
}

export default ({ sold, details, ids = [], batchUrl = null }) => ({
    active: recall(),
    sold,
    details,
    changed: {},
    busy: {},
    message: '',
    ids,
    batchUrl,
    ...recallSelection(),

    get selectedIds() {
        return Object.keys(this.selected).filter((id) => this.selected[id]);
    },

    get allSelected() {
        return this.ids.length > 0 && this.ids.every((id) => this.selected[id]);
    },

    setActive(active) {
        this.active = active;
        this.message = '';
        remember(active);
        if (active) {
            this.setSelecting(false);
        }
    },

    setSelecting(selecting) {
        this.selecting = selecting;
        if (selecting) {
            this.setActive(false);
        } else {
            this.selected = {};
        }
        rememberSelection(this.selecting, this.selected);
    },

    toggleAll() {
        const target = !this.allSelected;
        this.ids.forEach((id) => { this.selected[id] = target; });
        rememberSelection(this.selecting, this.selected);
    },

    openBatch() {
        const params = new URLSearchParams();
        this.selectedIds.forEach((id) => params.append('ids[]', id));
        params.append('back', window.location.href);
        this.setSelecting(false);
        window.location.href = `${this.batchUrl}?${params}`;
    },

    async tap(event, id, url) {
        if (this.selecting) {
            event.preventDefault();
            this.selected[id] = !this.selected[id];
            rememberSelection(this.selecting, this.selected);
            return;
        }
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
