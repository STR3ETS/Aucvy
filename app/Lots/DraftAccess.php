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

        if ($token !== '' && $lot->draft_token !== null && hash_equals($lot->draft_token, $token)) {
            return $lot;
        }

        throw new NotFoundHttpException;
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
            ->where('draft_token', $token)
            ->where('status', LotStatus::Draft)
            ->whereNull('seller_id')
            ->update([
                'seller_id' => $user->id,
                'company_id' => $user->company_id,
            ]);
    }
}
