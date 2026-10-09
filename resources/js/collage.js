const WIDTH = 1080;
const HEIGHT = 1920;
const PADDING = 40;
const GAP = 20;
const HEADER = 96;
const QUALITY = 0.92;
const FONT = '"Figtree", ui-sans-serif, system-ui, sans-serif';
const COLORS = { background: '#f4efe8', ink: '#1f2937', muted: '#6b7280', placeholder: '#e5e0d8' };

export const GRIDS = [[2, 2], [2, 3], [3, 3], [3, 4], [3, 5], [4, 5], [4, 6]];

export function formatSize(raw) {
    const size = String(raw).trim()
        .replace(/\s*\/\s*/g, ' / ')
        .replace(/(\d)\s*[-–]\s*(\d)/g, '$1–$2')
        .replace(/\b(monate?|jahre?)\b/gi, (word) => word[0].toUpperCase() + word.slice(1).toLowerCase());

    // Reine Zahlen und Zahlenbereiche wie „68 / 72" bekommen das Präfix, „6–12 Monate" oder „XL" nicht.
    return /^[\d\s/–.,]+$/.test(size) ? `Gr. ${size}` : size;
}

const images = new Map();

function loadImage(src) {
    if (!images.has(src)) {
        images.set(src, new Promise((resolve) => {
            const img = new Image();
            img.onload = () => resolve(img);
            img.onerror = () => resolve(null);
            img.src = src;
        }));
    }

    return images.get(src);
}

function font(size, weight) {
    return `${weight} ${Math.round(size)}px ${FONT}`;
}

// Verkleinert bis minSize und kürzt danach mit Auslassungszeichen.
function fitText(ctx, text, maxWidth, size, minSize, weight) {
    for (; size > minSize; size -= 1) {
        ctx.font = font(size, weight);
        if (ctx.measureText(text).width <= maxWidth) {
            return { text, size };
        }
    }

    ctx.font = font(minSize, weight);
    let shortened = text;
    while (shortened.length > 1 && ctx.measureText(shortened + '…').width > maxWidth) {
        shortened = shortened.slice(0, -1);
    }

    return { text: shortened === text ? text : shortened.trimEnd() + '…', size: minSize };
}

function roundedRect(ctx, x, y, w, h, r) {
    ctx.beginPath();
    ctx.roundRect(x, y, w, h, r);
}

function drawPill(ctx, label, right, top, maxWidth, size, { fill, color, shadow = true }) {
    const padX = size * 0.6;
    const fitted = fitText(ctx, label, maxWidth - 2 * padX, size, Math.max(12, size * 0.6), 700);
    ctx.font = font(fitted.size, 700);
    const width = ctx.measureText(fitted.text).width + 2 * padX;
    const height = fitted.size * 1.75;
    const left = right - width;

    ctx.save();
    if (shadow) {
        ctx.shadowColor = 'rgba(0, 0, 0, 0.22)';
        ctx.shadowBlur = size * 0.5;
        ctx.shadowOffsetY = size * 0.1;
    }
    ctx.fillStyle = fill;
    roundedRect(ctx, left, top, width, height, height / 2);
    ctx.fill();
    ctx.restore();

    ctx.fillStyle = color;
    ctx.textBaseline = 'middle';
    ctx.fillText(fitted.text, left + padX, top + height / 2 + fitted.size * 0.04);

    return { width, height };
}

function drawCell(ctx, img, article, x, y, w, h) {
    const unit = Math.min(w, h * 0.85);
    const margin = Math.max(10, unit * 0.055);
    const radius = Math.max(10, unit * 0.06);

    ctx.save();
    roundedRect(ctx, x, y, w, h, radius);
    ctx.clip();
    ctx.fillStyle = COLORS.placeholder;
    ctx.fillRect(x, y, w, h);

    if (img) {
        const scale = Math.max(w / img.naturalWidth, h / img.naturalHeight);
        const dw = img.naturalWidth * scale;
        const dh = img.naturalHeight * scale;
        if (article.sold) {
            ctx.filter = 'grayscale(1)';
        }
        ctx.drawImage(img, x + (w - dw) / 2, y + (h - dh) / 2, dw, dh);
        ctx.filter = 'none';
    }

    if (article.sold) {
        ctx.fillStyle = 'rgba(255, 255, 255, 0.45)';
        ctx.fillRect(x, y, w, h);
    }

    const gradient = ctx.createLinearGradient(0, y + h * 0.55, 0, y + h);
    gradient.addColorStop(0, 'rgba(0, 0, 0, 0)');
    gradient.addColorStop(1, 'rgba(0, 0, 0, 0.62)');
    ctx.fillStyle = gradient;
    ctx.fillRect(x, y + h * 0.55, w, h * 0.45);

    const brandSize = Math.min(56, Math.max(20, unit * 0.1));
    if ('letterSpacing' in ctx) {
        ctx.letterSpacing = `${Math.round(brandSize * 0.06)}px`;
    }
    const brand = fitText(ctx, article.brand.toUpperCase(), w - 2 * margin, brandSize, Math.max(16, brandSize * 0.6), 700);
    ctx.font = font(brand.size, 700);
    ctx.fillStyle = '#fff';
    ctx.textBaseline = 'alphabetic';
    ctx.shadowColor = 'rgba(0, 0, 0, 0.35)';
    ctx.shadowBlur = 8;
    ctx.fillText(brand.text, x + margin, y + h - margin);
    ctx.shadowColor = 'transparent';
    if ('letterSpacing' in ctx) {
        ctx.letterSpacing = '0px';
    }
    ctx.restore();

    const pillSize = Math.min(44, Math.max(17, unit * 0.075));
    drawPill(ctx, formatSize(article.size), x + w - margin, y + margin, w - 2 * margin, pillSize, { fill: '#fff', color: COLORS.ink });

    if (article.sold) {
        ctx.font = font(pillSize, 700);
        const label = 'Verkauft';
        const width = ctx.measureText(label).width + pillSize * 1.2;
        drawPill(ctx, label, x + (w + width) / 2, y + h / 2 - pillSize, w - 2 * margin, pillSize, { fill: COLORS.ink, color: '#fff' });
    }
}

async function renderPage({ title, articles, cols, rows, page, pageCount, showHeader }) {
    const canvas = document.createElement('canvas');
    canvas.width = WIDTH;
    canvas.height = HEIGHT;
    const ctx = canvas.getContext('2d');

    ctx.fillStyle = COLORS.background;
    ctx.fillRect(0, 0, WIDTH, HEIGHT);

    let top = PADDING;
    if (showHeader) {
        const counter = pageCount > 1 ? `${page} / ${pageCount}` : '';
        ctx.font = font(30, 600);
        const counterWidth = counter ? ctx.measureText(counter).width + 24 : 0;
        const heading = fitText(ctx, title, WIDTH - 2 * PADDING - counterWidth, 60, 34, 700);

        ctx.textBaseline = 'middle';
        ctx.fillStyle = COLORS.ink;
        ctx.font = font(heading.size, 700);
        ctx.fillText(heading.text, PADDING, top + HEADER / 2);
        if (counter) {
            ctx.font = font(30, 600);
            ctx.fillStyle = COLORS.muted;
            ctx.textAlign = 'right';
            ctx.fillText(counter, WIDTH - PADDING, top + HEADER / 2);
            ctx.textAlign = 'left';
        }
        top += HEADER + GAP;
    }

    const cellWidth = (WIDTH - 2 * PADDING - (cols - 1) * GAP) / cols;
    const cellHeight = (HEIGHT - top - PADDING - (rows - 1) * GAP) / rows;
    const loaded = await Promise.all(articles.map((article) => loadImage(article.image)));

    articles.forEach((article, index) => {
        const col = index % cols;
        const row = Math.floor(index / cols);
        drawCell(ctx, loaded[index], article, PADDING + col * (cellWidth + GAP), top + row * (cellHeight + GAP), cellWidth, cellHeight);
    });

    return new Promise((resolve, reject) => canvas.toBlob(
        (blob) => (blob ? resolve(blob) : reject(new Error('Collage konnte nicht erzeugt werden.'))),
        'image/jpeg',
        QUALITY,
    ));
}

export default ({ articles, title, slug }) => ({
    articles,
    title,
    grids: GRIDS,
    cols: 2,
    rows: 3,
    includeSold: false,
    showHeader: true,
    pages: [],
    busy: false,
    error: '',
    run: 0,

    init() {
        this.$watch('cols', () => this.render());
        this.$watch('rows', () => this.render());
        this.$watch('includeSold', () => this.render());
        this.$watch('showHeader', () => this.render());
        this.render();
    },

    get selected() {
        return this.articles.filter((article) => this.includeSold || !article.sold);
    },

    get pageCount() {
        return Math.ceil(this.selected.length / (this.cols * this.rows));
    },

    get canShare() {
        return this.pages.length > 0 && !!navigator.canShare?.({ files: this.pages.map((page) => page.file) });
    },

    setGrid([cols, rows]) {
        this.cols = cols;
        this.rows = rows;
    },

    async render() {
        const run = ++this.run;
        const selected = this.selected;
        const perPage = this.cols * this.rows;
        const pageCount = this.pageCount;
        this.busy = true;
        this.error = '';

        try {
            await Promise.all([document.fonts.load(font(40, 700)), document.fonts.load(font(40, 600))]).catch(() => {});

            const pages = [];
            for (let page = 1; page <= pageCount; page++) {
                const blob = await renderPage({
                    title: this.title,
                    articles: selected.slice((page - 1) * perPage, page * perPage),
                    cols: this.cols,
                    rows: this.rows,
                    page,
                    pageCount,
                    showHeader: this.showHeader,
                });
                if (run !== this.run) {
                    return;
                }
                const name = `${slug}-collage-${page}${pageCount > 1 ? `-von-${pageCount}` : ''}.jpg`;
                pages.push({ name, url: URL.createObjectURL(blob), file: new File([blob], name, { type: 'image/jpeg' }) });
            }

            this.pages.forEach((page) => URL.revokeObjectURL(page.url));
            this.pages = pages;
        } catch (e) {
            if (run === this.run) {
                this.error = e.message;
            }
        } finally {
            if (run === this.run) {
                this.busy = false;
            }
        }
    },

    async downloadAll() {
        for (const page of this.pages) {
            const link = document.createElement('a');
            link.href = page.url;
            link.download = page.name;
            link.click();
            // Browser verwerfen schnell aufeinanderfolgende Downloads sonst teilweise.
            await new Promise((resolve) => setTimeout(resolve, 300));
        }
    },

    async share() {
        try {
            await navigator.share({ files: this.pages.map((page) => page.file), title: this.title });
        } catch (e) {
            if (e.name !== 'AbortError') {
                this.error = 'Teilen nicht möglich – bitte einzeln herunterladen.';
            }
        }
    },
});
