<?php

declare(strict_types=1);

use App\Models\Lot;
use App\Models\User;

/*
| De backoffice op /admin (Filament): uitsluitend voor medewerkers, met
| resources voor alles wat beheerd moet kunnen worden.
*/

function staffMember(): User
{
    $user = User::factory()->verified()->create();
    $user->is_staff = true;
    $user->save();

    return $user;
}

it('weigert bezoekers en gewone gebruikers', function () {
    $this->get('/admin')->assertRedirect();

    $user = User::factory()->verified()->create();
    $this->actingAs($user)->get('/admin')->assertForbidden();
});

it('laat een medewerker binnen', function () {
    $this->actingAs(staffMember())->get('/admin')->assertOk();
});

it('toont de beheerlijsten', function (string $path) {
    Lot::factory()->live()->create();

    $this->actingAs(staffMember())->get($path)->assertOk();
})->with(['/admin/categories', '/admin/lots', '/admin/auctions', '/admin/orders', '/admin/users', '/admin/bids']);
