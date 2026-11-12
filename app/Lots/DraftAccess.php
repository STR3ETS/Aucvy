<?php

declare(strict_types=1);

namespace App\Lots;

use App\Enums\LotStatus;
use App\Models\Lot;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Wie mag aan een concept zitten: de eigenaar, of de browser die het maakte.
 *
 * De funnel werkt zonder account (briefing hfst. 13.4). Het concept hangt aan
 * een token in een cookie - Laravel versleutelt cookies, dus knoeien met de
 * waarde is zinloos. Na inloggen wordt het concept op naam gezet en werkt
 * beide routes.
 *
 * Bij geen toegang: 404, geen 403. Een 403 bevestigt dat het concept bestaat,
 * en dat gaat niemand anders iets aan.
 */
final class DraftAccess
{
    public const COOKIE = 'aucvy_draft';

    public function resolve(Request $request, Lot $lot): Lot
    {
        if ($lot->status !== LotStatus::Draft) {
            throw new NotFoundHttpException;
        }

        $user = $request->user();

        if ($user !== null && $lot->seller_id === $user->id) {
            return $lot;
        }

        $token = (string) $request->cookie(self::COOKIE, '');

        // De cookie opent een concept zolang het nog van niemand is; is het
        // op naam gezet, dan telt alleen die naam (gedeelde browser).
        if ($token !== '' && self::tokenOpens($lot, $token) && ($user === null || $lot->seller_id === null)) {
            return $lot;
        }

        throw new NotFoundHttpException;
    }

    /**
     * Voor foto's geldt een ruimere poort: ook de eigenaar van een kavel die
     * al live is mag zijn foto's beheren (briefing sectie 4: object
     * bewerken). Voor al het andere blijft het concept de grens.
     */
    /**
     * De cookie opent het concept zelf, en de kavels die uit dat concept
     * gesplitst zijn (SplitDraft): die hebben een eigen token, want een token
     * is uniek, en bewaren de hash van de cookie die ze maakte.
     */
    public static function tokenOpens(Lot $lot, string $token): bool
    {
        if ($lot->draft_token !== null && hash_equals($lot->draft_token, $token)) {
            return true;
        }

        $cookie = $lot->draft_meta['cookie'] ?? null;

        return is_string($cookie) && hash_equals($cookie, self::cookieHash($token));
    }

    public static function cookieHash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function resolveForMedia(Request $request, Lot $lot): Lot
    {
        $user = $request->user();

        if ($lot->status !== LotStatus::Draft && $user !== null && $lot->isManagedBy($user)
            && in_array($lot->status, Management\UpdateLot::EDITABLE, true)) {
            return $lot;
        }

        return $this->resolve($request, $lot);
    }

    /**
     * Het meest recente concept van deze bezoeker, op naam of op cookie.
     */
    public function current(Request $request): ?Lot
    {
        $user = $request->user();

        if ($user !== null) {
            $owned = Lot::query()
                ->where('seller_id', $user->id)
                ->where('status', LotStatus::Draft)
                ->latest('id')
                ->first();

            if ($owned !== null) {
                return $owned;
            }
        }

        $token = (string) $request->cookie(self::COOKIE, '');

        if ($token === '') {
            return null;
        }

        return Lot::query()
            ->where('draft_token', $token)
            ->where('status', LotStatus::Draft)
            ->latest('id')
            ->first();
    }

    /**
     * Zet concepten van deze browser op naam van de ingelogde gebruiker.
     *
     * Wordt bij het inloggen aangeroepen: "pas bij opslaan of publiceren komt
     * de accountvraag" werkt alleen als het werk van daarvoor meegaat.
     */
    public function claimFor(Request $request): void
    {
        $user = $request->user();
        $token = (string) $request->cookie(self::COOKIE, '');

        if ($user === null || $token === '') {
            return;
        }

        Lot::query()
            ->where(fn ($query) => $query->where('draft_token', $token)->orWhere('draft_meta->cookie', self::cookieHash($token)))
            ->where('status', LotStatus::Draft)
            ->whereNull('seller_id')
            ->update([
                'seller_id' => $user->id,
                'company_id' => $user->company_id,
            ]);
    }
}
