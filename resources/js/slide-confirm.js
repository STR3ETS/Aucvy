/**
 * Sleep-om-te-bevestigen voor handelingen die geld kosten.
 *
 * De gedachte: een bod hoort een bewuste handeling te zijn die je niet per
 * ongeluk doet. Slepen van links naar rechts (of met de pijltjestoetsen naar
 * rechts stappen) is die handeling; loslaten vóór het einde veert terug en
 * doet niets.
 *
 * Toegankelijkheid: het spoor is een echte slider (role="slider", tabindex),
 * bedienbaar met ArrowRight/ArrowLeft, Home/End en Escape. Wie niet kan
 * slepen, kan dus wel bieden - een bewuste reeks toetsaanslagen is net zo
 * bewust als een sleep.
 */
export default function slideConfirm(onConfirm) {
    return {
        progress: 0,
        dragging: false,
        fired: false,

        startDrag(event) {
            if (this.fired) return;

            this.dragging = true;
            event.target.setPointerCapture?.(event.pointerId);
        },

        moveDrag(event) {
            if (! this.dragging || this.fired) return;

            const track = this.$refs.track.getBoundingClientRect();
            const handle = 48;
            const x = event.clientX - track.left - handle / 2;

            this.progress = Math.max(0, Math.min(100, (x / (track.width - handle)) * 100));
        },

        endDrag() {
            if (! this.dragging) return;

            this.dragging = false;

            // Bijna aan het einde telt als einde; alles daarvoor veert terug.
            this.progress >= 92 ? this.fire() : (this.progress = 0);
        },

        nudge(delta) {
            if (this.fired) return;

            this.progress = Math.max(0, Math.min(100, this.progress + delta));

            if (this.progress >= 100) this.fire();
        },

        reset() {
            this.progress = 0;
        },

        fire() {
            this.fired = true;
            this.progress = 100;

            onConfirm();

            // Klaar voor een volgend bod zodra de verwerking voorbij is.
            setTimeout(() => {
                this.progress = 0;
                this.fired = false;
            }, 600);
        },
    };
}
