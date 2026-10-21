{{--
    Het afsluitende blok uit preview v4: "Wat wil je vandaag verhandelen?"
    Staat op de home en onderaan de uitlegpagina's (zekerheid, verificatie,
    veelgestelde vragen).
--}}
<section id="start" class="v4-section" style="padding-bottom:96px" aria-labelledby="start-title">
    <div style="position:relative;overflow:hidden;border-radius:var(--aj-radius-l);background:linear-gradient(130deg,var(--aj-navy) 30%,#14204a);box-shadow:var(--aj-shadow-frame);padding:clamp(40px,6vw,72px) 32px;text-align:center;color:#fff">
        <div class="v4-glow v4-glow--grad v4-anim-drift-a" aria-hidden="true" style="width:340px;height:340px;opacity:.32;top:-70px;right:-70px"></div>
        <x-v4.particles :count="9" />
        <div class="v4-mark v4-anim-float-slow" aria-hidden="true" style="bottom:-110px;left:-60px;opacity:.1"><x-v4.logo :size="320" /></div>
        <h2 id="start-title" style="position:relative;margin:0 auto;max-width:20ch;font-size:clamp(28px,4vw,44px);font-weight:800;letter-spacing:-.025em;line-height:1.1">Wat wil je vandaag verhandelen?</h2>
        <p style="position:relative;margin:14px auto 0;max-width:48ch;font-size:14.5px;color:rgba(255,255,255,.65)">Gratis starten, zonder creditcard. Je eerste kavel staat in ±3 minuten online, of Ivy gaat direct voor je zoeken.</p>
        <div style="position:relative;display:flex;flex-wrap:wrap;gap:14px;justify-content:center;margin-top:30px">
            <x-v4.button :href="route('sell')" size="lg" arrow>Plaats gratis een kavel</x-v4.button>
            <x-v4.button :href="route('lots.index')" variant="ghost" size="lg">Vraag Ivy om te zoeken</x-v4.button>
        </div>
        <div style="position:relative;display:inline-flex;align-items:center;gap:6px;margin-top:20px;font-size:11.5px;color:rgba(255,255,255,.5)"><x-v4.icon name="badge-check" :size="14" stroke="2.2" />Registreren hoeft pas bij opslaan, publiceren of bieden</div>
    </div>
</section>
