<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Distribution\ShareKit;
use App\Distribution\SocialPlanning;
use App\Ivy\StartTask;
use App\Ivy\Tasks\WriteChannelTexts;
use App\Models\Lot;
use App\Models\LotDistribution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * De tab Verspreiding van het kaveldossier: de deelkit (tekst per kanaal,
 * beeld in drie formaten, link met UTM) en de social push (één planning,
 * één akkoord, per bericht aan te passen).
 */
final class DistributionController extends Controller
{
    public function __construct(private readonly ShareKit $kit, private readonly SocialPlanning $planning) {}

    public function updateText(Request $request, Lot $lot, LotDistribution $distribution): RedirectResponse
    {
        $this->guard($request, $lot, $distribution);

        $validated = $request->validate(['body' => ['required', 'string', 'min:5', 'max:'.$distribution->channel()->maxLength()]]);

        $this->kit->updateText($distribution, $validated['body']);

        return $this->back($lot, 'Tekst opgeslagen.');
    }

    public function markPosted(Request $request, Lot $lot, LotDistribution $distribution): RedirectResponse
    {
        $this->guard($request, $lot, $distribution);

        $validated = $request->validate(['url' => ['nullable', 'url', 'max:500']]);

        $this->kit->markPosted($distribution, $request->user(), $validated['url'] ?? null);

        return $this->back($lot, $distribution->channelLabel().' staat op geplaatst.');
    }

    public function markWithdrawn(Request $request, Lot $lot, LotDistribution $distribution): RedirectResponse
    {
        $this->guard($request, $lot, $distribution);

        if ($distribution->isPlanned()) {
            $this->planning->skip($distribution);
        } else {
            $this->kit->markWithdrawn($distribution, $request->user());
        }

        return $this->back($lot, $distribution->channelLabel().' staat op offline.');
    }

    /**
     * Nova schrijft de teksten; de pagina toont intussen de sjablonen.
     */
    public function askIvy(Request $request, Lot $lot): RedirectResponse
    {
        abort_unless($lot->isManagedBy($request->user()), 404);

        $this->kit->for($lot);
        $this->planning->for($lot);

        StartTask::run(new WriteChannelTexts($lot), $request->user());

        return $this->back($lot, 'Nova schrijft de teksten; ververs over een paar seconden.');
    }

    public function approvePlanning(Request $request, Lot $lot): RedirectResponse
    {
        abort_unless($lot->isManagedBy($request->user()), 404);

        $this->planning->for($lot);
        $count = $this->planning->approve($lot, $request->user());

        return $this->back($lot, $count > 0 ? "Planning goedgekeurd: {$count} berichten ingepland." : 'Er stond niets meer open om in te plannen.');
    }

    private function guard(Request $request, Lot $lot, LotDistribution $distribution): void
    {
        abort_unless($lot->isManagedBy($request->user()) && $distribution->lot_id === $lot->id, 404);
    }

    private function back(Lot $lot, string $status): RedirectResponse
    {
        return redirect()->route('dashboard.lots.show', [$lot, 'tab' => 'verspreiding'])->with('status', $status);
    }
}
