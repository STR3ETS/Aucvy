<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\DocumentKind;
use App\Http\Controllers\Controller;
use App\Ivy\StartTask;
use App\Ivy\Tasks\ReadNameplate;
use App\Lots\Documents\DocumentVisibility;
use App\Lots\DraftAccess;
use App\Lots\DraftPayload;
use App\Lots\Scan\Gtin;
use App\Lots\Scan\ProductLookup;
use App\Models\Lot;
use App\Models\LotDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Scannen in de wizard (briefing nieuwe functies): een streepjescode met
 * controlegetal en productopzoeking, en een typeplaat die Ivy leest.
 * Typeplaatfoto's blijven intern (document, niet kavelfoto).
 */
final class ScanController extends Controller
{
    public function __construct(private readonly DraftAccess $access, private readonly DraftPayload $payload) {}

    public function gtin(Request $request, Lot $lot, ProductLookup $lookup): JsonResponse
    {
        $lot = $this->access->resolve($request, $lot);

        $validated = $request->validate(['code' => ['required', 'string', 'max:40']]);
        $gtin = Gtin::normalise($validated['code']);

        if ($gtin === null) {
            return response()->json(['message' => 'Dit is geen geldige productcode: het controlegetal klopt niet.'], 422);
        }

        $result = $lookup->lookup($gtin);
        $lookup->applyTo($lot, $gtin, $result);

        return response()->json([
            'gtin' => $gtin,
            'product' => $result,
            'message' => match ($result['status']) {
                'found' => 'Gevonden bij '.$result['source'].': '.$result['name'].'. Merk en model staan als voorstel op je kavel; controleer ze.',
                'no_provider' => 'Code opgeslagen. Productopzoeking volgt zodra de EAN-dienst is gekoppeld.',
                default => 'Code opgeslagen; geen productgegevens gevonden voor deze code.',
            },
        ] + $this->payload->for($lot->fresh(), $request->user()));
    }

    public function nameplate(Request $request, Lot $lot): JsonResponse
    {
        $lot = $this->access->resolve($request, $lot);

        $request->validate(['file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240']]);

        if ($lot->documents()->count() >= 15) {
            return response()->json(['message' => 'Maximaal 15 documenten per kavel.'], 422);
        }

        $file = $request->file('file');

        $document = LotDocument::create([
            'lot_id' => $lot->id,
            'kind' => DocumentKind::Nameplate->value,
            'visibility' => DocumentVisibility::defaultFor(DocumentKind::Nameplate),
            'path' => $file->store('documents/'.$lot->public_id, 'local'),
            'original_filename' => mb_substr((string) $file->getClientOriginalName(), 0, 255),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ]);

        $run = StartTask::run(new ReadNameplate($lot, $document), $request->user());

        return response()->json([
            'document' => ['id' => $document->id, 'name' => $document->original_filename, 'kind' => $document->kind->value],
            'run' => ['id' => $run->id, 'status' => $run->status->value],
        ], 201);
    }
}
