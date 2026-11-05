/*
 | Naadloze paginawissels in het dashboard.
 |
 | De zijbalk blijft staan; alleen de inhoud rechts (.dash-main) wisselt.
 | Elke link blijft een gewone link (deelbaar, terugknop, werkt zonder
 | JavaScript). Dit script vangt de klik af, zet het menu-item meteen op
 | actief, toont skeletons op de plek van de inhoud, haalt de pagina op met
 | fetch en wisselt de inhoud met een korte overgang. De URL gaat mee via
 | pushState en Alpine initialiseert de verse inhoud vanzelf via zijn
 | MutationObserver.
 |
 | Valt iets buiten de schil (een download, een pagina met eigen scripts,
 | een fout, een redirect naar buiten het dashboard), dan volgt een gewone
 | navigatie: nooit een stille kapotte pagina.
 */

// Paden die bij de dashboardschil horen. Alles daarbuiten navigeert gewoon.
const SHELL_PATHS = ['/dashboard', '/bestellingen', '/facturen', '/account'];

// Zo lang blijft het skeleton minimaal staan: korter oogt als een flits.
const MIN_SKELETON_MS = 220;

const SKELETON = `
<div class="dash-skeleton" aria-hidden="true">
    <div class="sk sk-title"></div>
    <div class="sk-cols">
        <div class="sk-stack">
            <div class="sk sk-card sk-tall"></div>
            <div class="sk sk-card"></div>
            <div class="sk sk-row"></div>
            <div class="sk sk-row"></div>
            <div class="sk sk-row"></div>
        </div>
        <div class="sk-stack sk-aside">
            <div class="sk sk-card sk-tall"></div>
            <div class="sk sk-card"></div>
        </div>
    </div>
</div>`;

export default function initDashNav() {
    const shell = document.querySelector('.dash-shell');

    if (! shell || ! window.fetch || ! window.history?.pushState) return;

    let controller = null;

    const belongsToShell = (url) => url.origin === window.location.origin
        && SHELL_PATHS.some((path) => url.pathname === path || url.pathname.startsWith(path + '/'));

    const main = () => shell.querySelector('.dash-main');

    /** Het menu-item van deze URL direct actief zetten, vóór de inhoud er is. */
    const markActive = (url) => {
        const links = [...shell.querySelectorAll('.dash-side .dash-nav a')];
        const match = links
            .filter((link) => {
                const path = new URL(link.href).pathname;

                return url.pathname === path || url.pathname.startsWith(path + '/');
            })
            // De langste match wint: /dashboard/kavels boven /dashboard.
            .sort((a, b) => new URL(b.href).pathname.length - new URL(a.href).pathname.length)[0];

        if (! match) return;

        links.forEach((link) => {
            link.classList.toggle('is-on', link === match);
            link === match ? link.setAttribute('aria-current', 'page') : link.removeAttribute('aria-current');
        });
    };

    const showSkeleton = () => {
        const current = main();

        current.classList.add('is-loading');
        current.setAttribute('aria-busy', 'true');

        const body = current.querySelector('.dash-body');

        if (body && ! body.querySelector('.dash-skeleton')) {
            body.insertAdjacentHTML('afterbegin', SKELETON);
        }
    };

    const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

    const visit = async (url, { push = true } = {}) => {
        controller?.abort();
        controller = new AbortController();

        const started = performance.now();

        markActive(url);
        showSkeleton();

        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                credentials: 'same-origin',
                signal: controller.signal,
            });
            const finalUrl = new URL(response.url || url, window.location.href);

            // Geen html (download), een fout, of een redirect naar buiten de schil.
            if (! response.ok || ! (response.headers.get('content-type') ?? '').includes('text/html') || ! belongsToShell(finalUrl)) {
                window.location.assign(url);

                return;
            }

            const fresh = new DOMParser().parseFromString(await response.text(), 'text/html');
            const freshMain = fresh.querySelector('.dash-shell .dash-main');

            // Een pagina met eigen scripts, of een andere layout: volledig laden.
            if (! freshMain || fresh.body.dataset.softNav !== 'on' || freshMain.querySelector('script')) {
                window.location.assign(finalUrl);

                return;
            }

            const elapsed = performance.now() - started;

            if (elapsed < MIN_SKELETON_MS) {
                await wait(MIN_SKELETON_MS - elapsed);
            }

            const current = main();

            current.className = freshMain.className;
            current.replaceChildren(...freshMain.childNodes);
            current.removeAttribute('aria-busy');
            current.classList.add('is-entering');
            // Na de overgang weer weg, zodat een volgende wissel opnieuw kan spelen.
            setTimeout(() => current.classList.remove('is-entering'), 450);
            // Ivy kijkt mee: na de wissel weet ze op welke pagina je nu zit.
            setTimeout(() => document.dispatchEvent(new CustomEvent('dash-nav:swapped')), 0);

            // Het menu van de server: de actieve staat en de tellers kloppen weer.
            const freshNav = fresh.querySelector('.dash-side .dash-nav');
            const nav = shell.querySelector('.dash-side .dash-nav');

            if (freshNav && nav) {
                nav.replaceChildren(...freshNav.childNodes);
            }

            const freshProfile = fresh.querySelector('.dash-side .dash-profile');
            const profile = shell.querySelector('.dash-side .dash-profile');

            if (freshProfile && profile) {
                profile.replaceChildren(...freshProfile.childNodes);
            }

            document.title = fresh.title;

            // Het token kan zijn vernieuwd; fetch-aanroepen op de pagina lezen de meta.
            const token = fresh.querySelector('meta[name="csrf-token"]')?.content;

            if (token) {
                document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', token);
            }

            if (push) {
                window.history.pushState({ dash: true }, '', finalUrl);
            }

            if (finalUrl.hash) {
                document.getElementById(finalUrl.hash.slice(1))?.scrollIntoView();
            } else {
                window.scrollTo({ top: 0 });
            }

            // Toetsenbord en schermlezer landen op de nieuwe inhoud.
            current.querySelector('#hoofdinhoud')?.focus({ preventScroll: true });
        } catch (error) {
            if (error.name !== 'AbortError') {
                window.location.assign(url);
            }
        }
    };

    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0
            || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        const anchor = event.target.closest('a[href]');

        if (! anchor || ! shell.contains(anchor) || anchor.target || anchor.hasAttribute('download') || anchor.dataset.hard !== undefined) {
            return;
        }

        const url = new URL(anchor.href, window.location.href);

        if (! belongsToShell(url)) return;

        // Alleen een anker op dezelfde pagina: de browser regelt het.
        if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return;

        event.preventDefault();
        visit(url);
    });

    // De terugknop: dezelfde wissel, zonder een nieuwe stap in de geschiedenis.
    window.addEventListener('popstate', () => {
        const url = new URL(window.location.href);

        if (belongsToShell(url)) {
            visit(url, { push: false });
        }
    });

    // Terug uit de bfcache: nooit een pagina die op skeletons is blijven staan.
    window.addEventListener('pageshow', (event) => {
        if (! event.persisted) return;

        const current = main();

        current?.classList.remove('is-loading');
        current?.removeAttribute('aria-busy');
        current?.querySelector('.dash-skeleton')?.remove();
    });
}
