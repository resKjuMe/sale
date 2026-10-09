const MAX_EDGE = 2000;
const QUALITY = 0.85;

export async function downscale(file) {
    if (!file.type.startsWith('image/')) {
        return file;
    }

    const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    const scale = Math.min(1, MAX_EDGE / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);
    canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close();

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', QUALITY));
    if (!blob || (scale === 1 && blob.size >= file.size)) {
        return file;
    }

    const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
    return new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() });
}

// Alpine-Komponente: Vorschau + Verkleinerung vor dem Upload (PHP-Limit, mobile Daten).
async function downscaleInput(input) {
    const files = await Promise.all([...input.files].map((file) => downscale(file).catch(() => file)));
    const transfer = new DataTransfer();
    files.forEach((file) => transfer.items.add(file));
    input.files = transfer.files;
}

const MAX_EXTRAS = 8;

export default (initialPreview = null) => ({
    preview: initialPreview,
    processing: false,
    extras: [],
    // Schnellerfassung: Fotos einzeln nacheinander sammeln, statt sie auf einmal zu wählen.
    collected: [],

    async collect(event) {
        const input = event.target;
        const files = [...input.files].slice(0, MAX_EXTRAS - this.collected.length);
        input.value = '';
        this.processing = true;
        try {
            for (const file of files) {
                const resized = await downscale(file).catch(() => file);
                this.collected.push({ file: resized, url: URL.createObjectURL(resized) });
            }
        } finally {
            this.processing = false;
            this.syncCollected();
        }
    },

    removeCollected(index) {
        URL.revokeObjectURL(this.collected[index].url);
        this.collected.splice(index, 1);
        this.syncCollected();
    },

    syncCollected() {
        const transfer = new DataTransfer();
        this.collected.forEach(({ file }) => transfer.items.add(file));
        this.$refs.photos.files = transfer.files;
    },

    async pickExtras(event) {
        const input = event.target;
        this.extras.forEach((url) => URL.revokeObjectURL(url));
        this.extras = [...input.files].map((file) => URL.createObjectURL(file));
        this.processing = true;
        try {
            await downscaleInput(input);
        } finally {
            this.processing = false;
        }
    },

    async pick(event) {
        const input = event.target;
        const file = input.files[0];
        if (!file) {
            return;
        }

        this.processing = true;
        this.preview = URL.createObjectURL(file);

        try {
            const resized = await downscale(file);
            if (resized !== file) {
                const transfer = new DataTransfer();
                transfer.items.add(resized);
                input.files = transfer.files;
            }
        } catch (e) {
            console.warn('Bild konnte nicht verkleinert werden, Original wird hochgeladen.', e);
        } finally {
            this.processing = false;
        }
    },
});
