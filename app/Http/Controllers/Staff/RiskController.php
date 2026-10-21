<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Risk\RiskScan;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Risicosignalen (briefing sectie 7): wat de regels zagen, met de link
 * naar de plek waar beheer het nakijkt en handelt.
 */
final class RiskController extends Controller
{
    public function index(Request $request, RiskScan $scan): View
    {
        $days = (int) $request->integer('dagen', 30);
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;

        return view('admin.risk', [
            'signals' => $scan->signals($days),
            'days' => $days,
        ]);
    }
}
