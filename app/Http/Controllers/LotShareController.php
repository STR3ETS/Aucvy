<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Distribution\Channel;
use App\Lots\Dossier\Dossier;
use App\Lots\Interest\Interest;
use App\Models\Lot;
use App\Models\LotActivity;
use App\Models\LotShare;
use App\Models\LotSignal;
use App\Notifications\ShareReaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Een koper deelt een kavel (briefing nieuwe functies): een eigen link met
 * token en UTM, een optionele notitie die de ontvanger boven de kavel ziet,
 * en één reactie terug. De ontvanger ziet nooit het bod of de biednaam van
 * de deler; de deler ziet of de link is geopend.
 */
final class LotShareController extends Controller
{
    public function store(Request $request, Lot $lot): JsonResponse
    {
        if (! $lot->status->isPublic()) {
            throw new NotFoundHttpException;
        }

        $validated = $request->validate([
            'channel' => ['required', Rule::in(array_map(fn (Channel $channel) => $channel->value, Channel::BUYER))],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $share = LotShare::create([
            'lot_id' => $lot->id,
            'user_id' => $request->user()?->id,
            'token' => LotShare::newToken(),
            'channel' => $validated['channel'],
            'note' => isset($validated['note']) && trim($validated['note']) !== '' ? trim($validated['note']) : null,
            'created_at' => now(),
        ]);

        Interest::record($lot, LotSignal::SHARED, Interest::visitorHash($request), $request->user());

        if ($request->user() !== null) {
            Dossier::record(
                lot: $lot->id,
                source: LotActivity::SOURCE_DISTRIBUTION,
                type: 'shared',
                visibility: LotActivity::USER,
                payload: ['channel' => $share->channel()->label()],
                actor: $request->user(),
                userId: $request->user()->id,
                ref: "share:{$share->id}",
            );
        }

        return response()->json([
            'url' => $share->url(),
            'text' => $lot->title.($share->note ? ' · '.$share->note : '').' · '.$share->url(),
        ], 201);
    }

    /**
     * De ontvanger opent de link: de kavelpagina met de notitie erboven.
     * De eerste keer telt als geopend; de deler zelf telt niet mee.
     */
    public function show(Request $request, Lot $lot, string $token): View
    {
        $share = LotShare::query()->where('token', $token)->where('lot_id', $lot->id)->first();

        if ($share === null) {
            throw new NotFoundHttpException;
        }

        $isSharer = $request->user() !== null && $share->user_id === $request->user()->id;

        if (! $isSharer) {
            $share->forceFill(['open_count' => $share->open_count + 1, 'opened_at' => $share->opened_at ?? now()])->save();

            if ($share->user_id !== null && $share->open_count === 1) {
                Dossier::record(
                    lot: $lot->id,
                    source: LotActivity::SOURCE_DISTRIBUTION,
                    type: 'opened',
                    visibility: LotActivity::USER,
                    payload: ['channel' => $share->channel()->label()],
                    userId: $share->user_id,
                    ref: "share:{$share->id}:opened",
                    actorType: 'visitor',
                );
            }
        }

        $request->attributes->set('share', $share);

        return app(LotController::class)->show($request, $lot);
    }

    public function react(Request $request, Lot $lot, string $token): RedirectResponse
    {
        $share = LotShare::query()->where('token', $token)->where('lot_id', $lot->id)->first();

        if ($share === null) {
            throw new NotFoundHttpException;
        }

        $validated = $request->validate([
            'reaction' => ['required', Rule::in(LotShare::REACTIONS)],
            'text' => ['required_if:reaction,text', 'nullable', 'string', 'max:300'],
        ]);

        if ($share->reaction === null) {
            $share->forceFill([
                'reaction' => $validated['reaction'],
                'reaction_text' => $validated['reaction'] === LotShare::TEXT ? trim((string) $validated['text']) : null,
                'reacted_at' => now(),
            ])->save();

            $share->user?->notify(new ShareReaction($share));
        }

        return redirect()->to(route('lots.shared', [$lot, $token]))->with('status', 'Je reactie is doorgegeven aan degene die de kavel deelde.');
    }
}
