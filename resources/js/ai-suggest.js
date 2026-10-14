// Füllt Marke/Größe bzw. Titel per KI – nur auf Knopfdruck. Schickt die gerade gewählten Fotos mit;
// bei bestehenden Artikeln ergänzt der Server die gespeicherten.
const FIELDS = { brand_size: ['brand', 'size'], title: ['title'] };

function field(id) {
    return document.getElementById(id);
}

const euro = new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' });

export default ({ url, articleId = null, priceUrl = null }) => ({
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

    // Speichert nur den Vorschlag; Preis und Verkaufspreis gehen bewusst nicht mit.
    async suggestPrice() {
        if (this.busy || !priceUrl) {
            return;
        }

        this.busy = 'price';
        this.show('Preis wird geschätzt …');

        try {
            const response = await fetch(priceUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(Object.fromEntries(['title', 'brand', 'size', 'condition'].map((id) => [id, field(id)?.value || null]))),
            });
            const data = await response.json().catch(() => ({}));
            if ([401, 419].includes(response.status)) {
                throw new Error('Sitzung abgelaufen – bitte die Seite neu laden.');
            }
            if (!response.ok) {
                throw new Error(data.message ?? 'Die KI ist gerade nicht erreichbar.');
            }
            if (data.price == null) {
                this.show('Für diesen Artikel gab es keinen Preisvorschlag.', true);
                return;
            }

            const input = field('price_suggestion');
            input.value = euro.format(data.price);
            field('price_reason').textContent = data.reason ?? '';
            field('price_suggestion_field').classList.remove('hidden');
            input.classList.add('ring-2', 'ring-violet-400');
            setTimeout(() => input.classList.remove('ring-2', 'ring-violet-400'), 2500);
            this.show(`Vorschlag: ${euro.format(data.price)}` + (data.reason ? ` – ${data.reason}` : ''));
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
