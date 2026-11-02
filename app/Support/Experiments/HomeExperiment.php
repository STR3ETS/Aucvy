<?php

declare(strict_types=1);

namespace App\Support\Experiments;

use App\Enums\HomeVariant;
use App\Support\Privacy\CookieConsent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Lottery;

/**
 * De A/B-test op de home: wie ziet welk ontwerp.
 *
 * Staat de test uit (de standaardstand sinds variant B is gekozen), dan
 * krijgt iedereen het standaardontwerp uit de config. Een cookie speelt dan
 * geen rol: wie ooit tijdens de test aan A is toegewezen, ziet gewoon B.
 *
 * Staat de test aan, dan is de toewijzing per browser en blijft hij in een
 * cookie staan, zodat een bezoeker niet bij elke paginaweergave een ander
 * ontwerp krijgt. Zonder vaste toewijzing valt er niets te meten: dan
 * vergelijk je bezoekers met zichzelf.
 *
 * Een variant is altijd af te dwingen met ?variant=a of ?variant=b. Tijdens
 * de test overschrijft dat de cookie, tenzij demo=1 meegaat: dan wordt de
 * keuze alleen getoond en niet onthouden. De vergelijkingspagina (/ab-test)
 * laadt beide varianten in een iframe en zou anders om de beurt de cookie
 * overschrijven.
 */
final class HomeExperiment
{
    public const COOKIE = 'aucvy_home_variant';

    public const QUERY = 'variant';

    public const DEMO = 'demo';

    public function resolve(Request $request): HomeVariant
    {
        $forced = HomeVariant::tryFrom((string) $request->query(self::QUERY));

        if (! $this->isEnabled()) {
            return $forced ?? $this->defaultVariant();
        }

        /*
         | Meedoen aan de test is statistiek: alleen met toestemming uit de
         | cookiebanner (besluit Raph, 2 november 2026). Zonder keuze krijgt
         | de bezoeker het standaardontwerp, of de variant uit de url, en
         | geen cookie.
         */
        $consented = app(CookieConsent::class)->allows($request, 'statistics');

        if ($forced !== null) {
            if ($consented && ! $request->boolean(self::DEMO)) {
                $this->remember($forced);
            }

            return $forced;
        }

        if (! $consented) {
            return $this->defaultVariant();
        }

        $remembered = HomeVariant::tryFrom((string) $request->cookie(self::COOKIE));

        if ($remembered !== null) {
            return $remembered;
        }

        $assigned = $this->assign();
        $this->remember($assigned);

        return $assigned;
    }

    public function isEnabled(): bool
    {
        return (bool) config('aucvy.experiments.home.enabled');
    }

    /**
     * Het ontwerp dat iedereen krijgt zolang de test uitstaat.
     */
    public function defaultVariant(): HomeVariant
    {
        return HomeVariant::tryFrom((string) config('aucvy.experiments.home.default'))
            ?? HomeVariant::Hybrid;
    }

    /**
     * Het aandeel bezoekers dat het nieuwe ontwerp krijgt, als fractie.
     */
    public function redesignShare(): float
    {
        return (float) config('aucvy.experiments.home.redesign_share');
    }

    private function assign(): HomeVariant
    {
        return Lottery::odds($this->redesignShare())
            ->winner(fn (): HomeVariant => HomeVariant::Redesign)
            ->loser(fn (): HomeVariant => HomeVariant::Current)
            ->choose();
    }

    private function remember(HomeVariant $variant): void
    {
        $minutes = (int) config('aucvy.experiments.home.cookie_days') * 24 * 60;

        Cookie::queue(self::COOKIE, $variant->value, $minutes);
    }
}
