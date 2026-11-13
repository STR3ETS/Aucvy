<?php

declare(strict_types=1);

use App\Mail\MailFields;
use App\Models\Lot;
use App\Models\User;

/*
| De velden van een mail over een kavel. Een concept heeft nog geen slug en
| geen openbare pagina; de automations crashten daar elk kwartier op.
*/

it('linkt een concept naar de funnel en een gepubliceerde kavel naar zijn pagina', function () {
    $user = User::factory()->create();
    $draft = Lot::factory()->anonymousDraft()->create(['seller_id' => $user->id, 'slug' => null]);
    $live = Lot::factory()->live()->create();

    expect(MailFields::build($user, ['lot' => $draft])['kavel']['link'])->toBe(route('sell', ['concept' => $draft->id]))
        ->and(MailFields::build($user, ['lot' => $live])['kavel']['link'])->toBe(route('lots.show', $live));
});
