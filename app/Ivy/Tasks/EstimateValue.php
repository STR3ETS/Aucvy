<?php

declare(strict_types=1);

namespace App\Ivy\Tasks;

use App\Enums\AiAgent;
use App\Enums\ValueConfidence;
use App\Ivy\Guards\ConfidencePolicy;
use App\Models\AiRun;

/**
 * Waardeschatting als bandbreedte (hfst. 8): het model levert vergelijkingen,
 * PHP maakt daar de range van. Het LLM raakt nooit rechtstreeks een bedrag
 * dat op het scherm komt - de rekenstap is deterministisch en navolgbaar.
 */
final class EstimateValue extends LotTask
{
    public function agent(): AiAgent
    {
        return AiAgent::Atlas;
    }

    public function name(): string
    {
        return 'estimate_value';
    }

    public function input(): array
    {
        return [
            'title' => $this->lot->title,
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
            Schat de veilingwaarde van dit kavel op de Nederlandse tweedehands
            B2B-markt door drie tot vijf vergelijkbare objecten te noemen met
            een realistische prijs in centen.

            {$facts}

            Noem geen definitieve waarde - alleen vergelijkingen; het platform
            rekent daar zelf een bandbreedte uit. Wees terughoudend: liever een
            lage confidence dan een stellige gok.

            Antwoord met één JSON-object volgens dit schema, zonder tekst eromheen:
            {$this->schemaJson()}
            PROMPT;
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['comparisons', 'reasoning', 'confidence'],
            'properties' => [
                'comparisons' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['label', 'price_cents'],
                        'properties' => [
                            'label' => ['type' => 'string', 'maxLength' => 120],
                            'price_cents' => ['type' => 'integer'],
                        ],
                    ],
                ],
                'reasoning' => ['type' => 'string', 'maxLength' => 1000],
                'confidence' => ['type' => 'number'],
            ],
        ];
    }

    public function apply(AiRun $run): void
    {
        $output = $run->output ?? [];
        $prices = array_column($output['comparisons'] ?? [], 'price_cents');

        if ($prices === []) {
            return;
        }

        // De range in PHP: laagste vergelijking afgerond omlaag, hoogste
        // omhoog, op hele honderd euro. Deterministisch en uitlegbaar.
        $min = intdiv(min($prices), 10_000) * 10_000;
        $max = (int) (ceil(max($prices) / 10_000) * 10_000);

        $this->lot->fresh()->update([
            'value_min_cents' => $min,
            'value_max_cents' => $max,
            'value_confidence' => ValueConfidence::from(
                app(ConfidencePolicy::class)->level($run->confidence),
            ),
        ]);
    }

    private function schemaJson(): string
    {
        return json_encode($this->schema(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}
