@use('App\Enums\HomeVariant')
@use('App\Support\Experiments\HomeExperiment')

{{--
    De A/B-test naast elkaar: beide ontwerpen van de home in een iframe, met
    een schakelaar voor A, B of allebei, en voor desktop of mobiel. Dezelfde
    opzet als de preview-pagina van het v4-ontwerp.

    De iframes laden met demo=1: ze tonen een variant zonder de cookie te
    zetten (zie HomeExperiment). Een eigen pagina zonder de site-bundel; dit
    is gereedschap, geen onderdeel van het ontwerp.
--}}
@php
    $frame = fn (HomeVariant $variant): string => route('home', [HomeExperiment::QUERY => $variant->value, HomeExperiment::DEMO => 1]);
    $sticky = fn (HomeVariant $variant): string => route('home', [HomeExperiment::QUERY => $variant->value]);
@endphp
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>A/B-test home · Aucvy</title>
    <style>
        *{box-sizing:border-box}
        html,body{height:100%;margin:0}
        body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Inter,Roboto,sans-serif;background:#0b1120;color:#eaf0ff;display:flex;flex-direction:column;height:100vh;overflow:hidden}
        header{display:flex;align-items:center;gap:14px;padding:10px 16px;background:linear-gradient(180deg,#10182f,#141d33);border-bottom:1px solid rgba(157,185,255,.14);box-shadow:0 8px 30px rgba(0,0,0,.35);flex:0 0 auto;z-index:5;flex-wrap:wrap}
        .brand{display:flex;align-items:center;gap:9px;font-weight:800;font-size:17px;color:inherit;text-decoration:none}
        .brand b{color:#9DB9FF}
        .v{font-size:11px;font-weight:800;color:#04120b;background:#6EE7B7;padding:3px 9px;border-radius:999px;white-space:nowrap}
        .seg{display:flex;gap:6px;background:rgba(157,185,255,.06);border:1px solid rgba(157,185,255,.14);border-radius:10px;padding:3px}
        .seg button{border:none;background:transparent;color:#93a1c8;font-size:12px;font-weight:700;padding:6px 12px;border-radius:8px;cursor:pointer;font-family:inherit}
        .seg button.on{background:#2f6bff;color:#fff}
        .right{margin-left:auto;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
        .link{font-size:12px;font-weight:700;color:#93a1c8;text-decoration:none}
        .link:hover{color:#fff}
        .stage{flex:1;display:flex;gap:16px;justify-content:center;align-items:stretch;padding:16px;overflow:auto;background:#0b1120}
        .pane{display:none;flex-direction:column;flex:1 1 0;min-width:0}
        .pane.on{display:flex}
        .pane-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:0 4px 8px;font-size:12.5px;font-weight:800;color:#93a1c8}
        .pane-head a{color:#9DB9FF;text-decoration:none;font-weight:700}
        .pane-head a:hover{color:#fff}
        .frame{flex:1;min-height:0;background:#fff;border-radius:12px;overflow:hidden;border:1px solid rgba(157,185,255,.16);box-shadow:0 30px 80px rgba(0,0,0,.5);display:flex;justify-content:center}
        .frame iframe{border:0;width:100%;height:100%;display:block;background:#fff}
        .stage.mobile .pane{flex:0 0 auto;align-items:center}
        .stage.mobile .frame{width:390px;max-width:100%;border-radius:38px;border:10px solid #1a2440;box-shadow:0 40px 100px rgba(0,0,0,.6)}
        footer{flex:0 0 auto;padding:8px 16px;font-size:12px;color:#7f8bb0;border-top:1px solid rgba(157,185,255,.1);background:#0e1730;display:flex;gap:16px;flex-wrap:wrap}
        footer b{color:#c9d2ea;font-weight:700}
        @media (max-width:900px){.stage{flex-direction:column}.pane{flex:1 1 auto;min-height:70vh}}
    </style>
</head>
<body>
    <header>
        <a class="brand" href="{{ route('home') }}"><svg width="22" height="22" viewBox="0 0 40 40" fill="none" aria-hidden="true"><path d="M20 5 L35 33 H27 L20 19 L13 33 H5 Z" fill="#2f6bff"/></svg>Auc<b>vy</b></a>
        <span class="v">A/B-test · home</span>
        <div class="seg" role="group" aria-label="Welke variant">
            <button type="button" data-view="a">A · Oud</button>
            <button type="button" data-view="b">B · Nieuw (v4)</button>
            <button type="button" data-view="c">C · Hybride</button>
            <button type="button" data-view="both" class="on">Naast elkaar</button>
        </div>
        <div class="seg" role="group" aria-label="Schermformaat">
            <button type="button" data-device="desktop" class="on">Desktop</button>
            <button type="button" data-device="mobile">Mobiel</button>
        </div>
        <div class="right">
            <a class="link" href="{{ $sticky(HomeVariant::Current) }}">Open home als A</a>
            <a class="link" href="{{ $sticky(HomeVariant::Redesign) }}">Open home als B</a>
            <a class="link" href="{{ $sticky(HomeVariant::Hybrid) }}">Open home als C</a>
        </div>
    </header>

    <div class="stage" id="stage">
        <section class="pane on" data-pane="a" aria-label="Variant A: oude ontwerp">
            <div class="pane-head"><span>A · {{ HomeVariant::Current->label() }}</span><a href="{{ $frame(HomeVariant::Current) }}" target="_blank" rel="noopener">Open los <x-v4.icon name="arrow-up-right" :size="12" stroke="2.6" style="display:inline-block;vertical-align:-1px" /></a></div>
            <div class="frame"><iframe src="{{ $frame(HomeVariant::Current) }}" title="Variant A: oude ontwerp" loading="lazy"></iframe></div>
        </section>
        <section class="pane on" data-pane="b" aria-label="Variant B: nieuw ontwerp">
            <div class="pane-head"><span>B · {{ HomeVariant::Redesign->label() }}</span><a href="{{ $frame(HomeVariant::Redesign) }}" target="_blank" rel="noopener">Open los <x-v4.icon name="arrow-up-right" :size="12" stroke="2.6" style="display:inline-block;vertical-align:-1px" /></a></div>
            <div class="frame"><iframe src="{{ $frame(HomeVariant::Redesign) }}" title="Variant B: nieuw ontwerp" loading="lazy"></iframe></div>
        </section>
        <section class="pane on" data-pane="c" aria-label="Variant C: hybride ontwerp">
            <div class="pane-head"><span>C · {{ HomeVariant::Hybrid->label() }}</span><a href="{{ $frame(HomeVariant::Hybrid) }}" target="_blank" rel="noopener">Open los <x-v4.icon name="arrow-up-right" :size="12" stroke="2.6" style="display:inline-block;vertical-align:-1px" /></a></div>
            <div class="frame"><iframe src="{{ $frame(HomeVariant::Hybrid) }}" title="Variant C: hybride ontwerp" loading="lazy"></iframe></div>
        </section>
    </div>

    <footer>
        <span>Toewijzing: <b>{{ $enabled ? round($share * 100).'% krijgt B, de rest A' : 'test uit, iedereen ziet '.strtoupper($default->value).' ('.$default->label().')' }}</b> (AUCVY_HOME_EXPERIMENT_ENABLED, AUCVY_HOME_VARIANT)</span>
        <span>Cookie tijdens de test: <b>{{ HomeExperiment::COOKIE }}</b>, {{ config('aucvy.experiments.home.cookie_days') }} dagen</span>
        <span>Afdwingen: <b>/?variant=a</b>, <b>/?variant=b</b> of <b>/?variant=c</b></span>
    </footer>

    <script>
        (function () {
            const stage = document.getElementById('stage');
            const panes = [...stage.querySelectorAll('.pane')];

            document.querySelectorAll('[data-view]').forEach((button) => {
                button.addEventListener('click', () => {
                    document.querySelectorAll('[data-view]').forEach((other) => other.classList.toggle('on', other === button));
                    const view = button.dataset.view;
                    panes.forEach((pane) => pane.classList.toggle('on', view === 'both' || pane.dataset.pane === view));
                });
            });

            document.querySelectorAll('[data-device]').forEach((button) => {
                button.addEventListener('click', () => {
                    document.querySelectorAll('[data-device]').forEach((other) => other.classList.toggle('on', other === button));
                    stage.classList.toggle('mobile', button.dataset.device === 'mobile');
                });
            });
        })();
    </script>
</body>
</html>
