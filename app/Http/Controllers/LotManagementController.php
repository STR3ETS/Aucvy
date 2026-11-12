<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\LotCondition;
use App\Enums\LotStatus;
use App\Lots\DeleteDraft;
use App\Lots\Management\DuplicateLot;
use App\Lots\Management\PauseLot;
use App\Lots\Management\ResumeLot;
use App\Lots\Management\UpdateLot;
use App\Lots\Translation;
use App\Models\Lot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Beheer van eigen kavels (briefing sectie 4: /studio/objecten/{id}):
 * bewerken, pauzeren, hervatten, dupliceren, opnieuw plaatsen en bulk.
 */
final class LotManagementController extends Controller
{
    public function edit(Request $request, Lot $lot, UpdateLot $update): View
    {
        $this->own($request, $lot);

        return view('dashboard.lot-edit', [
            'lot' => $lot->load(['auction', 'category', 'media']),
            'editable' => in_array($lot->status, UpdateLot::EDITABLE, true),
            'titleLocked' => $update->hasBids($lot),
            'conditions' => LotCondition::cases(),
        ]);
    }

    public function update(Request $request, Lot $lot, UpdateLot $update): RedirectResponse
    {
        $this->own($request, $lot);

        // Bedragen komen met een komma binnen, zoals ze in Nederland getypt worden.
        if ($request->filled('transport_from')) {
            $request->merge(['transport_from' => str_replace(',', '.', trim((string) $request->input('transport_from')))]);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'min:3', 'max:150'],
            'description' => ['nullable', 'string', 'max:8000'],
            'condition' => ['nullable', Rule::enum(LotCondition::class)],
            'brand' => ['nullable', 'string', 'max:80'],
            'model' => ['nullable', 'string', 'max:80'],
            'year' => ['nullable', 'integer', 'between:1900,'.now()->year],
            'quantity' => ['required', 'integer', 'between:1,100000'],
            'pickup_only' => ['nullable', 'boolean'],
            'viewing_available' => ['nullable', 'boolean'],
            'transport_from' => ['nullable', 'numeric', 'min:0'],
        ]);

        $data = [
            ...$validated,
            'pickup_only' => $request->boolean('pickup_only'),
            'viewing_available' => $request->boolean('viewing_available'),
            'transport_from_cents' => isset($validated['transport_from']) && $validated['transport_from'] !== null
                ? (int) round((float) $validated['transport_from'] * 100)
                : null,
        ];
        unset($data['transport_from']);

        $update->handle($lot, $request->user(), $data);

        return redirect()->route('dashboard.lots.edit', $lot)->with('status', __('lots.manage.saved'));
    }

    public function pause(Request $request, Lot $lot, PauseLot $pause): RedirectResponse
    {
        $this->own($request, $lot);

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:300']]);

        $pause->handle($lot, $request->user(), $validated['reason'] ?? null);

        return back()->with('status', __('lots.manage.paused'));
    }

    public function resume(Request $request, Lot $lot, ResumeLot $resume): RedirectResponse
    {
        $this->own($request, $lot);

        $resume->handle($lot, $request->user());

        return back()->with('status', __('lots.manage.resumed'));
    }

    /**
     * Dupliceren, of opnieuw plaatsen na "niet verkocht" of "ingetrokken":
     * technisch dezelfde kopie, maar met een ander verhaal voor de verkoper.
     */
    public function duplicate(Request $request, Lot $lot, DuplicateLot $duplicate): RedirectResponse
    {
        $this->own($request, $lot);

        $relist = $request->boolean('relist');
        $copy = $duplicate->handle($lot, $request->user(), $relist);

        return redirect()
            ->route('sell', ['concept' => $copy->id])
            ->with('status', __($relist ? 'lots.manage.relisted' : 'lots.manage.duplicated', ['id' => $copy->public_id]));
    }

    /**
     * Engelse vertaling opnieuw laten maken (briefing stap 4). Ivy doet het
     * ook vanzelf bij publicatie en na een tekstwijziging.
     */
    public function translate(Request $request, Lot $lot): RedirectResponse
    {
        $this->own($request, $lot);

        $run = Translation::start($lot, 'en', true);

        return redirect()
            ->route('dashboard.lots.edit', $lot)
            ->with('status', __($run === null ? 'lots.manage.translate_off' : 'lots.manage.translating'));
    }

    /**
     * Bulk vanaf "Mijn kavels" (briefing sectie 4: bulkacties).
     */
    public function bulk(Request $request, DuplicateLot $duplicate, DeleteDraft $deleteDraft): RedirectResponse
    {
        // Dupliceren kopieert elke foto en elk document op schijf; vijftig
        // tegelijk is een script, geen verkoper.
        $validated = $request->validate([
            'action' => ['required', Rule::in(['delete_drafts', 'duplicate'])],
            'lots' => ['required', 'array', 'min:1', $request->input('action') === 'duplicate' ? 'max:10' : 'max:50'],
            'lots.*' => ['integer'],
        ]);

        $lots = Lot::query()
            ->whereIn('seller_id', $request->user()->lotSellerIds())
            ->whereIn('id', $validated['lots'])
            ->get();

        $count = 0;

        foreach ($lots as $lot) {
            if ($validated['action'] === 'delete_drafts') {
                if ($lot->status === LotStatus::Draft) {
                    $deleteDraft->handle($lot, $request->user());
                    $count++;
                }

                continue;
            }

            $duplicate->handle($lot, $request->user());
            $count++;
        }

        return redirect()->route('dashboard.lots')->with('status', trans_choice('lots.manage.bulk_'.$validated['action'], $count, ['count' => $count]));
    }

    /**
     * Eén concept weg, vanaf Mijn kavels of uit het conceptmenu van de
     * funnel. Alleen een concept: wat live staat of verkocht is, blijft.
     */
    public function destroyDraft(Request $request, Lot $lot, DeleteDraft $deleteDraft): RedirectResponse
    {
        $this->own($request, $lot);
        abort_unless($lot->status === LotStatus::Draft, 404);

        $deleteDraft->handle($lot, $request->user());

        $status = trans_choice('lots.manage.bulk_delete_drafts', 1, ['count' => 1]);

        if ($request->input('terug') !== 'verkopen') {
            return redirect()->route('dashboard.lots', ['status' => 'concept'])->with('status', $status);
        }

        // Vanuit de funnel: terug naar het concept waar je was, of een nieuw als je dat net verwijderde.
        $concept = (int) $request->input('concept');

        return redirect()->route('sell', $concept > 0 && $concept !== $lot->id ? ['concept' => $concept] : ['nieuw' => 1])->with('status', $status);
    }

    private function own(Request $request, Lot $lot): void
    {
        abort_unless($lot->isManagedBy($request->user()), 404);
    }
}
