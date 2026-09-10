<?php

declare(strict_types=1);

namespace App\Ivy\Tasks;

use App\Enums\AiAgent;
use App\Models\AiRun;

/**
 * Schrijf de advertentie: titel en omschrijving in de toon van het platform -
 * eerlijk over gebreken, dat verkoopt aantoonbaar beter (hfst. 13.4).
 */
final class DraftListing extends LotTask
{
    public function agent(): AiAgent
    {
        return AiAgent::Nova;
    }

    public function name(): string
    {
        return 'draft_listing';
    }

    public function input(): array
    {
        return [
            'object_type' => $this->lot->specifications['objecttype']
                ?? $this->lot->category?->name,
            'description' => $this->lot->description,
            'brand' => $this->lot->brand,
            'model' => $this->lot->model,
            'year' => $this->lot->year,
            'condition' => $this->lot->condition?->value,
            'quantity' => $this->lot->quantity,
            'photo_count' => count($this->photoChecksums()),
        ];
    }

    public function prompt(): string
    {
        $facts = json_encode($this->input(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';

        return <<<PROMPT
            Schrijf een advertentietitel en -omschrijving voor dit kavel op een
            Nederlands B2B-veilingplatform. Gebruik de foto's en deze gegevens:

            {$facts}

            Toon: zakelijk, concreet en eerlijk - benoem zichtbare gebreken in
            plaats van ze te verzwijgen. Geen superlatieven, geen garanties,
            geen beloften namens het platform.

            Antwoord met één JSON-object volgens dit schema, zonder tekst eromheen:
            {$this->schemaJson()}
            PROMPT;
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['title', 'description', 'confidence'],
            'properties' => [
                'title' => ['type' => 'string', 'maxLength' => 150],
                'description' => ['type' => 'string', 'maxLength' => 4000],
                'confidence' => ['type' => 'number'],
            ],
        ];
    }

    public function apply(AiRun $run): void
    {
        $output = $run->output ?? [];
        $lot = $this->lot->fresh();

        // De omschrijving wordt alleen vervangen zolang er nog exact de tekst
        // staat waarmee de run gestart is - dat snapshot ligt vast in
        // `ai_runs.input`. Heeft de verkoper intussen zelf getypt, dan wint
        // de verkoper. Niet herberekenen uit het model: na de queue-roundtrip
        // zou dat de bewerkte tekst zijn en de vergelijking altijd slagen.
        $untouched = $lot->description === ($run->input['description'] ?? null);

        $lot->update([
            'title' => $lot->title ?? $output['title'],
            'description' => $untouched ? $output['description'] : $lot->description,
        ]);
    }

    private function schemaJson(): string
    {
        return json_encode($this->schema(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}
