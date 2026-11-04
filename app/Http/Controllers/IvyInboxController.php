<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auctions\Support\BidGuards;
use App\Ivy\Alerts\Alerts;
use App\Ivy\Chat\IvyFlags;
use App\Ivy\Chat\LotModes;
use App\Ivy\Chat\Proposals;
use App\Models\IvyActionProposal;
use App\Models\IvyAlert;
use App\Models\IvyLotMode;
use App\Models\Lot;
use App\Models\NotificationPreference;
use App\Models\SavedSearch;
use App\Models\WatchlistItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * De Ivy-inbox (K1): Wacht op jou, Ivy heeft gedaan, Ivy bewaakt. Een
 * keuze in een alert opent nooit zelf een bod: "Verhoog" wordt een
 * voorstel met het bevestigingsscherm.
 */
final class IvyInboxController extends Controller
{
    public function __construct(private readonly Alerts $alerts, private readonly Proposals $proposals, private readonly BidGuards $guards, private readonly LotModes $modes) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $inbox = $this->alerts->inbox($user);

        if ($request->filled('alert')) {
            IvyAlert::query()->whereKey($request->query('alert'))->where('user_id', $user->id)->update(['read_at' => now()]);
        }

        return view('dashboard.ivy-inbox', $inbox + [
            'proposals' => IvyActionProposal::query()->where('user_id', $user->id)->with('lot')->latest('id')->limit(10)->get(),
            'watching' => [
                'modes' => IvyLotMode::query()->where('user_id', $user->id)->whereNull('ended_at')->with('lot')->get(),
                'searches' => SavedSearch::query()->where('user_id', $user->id)->whereNull('paused_at')->get(),
                'watchlist' => WatchlistItem::query()->where('user_id', $user->id)->count(),
            ],
            'preferences' => collect(['outbid', 'new_bid', 'won', 'progress', 'interest', 'reserve_not_met', 'draft_idle'])->mapWithKeys(fn (string $key) => [$key => ['push' => NotificationPreference::allows($user, $key, 'push'), 'mail' => NotificationPreference::allows($user, $key, 'mail')]]),
        ]);
    }

    public function choose(Request $request, IvyAlert $alert, string $choice): RedirectResponse
    {
        abort_unless($alert->user_id === $request->user()->id, 404);

        $choices = collect($alert->choices ?? [])->keyBy('key');
        abort_unless($choices->has($choice), 404);

        $lot = $alert->lot;

        // "Verhoog": een voorstel met de eerstvolgende stap, nooit zelf bieden.
        if ($choice === 'raise' && $lot !== null && $lot->auction !== null && IvyFlags::on('ivy.proposals')) {
            $this->alerts->act($alert, $choice);
            $proposal = $this->proposals->create(IvyActionProposal::BID, $request->user(), $lot, ['amount_cents' => $this->guards->minimumBid($lot->auction)]);

            return redirect()->route('ivy.proposals.show', $proposal);
        }

        if ($choice === 'auto' && $lot !== null) {
            $this->alerts->act($alert, $choice);

            return redirect()->route('lots.show', $lot)->with('status', 'Kies hieronder je limiet; ik bied automatisch tot dat bedrag, nooit erboven.');
        }

        $this->alerts->act($alert, $choice);
        $url = $choices->get($choice)['url'] ?? $alert->url();

        return $url !== null ? redirect()->to($url) : redirect()->route('ivy.inbox')->with('status', 'Genoteerd.');
    }

    public function preferences(Request $request): RedirectResponse
    {
        $validated = $request->validate(['prefs' => ['array']]);

        foreach (['outbid', 'new_bid', 'won', 'progress', 'interest', 'reserve_not_met', 'draft_idle'] as $key) {
            foreach (['push', 'mail'] as $channel) {
                NotificationPreference::query()->updateOrCreate(
                    ['user_id' => $request->user()->id, 'alert_key' => $key, 'channel' => $channel],
                    ['enabled' => (bool) ($validated['prefs'][$key][$channel] ?? false)],
                );
            }
        }

        return back()->with('status', 'Meldingsvoorkeuren opgeslagen.');
    }

    /**
     * LotModeSelector (K4): alleen melden, eerst vragen, automatisch tot
     * limiet. Automatisch gaat via een voorstel dat de klant bevestigt.
     */
    public function mode(Request $request, Lot $lot): RedirectResponse
    {
        $validated = $request->validate([
            'mode' => ['required', Rule::in(IvyLotMode::MODES)],
            'limit' => ['required_if:mode,auto', 'nullable', 'numeric', 'min:1', 'max:10000000'],
        ]);

        if ($validated['mode'] === IvyLotMode::AUTO) {
            abort_unless(IvyFlags::on('ivy.proposals'), 404);
            $proposal = $this->proposals->create(IvyActionProposal::AUTO_BID, $request->user(), $lot, ['limit_cents' => (int) round((float) $validated['limit'] * 100)]);

            return redirect()->route('ivy.proposals.show', $proposal);
        }

        $this->modes->set($request->user(), $lot, $validated['mode'], null, $request->ip());

        return back()->with('status', $validated['mode'] === IvyLotMode::ASK ? 'Ivy zet bij elk moment een voorstel klaar en vraagt om een ja.' : 'Ivy meldt alleen wat er speelt; jij doet de rest.');
    }
}
