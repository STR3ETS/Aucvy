<?php

declare(strict_types=1);

use App\Models\Sale;
use App\Models\User;

/*
| Gebundelde veilingen (Auction Studio) in de backoffice: de lijst met
| verkoper, aantal kavels, status en sluiting.
*/

it('toont de gebundelde veilingen aan beheer en houdt anderen buiten', function () {
    $staff = User::factory()->create(['is_staff' => true]);
    $seller = User::factory()->verified()->create(['name' => 'Sander Vermeulen']);
    $sale = Sale::factory()->published()->create(['seller_id' => $seller->id, 'title' => 'Faillissement bakkerij Jansen']);
    Sale::factory()->create(['title' => 'Nog een concept']);

    $this->actingAs($staff)->get(route('admin.sales'))
        ->assertOk()
        ->assertSee('Faillissement bakkerij Jansen')
        ->assertSee('Sander Vermeulen')
        ->assertSee('Nog een concept')
        ->assertSee(route('sales.show', $sale));

    $this->actingAs($staff)->get(route('admin.sales', ['q' => 'bakkerij']))->assertOk()->assertSee('Faillissement bakkerij Jansen')->assertDontSee('Nog een concept');

    $this->actingAs($seller)->get(route('admin.sales'))->assertForbidden();
});
