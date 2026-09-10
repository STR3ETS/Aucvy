# Aucvy

Het AI-commerceplatform voor zakelijke goederen. Upload een foto, Ivy herkent het object, schrijft de advertentie en geeft een waarde-indicatie. Verkopen kan via veiling, afslag of koop direct; je betaalt alleen bij resultaat.

De volledige productbeschrijving staat in [aucvy-technische-briefing.md](aucvy-technische-briefing.md). Het ontwerp waar de styling letterlijk uit komt staat in [aucvy-home.html](aucvy-home.html).

## Stack

- Laravel 13 op PHP 8.5, MySQL 8
- Blade + Alpine.js + Tailwind CSS 4 (Vite)
- AI: Anthropic Claude achter een eigen provider-interface, met een deterministische fake voor lokaal en tests
- Betalingen: Mollie achter een provider-interface, met een fake-checkout voor lokaal en tests

## Lokaal draaien (Laragon)

Vereist: PHP 8.5, Composer, Node 22, MySQL. Site draait op https://aucvy.test.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed --class=DemoSeeder
npm run build
```

Daarna, telkens als je werkt, twee terminals naast de webserver:

```bash
php artisan queue:work --queue=ai,realtime,default   # zonder dit geen Ivy en geen mails
php artisan schedule:work                            # zonder dit sluiten veilingen niet
```

Na een .env-wijziging: `php artisan config:clear` en de worker herstarten.

### Demo-accounts

De DemoSeeder maakt speelbare data met echte biedingen via de biedmotor. Wachtwoord is overal `wachtwoord`.

| E-mail | Rol |
|---|---|
| verkoper@aucvy.test | verkoper (kan niet op eigen kavels bieden) |
| anna@aucvy.test | geverifieerde bieder |
| bram@aucvy.test | geverifieerde bieder |
| nieuw@aucvy.test | niet geverifieerd, ziet de verificatiestaat |
| beheer@aucvy.test | medewerker, mag bij /beheer |

Mails gaan lokaal naar `storage/logs/laravel.log`.

## Ivy (AI-laag)

Elke AI-taak loopt via een job op de `ai`-queue en wordt gelogd in `ai_runs` met tokens en kosten. Bewaking zit ingebouwd: een noodrem (`AUCVY_AI_ENABLED`), een budgetplafond per gebruiker en per omgeving per dag, een cache op identieke aanvragen en een outputvalidator met verboden formuleringen.

```env
AUCVY_AI_PROVIDER=anthropic   # of: fake (default, kost niets)
AUCVY_AI_MODEL=claude-sonnet-5
ANTHROPIC_API_KEY=sk-ant-...
```

## Betalingen

```env
AUCVY_PAYMENT_PROVIDER=fake   # lokaal: nepcheckout onder /dev/checkout
# AUCVY_PAYMENT_PROVIDER=mollie + MOLLIE_API_KEY=test_... (webhook vereist een publieke URL)
```

## Tests en kwaliteit

De testsuite is hermetisch: phpunit.xml pint de fake providers vast, er gaat nooit een echte API-call uit een test.

```bash
php artisan test --compact          # volledige suite
vendor/bin/pint --dirty             # codestijl
```

CI ([.github/workflows/ci.yml](.github/workflows/ci.yml)) draait Pint, de Vite-build en de suite tegen MySQL bij elke push en pull request.

### Loadtest

```bash
php artisan aucvy:loadtest-prepare --bidders=50
k6 run tools/k6/bid-storm.js -e AUCTION_ID=<id> --vus 50 --duration 60s
php artisan aucvy:loadtest-verify <id>
```

De verify-stap controleert de invarianten (geen dubbele winnaar, kloppende tellers, sluitende historie).

## Structuur in het kort

| Map | Wat er zit |
|---|---|
| app/Auctions | Biedmotor: PlaceBid, staffel, anti-sniping, borggate |
| app/Ivy | AI-taken, providers, budget- en outputbewaking |
| app/Orders | Orderstatusmachine, betalingen, uitbetaling, geschillen |
| app/Dashboard | Signalen, koperintentie, vraagvoorspelling |
| app/Support/Money | Al het geld, in centen, op één plek |
| resources/views/components | Blade-componenten (biedpaneel, kavelkaart, galerij) |
| tools/k6 | Loadtestscript |

Conventies voor AI-agents staan in [CLAUDE.md](CLAUDE.md).
