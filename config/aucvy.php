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
        'task_timeout_seconds' => 180,

        /*
         | Welke provider de taken uitvoert. `fake` is deterministisch en
         | draait zonder API-sleutel - lokaal en in tests. `anthropic` is de
         | echte multimodale API, achter een eigen interface zodat wisselen
         | één klasse is (hfst. 14).
         */
        'provider' => env('AUCVY_AI_PROVIDER', 'fake'),
        'model' => env('AUCVY_AI_MODEL', 'claude-opus-5'),

        /*
         | Onderdeel van de cachesleutel: hash(input + promptversie). Wijzigt
         | een prompt, verhoog dit nummer - anders serveert de cache antwoorden
         | van de oude prompt (hfst. 8).
         */
        'prompt_version' => 1,

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
    'retention' => [
        'financial_years' => 7,
        'auction_events_months' => 13,
        'ai_runs_months' => 12,
    ],

];
