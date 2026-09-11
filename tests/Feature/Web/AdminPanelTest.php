<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Lot;
use App\Models\User;
use Database\Seeders\CategorySeeder;

/*
| De backoffice op /admin: eigen huisstijl, menu links, alles inzien.
| Uitsluitend voor medewerkers, en categoriebeheer met vangrails.
*/

function staffMember(): User
{
    $user = User::factory()->verified()->create();
    $user->is_staff = true;
    $user->save();

    return $user;
}

it('weigert bezoekers en gewone gebruikers', function () {
    $this->get('/admin')->assertRedirect(route('login'));

    $user = User::factory()->verified()->create();
    $this->actingAs($user)->get('/admin')->assertForbidden();
});

it('laat een medewerker binnen op het overzicht', function () {
    $this->actingAs(staffMember())->get('/admin')
        ->assertOk()
        ->assertSee('Overzicht')
        ->assertSee('Live kavels');
});

it('stuurt een medewerker van het werkplan door naar de backoffice', function () {
    $this->actingAs(staffMember())->get('/dashboard')->assertRedirect('/admin');
});

it('toont de beheerlijsten', function (string $path) {
    Lot::factory()->live()->create();

    $this->actingAs(staffMember())->get($path)->assertOk();
})->with([
    '/admin/kavels', '/admin/veilingen', '/admin/biedingen', '/admin/orders',
    '/admin/uitbetalingen', '/admin/gebruikers', '/admin/bedrijven',
    '/admin/reviews', '/admin/bezichtigingen', '/admin/ai-runs', '/admin/categorieen',
]);

it('maakt een subcategorie aan onder een hoofdcategorie', function () {
    $this->seed(CategorySeeder::class);
    $root = Category::query()->roots()->first();

    $this->actingAs(staffMember())
        ->post('/admin/categorieen', ['name' => 'Palletstellingen', 'parent_id' => $root->id])
        ->assertRedirect();

    expect(Category::query()->where('name', 'Palletstellingen')->where('parent_id', $root->id)->exists())->toBeTrue();
});

it('weigert een categorie met kavels te verwijderen', function () {
    $lot = Lot::factory()->live()->create();
    $category = $lot->category;

    $this->actingAs(staffMember())
        ->delete('/admin/categorieen/'.$category->id)
        ->assertSessionHasErrors('category');

    expect(Category::query()->whereKey($category->id)->exists())->toBeTrue();
});

it('verwijdert een lege categorie wel', function () {
    $category = Category::factory()->create(['parent_id' => null]);

    $this->actingAs(staffMember())
        ->delete('/admin/categorieen/'.$category->id)
        ->assertRedirect();

    expect(Category::query()->whereKey($category->id)->exists())->toBeFalse();
});
