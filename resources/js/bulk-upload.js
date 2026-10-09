import { downscale } from './article-photo';

const FIELDS = ['title', 'brand', 'size', 'condition', 'price'];

// Dateien bleiben außerhalb des reaktiven Zustands, damit FormData echte File-Objekte bekommt.
const files = new Map();
let nextId = 1;

export default ({ storeUrl, doneUrl }) => ({
    items: [],
    defaults: { brand: '', size: '', condition: '' },
    saving: false,
    dragging: false,
    savedCount: 0,
    leaving: false,

    init() {
        window.addEventListener('beforeunload', (event) => {
            if (this.items.length && !this.leaving) {
                event.preventDefault();
            }
        });
    },

    get preparing() {
        return this.items.some((item) => item.preparing);
    },

    get pendingCount() {
        return this.items.length;
    },

    addFiles(event) {
        const selected = [...event.target.files];
        event.target.value = '';
        return this.addFileList(selected);
    },

    // Drag & Drop auf die ganze Seite, damit ein Fehlwurf den Browser nicht wegnavigiert.
    dragOver(event) {
        if (!event.dataTransfer?.types.includes('Files')) {
            return;
        }
        event.preventDefault();
        event.dataTransfer.dropEffect = this.saving ? 'none' : 'copy';
        this.dragging = !this.saving;
    },

    dragLeave(event) {
        if (!event.relatedTarget) {
            this.dragging = false;
        }
    },

    drop(event) {
        if (!event.dataTransfer?.types.includes('Files')) {
            return;
        }
        event.preventDefault();
        this.dragging = false;
        if (!this.saving) {
            this.addFileList([...event.dataTransfer.files].filter((file) => file.type.startsWith('image/')));
        }
    },

    async addFileList(selected) {
        const created = selected.map((file) => {
            const item = {
                id: nextId++,
                preview: URL.createObjectURL(file),
                preparing: true,
                saving: false,
                errors: {},
                title: '',
                brand: this.defaults.brand,
                size: this.defaults.size,
                condition: this.defaults.condition,
                price: '',
            };
            files.set(item.id, file);
            this.items.push(item);
            return item;
        });

        for (const item of created) {
            try {
                files.set(item.id, await downscale(files.get(item.id)));
            } catch (e) {
                console.warn('Bild konnte nicht verkleinert werden, Original wird hochgeladen.', e);
            }
            const current = this.find(item.id);
            if (current) {
                current.preparing = false;
            }
        }
    },

    find(id) {
        return this.items.find((item) => item.id === id);
    },

    remove(item) {
        URL.revokeObjectURL(item.preview);
        files.delete(item.id);
        this.items = this.items.filter((other) => other.id !== item.id);
    },

    applyDefaults() {
        for (const item of this.items) {
            for (const [field, value] of Object.entries(this.defaults)) {
                if (value !== '' && item[field] === '') {
                    item[field] = value;
                }
            }
        }
    },

    validate() {
        let valid = true;
        for (const item of this.items) {
            item.errors = {};
            if (!item.brand.trim()) {
                item.errors.brand = 'Marke fehlt.';
                valid = false;
            }
            if (!item.size.trim()) {
                item.errors.size = 'Größe fehlt.';
                valid = false;
            }
        }
        return valid;
    },

    async saveAll() {
        if (this.saving || this.preparing || !this.items.length || !this.validate()) {
            this.$nextTick(() => this.$root.querySelector('.has-error')?.scrollIntoView({ block: 'center', behavior: 'smooth' }));
            return;
        }

        this.saving = true;
        const token = document.querySelector('meta[name="csrf-token"]').content;

        for (const item of [...this.items]) {
            item.saving = true;
            const body = new FormData();
            body.append('image', files.get(item.id));
            FIELDS.forEach((field) => body.append(field, item[field] ?? ''));

            try {
                const response = await fetch(storeUrl, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token },
                    body,
                });

                if (response.ok) {
                    this.savedCount++;
                    this.remove(item);
                    continue;
                }

                const data = await response.json().catch(() => ({}));
                item.errors = Object.fromEntries(
                    Object.entries(data.errors ?? { image: [data.message || `Fehler ${response.status}`] })
                        .map(([field, messages]) => [field, messages[0]]),
                );
            } catch (e) {
                item.errors = { image: 'Keine Verbindung zum Server.' };
            }
            item.saving = false;
        }

        this.saving = false;

        if (!this.items.length) {
            this.leaving = true;
            window.location.href = `${doneUrl}?saved=${this.savedCount}`;
        }
    },
});
