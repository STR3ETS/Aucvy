<?php

declare(strict_types=1);

namespace App\Distribution;

use App\Models\Lot;
use App\Models\LotDistribution;
use App\Support\Money\Money;

/**
 * De tekst per kanaal en moment. Eerst een vaste sjabloon met feiten uit
 * de kavel, daarna mag Nova hem beter maken; de verkoper kan hem altijd
 * aanpassen. Het bod, de sluiting en de link blijven plekken ({bod},
 * {sluit}, {link}) die pas vlak voor het plaatsen ingevuld worden.
 */
final class ChannelTexts
{
    public static function template(Lot $lot, Channel $channel, string $moment): string
    {
        $title = trim((string) $lot->title);
        $category = $lot->category?->name;
        $where = $lot->location?->city ?? null;
        $facts = array_values(array_filter([
            $lot->brand ? trim($lot->brand.' '.$lot->model) : null,
            $lot->year ? 'bouwjaar '.$lot->year : null,
            $where ? 'in '.$where : null,
        ]));
        $factLine = $facts === [] ? '' : ' '.ucfirst(implode(', ', $facts)).'.';

        $opening = match ($moment) {
            LotDistribution::DAY_BEFORE => "Sluit morgen: {$title}.{$factLine} Huidig bod {bod}, sluit {sluit}.",
            LotDistribution::LAST_HOUR => "Laatste uur voor {$title}. Huidig bod {bod}, sluit om {sluit_tijd}. Nu of nooit.",
            default => "Nieuw in de veiling: {$title}.{$factLine} ".self::priceLabel($lot, $moment).' {bod}, sluit {sluit}.',
        };

        return match ($channel) {
            Channel::Instagram, Channel::Story => $opening."\n\nBieden kan via de link in bio of op aucvy.nl.\n\n".self::hashtags($category),
            Channel::LinkedIn => $opening."\n\nZakelijk kopen met zekerheid: betaling via Aucvy, uitbetaling na levering.\n{link}",
            Channel::Marktplaats => $opening."\n\nDit is een veilingkavel op Aucvy; het bod loopt daar. Bekijk de kavel en bied: {link}",
            Channel::WhatsApp => "{$title}: ".self::priceLabel($lot, $moment).' {bod}, sluit {sluit}. {link}',
            Channel::Mail => "{$title} op Aucvy\n\n{$opening}\n\nBekijk de kavel: {link}",
            default => $opening."\n\nBekijk en bied: {link}",
        };
    }

    /**
     * Vult de plekken met de stand van nu; de geplaatste tekst verandert
     * daarna niet meer, de link toont altijd het actuele bod.
     */
    public static function render(LotDistribution $distribution): string
    {
        $lot = $distribution->lot;
        $lot->loadMissing(['auction', 'category', 'location']);
        $ends = $lot->auction?->ends_at?->timezone(config('aucvy.display_timezone'));

        $replacements = [
            '{bod}' => $lot->auction ? Money::format($lot->currentPriceCents()) : '',
            '{sluit}' => $ends ? $ends->isoFormat('dddd D MMMM, HH:mm') : '',
            '{sluit_tijd}' => $ends ? $ends->isoFormat('HH:mm') : '',
            '{link}' => ShareKit::link($lot, $distribution->channel(), $distribution->moment),
            '{titel}' => (string) $lot->title,
        ];

        $body = strtr((string) $distribution->body, $replacements);

        return mb_substr(trim(preg_replace('/[ \t]+\n/', "\n", $body) ?? $body), 0, $distribution->channel()->maxLength());
    }

    public static function priceLabel(Lot $lot, string $moment): string
    {
        if ($lot->auction === null) {
            return '';
        }

        if ($lot->auction->isClosed()) {
            return 'Gesloten op';
        }

        return $moment === LotDistribution::START && (int) $lot->auction->bid_count === 0 ? 'Startbod' : 'Huidig bod';
    }

    public static function momentEyebrow(string $moment): string
    {
        return match ($moment) {
            LotDistribution::DAY_BEFORE => 'Sluit morgen',
            LotDistribution::LAST_HOUR => 'Laatste uur',
            LotDistribution::START => 'Nieuw in de veiling',
            default => 'Nu in de veiling',
        };
    }

    private static function hashtags(?string $category): string
    {
        $tags = ['#aucvy', '#veiling', '#zakelijk'];

        if ($category !== null && $category !== '') {
            $tags[] = '#'.preg_replace('/[^a-z0-9]/', '', mb_strtolower($category));
        }

        return implode(' ', $tags);
    }
}
