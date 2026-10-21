<?php

declare(strict_types=1);

namespace App\Lots;

use App\Enums\LotStatus;
use App\Models\Lot;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\Storage;
use LogicException;

/**
 * Een concept weggooien, inclusief de bestanden erachter.
 *
 * De foto's staan op de publieke disk en de documenten op de private; een
 * rij verwijderen zonder die bestanden laat ze daar eeuwig staan. Alleen
 * concepten: alles met een publieke status of een verkoop erachter is geen
 * concept meer en blijft staan.
 */
final class DeleteDraft
{
    public function handle(Lot $lot, ?User $actor = null): void
    {
        if ($lot->status !== LotStatus::Draft) {
            throw new LogicException("Kavel {$lot->id} is geen concept en kan niet worden weggegooid.");
        }

        $lot->loadMissing(['media', 'documents']);

        foreach ($lot->media as $media) {
            if (isset($media->variants['original'])) {
                Storage::disk('public')->delete($media->variants['original']);
            }
        }

        foreach ($lot->documents as $document) {
            Storage::disk('local')->delete($document->path);
        }

        Audit::record('lot.draft_deleted', $lot, $actor, before: ['title' => $lot->title, 'seller_id' => $lot->seller_id]);

        $lot->delete();
    }
}
