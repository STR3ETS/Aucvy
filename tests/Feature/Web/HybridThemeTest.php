<?php

declare(strict_types=1);

use App\Models\User;

/*
| De hybride stijlgids (variant C) is het thema van elke pagina buiten de
| A/B-test: de body draagt .c, de header toont het merkteken en de footer is
| de uitgebreide variant. Alleen de home van ontwerp A doet niet mee.
*/

it('geeft de publieke pagina\'s het thema van variant c', function (string $url) {
    $this->get($url)
        ->assertOk()
        ->assertSee('class="no-js c"', escape: false)
        ->assertSee('footer-c', escape: false)
        ->assertSee('page-hero', escape: false);
})->with(['/veilingen', '/faq', '/zekerheid', '/verkooproute']);

it('geeft kavel plaatsen een eigen schil zonder sitemenu en voettekst', function () {
    $this->get('/verkopen')
        ->assertOk()
        ->assertSee('class="no-js c funnel"', escape: false)
        ->assertSee('fn-card', escape: false)
        ->assertSee('Opslaan en sluiten')
        ->assertSee('stap <span x-text="step + 1">1</span> van 8', escape: false)
        ->assertDontSee('class="header', escape: false)
        ->assertDontSee('footer-c');
});

it('zet de inlogomgeving op een eigen pagina zonder header en footer', function (string $url) {
    $this->get($url)
        ->assertOk()
        ->assertSee('class="no-js c auth"', escape: false)
        ->assertSee('auth-card', escape: false)
        ->assertDontSee('class="header', escape: false)
        ->assertDontSee('footer-c')
        ->assertDontSee('ivy-launch');
})->with(['/inloggen', '/registreren', '/wachtwoord-vergeten']);

it('geeft het dashboard en de bestellingen de dashboardschil met zijbalk', function (string $url) {
    $this->actingAs(User::factory()->verified()->create(['name' => 'Anna de Vries']))
        ->get($url)
        ->assertOk()
        ->assertSee('class="no-js c dash"', escape: false)
        ->assertSee('dash-side', escape: false)
        ->assertSee('dash-band', escape: false)
        ->assertSee('d-tile', escape: false)
        ->assertSee('Anna de Vries')
        ->assertDontSee('footer-c');
})->with(['/dashboard', '/dashboard/kavels', '/dashboard/biedingen', '/dashboard/uitbetalingen', '/dashboard/geschillen', '/bestellingen']);

it('markeert de huidige pagina in het hoofdmenu', function () {
    $this->get('/veilingen')
        ->assertOk()
        ->assertSee('class="is-on" aria-current="page">Veilingen', escape: false)
        ->assertDontSee('aria-current="page">Zakelijk', escape: false);

    $this->get('/zakelijk')
        ->assertOk()
        ->assertSee('class="is-on" aria-current="page">Zakelijk', escape: false);
});

it('toont uitgelogd alleen inloggen en kavel plaatsen in de header', function () {
    $this->get('/veilingen')
        ->assertOk()
        ->assertSee('Inloggen')
        ->assertDontSee('user-menu')
        ->assertDontSee('class="link" href="'.route('lots.index').'">Zoeken', escape: false);
});

it('toont ingelogd een profielknop met de snelle acties', function () {
    $this->actingAs(User::factory()->verified()->create(['name' => 'Anna de Vries']))
        ->get('/veilingen')
        ->assertOk()
        ->assertSee('user-menu')
        ->assertSee('Anna de Vries')
        ->assertSee('Mijn biedingen')
        ->assertSee('Uitbetalingen')
        ->assertSee('Uitloggen')
        ->assertDontSee('class="link" href="'.route('lots.index').'">Zoeken', escape: false)
        ->assertDontSee('class="link" href="'.route('dashboard').'"', escape: false);
});

it('laat de home van ontwerp a in de a/b-test buiten het thema', function () {
    $this->get('/?variant=a')
        ->assertOk()
        ->assertSee('<body class="no-js">', escape: false)
        ->assertDontSee('footer-c');
});
