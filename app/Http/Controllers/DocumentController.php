<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Lots\Documents\DocumentAccess;
use App\Lots\Documents\DocumentVisibility;
use App\Lots\Dossier\Dossier;
use App\Models\Lot;
use App\Models\LotActivity;
use App\Models\LotDocument;
use App\Support\Audit\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Het documenten-oogje (briefing nieuwe functies): een document opent op de
 * pagina, zonder download, na een controle op de server. Het bestand komt
 * via een getekende URL van vijf minuten. Download blijft mogelijk als
 * tonen mislukt.
 */
final class DocumentController extends Controller
{
    public function __construct(private readonly DocumentAccess $access) {}

    public function show(Request $request, LotDocument $document): View
    {
        $document->load(['lot.documents', 'lot.media', 'lot.order']);
        $user = $request->user();
        $lock = $this->access->lockReason($user, $document);

        if ($lock === null) {
            $this->access->recordView($user, $document, $request->ip());
        }

        return view('documents.show', [
            'document' => $document,
            'lot' => $document->lot,
            'lock' => $lock,
            'fileUrl' => $lock === null ? $this->access->signedFileUrl($document) : null,
            'siblings' => $document->lot->documents->map(fn (LotDocument $sibling) => [
                'document' => $sibling,
                'lock' => $this->access->lockReason($user, $sibling),
            ]),
            'isSeller' => $user !== null && $document->lot->isManagedBy($user),
        ]);
    }

    /**
     * Het bestand zelf, alleen via de getekende URL en met een strikte
     * Content-Security-Policy: het document mag niets laden of uitvoeren.
     */
    public function file(Request $request, LotDocument $document): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403);
        abort_unless($this->access->canView($request->user(), $document), 403);

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return Storage::disk('local')->response($document->path, $document->original_filename, [
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], $disposition);
    }

    /**
     * De verkoper kiest per document wie het mag zien. Een privé document
     * openbaar maken kan, met een waarschuwing om persoonsgegevens af te
     * schermen.
     */
    public function updateVisibility(Request $request, Lot $lot, LotDocument $document): RedirectResponse
    {
        abort_unless($lot->isManagedBy($request->user()) && $document->lot_id === $lot->id, 404);

        $validated = $request->validate(['visibility' => ['required', Rule::in(DocumentVisibility::ALL)]]);

        $before = $document->visibility;
        $document->forceFill(['visibility' => $validated['visibility']])->save();

        if ($before !== $validated['visibility']) {
            Audit::record('document.visibility_changed', $document, $request->user(), ['visibility' => $before], ['visibility' => $validated['visibility']], $request->ip());

            Dossier::record(
                lot: $lot->id,
                source: LotActivity::SOURCE_DOCUMENT,
                type: 'visibility',
                visibility: LotActivity::SELLER,
                payload: ['kind' => $document->kind->label(), 'document_id' => $document->id, 'to' => DocumentVisibility::label($validated['visibility'])],
                actor: $request->user(),
            );
        }

        return redirect()->route('dashboard.lots.show', [$lot, 'tab' => 'documenten'])->with('status', __('documents.status.visibility_saved', ['kind' => $document->kind->label(), 'level' => DocumentVisibility::label($validated['visibility'])]));
    }
}
