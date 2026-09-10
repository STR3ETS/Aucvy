/*
 | Het ademende stippenveld van de home-hero (home.js), maar dan als
 | herbruikbaar Alpine-component voor kaarten met .panel-hero.
 |
 | Zelfde recept: twee langzaam interfererende golven bepalen per stip de
 | grootte en dekking, en de cursor werkt als lens. Geen ripples - op een
 | kaart vol knoppen is elke klik al een handeling.
 |
 | Gebruik: <canvas class="dots" x-data="dotField" aria-hidden="true"></canvas>
 | als eerste kind van de kaart. CSS legt hem achter de inhoud.
 |
 | Opties: kleur en sterkte. Wit op de blauwe kaarten (standaard), donker en
 | zwak als paginalaag op de lichte achtergrond:
 | x-data="dotField({ color: '#0e1526', strength: 0.25 })"
 */
export default function dotField({ color = '#ffffff', strength = 1 } = {}) {
    return {
        frame: null,
        teardown: null,

        init() {
            const canvas = this.$el;
            const host = canvas.parentElement;
            const context = canvas.getContext('2d');
            const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            let width = 0;
            let height = 0;
            let columns = 0;
            let rows = 0;
            const gap = 22;
            let time = 0;
            let visible = true;
            const mouse = { x: -9999, y: -9999, tx: -9999, ty: -9999 };

            const resize = () => {
                const ratio = Math.min(window.devicePixelRatio || 1, 2);
                width = canvas.clientWidth;
                height = canvas.clientHeight;
                canvas.width = width * ratio;
                canvas.height = height * ratio;
                context.setTransform(ratio, 0, 0, ratio, 0, 0);
                columns = Math.ceil(width / gap) + 1;
                rows = Math.ceil(height / gap) + 1;
            };

            const draw = () => {
                context.clearRect(0, 0, width, height);
                time += reduce ? 0 : 0.012;
                mouse.x += (mouse.tx - mouse.x) * 0.12;
                mouse.y += (mouse.ty - mouse.y) * 0.12;

                for (let j = 0; j < rows; j++) {
                    for (let i = 0; i < columns; i++) {
                        const x = i * gap;
                        const y = j * gap;
                        const wave = Math.sin(i * 0.28 + time * 1.1) * Math.cos(j * 0.22 - time * 0.8);
                        const wave2 = Math.sin((i + j) * 0.12 - time * 0.6);
                        let value = 0.32 + 0.28 * wave + 0.18 * wave2;

                        const dx = x - mouse.x;
                        const dy = y - mouse.y;
                        const distance = Math.sqrt(dx * dx + dy * dy);
                        if (distance < 160) {
                            const lens = 1 - distance / 160;
                            value += lens * lens * 0.9;
                        }

                        value = Math.max(0.08, Math.min(1.25, value));
                        context.globalAlpha = Math.min(1, 0.14 + value * 0.5) * strength;
                        context.fillStyle = color;
                        context.beginPath();
                        context.arc(x, y, 0.7 + value * 1.6, 0, 6.2832);
                        context.fill();
                    }
                }

                context.globalAlpha = 1;

                if (visible && ! reduce) {
                    this.frame = requestAnimationFrame(draw);
                }
            };

            const onMove = (event) => {
                const box = canvas.getBoundingClientRect();
                mouse.tx = event.clientX - box.left;
                mouse.ty = event.clientY - box.top;
            };
            const onLeave = () => {
                mouse.tx = -9999;
                mouse.ty = -9999;
            };

            /*
             | Niet eenmalig meten maar observeren: bij init staat de kaart
             | vaak nog achter x-cloak en is de canvas 0 breed. Zodra hij
             | zichtbaar wordt (of de layout verandert) meet dit opnieuw.
             */
            const sizer = new ResizeObserver(() => {
                resize();
                if (reduce) draw();
            });
            sizer.observe(host);
            host.addEventListener('pointermove', onMove, { passive: true });
            host.addEventListener('pointerleave', onLeave, { passive: true });

            // Buiten beeld hoeft er niets te ademen.
            const observer = 'IntersectionObserver' in window
                ? new IntersectionObserver((entries) => {
                    const onScreen = entries[0].isIntersecting;
                    if (onScreen && ! visible) {
                        visible = true;
                        draw();
                    } else if (! onScreen) {
                        visible = false;
                    }
                })
                : null;
            observer?.observe(host);

            draw();

            this.teardown = () => {
                visible = false;
                if (this.frame !== null) cancelAnimationFrame(this.frame);
                sizer.disconnect();
                host.removeEventListener('pointermove', onMove);
                host.removeEventListener('pointerleave', onLeave);
                observer?.disconnect();
            };
        },

        destroy() {
            this.teardown?.();
        },
    };
}
