<?php

declare(strict_types=1);

namespace App\Auctions\Support;

use App\Models\Auction;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * De afslagprijs: een pure functie van de tijd.
 *
 *     max(bodem, start − stap × floor((nu − starts_at) / interval))
 *
 * ---------------------------------------------------------------------------
 * Waarom hier geen scheduler staat
 * ---------------------------------------------------------------------------
 *
 * De voor de hand liggende oplossing is een taak die elke twintig seconden de
 * prijs verlaagt en een event uitzendt. Dat is precies wat de briefing afwijst,
 * en met reden:
 *
 *  - het zijn duizenden schrijfacties per dag voor informatie die je uit de
 *    klok kunt aflezen;
 *  - een worker die een halve minuut achterloopt betekent dat iemand afmijnt
 *    op een prijs die niet meer geldt;
 *  - en een herstart midden in een afslag laat de prijs stilstaan.
 *
 * In plaats daarvan telt de client zelf af met dezelfde formule, en rekent de
 * server bij acceptatie opnieuw. Dát bedrag geldt. Wijkt wat de client zag te
 * ver af, dan krijgt hij de actuele prijs terug in plaats van een afrekening
 * waar hij niet om vroeg (hfst. 6).
 */
final class DutchPriceCalculator
{
    /**
     * De geldende prijs op een moment.
     */
    public function priceAt(Auction $auction, ?CarbonImmutable $at = null): int
    {
        $this->guardConfigured($auction);

        $at ??= CarbonImmutable::now();

        $elapsedSeconds = $at->getTimestamp() - $auction->starts_at->getTimestamp();

        // Vóór de start geldt de startprijs.
        if ($elapsedSeconds <= 0) {
            return $auction->dutch_start_cents;
        }

        $stepsTaken = intdiv($elapsedSeconds, $auction->dutch_interval_s);
        $price = $auction->dutch_start_cents - ($stepsTaken * $auction->dutch_step_cents);

        // De bodem is de bodem: daaronder zakt hij niet, hoe lang je ook wacht.
        return max($auction->dutch_floor_cents, $price);
    }

    /**
     * Wanneer de prijs de volgende keer daalt.
     *
     * Null zodra de bodem bereikt is - dan gebeurt er niets meer, en dat moet
     * de UI ook zeggen in plaats van een teller te blijven tonen die nergens
     * heen loopt.
     */
    public function nextDropAt(Auction $auction, ?CarbonImmutable $at = null): ?CarbonImmutable
    {
        $this->guardConfigured($auction);

        $at ??= CarbonImmutable::now();

        if ($this->hasReachedFloor($auction, $at)) {
            return null;
        }

        $elapsedSeconds = max(0, $at->getTimestamp() - $auction->starts_at->getTimestamp());
        $stepsTaken = intdiv($elapsedSeconds, $auction->dutch_interval_s);

        return $auction->starts_at->addSeconds(($stepsTaken + 1) * $auction->dutch_interval_s);
    }

    public function hasReachedFloor(Auction $auction, ?CarbonImmutable $at = null): bool
    {
        return $this->priceAt($auction, $at) <= $auction->dutch_floor_cents;
    }

    /**
     * Of de prijs die de client zág nog bruikbaar is.
     *
     * Eén stap speling: tussen het renderen van de knop en het aankomen van het
     * verzoek kan de prijs één keer gezakt zijn, en daar mag een koper niet op
     * stuklopen. Twee stappen verschil betekent dat het scherm te oud was
     * (hfst. 7).
     */
    public function isWithinTolerance(Auction $auction, int $seenPriceCents, ?CarbonImmutable $at = null): bool
    {
        $actual = $this->priceAt($auction, $at);

        // Alleen omlaag: wie één stap te hoog zag, betaalt de lagere echte prijs;
        // wie te laag zag, koopt niet voor meer dan hij zag.
        return $actual <= $seenPriceCents && $seenPriceCents - $actual <= $auction->dutch_step_cents;
    }

    /**
     * Alles wat de kavelpagina nodig heeft om zelf af te tellen.
     *
     * @return array<string, mixed>
     */
    public function stateFor(Auction $auction, ?CarbonImmutable $at = null): array
    {
        $at ??= CarbonImmutable::now();
        $nextDrop = $this->nextDropAt($auction, $at);

        return [
            'price_cents' => $this->priceAt($auction, $at),
            // De client rekent zelf verder met dezelfde formule en heeft
            // daarvoor de startprijs nodig, niet de huidige.
            'start_cents' => $auction->dutch_start_cents,
            'floor_cents' => $auction->dutch_floor_cents,
            'step_cents' => $auction->dutch_step_cents,
            'interval_seconds' => $auction->dutch_interval_s,
            'starts_at' => $auction->starts_at->toIso8601String(),
            'next_drop_at' => $nextDrop?->toIso8601String(),
            'floor_reached' => $nextDrop === null,
            // De client rekent met een offset tussen server- en clienttijd; een
            // telefoon die tien seconden voorloopt is normaal (hfst. 11).
            'server_time' => $at->toIso8601String(),
        ];
    }

    private function guardConfigured(Auction $auction): void
    {
        $missing = $auction->dutch_start_cents === null
            || $auction->dutch_floor_cents === null
            || $auction->dutch_step_cents === null
            || $auction->dutch_interval_s === null;

        if ($missing) {
            throw new RuntimeException("Veiling {$auction->id} heeft geen afslaginstellingen.");
        }

        if ($auction->dutch_interval_s < 1) {
            throw new RuntimeException("Veiling {$auction->id} heeft een interval van nul seconden.");
        }

        if ($auction->dutch_floor_cents > $auction->dutch_start_cents) {
            throw new RuntimeException("Veiling {$auction->id} heeft een bodem boven de startprijs.");
        }
    }
}
