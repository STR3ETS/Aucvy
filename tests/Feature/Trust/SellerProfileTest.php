<?php

declare(strict_types=1);

use App\Enums\CompanyRole;
use App\Enums\LotStatus;
use App\Models\Auction;
use App\Models\Category;
use App\Models\Company;
use App\Models\Location;
use App\Models\Lot;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
| Het publieke verkopersprofiel uit de masterbriefing (4.1): over de
| verkoper, cijfers met definitie, verkochte kavels filterbaar op categorie,
| en nooit een gerealiseerde prijs.
*/

it('toont omschrijving, beeldmerk, regio en de cijfers met hun definitie', function () {
    $company = Company::factory()->create(['description' => 'Al dertig jaar in horeca-inventaris.', 'logo_path' => '/storage/companies/1/logo.png']);
    $seller = User::factory()->verified()->create(['company_id' => $company->id, 'company_role' => CompanyRole::Owner]);
    $location = Location::factory()->create(['province' => 'Utrecht']);
    $lot = Lot::factory()->live()->create(['seller_id' => $seller->id, 'location_id' => $location->id]);
    Auction::factory()->create(['lot_id' => $lot->id]);

    $this->get(route('sellers.show', $seller))
        ->assertOk()
        ->assertSee('Al dertig jaar in horeca-inventaris.')
        ->assertSee('/storage/companies/1/logo.png')
        ->assertSee('Utrecht')
        ->assertSee('Actieve kavels')
        ->assertSee('nu te zien en te bieden')
        ->assertSee('actief sinds');
});

it('toont verkochte kavels zonder prijs, filterbaar op categorie', function () {
    $seller = User::factory()->verified()->create();
    $keukens = Category::factory()->create(['name' => 'Keukens', 'slug' => 'keukens-'.fake()->unique()->numerify('###')]);
    $heftrucks = Category::factory()->create(['name' => 'Heftrucks', 'slug' => 'heftrucks-'.fake()->unique()->numerify('###')]);

    $keuken = Lot::factory()->create(['seller_id' => $seller->id, 'status' => LotStatus::Sold, 'category_id' => $keukens->id, 'title' => 'Designkeuken Siemens', 'published_at' => now()->subDays(5)]);
    Auction::factory()->closed()->create(['lot_id' => $keuken->id, 'current_price_cents' => 940_000]);
    $heftruck = Lot::factory()->create(['seller_id' => $seller->id, 'status' => LotStatus::Sold, 'category_id' => $heftrucks->id, 'title' => 'Heftruck Linde H25', 'published_at' => now()->subDays(3)]);
    Auction::factory()->closed()->create(['lot_id' => $heftruck->id, 'current_price_cents' => 1_250_000]);

    $this->get(route('sellers.show', $seller))
        ->assertOk()
        ->assertSee('Verkochte kavels')
        ->assertSee('Designkeuken Siemens')
        ->assertSee('Heftruck Linde H25')
        // Openbaar (vraag 13), maar alleen bij een echte order; hier is er geen.
        ->assertSee('Gerealiseerde prijzen zijn openbaar')
        ->assertDontSee('9.400,00')
        ->assertDontSee('12.500,00');

    $this->get(route('sellers.show', [$seller, 'categorie' => $keukens->slug]))
        ->assertOk()
        ->assertSee('Designkeuken Siemens')
        ->assertDontSee('Heftruck Linde H25');
});

it('laat de eigenaar omschrijving, beeldmerk en cover bijwerken', function () {
    Storage::fake('public');
    $company = Company::factory()->create();
    $owner = User::factory()->verified()->create(['company_id' => $company->id, 'company_role' => CompanyRole::Owner]);

    $this->actingAs($owner)
        ->patch(route('organisation.update'), [
            'name' => $company->name,
            'description' => 'Specialist in tweedehands keukens sinds 1998.',
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            'cover' => UploadedFile::fake()->image('cover.jpg', 1200, 400),
        ])
        ->assertRedirect(route('organisation.show'));

    $company->refresh();

    expect($company->description)->toBe('Specialist in tweedehands keukens sinds 1998.')
        ->and($company->logo_path)->toContain("/storage/companies/{$company->id}/")
        ->and($company->cover_path)->toContain("/storage/companies/{$company->id}/");
});

it('weigert een profielfoto die geen jpg, png of webp is', function () {
    Storage::fake('public');
    $company = Company::factory()->create();
    $owner = User::factory()->verified()->create(['company_id' => $company->id, 'company_role' => CompanyRole::Owner]);

    $this->actingAs($owner)
        ->patch(route('organisation.update'), ['name' => $company->name, 'logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')])
        ->assertSessionHasErrors('logo');
});

it('telt afgeronde verkopen en verkochte kavels apart', function () {
    $seller = User::factory()->verified()->create();
    $lot = Lot::factory()->create(['seller_id' => $seller->id, 'status' => LotStatus::Sold, 'published_at' => now()->subDay()]);
    Order::factory()->completed()->create(['seller_id' => $seller->id, 'lot_id' => $lot->id]);

    $response = $this->get(route('sellers.show', $seller))->assertOk();

    expect($response->viewData('completedSales'))->toBe(1)
        ->and($response->viewData('soldCount'))->toBe(1)
        ->and($response->viewData('activeCount'))->toBe(0);
});
