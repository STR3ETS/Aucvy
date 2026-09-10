<?php

declare(strict_types=1);

namespace App\Ivy\Tasks;

use App\Enums\AiAgent;
use App\Enums\LotCondition;
use App\Models\AiRun;

/**
 * Herken het object uit de foto's en de eigen woorden van de verkoper
 * (funnel stap 3, hfst. 13.4). Vult alleen velden die nog leeg zijn - wat de
 * verkoper zelf invulde wint altijd van wat Ivy denkt te zien.
 */
final class RecogniseLot extends LotTask
{
    public function agent(): AiAgent
    {
        return AiAgent::Ivy;
    }

    public function name(): string
    {
        return 'recognise_lot';
    }

    public function input(): array
    {
        return [
            'description' => $this->lot->description,
            'quantity' => $this->lot->quantity,
            'photo_count' => count($this->photoChecksums()),
            'photo_checksums' => $this->photoChecksums(),
        ];
    }

    public function prompt(): string
    {
        $description = $this->lot->description ?: '(geen omschrijving opgegeven)';

        return <<<PROMPT
            Bekijk de foto's en de omschrijving van de verkoper en herken het object.

            Omschrijving van de verkoper: "{$description}"
            Aantal objecten: {$this->lot->quantity}

            Antwoord met één JSON-object volgens dit schema, zonder tekst eromheen:
            {$this->schemaJson()}

            - `object_type`: wat het is, kort en concreet (bijv. "Professionele keuken").
            - `brand`, `model`, `year`: alleen als je het echt kunt zien of afleiden, anders null.
            - `condition`: new, like_new, good, used of defective - bij twijfel de voorzichtigste keuze.
            - `specifications`: aantoonbare eigenschappen als korte sleutel/waarde-paren.
            - `confidence`: 0 tot 1 - hoe zeker je bent van het geheel.
            PROMPT;
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['object_type', 'condition', 'confidence'],
            'properties' => [
                'object_type' => ['type' => 'string', 'maxLength' => 80],
                'brand' => ['type' => ['string', 'null'], 'maxLength' => 80],
                'model' => ['type' => ['string', 'null'], 'maxLength' => 80],
                'year' => ['type' => ['integer', 'null']],
                'condition' => ['type' => 'string', 'enum' => array_column(LotCondition::cases(), 'value')],
                'specifications' => ['type' => 'object'],
                'confidence' => ['type' => 'number'],
            ],
        ];
    }

    public function apply(AiRun $run): void
    {
        $output = $run->output ?? [];
        $lot = $this->lot->fresh();

        $specifications = $lot->specifications ?? [];

        foreach (($output['specifications'] ?? []) as $key => $value) {
            $specifications[$key] ??= $value;
        }

        // Het objecttype heeft geen eigen kolom; het leeft in specifications
        // zodat DraftListing er straks een titel van kan maken.
        $specifications['objecttype'] ??= $output['object_type'];

        $lot->update([
            'brand' => $lot->brand ?? $output['brand'] ?? null,
            'model' => $lot->model ?? $output['model'] ?? null,
            'year' => $lot->year ?? $output['year'] ?? null,
            'condition' => $lot->condition ?? LotCondition::from($output['condition']),
            'specifications' => $specifications,
        ]);
    }

    private function schemaJson(): string
    {
        return json_encode($this->schema(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}
