<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Money\Money;
use App\Support\PlatformFacts;
use Illuminate\View\View;

/**
 * Voorwaarden, privacy en cookies (besluit Raph, 2 november 2026). De
 * voorwaarden schrijft Raph, de privacytekst komt via Don. Tot die er
 * zijn, staat er een tijdelijke pagina die niet geïndexeerd wordt, met de
 * regels die het platform nu al toepast; de cookieverklaring is echt, want
 * die volgt uit de code.
 */
final class LegalController extends Controller
{
    public function terms(): View
    {
        if (view()->exists('legal.terms')) {
            return view('legal.terms');
        }

        // Dezelfde bronnen als de code die het afdwingt.
        $facts = PlatformFacts::all();
        $pay = (int) $facts['payment_deadline_hours'];
        $report = (int) $facts['dispute_window_hours'];
        $respond = (int) $facts['seller_response_hours'];
        $adminFee = (int) $facts['admin_fee_cents'];
        $deposit = Money::format((int) config('aucvy.deposit.threshold_cents'));
        $freeLimit = Money::format((int) $facts['bid_free_limit_cents']);
        $window = (int) config('aucvy.auction.anti_snipe_window_seconds');
        $extend = (int) config('aucvy.auction.anti_snipe_extend_seconds');

        return view('legal.placeholder', [
            'title' => 'Algemene voorwaarden',
            'first' => 'Algemene',
            'accent' => 'voorwaarden.',
            'intro' => 'Wat je mag verwachten als koper en als verkoper, en wat wij van jou verwachten.',
            'lead' => 'Hieronder staan de regels die het platform nu al toepast. De volledige algemene voorwaarden volgen vóór de lancering.',
            'summary' => [
                'Een bod' => 'Is bindend',
                'Betalen' => "Binnen {$pay} u",
                'Melden na levering' => "Binnen {$report} u",
                'Reactie verkoper' => "Binnen {$respond} u",
                'Opgeld' => '€ 0',
            ],
            'sections' => [
                ['Accounts en verificatie', "Rondkijken en volgen kan zonder verificatie. Om te bieden heb je een account nodig met een bevestigd e-mailadres en telefoonnummer; tot {$freeLimit} is dat genoeg, daarboven vragen we je identiteit."],
                ['Wie mag verkopen', 'Alleen bedrijven met een geverifieerd zakelijk account mogen kavels publiceren. Daarvoor controleren we de KVK-inschrijving en de identiteit. Een particulier account kan bieden en kopen.'],
                ['Bieden', "Een bod is bindend. Een bod in de laatste {$window} seconden verlengt de veiling met {$extend} seconden. Boven {$deposit} vragen we vooraf een borgreservering."],
                ['Betalen', "De koper betaalt binnen {$pay} uur na de gunning, aan het platform."],
                ['Kosten', 'Plaatsen is gratis. Kopers betalen geen opgeld'.($adminFee > 0 ? ', wel '.Money::format($adminFee).' administratiekosten per bestelling' : '').'. Verkopers betalen een succesfee over een geslaagde verkoop; het tarief staat vooraf in de kostenopbouw en op de prijzenpagina.'],
                ['Levering en geschillen', "Na levering heeft de koper {$report} uur om iets te melden. De verkoper reageert binnen {$respond} uur met eigen bewijs, en daarna beoordeelt een medewerker. Zonder melding komt de uitbetaling vrij."],
            ],
        ]);
    }

    public function privacy(): View
    {
        if (view()->exists('legal.privacy')) {
            return view('legal.privacy');
        }

        return view('legal.placeholder', [
            'title' => 'Privacyverklaring',
            'first' => 'Privacy',
            'accent' => 'verklaring.',
            'intro' => 'Welke gegevens we verwerken, waarom, hoe lang, en welke rechten je hebt.',
            'lead' => 'De volledige privacyverklaring volgt vóór de lancering. Je gegevens inzien kan nu al via je accountinstellingen.',
            'summary' => [
                'Identiteitscontrole' => 'Alleen resultaat en datum',
                'Inzien' => 'Via je accountinstellingen',
                'Cookies' => 'Alleen eigen cookies',
            ],
            'sections' => [
                ['Identiteitscontrole', 'We bewaren alleen het resultaat en de datum van de controle, nooit je documentbeeld of selfie.'],
                ['Je gegevens inzien', 'Wat we van je account hebben vastgelegd, zie je in je accountinstellingen. Daar pas je ook je gegevens aan.'],
            ],
        ]);
    }

    public function cookies(): View
    {
        return view('legal.cookies');
    }
}
