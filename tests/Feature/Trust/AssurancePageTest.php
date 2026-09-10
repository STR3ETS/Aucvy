<?php

declare(strict_types=1);

/*
| "De zekerheidspagina is een belofte met deadlines - verandert de tekst, dan
| verandert de code mee, en andersom" (hfst. 13.6). Deze test bewijst dat de
| koppeling echt bestaat: de pagina leest dezelfde config als orders:advance.
*/

it('toont de termijnen uit de config', function () {
    $this->get('/zekerheid')
        ->assertOk()
        ->assertSee('binnen 48 uur')
        ->assertSee('binnen 72 uur')
        ->assertSee('medewerker')
        ->assertSee('Waar we nog aan werken');
});

it('verandert mee met de config - tekst en code kunnen niet uiteenlopen', function () {
    config(['aucvy.orders.seller_response_hours' => 96]);

    $this->get('/zekerheid')->assertSee('binnen 96 uur');
});

it('claimt nergens escrow', function () {
    $this->get('/zekerheid')->assertDontSee('escrow');
});
