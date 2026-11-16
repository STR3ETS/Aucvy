<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\LotStatus;
use App\Enums\MediaType;
use App\Models\Lot;
use App\Models\LotMedia;
use Illuminate\Database\Seeder;

/**
 * Foto's bij de demokavels. Lokaal en op een demo-omgeving, nooit op productie.
 *
 * Elke demokavel krijgt twee foto's van wat er in de titel staat (een
 * heftruck, een trilplaat, bureaustoelen), en pas als de titel niets
 * herkenbaars heeft een foto bij zijn (sub)categorie. De keuze is
 * deterministisch op kavel-id, zodat een pagina er bij elke run hetzelfde
 * uitziet. De foto's komen van Pexels (vrij voor commercieel gebruik, geen
 * naamsvermelding nodig), per URL: geen bestanden in de repo.
 *
 * Opnieuw draaien vervangt alleen eerdere demofoto's (Unsplash of Pexels per
 * URL). Foto's die een verkoper zelf uploadde blijven altijd staan.
 */
final class DemoPhotoSeeder extends Seeder
{
    /**
     * Pexels-foto-id's per soort product, op volgorde van specifiek naar
     * algemeen: de eerste regel waarvan het patroon in de titel past, wint.
     *
     * @var list<array{0: string, 1: list<int>}>
     */
    private const BY_TITLE = [
        ['/heftruck|vorkheftruck|loadtest/', [29491402, 6026765, 14688876]],
        ['/trilplaat/', [11564532, 5669019]],
        ['/steiger/', [10134469, 2079615, 154141]],
        ['/aanhanger|oplegger/', [10304035, 943930]],
        ['/afkortzaag|zaag/', [8817827, 8820186, 9049467]],
        ['/compressor/', [9754814]],
        ['/minigraaf|graafmachine/', [14846286, 5668922]],
        ['/bouwcontainer|container/', [13415135, 3704162]],
        ['/palletstelling|stelling 40/', [4487383, 4170172]],
        ['/boot|sloep|jacht/', [10955648, 296236]],
        ['/camera|sony fx3/', [2873486, 10395639]],
        ['/playstation/', [5961216]],
        ['/laptop/', [265144, 7437087]],
        ['/monitor|presentatiescherm/', [8353774, 326512]],
        ['/vergaderinventaris|vergadertafel/', [4985341, 7648040, 3767172]],
        ['/barkruk|statafel|roltafel/', [5530260, 5858030, 12756679]],
        ['/horecastoel|restaurantstoel/', [11572926, 9773757, 10916355]],
        ['/bureaustoel|kantoorstoel|vergaderstoel|herman miller/', [9300769, 295480, 273671]],
        ['/zit-sta|flexplek/', [6326260, 39178037, 273671]],
        ['/werkkleding|kleding/', [6045285]],
        ['/toonbank|winkelinrichting/', [30948318, 14201321]],
        ['/receptiebalie/', [14036251]],
        ['/espresso|marzocco|barista/', [12155110, 6612589, 4349954]],
        ['/koffiemolen|bonenmolen/', [9146682, 9151941]],
        ['/combisteamer|combi-oven/', [5953578]],
        ['/bakoven/', [10481790]],
        ['/mixer/', [27564591, 10481790]],
        ['/gasfornuis|fornuis/', [13422435, 6375553]],
        ['/vaatwasser/', [3829549]],
        ['/ijsblokjes/', [7254237]],
        ['/koelvitrine/', [2524171, 11449152]],
        ['/koelwerkbank|werkbank rvs|horecakeuken/', [6375553, 27685507]],
        ['/designkeuken|keuken/', [2089698, 18285887]],
        ['/verlichting|hanglamp/', [9628657, 6925111, 7108120]],
        ['/akoestische/', [8425988]],
        ['/archiefkast|lockerkast|kastenwand/', [1370294, 4162587, 17116895]],
        ['/loungebank|lounge-set|bank velours/', [4846097, 5824901]],
        ['/tuinset|teak/', [12715508]],
        ['/vloer|visgraat/', [36178801]],
        ['/kantoorinventaris|bureaus/', [273671, 9300769, 4985341]],
    ];

    /**
     * Uitwijk per categorieslug als de titel niets herkenbaars heeft
     * (subcategorie gaat vóór de hoofdcategorie; '*' is de laatste uitwijk).
     *
     * @var array<string, list<int>>
     */
    private const BY_CATEGORY = [
        'machines-techniek' => [17229385, 9754814],
        'horeca-keuken' => [39556646, 6375553],
        'kantoor-inventaris' => [12703093, 273671],
        'voertuigen-transport' => [4620555, 10304035],
        'bouw-materieel' => [5125783, 10134469],
        'winkel-voorraad' => [28846853, 4487383],
        'boten-recreatie' => [17324998, 10955648],
        'elektronica' => [20501862, 8353774],
        '*' => [4170172, 12703093],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoPhotoSeeder draait niet op productie.');

            return;
        }

        $lots = Lot::query()
            ->with(['category.parent', 'media'])
            ->get()
            // Alleen kavels met uitsluitend demofoto's, of zonder foto's maar wel buiten de concepten:
            // een concept zonder foto's is van iemand die nog aan het uploaden is.
            ->filter(fn (Lot $lot): bool => $lot->media->isEmpty()
                ? $lot->status !== LotStatus::Draft
                : $lot->media->every(fn (LotMedia $media): bool => self::isDemoPhoto($media->path)));

        foreach ($lots as $lot) {
            $ids = $this->photosFor($lot);
            $offset = $lot->id % count($ids);

            // Twee foto's als de lijst er twee heeft, anders één.
            $chosen = array_values(array_unique([$ids[$offset], $ids[($offset + 1) % count($ids)]]));

            $lot->media()->delete();

            foreach ($chosen as $position => $id) {
                LotMedia::create([
                    'lot_id' => $lot->id,
                    'type' => MediaType::Image,
                    'path' => "https://images.pexels.com/photos/{$id}/pexels-photo-{$id}.jpeg?auto=compress&cs=tinysrgb&w=1200&h=900&fit=crop",
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

        $this->command?->info("Demofoto's gezet bij {$lots->count()} kavels.");
    }

    /**
     * Een demofoto staat als URL bij een fotobank; een upload van een verkoper is een pad op de eigen schijf.
     */
    public static function isDemoPhoto(string $path): bool
    {
        return str_starts_with($path, 'https://images.unsplash.com/') || str_starts_with($path, 'https://images.pexels.com/');
    }

    /**
     * @return list<int>
     */
    private function photosFor(Lot $lot): array
    {
        $title = mb_strtolower((string) $lot->title);

        foreach (self::BY_TITLE as [$pattern, $ids]) {
            if (preg_match($pattern, $title) === 1) {
                return $ids;
            }
        }

        $category = $lot->category;

        foreach ([$category?->slug, $category?->parent?->slug] as $slug) {
            if ($slug !== null && isset(self::BY_CATEGORY[$slug])) {
                return self::BY_CATEGORY[$slug];
            }
        }

        return self::BY_CATEGORY['*'];
    }
}
