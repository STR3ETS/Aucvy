<?php

declare(strict_types=1);

use App\Models\Lot;

/*
| Bladeren met de pijltjestoetsen (doomscrollen): de server levert de
| buurkavels als data-attributen, met wrap-around zodat het nooit ophoudt.
*/

it('geeft de buurkavels mee met wrap-around', function () {
    [$first, $middle, $last] = Lot::factory()->live()->count(3)->create()->all();

    $this->get('/kavel/'.$middle->slug)
        ->assertOk()
        ->assertSee('data-next="'.route('lots.show', $last).'"', false)
        ->assertSee('data-prev="'.route('lots.show', $first).'"', false);

    // Na de laatste kom je weer bij de eerste, en andersom.
    $this->get('/kavel/'.$last->slug)
        ->assertSee('data-next="'.route('lots.show', $first).'"', false);

    $this->get('/kavel/'.$first->slug)
        ->assertSee('data-prev="'.route('lots.show', $last).'"', false);
});

it('laat de bladerattributen weg als er niets te bladeren valt', function () {
    $lot = Lot::factory()->live()->create();

    $this->get('/kavel/'.$lot->slug)
        ->assertOk()
        ->assertDontSee('data-next=', false);
});
