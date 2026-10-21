<?php

declare(strict_types=1);

namespace App\Ivy\Tasks;

use App\Enums\AiAgent;
use App\Lots\Translation;
use App\Models\AiRun;
use App\Models\Lot;

/**
 * Vertaal de advertentie (briefing sectie 5, stap 4). De Nederlandse tekst
 * blijft de bindende versie; de vertaling krijgt de hash van die tekst mee,
 * zodat hij vanzelf vervalt als de verkoper iets wijzigt.
 */
final class TranslateListing extends LotTask
{
    public function __construct(Lot $lot, public string $locale = 'en')
    {
        parent::__construct($lot);
    }

    public function agent(): AiAgent
    {
        return AiAgent::Nova;
    }

    public function name(): string
    {
        return 'translate_listing';
    }

    /**
     * Alleen tekst: foto's maken de vertaling niet beter, wel duurder.
     */
    public function images(): array
    {
        return [];
    }

    public function input(): array
    {
        return [
            'locale' => $this->locale,
            'title' => $this->lot->title,
            'description' => $this->lot->description,
            'condition' => $this->lot->condition?->label(),
            'category' => $this->lot->category?->name,
            'hash' => Translation::hash($this->lot),
        ];
    }

    public function prompt(): string
    {
        $language = Translation::languageName($this->locale);
        $facts = json_encode($this->input(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';

        return <<<PROMPT
            Vertaal deze advertentie van een Nederlands B2B-veilingplatform naar het
            {$language}. Vertaal de inhoud precies: niets toevoegen, niets weglaten,
            niets mooier maken. Merknamen, modelnummers, maten, aantallen en
            bedragen blijven staan zoals ze zijn. Zakelijke, concrete toon; geen
            garanties en geen beloften namens het platform.

            {$facts}

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
                'description' => ['type' => 'string', 'maxLength' => 8000],
                'confidence' => ['type' => 'number'],
            ],
        ];
    }

    public function apply(AiRun $run): void
    {
        $lot = $this->lot->fresh();
        $output = $run->output ?? [];

        // Is de Nederlandse tekst intussen veranderd, dan hoort deze vertaling
        // nergens meer bij: niet opslaan, de nieuwe run komt vanzelf.
        if ($lot === null || Translation::hash($lot) !== ($run->input['hash'] ?? null)) {
            return;
        }

        $translations = $lot->translations ?? [];
        $translations[$this->locale] = [
            'title' => mb_substr(trim((string) ($output['title'] ?? '')), 0, 150),
            'description' => mb_substr(trim((string) ($output['description'] ?? '')), 0, 8000),
            'hash' => $run->input['hash'],
            'run_id' => $run->id,
            'source' => 'ivy',
            'translated_at' => now()->toIso8601String(),
        ];

        $lot->update(['translations' => $translations]);
    }

    private function schemaJson(): string
    {
        return json_encode($this->schema(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}
