import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import auction from './auction';
import funnel from './funnel';
import { ivyAdvice, searchInterpret } from './ivy-tasks';
import slideConfirm from './slide-confirm';
import dotField from './dot-field';
import sellingDemo from './selling-demo';
import bidForm from './bid-form';
import pushToggle from './push';
import adminLive from './admin-live';
import initHome from './home';
import initLotsIndex from './lots-index';
import initLotNav from './lot-nav';

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

/**
 * Echo verzorgt de websocketverbinding. Valt hij weg, dan schakelt de
 * veilingcomponent zelf over op pollen - nooit stilzwijgend een bevroren
 * scherm (hfst. 11).
 */
import './echo';
