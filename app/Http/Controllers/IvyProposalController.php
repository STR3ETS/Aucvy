<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Ivy\Chat\ConfirmIvyProposal;
use App\Ivy\Chat\Proposals;
use App\Models\IvyActionProposal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Het bevestigingsscherm (K6): actueel bod, volledige kosten, bindend-
 * tekst, melding bij wijziging. Bevestigen met de verwachte stand en een
 * idempotency key; bij een verschoven prijs een 409 en opnieuw bevestigen.
 */
final class IvyProposalController extends Controller
{
    public function __construct(private readonly Proposals $proposals, private readonly ConfirmIvyProposal $confirm) {}

    public function show(Request $request, IvyActionProposal $proposal): View
    {
        abort_unless($proposal->user_id === $request->user()->id, 404);

        return view('ivy.proposal', $this->proposals->show($proposal) + ['idempotencyKey' => bin2hex(random_bytes(12))]);
    }

    public function confirm(Request $request, IvyActionProposal $proposal): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'expected_current_cents' => ['nullable', 'integer'],
            'expected_ends_at' => ['nullable', 'string'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:80'],
        ]);

        $outcome = $this->confirm->handle(
            $proposal,
            $request->user(),
            isset($validated['expected_current_cents']) ? (int) $validated['expected_current_cents'] : null,
            $validated['expected_ends_at'] ?? null,
            $validated['idempotency_key'],
            $request->ip(),
        );

        $status = $outcome['state'] === IvyActionProposal::PRICE_CHANGED ? 409 : 200;
        $outcome['message'] = __($outcome['message_key']);

        if ($request->expectsJson()) {
            return response()->json($outcome, $status);
        }

        return redirect()->route('ivy.proposals.show', $proposal)->with($status === 409 ? 'warning' : 'status', $outcome['message']);
    }

    public function reject(Request $request, IvyActionProposal $proposal): RedirectResponse
    {
        abort_unless($proposal->user_id === $request->user()->id, 404);

        $this->proposals->reject($proposal);

        return redirect()->to($proposal->lot !== null ? route('lots.show', $proposal->lot) : route('ivy.inbox'))->with('status', 'Voorstel niet uitgevoerd.');
    }
}
