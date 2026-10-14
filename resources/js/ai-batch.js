// Setzt Titel oder Preisvorschläge mehrerer Artikel nacheinander per KI; einzeln, damit Fortschritt sichtbar ist und nichts in ein Timeout läuft.
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const KINDS = {
    title: {
        confirm: (count) => `Für alle ${count} Artikel den Titel per KI setzen? Vorhandene Titel werden überschrieben.`,
        done: (count) => `${count} Titel gesetzt`,
    },
    price: {
        confirm: (count) => `Für alle ${count} Artikel einen Vinted-Preis per KI vorschlagen? Vorhandene Vorschläge werden überschrieben, die Preise selbst bleiben.`,
        done: (count) => `${count} Preisvorschläge gesetzt`,
    },
};

export default ({ urls }) => ({
    open: false,
    running: false,
    stopping: false,
    kind: 'title',
    done: 0,
    failed: 0,
    message: '',

    get total() {
        return urls[this.kind].length;
    },

    async start(kind) {
        this.open = false;
        if (this.running || !confirm(KINDS[kind].confirm(urls[kind].length))) {
            return;
        }

        this.kind = kind;
        this.running = true;
        this.stopping = false;
        this.done = 0;
        this.failed = 0;
        this.message = '';
        const token = document.querySelector('meta[name="csrf-token"]').content;

        for (const url of urls[kind]) {
            if (this.stopping) {
                break;
            }
            try {
                let response;
                // Bei Ratenlimit warten und denselben Artikel erneut versuchen.
                while ((response = await fetch(url, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token } })).status === 429) {
                    this.message = 'Kurze Pause wegen Ratenlimit …';
                    await sleep((Number(response.headers.get('Retry-After')) || 10) * 1000);
                    this.message = '';
                }
                if ([401, 419].includes(response.status)) {
                    this.message = 'Sitzung abgelaufen – bitte die Seite neu laden.';
                    break;
                }
                if (response.status === 503) {
                    this.message = (await response.json().catch(() => ({}))).message ?? 'Die KI ist nicht erreichbar.';
                    break;
                }
                // Antwort: {"title": …} bzw. {"price": …}, null ohne Ergebnis.
                if (!response.ok || (await response.json())[kind] == null) {
                    this.failed++;
                }
            } catch (e) {
                this.failed++;
            }
            this.done++;
        }

        this.running = false;
        if (!this.message) {
            this.message = KINDS[kind].done(this.done - this.failed) + (this.failed ? `, ${this.failed} ohne Ergebnis.` : '.');
        }
        if (this.done > this.failed) {
            window.location.reload();
        }
    },
});
