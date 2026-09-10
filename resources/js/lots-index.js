/*
 | Zachte navigatie voor de veilingenpagina.
 |
 | De filters, chips, sortering en paginering zijn gewone GET-links en
 | -formulieren; dat blijft zo (deelbaar, terugknop, SEO, en zonder
 | JavaScript werkt alles nog). Dit script vangt ze af, haalt dezelfde
 | pagina via fetch op en wisselt alleen #lots-content. De URL gaat mee
 | via pushState, en Alpine initialiseert de verse inhoud vanzelf via
 | zijn MutationObserver.
 |
 | Gaat er iets mis (netwerk, onverwachte respons), dan valt hij terug op
 | een gewone navigatie - nooit een stille kapotte pagina.
 */
export default function initLotsIndex() {
    const region = document.getElementById('lots-content');

    if (! region) return;

    const isIndexUrl = (url) => url.origin === window.location.origin
        && (url.pathname === '/veilingen' || url.pathname.startsWith('/veilingen/'));

    const swap = async (url, { push = true } = {}) => {
        region.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });

            if (! response.ok) {
                window.location.assign(url);

                return;
            }

            const fresh = new DOMParser()
                .parseFromString(await response.text(), 'text/html');
            const content = fresh.getElementById('lots-content');

            if (! content) {
                window.location.assign(url);

                return;
            }

            region.replaceChildren(...content.childNodes);

            if (push) {
                window.history.pushState({ lots: true }, '', url);
            }

            document.title = fresh.title;

            // Bij paginering hoor je bovenaan de nieuwe resultaten te staan;
            // bij een filterklik blijft de pagina gewoon op zijn plek.
            if (url.searchParams.has('page')) {
                region.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        } catch {
            window.location.assign(url);
        } finally {
            region.removeAttribute('aria-busy');
        }
    };

    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0
            || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        const anchor = event.target.closest('a[href]');

        if (! anchor || ! region.contains(anchor)) return;

        const url = new URL(anchor.href, window.location.href);

        if (! isIndexUrl(url) || url.hash) return;

        event.preventDefault();
        swap(url);
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (! region.contains(form) || form.method.toLowerCase() !== 'get') return;

        const url = new URL(form.action || window.location.href);

        if (! isIndexUrl(url)) return;

        event.preventDefault();
        url.search = new URLSearchParams(new FormData(form)).toString();
        swap(url);
    });

    // Terug en vooruit binnen de zachte navigatie: zelfde wissel, geen push.
    window.addEventListener('popstate', () => {
        const url = new URL(window.location.href);

        if (isIndexUrl(url)) {
            swap(url, { push: false });
        }
    });
}
