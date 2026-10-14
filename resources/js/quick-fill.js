// Beträge direkt in der Liste nachtragen; danach weiter zum nächsten offenen Artikel.
export default () => ({
    busy: false,
    done: false,
    error: '',
    summary: '',

    inputs() {
        return [...this.$root.querySelectorAll('input[inputmode="decimal"]')];
    },

    focusNext(input) {
        const inputs = this.inputs();
        inputs[inputs.indexOf(input) + 1]?.focus();
    },

    // Übernimmt den einen erfassten Betrag in das leere Feld.
    copyFirst() {
        const [first, second] = this.inputs();
        if (first.value && !second.value) {
            second.value = first.value;
        } else if (second.value && !first.value) {
            first.value = second.value;
        }
    },

    focusNextRow() {
        const rows = [...document.querySelectorAll('[data-quick-fill]')];
        const next = rows.slice(rows.indexOf(this.$root) + 1).find((row) => !window.Alpine.$data(row).done);
        // Fokus bleibt in einem Eingabefeld, damit die Handy-Tastatur offen bleibt.
        const input = next && ([...next.querySelectorAll('input[inputmode="decimal"]')].find((field) => !field.value)
            ?? next.querySelector('input[inputmode="decimal"]'));
        if (input) {
            input.focus({ preventScroll: true });
            input.scrollIntoView({ block: 'center', behavior: 'smooth' });
        } else {
            document.activeElement?.blur();
        }
    },

    async submit(event) {
        if (this.busy) {
            return;
        }
        const form = event.target;
        const body = new FormData(form);
        if (event.submitter?.name) {
            body.append(event.submitter.name, event.submitter.value);
        }

        this.busy = true;
        this.error = '';
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body,
            });
            const result = await response.json().catch(() => ({}));
            if (response.status === 422) {
                this.error = Object.values(result.errors ?? {})[0]?.[0] ?? result.message ?? 'Bitte Eingabe prüfen.';
                return;
            }
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            this.summary = result.summary;
            if (result.missing) {
                this.error = `Gespeichert (${result.summary}) – es fehlt noch ein Betrag über 0 €.`;
                return;
            }
            this.done = true;
            window.dispatchEvent(new CustomEvent('quick-fill-done'));
            this.$nextTick(() => this.focusNextRow());
        } catch (e) {
            this.error = 'Speichern fehlgeschlagen – bitte erneut versuchen.';
        } finally {
            this.busy = false;
        }
    },
});
