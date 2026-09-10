<?php

declare(strict_types=1);

namespace App\Auctions\Support;

use App\Models\Auction;
use InvalidArgumentException;

/**
 * De biedstaffel: hoeveel een bod minimaal boven het huidige bod moet liggen.
 *
 *   tot € 500      stap € 10
 *   tot € 5.000    stap € 50
 *   daarboven      stap € 250
 *
 * De staffel komt van de veiling zelf, niet uit config. Dat is de hele reden
 * dat hij als jsonb op `auctions` staat: verandert de config, dan verandert de
 * biedstap van een lopende veiling niet met terugwerkende kracht mee. Iemand
 * die om 14:00 wist dat de volgende stap € 50 was, moet om 14:01 niet ineens
 * € 250 hoeven bieden (hfst. 5).
 */
final readonly class IncrementTable
{
    /**
     * @param  array<int, array{up_to_cents: int|null, step_cents: int}>  $tiers
     */
    private function __construct(private array $tiers) {}

    /**
     * @param  array<int, array{up_to_cents: int|null, step_cents: int}>  $tiers
     */
    public static function fromArray(array $tiers): self
    {
        if ($tiers === []) {
            throw new InvalidArgumentException('Een biedstaffel heeft minstens één trede nodig.');
        }

        $previousCeiling = 0;

        foreach ($tiers as $index => $tier) {
            if (($tier['step_cents'] ?? 0) <= 0) {
                throw new InvalidArgumentException("Trede {$index} heeft geen bruikbare stap.");
            }

            $ceiling = $tier['up_to_cents'] ?? null;

            if ($ceiling === null) {
                continue;
            }

            if ($ceiling <= $previousCeiling) {
                throw new InvalidArgumentException('Een biedstaffel moet oplopen.');
            }

            $previousCeiling = $ceiling;
        }

        // De laatste trede vangt alles daarboven af; zonder open bovengrens
        // valt een bod boven de hoogste trede door de mand.
        if (($tiers[array_key_last($tiers)]['up_to_cents'] ?? null) !== null) {
            throw new InvalidArgumentException('De laatste trede moet een open bovengrens hebben.');
        }

        return new self(array_values($tiers));
    }

    public static function forAuction(Auction $auction): self
    {
        return self::fromArray($auction->increment_table);
    }

    /**
     * De staffel waarmee een nieuwe veiling wordt aangemaakt.
     */
    public static function default(): self
    {
        return self::fromArray(config('aucvy.auction.increment_table'));
    }

    /**
     * De stap die hoort bij een bedrag.
     */
    public function stepFor(int $priceCents): int
    {
        foreach ($this->tiers as $tier) {
            if ($tier['up_to_cents'] === null || $priceCents <= $tier['up_to_cents']) {
                return $tier['step_cents'];
            }
        }

        // Onbereikbaar dankzij de validatie in fromArray(), maar een stille
        // nul zou hier betekenen dat elk bod van één cent geldig is.
        throw new InvalidArgumentException('Geen biedstap gevonden voor '.$priceCents.' cent.');
    }

    /**
     * Het laagste bedrag waarmee een bieder mee mag doen.
     *
     * Zonder biedingen is dat de startprijs zelf - je hoeft niet boven de
     * startprijs te beginnen. Daarna telkens één stap boven de stand.
     */
    public function minimumBid(int $currentPriceCents, bool $hasBids, int $startPriceCents): int
    {
        if (! $hasBids) {
            return $startPriceCents;
        }

        return $currentPriceCents + $this->stepFor($currentPriceCents);
    }

    /**
     * @return array<int, array{up_to_cents: int|null, step_cents: int}>
     */
    public function toArray(): array
    {
        return $this->tiers;
    }
}
