<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Lot;
use App\Models\LotImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
| Randgevallen uit de codereview van de import: een nul is een waarde, een
| verkeerd benoemd bestand geeft een nette fout, en twee keer klikken
| importeert niet twee keer.
*/

beforeEach(function () {
    Storage::fake('local');
    $this->seller = User::factory()->verified()->create();
    Category::factory()->create(['name' => 'Heftrucks', 'slug' => 'heftrucks']);
});

it('leest een nul als waarde en niet als leeg', function () {
    $csv = "Titel;Categorie;Alleen ophalen;Transport vanaf;Startprijs\r\nLinde H25;Heftrucks;0;0;0\r\n";

    $this->actingAs($this->seller)->post(route('dashboard.import.store'), ['file' => UploadedFile::fake()->createWithContent('kavels.csv', $csv)]);
    $import = LotImport::query()->sole();

    $this->actingAs($this->seller)
        ->post(route('dashboard.import.run', $import), ['mapping' => ['title' => 0, 'category' => 1, 'pickup_only' => 2, 'transport_from' => 3, 'start_price' => 4]])
        ->assertSessionHasNoErrors();

    $lot = Lot::query()->where('title', 'Linde H25')->sole();

    expect($lot->pickup_only)->toBeFalse()
        ->and($lot->transport_from_cents)->toBe(0)
        ->and($lot->draft_meta['start_price_cents'])->toBe(0);
});

it('geeft een nette fout voor een csv die als xlsx is opgeslagen en leest op inhoud', function () {
    // Geen zip, dus geen xlsx: de csv-lezer pakt hem op inhoud alsnog op.
    $this->actingAs($this->seller)
        ->post(route('dashboard.import.store'), ['file' => UploadedFile::fake()->createWithContent('kavels.xlsx', "Titel;Prijs\r\nIets;10\r\n")])
        ->assertRedirect(route('dashboard.import.show', LotImport::query()->sole()));

    expect(LotImport::query()->sole()->headers)->toBe(['Titel', 'Prijs']);

    // Een echt kapot zipbestand geeft een nette fout, geen 500.
    $broken = "PK\x03\x04".str_repeat("\0", 64);
    $this->actingAs($this->seller)
        ->from(route('dashboard.import'))
        ->post(route('dashboard.import.store'), ['file' => UploadedFile::fake()->createWithContent('kapot.xlsx', $broken)])
        ->assertRedirect(route('dashboard.import'))
        ->assertSessionHasErrors('file');

    expect(LotImport::query()->count())->toBe(1);
});

it('importeert niet twee keer bij een dubbele klik', function () {
    $csv = "Titel;Categorie\r\nLinde H25;Heftrucks\r\n";
    $this->actingAs($this->seller)->post(route('dashboard.import.store'), ['file' => UploadedFile::fake()->createWithContent('kavels.csv', $csv)]);
    $import = LotImport::query()->sole();

    $this->actingAs($this->seller)->post(route('dashboard.import.run', $import), ['mapping' => ['title' => 0, 'category' => 1]])->assertSessionHasNoErrors();
    $this->actingAs($this->seller)->post(route('dashboard.import.run', $import), ['mapping' => ['title' => 0, 'category' => 1]])->assertRedirect();

    expect(Lot::query()->where('title', 'Linde H25')->count())->toBe(1)
        ->and($import->fresh()->rows_created)->toBe(1);
});
