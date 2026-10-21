<?php

declare(strict_types=1);

use App\Enums\LotStatus;
use App\Models\Category;
use App\Models\Lot;
use App\Models\LotImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
| CSV-import (briefing sectie 4): uploaden, kolommen koppelen met een
| voorzet, voorbeeld met fouten, importeren als concepten en een rapport.
*/

beforeEach(function () {
    Storage::fake('local');
    $this->seller = User::factory()->verified()->create();
    Category::factory()->create(['name' => 'Heftrucks', 'slug' => 'heftrucks']);
});

function csvUpload(string $contents, string $name = 'kavels.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $contents);
}

it('herkent de kolommen op de koptekst en toont een voorbeeld met fouten', function () {
    $csv = "Titel;Omschrijving;Categorie;Conditie;Verkoopvorm;Startprijs;Bouwjaar\r\n"
        ."Linde H25;Nette heftruck;Heftrucks;goed;veiling;1.250,50;2019\r\n"
        .";Zonder titel;Heftrucks;goed;veiling;100;2019\r\n"
        ."Onbekende categorie;Tekst;Kantoor;nieuw;koop direct;99,95;abcd\r\n";

    $this->actingAs($this->seller)
        ->post(route('dashboard.import.store'), ['file' => csvUpload($csv)])
        ->assertRedirect();

    $import = LotImport::query()->sole();

    expect($import->headers)->toBe(['Titel', 'Omschrijving', 'Categorie', 'Conditie', 'Verkoopvorm', 'Startprijs', 'Bouwjaar'])
        ->and($import->rows_total)->toBe(3);

    $this->actingAs($this->seller)->get(route('dashboard.import.show', $import))
        ->assertOk()
        ->assertSee('Linde H25')
        ->assertSee('€ 1.250,50')
        ->assertSee('Geen titel.')
        ->assertSee('Onbekende categorie: Kantoor.')
        ->assertSee('Bouwjaar klopt niet: abcd.')
        ->assertViewHas('mapping', fn (array $mapping): bool => $mapping['title'] === 0 && $mapping['start_price'] === 5 && $mapping['year'] === 6);
});

it('importeert de geldige rijen als concepten en rapporteert de rest', function () {
    $csv = "title,description,category,condition,sale_type,start_price,quantity\r\n"
        ."Linde H25,Nette heftruck,heftrucks,used,auction,1250.50,1\r\n"
        ."Jungheinrich EFG,Elektrisch,Heftrucks,like_new,buy_now,,2\r\n"
        .",Geen titel,Heftrucks,good,auction,10,1\r\n";

    $this->actingAs($this->seller)->post(route('dashboard.import.store'), ['file' => csvUpload($csv)]);
    $import = LotImport::query()->sole();

    $this->actingAs($this->seller)
        ->post(route('dashboard.import.run', $import), ['mapping' => [
            'title' => 0, 'description' => 1, 'category' => 2, 'condition' => 3, 'sale_type' => 4, 'start_price' => 5, 'quantity' => 6,
        ]])
        ->assertRedirect(route('dashboard.import.show', $import));

    $import->refresh();

    expect($import->isDone())->toBeTrue()
        ->and($import->rows_created)->toBe(2)
        ->and($import->errors)->toHaveCount(1)
        ->and($import->errors[0]['line'])->toBe(4);

    $lot = Lot::query()->where('title', 'Linde H25')->sole();

    expect($lot->status)->toBe(LotStatus::Draft)
        ->and($lot->seller_id)->toBe($this->seller->id)
        ->and($lot->category->slug)->toBe('heftrucks')
        ->and($lot->condition->value)->toBe('used')
        ->and($lot->draft_meta['sale_type'])->toBe('auction')
        ->and($lot->draft_meta['start_price_cents'])->toBe(125_050)
        ->and($lot->draft_token)->not->toBeNull()
        ->and($lot->field_sources['title']['source'])->toBe('seller');

    $this->actingAs($this->seller)->get(route('dashboard.import.show', $import))
        ->assertOk()
        ->assertSee('2 van 3 rijen staan als concept klaar')
        ->assertSee('Rij 4');

    $this->actingAs($this->seller)->get(route('dashboard.lots'))->assertOk()->assertSee('Jungheinrich EFG');
});

it('weigert een bestand zonder rijen en houdt imports van anderen buiten bereik', function () {
    $this->actingAs($this->seller)
        ->from(route('dashboard.import'))
        ->post(route('dashboard.import.store'), ['file' => csvUpload("Titel;Prijs\r\n")])
        ->assertSessionHasErrors('file');

    $this->actingAs($this->seller)->post(route('dashboard.import.store'), ['file' => csvUpload("Titel\r\nIets\r\n")]);
    $import = LotImport::query()->sole();

    $this->actingAs(User::factory()->create())->get(route('dashboard.import.show', $import))->assertNotFound();
});
