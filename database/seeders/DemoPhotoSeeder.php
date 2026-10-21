<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MediaType;
use App\Models\Lot;
use App\Models\LotMedia;
use Illuminate\Database\Seeder;

/**
 * Foto's bij de demokavels. Lokaal en op een demo-omgeving, nooit op productie.
 *
 * Elke kavel zonder media krijgt twee foto's die bij zijn (sub)categorie
 * passen, deterministisch gekozen op kavel-id zodat een pagina er bij elke
 * run hetzelfde uitziet. De foto's komen van Unsplash, per URL: geen
 * bestanden in de repo. `path` is in alle views de bron van de <img>, dus
 * een absolute URL werkt net als een opgeslagen bestand.
 *
 * Idempotent: kavels die al media hebben worden overgeslagen.
 */
final class DemoPhotoSeeder extends Seeder
{
    /**
     * Unsplash-foto-id's per categorieslug (subcategorie gaat vóór de
     * hoofdcategorie; '*' is de uitwijk). Handmatig gekozen op wat erop staat.
     *
     * @var array<string, list<string>>
     */
    private const PHOTOS = [
        'machines-techniek' => ['photo-1581091226825-a6a2a5aee158', 'photo-1486262715619-67b85e0b08d3', 'photo-1504917595217-d4dc5ebe6122', 'photo-1558618666-fcd25c85cd64', 'photo-1513828583688-c52646db42da'],
        'machines-techniek-graafmachines' => ['photo-1504307651254-35680f356dfd', 'photo-1541888946425-d81bb19240f5'],
        'machines-techniek-heftrucks' => ['photo-1586528116311-ad8dd3c8310d', 'photo-1553413077-190dd305871c', 'photo-1558618666-fcd25c85cd64'],
        'machines-techniek-metaalbewerking' => ['photo-1504917595217-d4dc5ebe6122', 'photo-1581091226825-a6a2a5aee158'],
        'machines-techniek-houtbewerking' => ['photo-1503387762-592deb58ef4e', 'photo-1530124566582-a618bc2615dc'],
        'machines-techniek-compressoren-pompen' => ['photo-1486262715619-67b85e0b08d3', 'photo-1513828583688-c52646db42da'],

        'horeca-keuken' => ['photo-1556911220-bff31c812dba', 'photo-1497366216548-37526070297c', 'photo-1555396273-367ea4eb4db5'],
        'horeca-keuken-koeling-vriescellen' => ['photo-1497366216548-37526070297c', 'photo-1513828583688-c52646db42da'],
        'horeca-keuken-complete-keukens' => ['photo-1556911220-bff31c812dba', 'photo-1497366216548-37526070297c'],
        'horeca-keuken-kookapparatuur' => ['photo-1497366216548-37526070297c', 'photo-1556911220-bff31c812dba'],
        'horeca-keuken-horecameubilair' => ['photo-1414235077428-338989a2e8c0', 'photo-1555396273-367ea4eb4db5', 'photo-1567521464027-f127ff144326'],

        'kantoor-inventaris' => ['photo-1524758631624-e2822e304c36', 'photo-1497366811353-6870744d04b2', 'photo-1559136555-9303baea8ebd'],
        'kantoor-inventaris-bureaustoelen' => ['photo-1524758631624-e2822e304c36', 'photo-1559136555-9303baea8ebd'],
        'kantoor-inventaris-bureaus-kasten' => ['photo-1497366811353-6870744d04b2', 'photo-1524758631624-e2822e304c36'],
        'kantoor-inventaris-it-elektronica' => ['photo-1519389950473-47ba0277781c', 'photo-1516321318423-f06f85e504b3', 'photo-1518770660439-4636190af475'],
        'kantoor-inventaris-complete-inventaris' => ['photo-1497366811353-6870744d04b2', 'photo-1559136555-9303baea8ebd', 'photo-1524758631624-e2822e304c36'],

        'voertuigen-transport' => ['photo-1601584115197-04ecc0da31d7', 'photo-1541899481282-d53bffe3c35d', 'photo-1503376780353-7e6692767b70'],
        'voertuigen-transport-bestelwagens' => ['photo-1601584115197-04ecc0da31d7'],
        'voertuigen-transport-personenautos' => ['photo-1541899481282-d53bffe3c35d', 'photo-1503376780353-7e6692767b70'],
        'voertuigen-transport-aanhangers-opleggers' => ['photo-1601584115197-04ecc0da31d7', 'photo-1565793298595-6a879b1d9492'],

        'bouw-materieel' => ['photo-1504307651254-35680f356dfd', 'photo-1541888946425-d81bb19240f5', 'photo-1504148455328-c376907d081c'],
        'bouw-materieel-steigers' => ['photo-1504307651254-35680f356dfd', 'photo-1541888946425-d81bb19240f5'],
        'bouw-materieel-gereedschap' => ['photo-1504148455328-c376907d081c', 'photo-1530124566582-a618bc2615dc', 'photo-1621905251189-08b45d6a269e'],
        'bouw-materieel-grondverzet' => ['photo-1541888946425-d81bb19240f5', 'photo-1504307651254-35680f356dfd'],

        'winkel-voorraad' => ['photo-1586528116311-ad8dd3c8310d', 'photo-1553413077-190dd305871c', 'photo-1441986300917-64674bd600d8'],
        'winkel-voorraad-kleding-mode' => ['photo-1441986300917-64674bd600d8', 'photo-1567401893414-76b7b1e5a7a5'],
        'winkel-voorraad-restpartijen' => ['photo-1586528116311-ad8dd3c8310d', 'photo-1553413077-190dd305871c'],
        'winkel-voorraad-displays-inrichting' => ['photo-1441986300917-64674bd600d8', 'photo-1567521464027-f127ff144326'],

        'boten-recreatie' => ['photo-1567899378494-47b22a2ae96a', 'photo-1540946485063-a40da27545f8', 'photo-1523987355523-c7b5b0dd90a7'],
        'boten-recreatie-zeil-motorjachten' => ['photo-1567899378494-47b22a2ae96a', 'photo-1540946485063-a40da27545f8'],
        'boten-recreatie-sloepen' => ['photo-1540946485063-a40da27545f8', 'photo-1567899378494-47b22a2ae96a'],
        'boten-recreatie-campers-caravans' => ['photo-1523987355523-c7b5b0dd90a7'],

        'elektronica' => ['photo-1518770660439-4636190af475', 'photo-1517336714731-489689fd1ca8', 'photo-1495707902641-75cac588d2e9'],
        'elektronica-cameras-optiek' => ['photo-1495707902641-75cac588d2e9', 'photo-1498049794561-7780e7231661'],
        'elektronica-computers' => ['photo-1517336714731-489689fd1ca8', 'photo-1531297484001-80022131f5a1', 'photo-1516321318423-f06f85e504b3', 'photo-1518770660439-4636190af475'],
        'elektronica-audio-video' => ['photo-1498049794561-7780e7231661', 'photo-1517336714731-489689fd1ca8'],

        '*' => ['photo-1524758631624-e2822e304c36', 'photo-1586528116311-ad8dd3c8310d', 'photo-1581091226825-a6a2a5aee158'],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoPhotoSeeder draait niet op productie.');

            return;
        }

        $lots = Lot::query()
            ->doesntHave('media')
            ->with('category.parent')
            ->get();

        foreach ($lots as $lot) {
            $ids = $this->photosFor($lot);
            $offset = $lot->id % count($ids);

            // Twee foto's als de lijst er twee heeft, anders één.
            $chosen = array_unique([$ids[$offset], $ids[($offset + 1) % count($ids)]]);

            foreach (array_values($chosen) as $position => $id) {
                LotMedia::create([
                    'lot_id' => $lot->id,
                    'type' => MediaType::Image,
                    'path' => "https://images.unsplash.com/{$id}?q=80&w=1200&h=900&auto=format&fit=crop",
                    'variants' => [],
                    'width' => 1200,
                    'height' => 900,
                    'position' => $position,
                    'alt' => $lot->title,
                    'checksum' => hash('sha256', "demo:{$lot->id}:{$id}"),
                    'size_bytes' => null,
                ]);
            }
        }

        $this->command?->info("Demofoto's toegevoegd aan {$lots->count()} kavels.");
    }

    /**
     * @return list<string>
     */
    private function photosFor(Lot $lot): array
    {
        $category = $lot->category;

        foreach ([$category?->slug, $category?->parent?->slug] as $slug) {
            if ($slug !== null && isset(self::PHOTOS[$slug])) {
                return self::PHOTOS[$slug];
            }
        }

        return self::PHOTOS['*'];
    }
}
