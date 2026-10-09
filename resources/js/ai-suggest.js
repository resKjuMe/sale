// Füllt Marke/Größe bzw. Titel per KI – nur auf Knopfdruck. Schickt die gerade gewählten Fotos mit;
// bei bestehenden Artikeln ergänzt der Server die gespeicherten.
const FIELDS = { brand_size: ['brand', 'size'], title: ['title'] };

function field(id) {
    return document.getElementById(id);
}

export default ({ url, articleId = null }) => ({
    busy: null,
    message: '',
    failed: false,

    async suggest(mode) {
        if (this.busy) {
            return;
        }

        const body = new FormData();
        body.append('mode', mode);
        if (articleId) {
            body.append('article_id', articleId);
        }
        for (const input of [field('image'), field('photos')]) {
            [...(input?.files ?? [])].forEach((file) => body.append('images[]', file));
        }
        ['brand', 'size'].forEach((id) => body.append(id, field(id)?.value ?? ''));

        if (!articleId && !body.has('images[]')) {
            this.show('Bitte zuerst ein Foto auswählen.', true);
            return;
        }

        this.busy = mode;
        this.show(mode === 'title' ? 'Titel wird vorgeschlagen …' : 'Fotos werden ausgewertet …');

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body,
            });
            const data = await response.json().catch(() => ({}));
            if ([401, 419].includes(response.status)) {
                throw new Error('Sitzung abgelaufen – bitte die Seite neu laden.');
            }
            if (!response.ok) {
                throw new Error(data.message ?? 'Die KI ist gerade nicht erreichbar.');
            }

            const filled = FIELDS[mode].filter((id) => data[id] && field(id));
            filled.forEach((id) => {
                const input = field(id);
                input.value = data[id];
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.classList.add('ring-2', 'ring-violet-400');
                setTimeout(() => input.classList.remove('ring-2', 'ring-violet-400'), 2500);
            });
            this.show(filled.length ? 'Vorschlag übernommen – bitte kurz prüfen.' : 'Auf den Fotos war nichts Eindeutiges zu erkennen.', !filled.length);
        } catch (e) {
            this.show(e.message, true);
        } finally {
            this.busy = null;
        }
    },

    show(message, failed = false) {
        this.message = message;
        this.failed = failed;
    },
});
