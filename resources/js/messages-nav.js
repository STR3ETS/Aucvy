/*
 | Wisselen tussen gesprekken op de berichtenpagina: de lijst links blijft
 | staan, alleen het gesprek en de kolom ernaast laden opnieuw. Dit loopt
 | vóór de algemene paginawissel van het dashboard (dash-nav.js), die anders
 | de hele pagina als skelet toont.
 */
const SKELETON_THREAD = `
    <div class="d-msg-skeleton" aria-hidden="true">
        <span class="sk" style="height:44px;width:60%"></span>
        <span class="sk" style="height:38px;width:100%"></span>
        <span class="sk" style="height:64px;width:72%"></span>
        <span class="sk" style="height:48px;width:54%;align-self:flex-end"></span>
        <span class="sk" style="height:64px;width:68%"></span>
        <span class="sk" style="height:44px;width:100%;margin-top:auto"></span>
    </div>`;

const SKELETON_ASIDE = `
    <div class="d-msg-skeleton" aria-hidden="true">
        <span class="sk" style="height:22px;width:70%"></span>
        <span class="sk" style="height:44px;width:100%"></span>
        <span class="sk" style="height:44px;width:100%"></span>
        <span class="sk" style="height:44px;width:100%"></span>
    </div>`;

export default function initMessagesNav() {
    if (! window.fetch || ! window.history?.pushState) return;

    let controller = null;

    const layout = () => document.querySelector('.d-msg-layout');
    const isConversationUrl = (url) => url.origin === window.location.origin && /^\/dashboard\/berichten\/\d+$/.test(url.pathname);

    /** De kolommen van de pagina: lijst, gesprek, en de kolom "Alles over deze kavel". */
    const parts = (root) => ({
        list: root.querySelector(':scope > nav'),
        thread: root.querySelector(':scope > .d-thread'),
        aside: root.querySelector(':scope > aside'),
    });

    const markActive = (current, url) => {
        current.list?.querySelectorAll('.d-conv').forEach((item) => {
            const active = new URL(item.href).pathname === url.pathname;

            item.classList.toggle('is-on', active);
            active ? item.setAttribute('aria-current', 'page') : item.removeAttribute('aria-current');

            // Openen is lezen: de teller van dit gesprek kan weg.
            if (active) item.querySelector('.d-conv-unread')?.remove();
        });
    };

    const open = async (url, push) => {
        const root = layout();
        const current = root ? parts(root) : null;

        if (! current?.thread || ! current.aside) return false;

        controller?.abort();
        controller = new AbortController();

        markActive(current, url);

        // Pas een skelet als het laden merkbaar duurt; een snelle wissel flitst dan niet.
        const skeleton = setTimeout(() => {
            current.thread.innerHTML = SKELETON_THREAD;
            current.thread.removeAttribute('style');
            current.thread.classList.remove('is-empty');
            current.aside.innerHTML = SKELETON_ASIDE;
            current.aside.className = 'd-card';
        }, 140);

        try {
            const response = await fetch(url, { headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal });
            const fresh = new DOMParser().parseFromString(await response.text(), 'text/html');
            const next = fresh.querySelector('.d-msg-layout') ? parts(fresh.querySelector('.d-msg-layout')) : null;

            clearTimeout(skeleton);

            // Geen berichtenpagina terug (uitgelogd, fout, doorverwijzing): gewoon erheen.
            if (! response.ok || ! next?.thread || ! next.aside) {
                window.location.assign(url);

                return true;
            }

            // Alpine start het nieuwe gesprek vanzelf en ruimt het oude op.
            current.thread.replaceWith(document.adoptNode(next.thread));
            current.aside.replaceWith(document.adoptNode(next.aside));

            // Op de telefoon staat nu het gesprek in beeld in plaats van de lijst.
            root.classList.replace('is-list', 'is-open');

            document.title = fresh.title;

            const crumbs = fresh.querySelector('.dash-crumbs');
            if (crumbs) document.querySelector('.dash-crumbs')?.replaceWith(document.adoptNode(crumbs));

            const token = fresh.querySelector('meta[name="csrf-token"]')?.content;
            if (token) document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', token);

            if (push) window.history.pushState({ dash: true }, '', url);

            document.dispatchEvent(new CustomEvent('dash-nav:swapped'));

            // Op een smal scherm staat het gesprek onder de lijst: breng het in beeld.
            if (window.matchMedia('(max-width: 760px)').matches) {
                layout()?.querySelector(':scope > .d-thread')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        } catch (error) {
            clearTimeout(skeleton);
            if (error.name !== 'AbortError') window.location.assign(url);
        }

        return true;
    };

    // In de vangfase, zodat de algemene paginawissel deze klik niet meer ziet.
    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const link = event.target.closest?.('.d-msg-layout a.d-conv');
        if (! link || link.target === '_blank') return;

        const url = new URL(link.href);
        if (! isConversationUrl(url)) return;

        event.preventDefault();
        event.stopPropagation();

        if (url.pathname !== window.location.pathname) open(url, true);
    }, true);

    // Terug en vooruit tussen gesprekken: ook alleen het gesprek wisselen.
    window.addEventListener('popstate', (event) => {
        const url = new URL(window.location.href);

        if (! layout() || ! isConversationUrl(url)) return;

        event.stopImmediatePropagation();
        open(url, false);
    });
}
