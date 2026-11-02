<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Voorwaarden, privacy en cookies (besluit Raph, 2 november 2026). De
 * voorwaarden schrijft Raph, de privacytekst komt via Don. Tot die er
 * zijn, staat er een nette tijdelijke pagina die niet geïndexeerd wordt;
 * de cookieverklaring is echt, want die volgt uit de code.
 */
final class LegalController extends Controller
{
    public function terms(): View
    {
        return view()->exists('legal.terms')
            ? view('legal.terms')
            : view('legal.placeholder', [
                'title' => 'Algemene voorwaarden',
                'lead' => 'Hier komen de algemene voorwaarden van Aucvy: wat je mag verwachten als koper en als verkoper, en wat wij van jou verwachten.',
            ]);
    }

    public function privacy(): View
    {
        return view()->exists('legal.privacy')
            ? view('legal.privacy')
            : view('legal.placeholder', [
                'title' => 'Privacyverklaring',
                'lead' => 'Hier komt de privacyverklaring: welke gegevens we verwerken, waarom, hoe lang, en welke rechten je hebt. Je gegevens inzien kan nu al via je accountinstellingen.',
            ]);
    }

    public function cookies(): View
    {
        return view('legal.cookies');
    }
}
