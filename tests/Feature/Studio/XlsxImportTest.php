<?php

declare(strict_types=1);

use App\Lots\Import\XlsxReader;
use App\Models\Category;
use App\Models\Lot;
use App\Models\LotImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
| Excel-import (briefing sectie 4): een xlsx is een zip met XML; de lezer
| haalt het eerste werkblad eruit zonder bibliotheek en de rest van de
| import is dezelfde als bij csv.
*/

beforeEach(function () {
    Storage::fake('local');
    $this->seller = User::factory()->verified()->create();
    Category::factory()->create(['name' => 'Heftrucks', 'slug' => 'heftrucks']);
});

/**
 * Een minimaal xlsx-bestand: gedeelde tekst voor de kopregel en een
 * inline string, getallen, een formulecel met waarde en een boolean.
 */
function xlsxFixture(): string
{
    $path = tempnam(sys_get_temp_dir(), 'aucvy').'.xlsx';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Kavels" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
    $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="7" uniqueCount="7"><si><t>Titel</t></si><si><t>Categorie</t></si><si><t>Conditie</t></si><si><t>Startprijs</t></si><si><t>Alleen ophalen</t></si><si><r><t>Linde </t></r><r><t>H25</t></r></si><si><t>Heftrucks</t></si></sst>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
        .'<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c><c r="D1" t="s"><v>3</v></c><c r="E1" t="s"><v>4</v></c></row>'
        .'<row r="2"><c r="A2" t="s"><v>5</v></c><c r="B2" t="s"><v>6</v></c><c r="C2" t="inlineStr"><is><t>goed</t></is></c><c r="D2"><f>1250+0.5</f><v>1250.5</v></c><c r="E2" t="b"><v>1</v></c></row>'
        .'<row r="3"/>'
        .'<row r="4"><c r="A4" t="inlineStr"><is><t>Zonder categorie</t></is></c><c r="D4"><v>99</v></c></row>'
        .'</sheetData></worksheet>');
    $zip->close();

    return $path;
}

it('leest het eerste werkblad met gedeelde en inline tekst, getallen en booleans', function () {
    $result = app(XlsxReader::class)->read(xlsxFixture());

    expect($result['headers'])->toBe(['Titel', 'Categorie', 'Conditie', 'Startprijs', 'Alleen ophalen'])
        ->and($result['rows'])->toHaveCount(2)
        ->and($result['rows'][0])->toBe(['Linde H25', 'Heftrucks', 'goed', '1250.5', 'ja'])
        ->and($result['rows'][1])->toBe(['Zonder categorie', '', '', '99', '']);
});

it('importeert een xlsx net als een csv', function () {
    $file = new UploadedFile(xlsxFixture(), 'kavels.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $this->actingAs($this->seller)->post(route('dashboard.import.store'), ['file' => $file])->assertRedirect();

    $import = LotImport::query()->sole();
    expect($import->headers)->toBe(['Titel', 'Categorie', 'Conditie', 'Startprijs', 'Alleen ophalen'])->and($import->rows_total)->toBe(2);

    $this->actingAs($this->seller)->get(route('dashboard.import.show', $import))->assertOk()->assertSee('Linde H25')->assertSee('€ 1.250,50');

    $this->actingAs($this->seller)
        ->post(route('dashboard.import.run', $import), ['mapping' => ['title' => 0, 'category' => 1, 'condition' => 2, 'start_price' => 3, 'pickup_only' => 4]])
        ->assertRedirect();

    $lot = Lot::query()->where('title', 'Linde H25')->sole();
    expect($lot->draft_meta['start_price_cents'])->toBe(125_050)
        ->and($lot->pickup_only)->toBeTrue()
        ->and($lot->condition->value)->toBe('good')
        ->and($import->fresh()->rows_created)->toBe(2);
});
