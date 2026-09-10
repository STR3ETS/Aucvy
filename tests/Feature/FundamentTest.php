<?php

declare(strict_types=1);

/*
| De definition of done van sprint 0: een pagina in de huisstijl staat er, in
| het Nederlands, met de toegankelijkheidsvloer op zijn plek. Deze tests leggen
| die afspraken vast zodat ze niet stilzwijgend wegzakken.
*/

it('serveert de homepage', function () {
    $this->get('/')->assertOk();
});

it('rendert de publieke layout met een skip-link', function () {
    // Skip-link bovenaan elke pagina is een harde eis uit hfst. 12.
    $this->get('/')
        ->assertOk()
        ->assertSee('Naar de hoofdinhoud')
        ->assertSee('id="hoofdinhoud"', escape: false);
});

it('staat in het nederlands', function () {
    expect(app()->getLocale())->toBe('nl')
        ->and(config('app.fallback_locale'))->toBe('nl');

    $this->get('/')->assertSee('lang="nl"', escape: false);
});

it('slaat tijden op in utc en toont ze in europe/amsterdam', function () {
    // Zou de opslag in lokale tijd staan, dan verschuift een veilingeindtijd
    // tijdens de zomertijdovergang. Zie config/aucvy.php.
    expect(config('app.timezone'))->toBe('UTC')
        ->and(config('aucvy.display_timezone'))->toBe('Europe/Amsterdam');
});

it('draait de testsuite tegen mysql, niet sqlite', function () {
    // De biedmotor leunt op rij-locks (SELECT ... FOR UPDATE). Draait deze
    // suite ooit weer op sqlite, dan zijn de concurrency-tests uit stap 3
    // stilzwijgend betekenisloos. Vandaar deze wacht.
    expect(config('database.default'))->toBe('mysql');
});

describe('platformconfiguratie', function () {
    it('houdt het opgeld voor kopers op nul in de mvp', function () {
        expect(config('aucvy.money.buyer_premium_percent'))->toBe(0.0);
    });

    it('heeft een oplopende biedstaffel die eindigt met een open bovengrens', function () {
        $table = config('aucvy.auction.increment_table');

        expect($table)->not->toBeEmpty()
            ->and(end($table)['up_to_cents'])->toBeNull();

        $previousCeiling = 0;

        foreach ($table as $tier) {
            expect($tier['step_cents'])->toBeCents()->toBeGreaterThan(0);

            if ($tier['up_to_cents'] !== null) {
                expect($tier['up_to_cents'])->toBeGreaterThan($previousCeiling);
                $previousCeiling = $tier['up_to_cents'];
            }
        }
    });

    it('heeft een noodrem voor de ai-laag', function () {
        // Ivy mag nooit een blokkade zijn (hfst. 8) - de vlag moet bestaan.
        expect(config('aucvy.ai'))->toHaveKey('enabled');
    });
});
