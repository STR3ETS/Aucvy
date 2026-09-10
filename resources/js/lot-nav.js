/*
 | Doomscrollen door de kavels, zonder zichtbare herlaad: de buurkavels
 | worden op de achtergrond alvast opgehaald, en een pijltje wisselt alleen
 | de pagina-inhoud - de oude schuift het scherm uit, de nieuwe komt van de
 | andere kant binnen. De URL en de titel gaan netjes mee (pushState), en
 | Alpine initialiseert de verse componenten vanzelf; de oude ruimen hun
 | timers op via hun destroy().
 |
 | De pijltjes blijven van alles af wat ze zelf nodig heeft: invoervelden,
 | het sleepspoor (role=slider) en open vensters zoals de fotoviewer.
 */
export default function initLotNav() {
    let host = document.querySelector('[data-lot-nav]');

    if (! host) return;

    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const cache = new Map();
    let busy = false;

    const prefetch = (url) => {
        if (! url || cache.has(url)) return;

        cache.set(url, fetch(url)
            .then((response) => (response.ok ? response.text() : null))
            .catch(() => null));

        // Nooit meer dan een handvol pagina's vasthouden.
        while (cache.size > 6) {
            cache.delete(cache.keys().next().value);
        }
    };

    const prefetchNeighbours = () => {
        prefetch(host.dataset.next);
        prefetch(host.dataset.prev);
    };

    const swap = async (url, direction) => {
        prefetch(url);

        const outClass = direction === 'next' ? 'lot-nav-out-left' : 'lot-nav-out-right';
        const inClass = direction === 'next' ? 'lot-nav-in-right' : 'lot-nav-in-left';

        const started = performance.now();
        if (! reduce) host.classList.add(outClass);

        const html = await cache.get(url);
        // De opgehaalde stand kan even oud zijn; de veilingcomponent haalt
        // zelf direct de verse stand op. Niet hergebruiken voor een tweede keer.
        cache.delete(url);

        if (! html) {
            window.location.assign(url);

            return false;
        }

        // De uit-animatie mag afmaken, maar langer wachten we niet.
        if (! reduce) {
            const remaining = 200 - (performance.now() - started);
            if (remaining > 0) await new Promise((resolve) => setTimeout(resolve, remaining));
        }

        const fresh = new DOMParser().parseFromString(html, 'text/html');
        const content = fresh.querySelector('[data-lot-nav]');

        if (! content) {
            window.location.assign(url);

            return false;
        }

        document.title = fresh.title;
        window.scrollTo(0, 0);

        if (! reduce) content.classList.add(inClass);
        host.replaceWith(content);
        host = content;
        host.addEventListener('animationend', () => host.classList.remove(inClass), { once: true });

        prefetchNeighbours();

        return true;
    };

    const go = async (direction) => {
        const url = host.dataset[direction];

        if (! url || busy) return;
        busy = true;

        window.history.pushState({ lot: true }, '', url);
        await swap(url, direction);

        busy = false;
    };

    // Terug en vooruit van de browser: zelfde wissel, andere richting.
    window.addEventListener('popstate', async () => {
        if (! window.location.pathname.startsWith('/kavel/')) return;
        if (busy || ! document.contains(host)) return;

        busy = true;
        await swap(window.location.href, 'prev');
        busy = false;
    });

    window.addEventListener('keydown', (event) => {
        if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') return;
        if (event.metaKey || event.ctrlKey || event.altKey || event.shiftKey) return;

        if (event.target instanceof Element
            && event.target.closest('input, textarea, select, [contenteditable], [role="slider"]')) {
            return;
        }

        // Een open venster (fotoviewer, filtersheet) heeft de pijltjes zelf nodig.
        for (const modal of document.querySelectorAll('[aria-modal="true"]')) {
            if (getComputedStyle(modal).display !== 'none') return;
        }

        event.preventDefault();
        go(event.key === 'ArrowRight' ? 'next' : 'prev');
    });

    // Alvast klaarstaan: zodra de pagina rustig is, halen we de buren op.
    ('requestIdleCallback' in window ? requestIdleCallback : setTimeout)(prefetchNeighbours);
}
