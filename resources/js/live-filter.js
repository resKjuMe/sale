// Filterformulare ohne Neuladen: Seite im Hintergrund holen, markierte Bereiche tauschen, URL mitführen.
let controller = null;

async function update(form) {
    const params = new URLSearchParams(new FormData(form));
    const url = form.action + (params.size ? `?${params}` : '');
    const targets = document.querySelectorAll('[data-live-target]');

    controller?.abort();
    controller = new AbortController();
    targets.forEach((el) => el.classList.add('opacity-60', 'transition-opacity'));

    try {
        const response = await fetch(url, { signal: controller.signal, headers: { Accept: 'text/html', 'X-Live-Filter': '1' } });
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
        targets.forEach((el) => {
            const fresh = doc.querySelector(`[data-live-target="${el.dataset.liveTarget}"]`);
            if (fresh) {
                el.replaceWith(document.importNode(fresh, true));
            } else {
                el.classList.remove('opacity-60');
            }
        });
        history.replaceState(history.state, '', url);
    } catch (e) {
        if (e.name !== 'AbortError') {
            window.location.href = url;
        }
    }
}

document.addEventListener('change', (event) => {
    const form = event.target.closest('form[data-live-filter]');
    // Die Aufklapp-Checkbox hat keinen Namen und filtert nicht.
    if (form && event.target.name) {
        update(form);
    }
});

document.addEventListener('submit', (event) => {
    if (event.target.matches('form[data-live-filter]')) {
        event.preventDefault();
        update(event.target);
    }
});

document.addEventListener('click', (event) => {
    const link = event.target.closest('a[data-live-reset]');
    const form = link?.closest('form[data-live-filter]');
    if (form) {
        event.preventDefault();
        form.querySelectorAll('input[name]').forEach((input) => { input.checked = false; });
        update(form);
    }
});
