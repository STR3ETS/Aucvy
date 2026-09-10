/**
 * De biedstorm uit hfst. 16: honderden gelijktijdige bieders op één kavel -
 * vóór de launch, niet erna. De laatste minuut van een populaire veiling is
 * een piek van events op één rij; als het daar breekt, breekt het precies
 * waar geld op tafel ligt.
 *
 * Voorbereiden:  php artisan aucvy:loadtest-prepare --bidders=500
 * Draaien:       k6 run tools/k6/bid-storm.js -e AUCTION_ID=<id> [-e BASE_URL=…] [-e VUS=500]
 * Controleren:   php artisan aucvy:loadtest-verify <id>
 *
 * De thresholds hieronder zijn de snelheidslat; de échte acceptatie is de
 * verify-command: volgnummers zonder gaten, één winnaar, kloppende tellers.
 * Een geweigerd bod (te laag door een race) is normaal veilinggedrag en telt
 * níet als fout - een 5xx of een kapotte stand wel.
 */
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Trend } from 'k6/metrics';

const BASE = __ENV.BASE_URL || 'https://aucvy.test';
const AUCTION = __ENV.AUCTION_ID;

const accepted = new Counter('bids_accepted');
const rejected = new Counter('bids_rejected_ok');
const bidLatency = new Trend('bid_latency', true);

// 4xx is veilinggedrag (te laag, overboden, al hoogste, rate limit) - alleen
// 5xx telt als fout in http_req_failed.
http.setResponseCallback(http.expectedStatuses({ min: 200, max: 499 }));

export const options = {
    insecureSkipTLSVerify: true, // laragon-certificaat
    scenarios: {
        storm: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '30s', target: Number(__ENV.VUS || 500) },
                { duration: '2m', target: Number(__ENV.VUS || 500) },
                { duration: '10s', target: 0 },
            ],
        },
    },
    thresholds: {
        // Geen serverfouten, punt. 4xx (te laag/overboden) is veilinggedrag.
        http_req_failed: ['rate<0.01'],
        // De biedronde zelf onder de seconde voor 95% (hfst. 11: <300ms is
        // het doel op productie-infra; lokaal ligt de lat lager).
        bid_latency: ['p(95)<1000'],
    },
};

function csrfFromCookie(jar) {
    const cookies = jar.cookiesForURL(BASE + '/');
    return cookies['XSRF-TOKEN'] ? decodeURIComponent(cookies['XSRF-TOKEN'][0]) : '';
}

// Per-VU staat: elke virtuele gebruiker heeft zijn eigen JS-runtime.
let loggedIn = false;

export default function () {
    const jar = http.cookieJar();

    // Eén keer per VU inloggen - elk virtueel persoon is een echte gebruiker
    // met sessie en CSRF, precies zoals een browser dat doet.
    if (!loggedIn) {
        const page = http.get(`${BASE}/inloggen`);
        const token = (page.body.match(/name="_token" value="([^"]+)"/) || [])[1];

        const login = http.post(`${BASE}/inloggen`, {
            _token: token,
            email: `storm${__VU}@aucvy.test`,
            password: 'wachtwoord',
        });

        check(login, { 'ingelogd': (r) => r.status === 200 || r.status === 302 });
        loggedIn = true;
    }

    const state = http.get(`${BASE}/api/auctions/${AUCTION}/state`, {
        headers: { Accept: 'application/json' },
    });

    if (!check(state, { 'stand opgehaald': (r) => r.status === 200 })) {
        sleep(1);
        return;
    }

    const minimum = state.json('minimum_bid_cents');

    if (!minimum) {
        sleep(1);
        return;
    }

    const bid = http.post(
        `${BASE}/api/auctions/${AUCTION}/bids`,
        JSON.stringify({ amount_cents: minimum }),
        {
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': csrfFromCookie(jar),
                'Idempotency-Key': `storm-${__VU}-${__ITER}`,
            },
        },
    );

    bidLatency.add(bid.timings.duration);

    if (bid.status === 201 || bid.status === 200) {
        accepted.add(1);
    } else if (bid.status >= 400 && bid.status < 500) {
        // Te laag, overboden, rate limit: veilinggedrag onder druk.
        rejected.add(1);
    }

    check(bid, { 'geen serverfout': (r) => r.status < 500 });

    sleep(Math.random() * 2);
}
