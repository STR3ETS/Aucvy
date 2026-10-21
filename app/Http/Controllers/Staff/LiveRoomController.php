<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Auctions\LiveRoom;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Live control room (briefing sectie 7): de pagina en het paneel dat elke
 * paar seconden ververst, net als het overzicht van de backoffice.
 */
final class LiveRoomController extends Controller
{
    public function index(LiveRoom $room): View
    {
        return view('admin.live', ['room' => $room->snapshot()]);
    }

    public function panel(LiveRoom $room): View
    {
        return view('admin.partials.live', ['room' => $room->snapshot()]);
    }
}
