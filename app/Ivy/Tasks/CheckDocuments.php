<?php

declare(strict_types=1);

namespace App\Ivy\Tasks;

use App\Enums\AiAgent;
use App\Enums\DocumentKind;
use App\Models\AiRun;
use App\Models\LotDocument;
use Illuminate\Support\Facades\Storage;

/**
 * Documentcontrole (hfst. 8: Lex) - "gecontroleerd op volledigheid, plus wat
 * ontbreekt".
 *
 * De taakverdeling is dezelfde als overal: wat telbaar is, telt PHP (welke
 * soorten ontbreken); het model kijkt naar wat alleen met ogen kan - is de
 * factuur leesbaar, staat er een tenaamstelling op, klopt het soort met de
 * inhoud. De pdf's zelf gaan mee naar de multimodale API; ze blijven op de
 * private disk en komen nooit in de uitvoer terug.
 */
final class CheckDocuments extends LotTask
{
    public function agent(): AiAgent
    {
        return AiAgent::Lex;
    }

    public function name(): string
    {
        return 'check_documents';
    }

    public function input(): array
    {
        return [
            'title' => $this->lot->title,
            'brand' => $this->lot->brand,
            'documents' => $this->lot->documents
                ->map(fn (LotDocument $document): array => [
                    'kind' => $document->kind->value,
                    'filename' => $document->original_filename,
                    // Pad plus grootte als vingerafdruk: een ander bestand is
                    // een andere controle, hetzelfde bestand een cachetreffer.
                    'fingerprint' => sha1($document->path.'|'.$document->size_bytes),
                ])
                ->values()
                ->all(),
            // Deterministisch: wat er hoort te zijn en er niet is.
            'missing_kinds' => $this->missingKinds(),
        ];
    }

    public function prompt(): string
    {
        $facts = json_encode($this->input(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';

        return <<<PROMPT
            Beoordeel de meegestuurde documenten van deze veilingkavel:

            {$facts}

            Per document: is het leesbaar, past de inhoud bij het opgegeven
            soort (factuur, eigendomsbewijs, garantie, handleiding), en valt
            er iets op dat de koper moet weten? Wees feitelijk - geen oordeel
            over echtheid, geen juridische claims, geen garanties.

            Antwoord met één JSON-object volgens dit schema, zonder tekst eromheen:
            {$this->schemaJson()}
            PROMPT;
    }

    /**
     * @return list<string>
     */
    public function documents(): array
    {
        return $this->lot->documents
            ->map(fn (LotDocument $document): string => Storage::disk('local')->path($document->path))
            ->filter(fn (string $path): bool => is_file($path))
            ->values()
            ->all();
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['documents', 'summary', 'confidence'],
            'properties' => [
                'documents' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['filename', 'readable'],
                        'properties' => [
                            'filename' => ['type' => 'string', 'maxLength' => 255],
                            'readable' => ['type' => 'boolean'],
                            'issues' => ['type' => ['string', 'null'], 'maxLength' => 300],
                        ],
                    ],
                ],
                'summary' => ['type' => 'string', 'maxLength' => 400],
                'confidence' => ['type' => 'number'],
            ],
        ];
    }

    /**
     * De bevindingen landen per document op `ai_checked_at`/`ai_findings` -
     * daar wacht het datamodel al op. Het ontbreken van soorten is al een
     * deterministisch dashboardsignaal (SignalSync).
     */
    public function apply(AiRun $run): void
    {
        $findings = collect($run->output['documents'] ?? [])->keyBy('filename');

        foreach ($this->lot->documents as $document) {
            $finding = $findings->get($document->original_filename);

            if ($finding === null) {
                continue;
            }

            $document->ai_checked_at = now();
            $document->ai_findings = [
                'readable' => (bool) $finding['readable'],
                'issues' => $finding['issues'] ?? null,
            ];
            $document->save();
        }
    }

    private function schemaJson(): string
    {
        return json_encode($this->schema(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    /**
     * @return list<string>
     */
    private function missingKinds(): array
    {
        $present = $this->lot->documents->pluck('kind')->all();

        return collect([DocumentKind::Invoice, DocumentKind::Ownership])
            ->reject(fn (DocumentKind $kind): bool => in_array($kind, $present, true))
            ->map(fn (DocumentKind $kind): string => $kind->value)
            ->values()
            ->all();
    }
}
