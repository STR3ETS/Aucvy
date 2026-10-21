<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ViewingStatus;
use App\Models\Viewing;
use App\Notifications\ViewingCancelled;
use App\Notifications\ViewingConfirmed;
use Illuminate\View\View;

/**
 * De verkoper reageert via de getekende links uit zijn mail - zonder in te
 * hoeven loggen, net als bij e-mailverificatie. De link is tijdelijk, en
 * alleen een lopende aanvraag op een kavel die nog loopt verandert; nogmaals
 * klikken doet niets (en stuurt geen tweede mail).
 */
final class ViewingResponseController extends Controller
{
    public function confirm(Viewing $viewing): View
    {
        if ($this->isOpen($viewing)) {
            $viewing->status = ViewingStatus::Confirmed;
            $viewing->address_sent_at = now();
            $viewing->save();

            $viewing->user->notify(new ViewingConfirmed($viewing));
        }

        return view('viewings.responded', ['viewing' => $viewing]);
    }

    public function decline(Viewing $viewing): View
    {
        if ($this->isOpen($viewing)) {
            $viewing->status = ViewingStatus::Cancelled;
            $viewing->save();

            $viewing->user->notify(new ViewingCancelled($viewing));
        }

        return view('viewings.responded', ['viewing' => $viewing]);
    }

    /**
     * Een aanvraag die nog loopt, op een kavel die nog te zien is, voor een
     * moment dat nog moet komen. Is de kavel inmiddels gesloten of het moment
     * voorbij, dan gaat er geen adres meer de deur uit.
     */
    private function isOpen(Viewing $viewing): bool
    {
        $viewing->loadMissing('lot');

        return $viewing->status === ViewingStatus::Requested
            && $viewing->lot->status->isPublic()
            && ! $viewing->lot->status->isFinished()
            && $viewing->slot_at->isFuture();
    }
}
