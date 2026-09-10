/*
 | Doomscrollen door de kavels: pijltje rechts schuift deze kavel het
 | scherm uit naar links en haalt de volgende van rechts binnen; pijltje
 | links precies andersom. De richtingen komen als data-attributen van de
 | server (met wrap-around, dus het houdt nooit op).
 |
 | De pijltjes blijven van alles af wat ze zelf nodig heeft: invoervelden,
 | het sleepspoor (role=slider) en open vensters zoals de fotoviewer.
 */
export default function initLotNav() {
    const host = document.querySelector('[data-lot-nav]');
    const main = document.getElementById('hoofdinhoud');

    if (! host || ! main) return;

    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // De inkomende beweging, op basis van hoe we hier kwamen.
    const arrived = sessionStorage.getItem('aucvy_lot_nav');
    sessionStorage.removeItem('aucvy_lot_nav');

    if (arrived && ! reduce) {
        const inClass = arrived === 'next' ? 'lot-nav-in-right' : 'lot-nav-in-left';
        main.classList.add(inClass);
        main.addEventListener('animationend', () => main.classList.remove(inClass), { once: true });
    }

    let busy = false;

    const go = (direction) => {
        const url = host.dataset[direction];

        if (! url || busy) return;
        busy = true;

        sessionStorage.setItem('aucvy_lot_nav', direction);

        if (reduce) {
            window.location.assign(url);

            return;
        }

        main.classList.add(direction === 'next' ? 'lot-nav-out-left' : 'lot-nav-out-right');
        setTimeout(() => window.location.assign(url), 230);
    };

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
}
