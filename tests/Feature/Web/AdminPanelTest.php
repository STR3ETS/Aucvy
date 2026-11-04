<?php

declare(strict_types=1);

use App\Enums\AiAgent;
use App\Enums\AiRunStatus;
use App\Enums\LotStatus;
use App\Models\AiRun;
use App\Models\Category;
use App\Models\Lot;
use App\Models\Order;
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
        ->assertSee('Vandaag')
        ->assertSee('Kavels live');
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

it('toont het kaveldetail binnen de backoffice', function () {
    $lot = Lot::factory()->live()->create();

    $this->actingAs(staffMember())->get('/admin/kavels/'.$lot->id)
        ->assertOk()
        ->assertSee($lot->title)
        ->assertSee('Kerngegevens');
});

it('toont het gebruikersdetail binnen de backoffice', function () {
    $user = User::factory()->verified()->create();

    $this->actingAs(staffMember())->get('/admin/gebruikers/'.$user->id)
        ->assertOk()
        ->assertSee($user->name);
});

it('toont het bedrijfsdetail binnen de backoffice', function () {
    $user = User::factory()->verified()->business()->create();

    $this->actingAs(staffMember())->get('/admin/bedrijven/'.$user->company->id)
        ->assertOk()
        ->assertSee($user->company->name)
        ->assertSee('Bedrijfsgegevens');
});

it('toont het orderdetail binnen de backoffice', function () {
    $order = Order::factory()->create();

    $this->actingAs(staffMember())->get('/admin/orders/'.$order->id)
        ->assertOk()
        ->assertSee($order->order_number);
});

it('linkt vanuit de kavellijst naar het eigen detail, niet naar de site', function () {
    $lot = Lot::factory()->live()->create();

    $this->actingAs(staffMember())->get('/admin/kavels')
        ->assertOk()
        ->assertSee('/admin/kavels/'.$lot->id)
        ->assertDontSee(route('lots.show', $lot));
});

it('filtert de kavellijst op status', function () {
    Lot::factory()->create(['title' => 'Conceptkavel zonder podium']);
    Lot::factory()->live()->create(['title' => 'Levende kavel op het podium']);

    $this->actingAs(staffMember())->get('/admin/kavels?status=draft')
        ->assertOk()
        ->assertSee('Conceptkavel zonder podium')
        ->assertDontSee('Levende kavel op het podium');
});

it('ruimt met de bulkactie alleen concepten op', function () {
    $draft = Lot::factory()->create();
    $live = Lot::factory()->live()->create();

    $this->actingAs(staffMember())
        ->post('/admin/kavels/concepten-verwijderen', ['ids' => [$draft->id, $live->id]])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(Lot::query()->whereKey($draft->id)->exists())->toBeFalse()
        ->and(Lot::query()->whereKey($live->id)->exists())->toBeTrue()
        ->and($live->fresh()->status)->toBe(LotStatus::Live);
});

it('verwijdert ai-runs in bulk', function () {
    $lot = Lot::factory()->live()->create();

    $run = AiRun::create([
        'subject_type' => $lot->getMorphClass(),
        'subject_id' => $lot->id,
        'user_id' => $lot->seller_id,
        'agent' => AiAgent::Ivy,
        'task' => 'recognise_lot',
        'input' => [],
        'status' => AiRunStatus::Completed,
    ]);

    $this->actingAs(staffMember())
        ->post('/admin/ai-runs/verwijderen', ['ids' => [$run->id]])
        ->assertRedirect();

    expect(AiRun::query()->whereKey($run->id)->exists())->toBeFalse();
});

it('houdt geschillen binnen de backoffice-layout', function () {
    $this->actingAs(staffMember())->get('/beheer/geschillen')
        ->assertOk()
        ->assertSee('Aucvy Backoffice')
        ->assertSee('Naar de site');
});

it('levert het pollbare activiteitenblok', function () {
    Lot::factory()->live()->create();

    $this->actingAs(staffMember())->get('/admin/activiteit')
        ->assertOk()
        ->assertSee('Live activiteit');
});

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
