<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\NotificationChannel;
use App\Lots\LotFilters;
use App\Lots\LotSearch;
use App\Models\SavedSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Bewaarde zoekopdrachten (briefing sectie 3: /app/alerts). Een alert is
 * een filtercombinatie zoals hij in de URL stond; `alerts:match` legt hem
 * elk uur langs de nieuwe kavels.
 */
final class SavedSearchController extends Controller
{
    public function index(Request $request, LotSearch $search): View
    {
        $searches = SavedSearch::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->get();

        // Per alert: hoeveel kavels er nu passen, en hoeveel daarvan in de laatste zeven dagen zijn gepubliceerd.
        $since = now()->subDays(7);
        $matches = $searches->mapWithKeys(fn (SavedSearch $saved): array => [$saved->id => [
            'total' => $search->count($saved->filters()),
            'new' => $saved->paused_at === null ? $search->newSince($saved->filters(), $since, 50)->count() : 0,
        ]])->all();

        return view('dashboard.alerts', [
            'searches' => $searches,
            'matches' => $matches,
        ]);
    }

    /**
     * Vanaf de zoekpagina: de huidige filters worden opnieuw door LotFilters
     * gehaald, zodat er niets in de alert belandt wat de zoekpagina zelf
     * niet zou begrijpen.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:80'],
            'query' => ['required', 'array'],
            'channel' => ['nullable', Rule::enum(NotificationChannel::class)],
        ]);

        $filters = LotFilters::fromRequest(Request::create('/veilingen', 'GET', $validated['query']));

        if (! $filters->hasAny()) {
            return back()->withErrors(['query' => 'Kies eerst een zoekterm of filter; een alert op alles is geen alert.']);
        }

        $query = $filters->toQuery();

        // Dezelfde filters nog een keer bewaren heeft geen zin; de
        // vergelijking gebeurt in PHP, want een JSON-kolom vergelijkt in SQL
        // niet betrouwbaar op volgorde.
        $existing = SavedSearch::query()
            ->where('user_id', $request->user()->id)
            ->get()
            ->first(fn (SavedSearch $search): bool => $this->normalised($search->query ?? []) === $this->normalised($query));

        if ($existing !== null) {
            return redirect()->route('dashboard.alerts')->with('status', 'Deze alert had je al.');
        }

        SavedSearch::query()->create([
            'user_id' => $request->user()->id,
            'query' => $query,
            'name' => $validated['name'] ?? null,
            'channel' => $validated['channel'] ?? NotificationChannel::Email->value,
            // Alleen kavels van ná dit moment tellen als treffer.
            'last_run_at' => now(),
        ]);

        return redirect()->route('dashboard.alerts')->with('status', 'Alert ingesteld. Je hoort het zodra er een nieuwe kavel bij past.');
    }

    public function update(Request $request, SavedSearch $savedSearch): RedirectResponse
    {
        abort_unless($savedSearch->user_id === $request->user()->id, 404);

        $validated = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:80'],
            'paused' => ['sometimes', 'boolean'],
            'channel' => ['sometimes', Rule::enum(NotificationChannel::class)],
        ]);

        if (array_key_exists('name', $validated)) {
            $savedSearch->name = $validated['name'];
        }

        if (array_key_exists('channel', $validated)) {
            $savedSearch->channel = NotificationChannel::from($validated['channel']);
        }

        if (array_key_exists('paused', $validated)) {
            $savedSearch->paused_at = $validated['paused'] ? now() : null;

            // Bij hervatten tellen alleen kavels vanaf nu; anders komt er een
            // stortvloed van alles wat tijdens de pauze is geplaatst.
            if (! $validated['paused']) {
                $savedSearch->last_run_at = now();
            }
        }

        $savedSearch->save();

        return back()->with('status', 'Alert bijgewerkt.');
    }

    public function destroy(Request $request, SavedSearch $savedSearch): RedirectResponse
    {
        abort_unless($savedSearch->user_id === $request->user()->id, 404);

        $savedSearch->delete();

        return back()->with('status', 'Alert verwijderd.');
    }

    /**
     * Dezelfde filters in dezelfde volgorde, ongeacht hoe de JSON-kolom ze
     * teruggeeft.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function normalised(array $query): array
    {
        ksort($query);

        foreach ($query as &$value) {
            if (is_array($value)) {
                sort($value);
            }
        }

        return $query;
    }
}
