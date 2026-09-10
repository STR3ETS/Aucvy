<?php

declare(strict_types=1);

namespace App\Support\Money;

use App\Enums\CompanyPlan;
use App\Enums\VatRegime;
use App\Models\Company;
use App\Models\Lot;
use InvalidArgumentException;

/**
 * Alle bedragen op het platform komen hiervandaan.
 *
 * Twee regels waar niet van afgeweken wordt:
 *
 *  1. Alles is een int in centen. Nergens een float, ook niet tussendoor. De
 *     percentageberekening hieronder rekent daarom in basispunten.
 *  2. De LLM raakt dit nooit aan. Vraagt een gebruiker Ivy "wat kost dit
 *     totaal?", dan roept de agent deze klasse aan en verwoordt de uitkomst.
 *     Hij rekent hem niet zelf uit (hfst. 8).
 *
 * ---------------------------------------------------------------------------
 * TWEE AANNAMES DIE DE ACCOUNTANT MOET BEVESTIGEN (hfst. 18, vóór sprint 6)
 * ---------------------------------------------------------------------------
 *
 *  A. De hamerslag is EXCLUSIEF btw bij het regime `vat_21`. De btw komt er
 *     bovenop. Dat volgt de opbouw uit het ontwerp ("bod + transport € 340 +
 *     btw = totaal") en is gebruikelijk bij zakelijke veilingen. Zou het
 *     inclusief moeten zijn - bijvoorbeeld omdat particuliere kopers een
 *     all-in prijs moeten zien - dan verandert `computeVat()` en niets anders.
 *
 *  B. Transport volgt het btw-regime van de kavel. Bij de margeregeling zit er
 *     dus geen aparte btw op het transport, omdat bijkomende kosten de
 *     hoofdprestatie volgen. Wordt het transport door een derde partij
 *     geleverd en doorbelast, dan is het een losse dienst met 21% en klopt dit
 *     niet meer.
 *
 * Beide aannames staan vastgepind in tests/Feature/Money/. Wijzigt het
 * antwoord, dan faalt daar een test met een uitleg erbij - precies de
 * bedoeling.
 */
final class Money
{
    /**
     * De kostenopbouw voor een koper, vóór hij bevestigt.
     *
     * @param  int  $hammerCents  het winnende bod, of het huidige bod bij een indicatie
     * @param  int  $transportCents  0 bij zelf ophalen
     */
    public static function forOrder(Lot $lot, int $hammerCents, int $transportCents = 0): OrderCosts
    {
        return self::compute(
            vatRegime: $lot->vat_regime,
            hammerCents: $hammerCents,
            transportCents: $transportCents,
        );
    }

    /**
     * Dezelfde som, maar zonder een model nodig te hebben.
     *
     * Handig voor de biedmotor en voor tests: die willen een bedrag narekenen
     * zonder eerst een kavel op te tuigen.
     */
    public static function compute(
        VatRegime $vatRegime,
        int $hammerCents,
        int $transportCents = 0,
        ?float $buyerPremiumPercent = null,
    ): OrderCosts {
        self::guardNotNegative($hammerCents, 'hammerCents');
        self::guardNotNegative($transportCents, 'transportCents');

        $premiumPercent = $buyerPremiumPercent ?? self::buyerPremiumPercent();
        $buyerPremiumCents = self::percentageOf($hammerCents, $premiumPercent);

        $vatCents = self::computeVat($vatRegime, $hammerCents, $buyerPremiumCents, $transportCents);

        return new OrderCosts(
            hammerCents: $hammerCents,
            buyerPremiumCents: $buyerPremiumCents,
            transportCents: $transportCents,
            vatCents: $vatCents,
            totalCents: $hammerCents + $buyerPremiumCents + $transportCents + $vatCents,
            vatRegime: $vatRegime,
        );
    }

    /**
     * Wat de verkoper overhoudt na de succesfee.
     *
     * Het percentage komt van het bedrijf, niet uit config: een afwijkende
     * afspraak met een grote verkoper mag geen codewijziging vragen. Staat er
     * geen bedrijf achter - een particuliere verkoper - dan geldt het
     * starttarief.
     */
    public static function payoutFor(int $hammerCents, ?Company $company = null): SellerProceeds
    {
        self::guardNotNegative($hammerCents, 'hammerCents');

        $feePercentage = $company !== null
            ? (float) $company->fee_percentage
            : CompanyPlan::Start->defaultFeePercentage();

        $feeCents = self::percentageOf($hammerCents, $feePercentage);

        return new SellerProceeds(
            hammerCents: $hammerCents,
            feePercentage: $feePercentage,
            feeCents: $feeCents,
            payoutCents: $hammerCents - $feeCents,
        );
    }

    /**
     * Een percentage van een bedrag, in hele centen.
     *
     * Rekent in basispunten (0,01%) zodat de vermenigvuldiging volledig in
     * gehele getallen gebeurt: 7,5% wordt 750. Afronding is naar de
     * dichtstbijzijnde cent, halve centen naar boven.
     *
     * Waarom niet gewoon `(int) round($cents * $pct / 100)`: dat werkt, maar
     * introduceert een float in het rekenpad. Eén keer een float in een
     * geldberekening en je discussieert later over een cent verschil op een
     * factuur.
     */
    public static function percentageOf(int $cents, float $percentage): int
    {
        if ($percentage === 0.0 || $cents === 0) {
            return 0;
        }

        $basisPoints = (int) round($percentage * 100);

        return intdiv($cents * $basisPoints + 5_000, 10_000);
    }

    public static function buyerPremiumPercent(): float
    {
        return (float) config('aucvy.money.buyer_premium_percent');
    }

    public static function vatRatePercent(): float
    {
        return (float) config('aucvy.money.vat_rate_percent');
    }

    /**
     * Nederlandse notatie, opgebouwd uit gehele getallen.
     *
     * Bewust niet via `$cents / 100`: dat is de enige plek waar een float
     * alsnog binnen zou sluipen, en juist bij weergave valt een afrondingsfout
     * het hardst op.
     */
    public static function format(int $cents, bool $withSymbol = true): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        $euros = intdiv($absolute, 100);
        $remainder = $absolute % 100;

        $formatted = number_format($euros, 0, ',', '.')
            .','
            .str_pad((string) $remainder, 2, '0', STR_PAD_LEFT);

        return $sign.($withSymbol ? '€ ' : '').$formatted;
    }

    /**
     * Btw over de koperzijde.
     *
     * Bij de margeregeling is er voor de koper geen verrekenbare btw - dat is
     * geen afronding naar nul maar een andere regeling, en het scheelt een
     * zakelijke koper 21%. Het moet daarom ook letterlijk op de kavelpagina
     * staan, niet weggelaten worden.
     */
    private static function computeVat(
        VatRegime $vatRegime,
        int $hammerCents,
        int $buyerPremiumCents,
        int $transportCents,
    ): int {
        if (! $vatRegime->isDeductible()) {
            return 0;
        }

        return self::percentageOf(
            $hammerCents + $buyerPremiumCents + $transportCents,
            self::vatRatePercent(),
        );
    }

    private static function guardNotNegative(int $cents, string $name): void
    {
        if ($cents < 0) {
            throw new InvalidArgumentException("{$name} mag niet negatief zijn, kreeg {$cents}.");
        }
    }
}
