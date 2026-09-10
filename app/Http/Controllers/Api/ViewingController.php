<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\ViewingStatus;
use App\Http\Controllers\Controller;
use App\Models\Lot;
use App\Models\Viewing;
use App\Notifications\ViewingRequested;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

/**
 * Bezichtiging aanvragen (hfst. 7). De koper stelt een moment voor, de
 * verkoper bevestigt per mail - en pas daarná gaat het adres mee. Het adres
 * staat dus nooit in deze respons en nooit op de kavelpagina.
 */
final class ViewingController extends Controller
{
    public function store(Request $request, Lot $lot): JsonResponse
    {
        if (! $lot->status->isPublic() || ! $lot->viewing_available) {
            return response()->json([
                'message' => 'Bij deze kavel is geen bezichtiging mogelijk.',
            ], 422);
        }

        if ($lot->seller_id === $request->user()->id) {
            return response()->json([
                'message' => 'Dit is je eigen kavel.',
            ], 422);
        }

        $validated = $request->validate([
            'slot_at' => ['required', 'date', 'after:now'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        /*
         | Eén lopende aanvraag per koper per kavel. Nogmaals klikken maakt
         | geen tweede aanvraag en stuurt de verkoper geen tweede mail.
         */
        $existing = Viewing::query()
            ->where('lot_id', $lot->id)
            ->where('user_id', $request->user()->id)
            ->whereIn('status', [ViewingStatus::Requested, ViewingStatus::Confirmed])
            ->first();

        if ($existing !== null) {
            return response()->json(['viewing' => $this->payload($existing)]);
        }

        // De bezoeker typt in de weergavetijdzone; opslag blijft UTC.
        $viewing = Viewing::create([
            'lot_id' => $lot->id,
            'user_id' => $request->user()->id,
            'slot_at' => Date::parse($validated['slot_at'], config('aucvy.display_timezone')),
            'status' => ViewingStatus::Requested,
            'note' => $validated['note'] ?? null,
        ]);

        $lot->seller->notify(new ViewingRequested($viewing));

        return response()->json(['viewing' => $this->payload($viewing)], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Viewing $viewing): array
    {
        return [
            'status' => $viewing->status->value,
            'slot_display' => $viewing->slot_at
                ->timezone(config('aucvy.display_timezone'))
                ->isoFormat('D MMMM, HH:mm'),
        ];
    }
}
