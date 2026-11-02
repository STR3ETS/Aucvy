<?php

declare(strict_types=1);

namespace App\Compliance\Identity;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Identiteit vaststellen via de bank (besluit Raph, 2 november 2026: iDIN,
 * partij in overleg met Remco, vervolgvraag V7). De gebruiker gaat naar
 * de partij en komt terug op Aucvy met een uitkomst. Opgeslagen wordt
 * alleen die uitkomst, met een referentie; nooit naam, geboortedatum of
 * adres uit de bank.
 */
interface IdentityProvider
{
    public function name(): string;

    /**
     * De URL waar de gebruiker zijn identiteit bevestigt.
     */
    public function start(User $user, string $returnUrl): string;

    /**
     * De uitkomst uit de terugkeer-request. Gooit niets: een ongeldige
     * terugkeer is een mislukte uitkomst.
     */
    public function complete(User $user, Request $request): IdentityResult;
}
