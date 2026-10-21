/**
 * Het script uit aucvy-home.html, ongewijzigd in logica.
 *
 * Drie aanpassingen ten opzichte van het origineel, allemaal technisch:
 *  - GSAP komt uit npm in plaats van het cdnjs-<script> - zelfde library,
 *    zelfde animaties, geen derde partij in het laadpad;
 *  - elk blok controleert eerst of zijn elementen bestaan, omdat deze bundel
 *    op élke pagina laadt en niet alleen op de home;
 *  - de afslagticker pakt álle [data-dutch]-elementen in plaats van één,
 *    want op /veilingen kunnen meerdere afslagkaarten staan.
 */
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

export default function initHome() {
  document.body.classList.remove('no-js');
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const hasGsap = true;
  gsap.registerPlugin(ScrollTrigger);

  /* ============ 1. DOT FIELD (hero) ============ */
  const canvas = document.getElementById('dots');
  const hero = document.querySelector('.hero');
  let W = 0, H = 0;
  const ripples = [];

  if (canvas && hero) {
    const ctx = canvas.getContext('2d');
    let dpr = 1, cols = 0, rows = 0, gap = 26;
    const mouse = { x: -9999, y: -9999, tx: -9999, ty: -9999 };
    let t = 0, running = true;

    function resize() {
      dpr = Math.min(window.devicePixelRatio || 1, 2);
      W = canvas.clientWidth; H = canvas.clientHeight;
      canvas.width = W * dpr; canvas.height = H * dpr;
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      gap = W < 760 ? 22 : 26;
      cols = Math.ceil(W / gap) + 1; rows = Math.ceil(H / gap) + 1;
    }
    function draw() {
      ctx.clearRect(0, 0, W, H);
      t += reduce ? 0 : 0.012;
      mouse.x += (mouse.tx - mouse.x) * 0.12; mouse.y += (mouse.ty - mouse.y) * 0.12;
      for (let i = ripples.length - 1; i >= 0; i--) { ripples[i].r += 7; ripples[i].a *= 0.965; if (ripples[i].a < 0.02) ripples.splice(i, 1); }
      for (let j = 0; j < rows; j++) {
        for (let i = 0; i < cols; i++) {
          const x = i * gap, y = j * gap;
          // two slow interfering waves -> organic breathing field
          const w = Math.sin(i * 0.28 + t * 1.1) * Math.cos(j * 0.22 - t * 0.8);
          const w2 = Math.sin((i + j) * 0.12 - t * 0.6);
          let v = 0.32 + 0.28 * w + 0.18 * w2;           // 0..~0.78
          // cursor lens
          const dx = x - mouse.x, dy = y - mouse.y, d = Math.sqrt(dx * dx + dy * dy);
          if (d < 220) { const k = 1 - d / 220; v += k * k * 0.9; }
          // click ripples
          for (const rp of ripples) { const dd = Math.abs(Math.hypot(x - rp.x, y - rp.y) - rp.r); if (dd < 28) { v += (1 - dd / 28) * rp.a; } }
          v = Math.max(0.08, Math.min(1.25, v));
          const r = 0.8 + v * 1.9;
          ctx.globalAlpha = Math.min(1, 0.18 + v * 0.65);
          ctx.fillStyle = '#ffffff';
          ctx.beginPath(); ctx.arc(x, y, r, 0, 6.2832); ctx.fill();
        }
      }
      ctx.globalAlpha = 1;
      if (running && !reduce) requestAnimationFrame(draw);
    }
    resize(); window.addEventListener('resize', resize, { passive: true });
    hero.addEventListener('pointermove', e => { const b = canvas.getBoundingClientRect(); mouse.tx = e.clientX - b.left; mouse.ty = e.clientY - b.top; }, { passive: true });
    hero.addEventListener('pointerleave', () => { mouse.tx = -9999; mouse.ty = -9999; });
    hero.addEventListener('pointerdown', e => { if (e.target.closest('a,button,input,label')) return; const b = canvas.getBoundingClientRect(); ripples.push({ x: e.clientX - b.left, y: e.clientY - b.top, r: 0, a: 1 }); });
    // pause the loop when hero is off-screen
    if ('IntersectionObserver' in window) { new IntersectionObserver(es => { const on = es[0].isIntersecting; if (on && !running) { running = true; draw(); } else if (!on) { running = false; } }).observe(hero); }
    draw();

    /* ============ 2. HERO LOAD SEQUENCE ============ */
    if (hasGsap && !reduce) {
      const tl = gsap.timeline({ defaults: { ease: 'expo.out' } });
      tl.to('#dots', { opacity: 1, duration: 1.6, ease: 'power2.out' }, 0)
        .from('.header', { y: -16, opacity: 0, duration: .9 }, 0.1)
        .from('.hero h1 .w span', { yPercent: 110, duration: 1.1, stagger: .07 }, 0.25)
        .from('[data-hero]', { y: 18, opacity: 0, duration: .9, stagger: .09 }, 0.7);
      // ripple burst from the headline once it's landed
      tl.add(() => { ripples.push({ x: W * 0.28, y: H * 0.5, r: 0, a: .8 }); }, 1.1);
      gsap.to('#dots', { yPercent: 12, ease: 'none', scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: true } });
    } else {
      canvas.style.opacity = 1;
      document.querySelectorAll('.hero h1 .w span').forEach(s => s.style.transform = 'none');
    }
  }

  /* ============ 3. SCROLL REVEALS (one pass, no per-card hover fx) ============ */
  if (hasGsap && !reduce && document.querySelector('.rv')) {
    ScrollTrigger.batch('.rv', {
      start: 'top 88%',
      onEnter: b => gsap.to(b, { opacity: 1, y: 0, duration: 1, ease: 'expo.out', stagger: .09, overwrite: true }),
      once: true
    });
  } else { document.querySelectorAll('.rv').forEach(el => { el.style.opacity = 1; el.style.transform = 'none'; }); }

  /* ============ 4. TYPING PLACEHOLDER ============ */
  const q = document.getElementById('q');
  const examples = ['Een complete horecakeuken', '40 bureaustoelen uit een kantoorpand', 'Een Volvo FH uit 2019', 'De inventaris van een failliete bakkerij', 'Een CNC-freesbank Haas VF-2'];
  if (q && !reduce) {
    let ei = 0, ci = 0, del = false, timer;
    function type() {
      if (document.activeElement === q || q.value) { timer = setTimeout(type, 1200); return; }
      const s = examples[ei];
      if (!del) { ci++; if (ci > s.length) { del = true; timer = setTimeout(type, 1700); q.placeholder = s; return; } }
      else { ci--; if (ci < 0) { del = false; ci = 0; ei = (ei + 1) % examples.length; } }
      q.placeholder = ci === 0 && !del ? 'Wat wil je vandaag verhandelen?' : s.slice(0, ci);
      timer = setTimeout(type, del ? 28 : 55);
    }
    setTimeout(type, 2600);
  }

  /* ============ 5. COUNTDOWNS (local, sync via /api/auctions/state) ============ */
  const pad = n => String(n).padStart(2, '0');
  // data-short: de compacte notatie van de v4-kaarten ("2d 04u", "01:42").
  const cds = [...document.querySelectorAll('[data-countdown]')].map(el => ({ el, end: Date.now() + parseInt(el.dataset.countdown, 10) * 1000, short: 'short' in el.dataset }));
  if (cds.length) {
    function tick() {
      const now = Date.now();
      cds.forEach(({ el, end, short }) => {
        let s = Math.max(0, Math.round((end - now) / 1000));
        if (s >= 86400) { el.textContent = short ? Math.floor(s / 86400) + 'd ' + pad(Math.floor(s % 86400 / 3600)) + 'u' : 'nog ' + Math.round(s / 86400) + ' dagen'; return; }
        if (s >= 3600) { el.textContent = short ? Math.floor(s / 3600) + 'u ' + pad(Math.floor(s % 3600 / 60)) + 'm' : 'nog ' + Math.floor(s / 3600) + ' u ' + pad(Math.floor(s % 3600 / 60)) + ' min'; return; }
        el.textContent = (short ? '' : 'nog ') + pad(Math.floor(s / 60)) + ':' + pad(s % 60);
        if (s < 300) el.classList.add('hot');
      });
    }
    tick(); setInterval(tick, 1000);
  }

  /* ============ 6. DUTCH AUCTION PRICE DROP ============ */
  document.querySelectorAll('[data-dutch]').forEach(dutch => {
    const dutchT = dutch.closest('.meta')?.querySelector('[data-dutch-timer]');
    let price = parseInt(dutch.dataset.dutch, 10), step = parseInt(dutch.dataset.step, 10), floor = parseInt(dutch.dataset.floor, 10), left = 20;
    const fmt = n => '€ ' + n.toLocaleString('nl-NL');
    setInterval(() => {
      left--;
      if (left <= 0) {
        left = 20;
        if (price - step >= floor) {
          const from = price; price -= step;
          if (hasGsap && !reduce) {
            const o = { v: from }; gsap.to(o, { v: price, duration: .7, ease: 'expo.out', onUpdate: () => dutch.textContent = fmt(Math.round(o.v)) });
            gsap.fromTo(dutch, { color: '#2450A8' }, { color: '#0e1526', duration: 1.2 });
          }
          else dutch.textContent = fmt(price);
        } else { if (dutchT) dutchT.textContent = 'bodem bereikt'; return; }
      }
      if (dutchT) dutchT.textContent = 'daalt over ' + left + 's';
    }, 1000);
  });

  /* ============ 7. VERKOPERSREIS - autoplay with pause ============ */
  const steps = [...document.querySelectorAll('#steps li')];
  const scenes = [...document.querySelectorAll('#stage .scene')];
  const toggle = document.getElementById('reisToggle');
  if (steps.length && scenes.length && toggle) {
    let cur = 0, playing = !reduce, bar = null, stageVisible = false;
    const DUR = 4.2;

    function show(i) {
      steps[cur].classList.remove('on'); scenes[cur].classList.remove('on');
      cur = i; steps[cur].classList.add('on'); scenes[cur].classList.add('on');
      if (hasGsap && !reduce) {
        const art = scenes[cur].querySelector('.art');
        gsap.fromTo(scenes[cur].querySelectorAll('.art > *, .cap'), { y: 14, opacity: 0 }, { y: 0, opacity: 1, duration: .7, ease: 'expo.out', stagger: .06, overwrite: true });
        const conf = art.querySelector('[data-conf]'); if (conf) gsap.fromTo(conf, { width: 0 }, { width: conf.dataset.conf + '%', duration: 1.1, ease: 'expo.out', delay: .3 });
      }
      runBar();
    }
    function runBar() {
      if (bar) bar.kill();
      steps.forEach(s => s.querySelector('.bar').style.width = '0');
      if (!playing || !hasGsap || reduce) return;
      bar = gsap.fromTo(steps[cur].querySelector('.bar'), { width: '0%' }, { width: '100%', duration: DUR, ease: 'none', onComplete: () => show((cur + 1) % steps.length) });
      if (!stageVisible) bar.pause();
    }
    steps.forEach((li, i) => { li.addEventListener('click', () => { if (i !== cur) show(i); }); li.tabIndex = 0; li.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); if (i !== cur) show(i); } }); });
    toggle.addEventListener('click', () => {
      playing = !playing; toggle.setAttribute('aria-pressed', String(!playing));
      document.getElementById('icoPause').style.display = playing ? '' : 'none';
      document.getElementById('icoPlay').style.display = playing ? 'none' : '';
      document.getElementById('reisLabel').textContent = playing ? 'Pauze' : 'Afspelen';
      toggle.nextElementSibling.textContent = playing ? 'Speelt automatisch af' : 'Gepauzeerd';
      runBar();
    });
    if (reduce) { // all steps visible, nothing moves
      steps.forEach(s => s.classList.add('on')); toggle.style.display = 'none'; toggle.nextElementSibling.textContent = 'Klik een stap om het voorbeeld te zien';
    } else {
      // only run while the section is on screen
      new IntersectionObserver(es => { stageVisible = es[0].isIntersecting; if (bar) { stageVisible ? bar.play() : bar.pause(); } }, { threshold: .25 }).observe(document.getElementById('reis'));
      runBar();
    }
  }
}
