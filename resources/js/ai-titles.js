// Setzt die Titel mehrerer Artikel nacheinander per KI; einzeln, damit Fortschritt sichtbar ist und nichts in ein Timeout läuft.
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

export default ({ urls }) => ({
    running: false,
    stopping: false,
    done: 0,
    failed: 0,
    message: '',

    get total() {
        return urls.length;
    },

    async start() {
        if (this.running || !confirm(`Für alle ${urls.length} Artikel den Titel per KI setzen? Vorhandene Titel werden überschrieben.`)) {
            return;
        }

        this.running = true;
        this.stopping = false;
        this.done = 0;
        this.failed = 0;
        this.message = '';
        const token = document.querySelector('meta[name="csrf-token"]').content;

        for (const url of urls) {
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
                if (!response.ok || !(await response.json()).title) {
                    this.failed++;
                }
            } catch (e) {
                this.failed++;
            }
            this.done++;
        }

        this.running = false;
        if (!this.message) {
            this.message = `${this.done - this.failed} Titel gesetzt` + (this.failed ? `, ${this.failed} ohne Ergebnis.` : '.');
        }
        if (this.done > this.failed) {
            window.location.reload();
        }
    },
});
