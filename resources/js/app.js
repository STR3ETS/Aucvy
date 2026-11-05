import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import auction from './auction';
import funnel from './funnel';
import { ivyAdvice, searchInterpret } from './ivy-tasks';
import { ivyPanel } from './ivy-panel';
import slideConfirm from './slide-confirm';
import dotField from './dot-field';
import sellingDemo from './selling-demo';
import bidForm from './bid-form';
import pushToggle from './push';
import adminLive from './admin-live';
import lotMedia from './lot-media';
import initHome from './home';
import initLotsIndex from './lots-index';
import initLotNav from './lot-nav';
import initDashNav from './dash-nav';
import { v4Demo, v4Story, v4Engine, v4Dock, v4Nav, initV4Reveal } from './v4';

/*
 | focus levert $focus, nodig om de focus vast te houden binnen een modal of
 | bottom sheet. Zonder dat tabt een schermlezergebruiker achter het paneel
 | langs de pagina in.
 */
Alpine.plugin(focus);

/*
 | Eén component bezit de veilingstand; alle panelen lezen eruit. Zie
 | auction.js voor waarom dat één plek moet zijn.
 */
Alpine.data('auction', auction);
Alpine.data('funnel', funnel);
Alpine.data('ivyAdvice', ivyAdvice);
Alpine.data('searchInterpret', searchInterpret);
Alpine.data('slideConfirm', slideConfirm);
Alpine.data('dotField', dotField);
Alpine.data('sellingDemo', sellingDemo);
Alpine.data('bidForm', bidForm);
Alpine.data('pushToggle', pushToggle);
Alpine.data('adminLive', adminLive);
Alpine.data('lotMedia', lotMedia);

// Het zwevende paneel van Ivy, rechtsonder op elke pagina.
Alpine.data('ivyPanel', ivyPanel);

/*
 | Variant B van de A/B-test op de home (preview v4): de productdemo, de
 | verhalen, de Ivy-tabs, de Ivy-dock en het menu. Zie v4.js.
 */
Alpine.data('v4Demo', v4Demo);
Alpine.data('v4Story', v4Story);
Alpine.data('v4Engine', v4Engine);
Alpine.data('v4Dock', v4Dock);
Alpine.data('v4Nav', v4Nav);

window.Alpine = Alpine;

Alpine.start();

/*
 | Het paginascript uit aucvy-home.html: stippenveld, hero-animatie, reveals,
 | typende placeholder, tellers, afslagticker en de verkopersreis.
 */
initHome();

// Zachte navigatie op de veilingenpagina: filters wisselen zonder reload.
initLotsIndex();

// Doomscrollen op de kavelpagina: pijltjestoetsen bladeren door de kavels.
initLotNav();

// Naadloze paginawissels in het dashboard: het menu blijft staan, de inhoud wisselt.
initDashNav();

// Scroll-reveals van het v4-ontwerp (variant B van de home).
initV4Reveal();

/**
 * Echo verzorgt de websocketverbinding. Valt hij weg, dan schakelt de
 * veilingcomponent zelf over op pollen - nooit stilzwijgend een bevroren
 * scherm (hfst. 11).
 */
import './echo';
