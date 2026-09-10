<?php

declare(strict_types=1);

/*
| De uitlegpagina's uit hfst. 2b. Net als bij de zekerheidspagina geldt:
| tarieven en drempels komen uit dezelfde bron als de code, dus de tests
| bewijzen die koppeling - en dat er nergens een escrow-claim staat.
*/

it('toont de verkooproute met de tarieven uit CompanyPlan', function () {
    $this->get('/verkooproute')
        ->assertOk()
        ->assertSee('Van foto tot uitbetaling')
        ->assertSee('7,5%')
        ->assertSee('3,5%')
        ->assertSee('2,5%')
        ->assertSee('Kavel plaatsen');
});

it('toont de faq met de drempels en termijnen uit de config', function () {
    $this->get('/faq')
        ->assertOk()
        ->assertSee('Veelgestelde vragen')
        ->assertSee('binnen '.config('aucvy.orders.payment_deadline_hours').' uur');
});

it('claimt nergens escrow of garantie', function () {
    $this->get('/verkooproute')->assertDontSee('escrow')->assertDontSee('gegarandeerd');
    $this->get('/faq')->assertDontSee('escrow')->assertDontSee('gegarandeerd');
});

it('stuurt /zoeken door naar de veilingenpagina met de vraag mee', function () {
    $this->get('/zoeken?q=keukens')
        ->assertRedirect(route('lots.index', ['q' => 'keukens']));

    $this->get('/zoeken')->assertRedirect(route('lots.index'));
});
