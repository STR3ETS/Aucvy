<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Weergavetijdzone
    |--------------------------------------------------------------------------
    |
    | De applicatie slaat alles op in UTC (config/app.php). Deze tijdzone geldt
    | uitsluitend voor weergave. Zou je in lokale tijd opslaan, dan verschuift
    | een veilingeindtijd tijdens de zomertijdovergang - dat is een uur waarin
    | biedingen op de verkeerde stand landen.
    |
    */

    'display_timezone' => env('AUCVY_DISPLAY_TIMEZONE', 'Europe/Amsterdam'),

    // De gegevens van Aucvy zelf op facturen (hoofdbriefing p. 7); adres, KVK en btw-nummer vult Raph aan.
    'company' => [
        'name' => env('AUCVY_COMPANY_NAME', 'Aucvy B.V.'),
        'address' => env('AUCVY_COMPANY_ADDRESS', ''),
        'postal_city' => env('AUCVY_COMPANY_POSTAL_CITY', ''),
        'kvk' => env('AUCVY_COMPANY_KVK'),
        'vat' => env('AUCVY_COMPANY_VAT'),
    ],

    // Delen en verspreiden: het lettertype van het deelbeeld (Manrope, OFL).
    'distribution' => [
        'font' => env('AUCVY_SHARE_FONT', resource_path('fonts/Manrope-Variable.ttf')),
    ],

    /*
     | Ivy als gesprekspartner (Ivy-briefing): de noodremmen per onderdeel,
     | de chatprovider, bundel- en stille uren, bewaartermijnen voor gasten.
     | De vlaggen zijn in beheer om te zetten zonder deploy.
     */
    'ivy' => [
        'chat_provider' => env('AUCVY_IVY_CHAT_PROVIDER', 'fake'),
        'flags' => [
            'ivy.enabled' => (bool) env('AUCVY_IVY_ENABLED', true),
            'ivy.website' => true,
            'ivy.seller' => true,
            'ivy.buyer' => true,
            'ivy.proposals' => true,
            'ivy.reserve_change' => false,
            'auctions.dutch' => (bool) env('AUCVY_DUTCH_ENABLED', false),
        ],
        'bundle_minutes' => 15,
        'quiet_start' => 22,
        'quiet_end' => 8,
        'guest_retention_days' => 30,
        'guest_photo_hours' => 24,
        'guest_upload_max' => 5,
    ],

    /*
     | Live veilen (briefing nieuwe functies): achter een vlag, eerst een
     | besloten proef na Stripe Connect. LiveKit Cloud als mediaserver, later
     | zelf gehost; `fake` draait zonder server. Kijkersmaximum per
     | uitzending en een maandbudget in kijkminuten met een alert.
     */
    'live' => [
        'enabled' => (bool) env('AUCVY_LIVE_ENABLED', false),
        'provider' => env('AUCVY_LIVE_PROVIDER', 'fake'),
        'max_viewers' => (int) env('AUCVY_LIVE_MAX_VIEWERS', 500),
        'monthly_budget_minutes' => (int) env('AUCVY_LIVE_BUDGET_MINUTES', 600),
        'fake_webhook_secret' => env('AUCVY_LIVE_FAKE_SECRET', 'fake'),
        // De browserclient van LiveKit; wordt alleen geladen als er een uitzending loopt.
        'client_script' => env('AUCVY_LIVE_CLIENT_SCRIPT', 'https://cdn.jsdelivr.net/npm/livekit-client@2.9.0/dist/livekit-client.umd.min.js'),
        'livekit' => [
            'host' => env('LIVEKIT_HOST'),
            'ws_url' => env('LIVEKIT_WS_URL'),
            'api_key' => env('LIVEKIT_API_KEY'),
            'api_secret' => env('LIVEKIT_API_SECRET'),
        ],
    ],

    /*
     | Mail Builder en reeksen (briefing nieuwe functies). Service gaat
     | altijd; activatie tot iemand zich afmeldt; campagne alleen met een
     | uitdrukkelijke ja. Don toetst de indeling; dit zijn de standaarden.
     | Marketing gaat via een eigen Mailgun-subdomein als die is ingesteld.
     */
    'mail' => [
        'marketing_mailer' => env('AUCVY_MARKETING_MAILER'),
        'campaign_requires_consent' => true,
        'activation_requires_consent' => false,
        'weekly_marketing_limit' => (int) env('AUCVY_MAIL_WEEKLY_LIMIT', 3),
        'suppress_service_too' => false,
    ],

    /*
     | Interesse en Hot (hoofdbriefing p. 7). Hot als de score van de
     | afgelopen 48 uur bij de hoogste 10% van de categorie hoort, met
     | minimaal 50 kijkers en bij een veiling 3 bieders. Drempels instelbaar.
     */
    'interest' => [
        'window_hours' => 48,
        'top_fraction' => 0.10,
        'min_viewers' => (int) env('AUCVY_HOT_MIN_VIEWERS', 50),
        'min_bidders' => (int) env('AUCVY_HOT_MIN_BIDDERS', 3),
        'weights' => ['viewer' => 1, 'document' => 2, 'saved' => 3, 'shared' => 3, 'bidder' => 5],
    ],

    /*
    |--------------------------------------------------------------------------
    | Biedmotor
    |--------------------------------------------------------------------------
    |
    | Dit zijn de standaardwaarden waarmee een nieuwe veiling wordt aangemaakt.
    | De veiling krijgt daarna een eigen kopie in de database. Dat is bewust:
    | verandert deze config, dan verandert de staffel van een lopende veiling
    | niet met terugwerkende kracht mee (briefing hfst. 5).
    |
    */

    'auction' => [

        /*
         | Anti-sniping: een bod binnen `window` seconden van het einde verlengt
         | de veiling met `extend` seconden. Onbeperkt herhaalbaar.
         */
        'anti_snipe_window_seconds' => (int) env('AUCVY_ANTI_SNIPE_WINDOW_SECONDS', 30),
        'anti_snipe_extend_seconds' => (int) env('AUCVY_ANTI_SNIPE_EXTEND_SECONDS', 60),

        /*
         | Biedstaffel. Elke regel geldt tot en met `up_to_cents`; de laatste
         | regel heeft `up_to_cents => null` en vangt alles daarboven.
         |
         |   tot € 500      → stap € 10
         |   tot € 5.000    → stap € 50
         |   daarboven      → stap € 250
         */
        'increment_table' => [
            ['up_to_cents' => 50_000, 'step_cents' => 1_000],
            ['up_to_cents' => 500_000, 'step_cents' => 5_000],
            ['up_to_cents' => null, 'step_cents' => 25_000],
        ],

        /*
         | Maximaal aantal events dat `/events?after={seq}` teruggeeft. Meer dan
         | dit betekent voor de client: volledig herladen (briefing hfst. 7).
         */
        'max_replay_events' => 200,

        /*
         | Rate limiting op het biedpad (briefing hfst. 6).
         */
        'rate_limit' => [
            'bids_per_user_per_auction' => 10,
            'decay_seconds' => 10,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Geld
    |--------------------------------------------------------------------------
    |
    | Alle bedragen in centen, altijd int, nooit float. Zie hfst. 4 en 9.
    |
    */

    'money' => [

        /*
         | Opgeld voor de koper. Blijft 0 in de MVP - het veld bestaat zodat het
         | later aan te zetten is zonder migratie op een draaiende veiling.
         */
        'buyer_premium_percent' => (float) env('AUCVY_BUYER_PREMIUM_PERCENT', 0),

        /*
         | Btw-tarief bij het regime `vat_21`. Bij `margin` is er geen
         | verrekenbare btw en telt dit tarief niet mee.
         */
        'vat_rate_percent' => 21.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Borg bij het bieden
    |--------------------------------------------------------------------------
    |
    | Boven dit bedrag vraagt het platform een reservering vóórdat iemand mag
    | bieden. Dit is de enige echte bescherming tegen een winnende bieder die
    | niet betaalt (briefing hfst. 9). De exacte drempel staat nog als open
    | beslissing in hfst. 18 - dit is het voorstel.
    |
    */

    'deposit' => [
        'threshold_cents' => (int) env('AUCVY_DEPOSIT_THRESHOLD_CENTS', 250_000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Prijzen en treden (besluiten Raph en Don, 2 november 2026)
    |--------------------------------------------------------------------------
    |
    | Eén bron voor alle getallen die in chat, mail en op pagina's terugkomen;
    | zie App\Support\PlatformFacts. Niets hiervan staat ergens hardcoded.
    |
    */

    'pricing' => [
        // Succesfee als staffel over de omzet op Aucvy in de laatste twaalf
        // maanden (hamerbedrag); het tarief ligt vast op het moment van verkoop.
        'use_fee_tiers' => (bool) env('AUCVY_USE_FEE_TIERS', true),
        'fee_tier_window_months' => 12,
        'fee_tiers' => [
            ['up_to_cents' => 2_500_000, 'percent' => 15.0],
            ['up_to_cents' => 5_000_000, 'percent' => 12.5],
            ['up_to_cents' => 25_000_000, 'percent' => 10.0],
            ['up_to_cents' => 50_000_000, 'percent' => 9.0],
            ['up_to_cents' => 100_000_000, 'percent' => 8.0],
            ['up_to_cents' => 250_000_000, 'percent' => 7.0],
            ['up_to_cents' => null, 'percent' => 6.0],
        ],

        // Administratiekosten voor de koper, per order, exclusief btw (N4).
        'admin_fee_cents' => (int) env('AUCVY_ADMIN_FEE_CENTS', 1_500),

        // Tot dit bedrag bieden met bevestigd e-mailadres en telefoonnummer;
        // daarboven is de identiteitscheck nodig (N5).
        'bid_free_limit_cents' => (int) env('AUCVY_BID_FREE_LIMIT_CENTS', 25_000),

        // Algemene borg, exclusief btw, in treden op het bod (N6).
        'deposit_tiers' => [
            ['above_cents' => 250_000, 'deposit_cents' => 20_000],
            ['above_cents' => 1_000_000, 'deposit_cents' => 50_000],
            ['above_cents' => 2_500_000, 'deposit_cents' => 100_000],
        ],

        // Gerealiseerde prijzen en biednamen zijn openbaar (vraag 13).
        'public_sold_prices' => (bool) env('AUCVY_PUBLIC_SOLD_PRICES', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Orders en uitbetaling
    |--------------------------------------------------------------------------
    |
    | De termijnen uit de zekerheidspagina. Wat daar beloofd wordt, dwingt het
    | systeem hier af - verandert de tekst, dan verandert deze config mee.
    |
    */

    'orders' => [
        'payment_deadline_hours' => 48,
        'auto_deliver_after_days' => 7,
        'dispute_window_hours' => 48,
        'seller_response_hours' => 72,
        'payout_hold_hours' => 48,
    ],

    /*
    |--------------------------------------------------------------------------
    | Betaalprovider
    |--------------------------------------------------------------------------
    |
    | Zelfde patroon als de AI-provider: één interface, wisselen is één
    | env-variabele. `fake` draait zonder Mollie-account en levert een lokale
    | nepcheckout; `mollie` is de echte API (MOLLIE_API_KEY in services).
    |
    */

    'payments' => [
        'provider' => env('AUCVY_PAYMENT_PROVIDER', 'fake'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ivy - de AI-laag
    |--------------------------------------------------------------------------
    |
    | `enabled` is de noodrem. Staat hij uit, dan blijft de hele applicatie
    | werken: de funnel is handmatig invulbaar, de zoekpagina valt terug op
    | gewone filters. Ivy mag nooit een blokkade zijn (briefing hfst. 8).
    |
    */

    'ai' => [
        'enabled' => (bool) env('AUCVY_AI_ENABLED', true),
        'daily_budget_cents_per_user' => (int) env('AUCVY_AI_DAILY_BUDGET_CENTS', 500),
        // Totaalbudget per omgeving per dag - de tweede rem uit hfst. 8, ook
        // voor bezoekers zonder account (de funnel werkt zonder login).
        'daily_budget_cents_total' => (int) env('AUCVY_AI_DAILY_BUDGET_TOTAL_CENTS', 10_000),
        // Zonder account: zoveel taken per adres per dag (zoekvragen, de
        // funnel zonder login). Het omgevingsbudget blijft de harde grens.
        'daily_runs_per_guest_ip' => (int) env('AUCVY_AI_DAILY_RUNS_PER_GUEST_IP', 60),
        'task_timeout_seconds' => 180,

        /*
         | Welke provider de taken uitvoert. `fake` is deterministisch en
         | draait zonder API-sleutel - lokaal en in tests. `anthropic` is de
         | echte multimodale API, achter een eigen interface zodat wisselen
         | één klasse is (hfst. 14).
         */
        'provider' => env('AUCVY_AI_PROVIDER', 'fake'),
        'model' => env('AUCVY_AI_MODEL', 'claude-opus-5'),
        // Goedkoper model voor eenvoudige chatvragen; het sterkere voor advies en foto's (Ivy-briefing hfst. 13).
        'chat_model_fast' => env('AUCVY_AI_CHAT_MODEL_FAST', 'claude-sonnet-5'),

        /*
         | Onderdeel van de cachesleutel: hash(input + promptversie). Wijzigt
         | een prompt, verhoog dit nummer - anders serveert de cache antwoorden
         | van de oude prompt (hfst. 8).
         */
        'prompt_version' => 2,

        /*
         | Onder deze zekerheid moet de verkoper een door Ivy ingevuld veld
         | bevestigen voordat de kavel gepubliceerd kan worden (briefing,
         | regels voor de AI-uitvoer: voorstel 0,8).
         */
        'confirmation_threshold' => (float) env('AUCVY_AI_CONFIRMATION_THRESHOLD', 0.8),

        // Engelse vertaling van elke gepubliceerde advertentie (briefing stap 4). Eén run per publicatie of tekstwijziging.
        'translate' => (bool) env('AUCVY_AI_TRANSLATE', true),

        /*
         | Prijzen in centen per miljoen tokens, om cost_cents te vullen.
         | Onbekende modellen (waaronder `fake`) kosten 0.
         */
        'pricing' => [
            'claude-opus-5' => ['input_cents_per_mtok' => 500, 'output_cents_per_mtok' => 2_500],
            'claude-sonnet-5' => ['input_cents_per_mtok' => 300, 'output_cents_per_mtok' => 1_500],
        ],
    ],

    /*
     | Support (briefing sectie 1): waar het contactformulier heen mailt.
     | Zonder eigen adres valt hij terug op de afzender van de app.
     */
    'support' => [
        // `?:` en niet een standaardwaarde: een lege regel in .env is anders
        // een leeg adres, en een mail naar '' verdwijnt zonder fout.
        'email' => env('AUCVY_SUPPORT_EMAIL') ?: env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        // Klantenservice (control center): eerste reactie van een mens binnen
        // zoveel uur; te laat wordt een taak in "Wacht op jou".
        'first_response_hours' => (int) env('AUCVY_SUPPORT_FIRST_RESPONSE_HOURS', 8),
        // Mailgun-route voor inkomende mail: een mail aan dit adres wordt een ticket.
        'inbound_address' => env('AUCVY_SUPPORT_INBOUND', 'service@aucvy.nl'),
    ],

    /*
     | Het grootboek van het platform (control center). Betaalkosten komen
     | straks uit de Stripe-webhook; tot dan een schatting per order.
     */
    'ledger' => [
        'payment_cost_percent' => (float) env('AUCVY_PAYMENT_COST_PERCENT', 1.4),
        'payment_cost_fixed_cents' => (int) env('AUCVY_PAYMENT_COST_FIXED_CENTS', 25),
    ],

    /*
     | Kavels: de preflight van stap 8. De lijst verboden categorieën is een
     | besluit van Raphael; tot die er is, is hij leeg. Slugs van categorieën,
     | gescheiden door komma's; een ouder telt voor al haar kinderen.
     */
    'listings' => [
        // Vooraf modereren (besluit Raph, 2 november 2026): Ivy controleert
        // elke kavel vóór hij live gaat. Alleen uitzetten in een noodgeval.
        'pre_moderation' => (bool) env('AUCVY_PRE_MODERATION', true),

        'forbidden_categories' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('AUCVY_FORBIDDEN_CATEGORIES', '')),
        ))),
    ],

    /*
     | Verificatie (briefing flow E en sectie 7). Zonder gekoppelde
     | identiteitsdienst gaat een aangevraagde identiteits- of bedrijfscontrole
     | naar de compliance-queue in de backoffice en beslist een mens. Met
     | `auto_approve` aan keurt het systeem direct goed: alleen voor lokaal
     | en demo, nooit in productie.
     */
    'verification' => [
        'auto_approve' => (bool) env('AUCVY_VERIFICATION_AUTO_APPROVE', false),
    ],

    /*
     | Identiteit via de bank (besluit Raph, 2 november 2026: iDIN, partij in
     | overleg met Remco, V7). 'manual' is de compliance-queue zoals nu;
     | 'fake' is de nepbank voor lokaal. Een echte partij is één klasse erbij.
     */
    'identity' => [
        'provider' => env('AUCVY_IDENTITY_PROVIDER', 'manual'),
    ],

    /*
     | Sms voor de telefoonbevestiging (besluit Raph, 2 november 2026). Tot de
     | partij gekozen is (V3) gaat alles naar het log.
     */
    'sms' => [
        'driver' => env('AUCVY_SMS_DRIVER', 'log'),
    ],

    /*
     | De Trust Index (preview v4, briefing hfst. 19): onder dit aantal
     | afgeronde transacties tonen we bewust géén score - er valt dan te
     | weinig te meten om eerlijk te zijn.
     */
    'trust' => [
        'minimum_transactions' => 10,
    ],

    /*
     | Transportindicaties (hfst. 14): providervrij. `fake` rekent een
     | deterministische indicatie; een echte partner is één klasse erbij.
     | De indicatie blijft per kavel-postcodecombinatie een dag gecached.
     */
    'shipping' => [
        'provider' => env('AUCVY_SHIPPING_PROVIDER', 'fake'),
        'quote_ttl_hours' => 24,
    ],

    /*
     | Bewaartermijnen (hfst. 15): vastgelegd in een verwijderjob, niet in
     | een document. `aucvy:prune-expired` dwingt ze dagelijks af.
     |
     |   financieel (biedingen, orders)  7 jaar  - fiscale bewaarplicht
     |   veilingevents                   13 maanden
     |   AI-runs                         12 maanden
     */
    /*
     | Het werkplan: de signalen (taken) worden bij een bezoek bijgewerkt,
     | maar hooguit één keer per zoveel seconden per gebruiker. Nul zet de
     | drempel uit (tests).
     */
    'dashboard' => [
        'signal_sync_seconds' => (int) env('AUCVY_SIGNAL_SYNC_SECONDS', 60),
    ],

    'retention' => [
        'financial_years' => 7,
        'auction_events_months' => 13,
        'ai_runs_months' => 12,
        // Het auditlog is bewijs bij geschillen en voor de fiscus; na deze
        // termijn mag het weg, in stukken, buiten de drukte.
        'audit_log_years' => 7,
        // De inzage-export: de downloadlink is 24 uur geldig, het bestand
        // zelf (naam, adres, biedingen) hoort daarna niet te blijven staan.
        'exports_days' => 7,
        // Het afleverlog van mail: lang genoeg om een klacht over een
        // gemiste mail na te kunnen gaan, niet langer.
        'mail_deliveries_days' => 90,
    ],

    /*
     | De A/B-test op de home: het oude ontwerp uit aucvy-home.html (a)
     | tegenover het nieuwe ontwerp uit preview v4 (b). Variant b is gekozen:
     | de test staat uit en iedereen krijgt `default`. Zet de test aan en de
     | verdeling is het aandeel bezoekers dat b krijgt; de cookie houdt die
     | toewijzing zoveel dagen vast. ?variant=a en ?variant=b werken altijd.
     */
    'experiments' => [
        'home' => [
            'enabled' => (bool) env('AUCVY_HOME_EXPERIMENT_ENABLED', false),
            'default' => env('AUCVY_HOME_VARIANT', 'c'),
            'redesign_share' => 0.5,
            'cookie_days' => 30,
        ],
    ],

];
