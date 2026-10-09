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

export default (initialPreview = null) => ({
    preview: initialPreview,
    processing: false,
    extras: [],

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
