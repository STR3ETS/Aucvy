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
 * hoeven loggen, net als bij e-mailverificatie. Alleen een lopende aanvraag
 * verandert; nogmaals klikken doet niets (en stuurt geen tweede mail).
 */
final class ViewingResponseController extends Controller
{
    public function confirm(Viewing $viewing): View
    {
        if ($viewing->status === ViewingStatus::Requested) {
            $viewing->status = ViewingStatus::Confirmed;
            $viewing->address_sent_at = now();
            $viewing->save();

            $viewing->user->notify(new ViewingConfirmed($viewing));
        }

        return view('viewings.responded', ['viewing' => $viewing]);
    }

    public function decline(Viewing $viewing): View
    {
        if ($viewing->status === ViewingStatus::Requested) {
            $viewing->status = ViewingStatus::Cancelled;
            $viewing->save();

            $viewing->user->notify(new ViewingCancelled($viewing));
        }

        return view('viewings.responded', ['viewing' => $viewing]);
    }
}
