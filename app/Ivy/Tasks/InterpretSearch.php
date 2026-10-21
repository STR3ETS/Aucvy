<?php

declare(strict_types=1);

namespace App\Ivy\Tasks;

use App\Enums\AiAgent;
use App\Enums\LotCondition;
use App\Ivy\Contracts\AiTask;
use App\Models\AiRun;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;

/**
 * Zoekinterpretatie (hfst. 8 en 13.2): de zoekvraag als corrigeerbare
 * filters vóór de resultaten.
 *
 * De uitkomst is een filterset plus één zin. Corrigeren gebeurt met links -
 * er wordt nooit een tweede LLM-call gedaan voor een correctie. Geen kavel
 * en soms geen gebruiker als onderwerp: zoeken is publiek, en de cache op de
 * kale zoekterm maakt herhaalde vragen gratis.
 */
final class InterpretSearch implements AiTask
{
    use SerializesModels;

    public function __construct(
        public string $query,
        public ?User $user,
    ) {}

    public function agent(): AiAgent
    {
        return AiAgent::Ivy;
    }

    public function name(): string
    {
        return 'interpret_search';
    }

    public function subject(): ?Model
    {
        return $this->user;
    }

    public function input(): array
    {
        return [
            'query' => mb_substr(trim($this->query), 0, 120),
            // De echte taxonomie als kader: het model mag alleen kiezen uit
            // categorieën die bestaan.
            'categories' => Category::query()->whereNotNull('parent_id')->pluck('slug')->values()->all(),
            'conditions' => array_column(LotCondition::cases(), 'value'),
        ];
    }

    public function prompt(): string
    {
        $facts = json_encode($this->input(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';

        return <<<PROMPT
            Vertaal deze zoekvraag op een Nederlands B2B-veilingplatform naar
            filters, en vat in één korte zin samen hoe je hem gelezen hebt:

            {$facts}

            - `categorie`: alleen een slug uit de lijst, anders null.
            - `vanaf`/`tot`: prijsgrenzen in hele euro's, alleen als de vraag
              ze noemt, anders null.
            - `conditie`: alleen een waarde uit de lijst, anders null.
            - De zin is voor de zoeker: "Ik lees: …" zonder jargon.

            Antwoord met één JSON-object volgens dit schema, zonder tekst eromheen:
            {$this->schemaJson()}
            PROMPT;
    }

    public function images(): array
    {
        return [];
    }

    public function documents(): array
    {
        return [];
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['sentence', 'filters', 'confidence'],
            'properties' => [
                'sentence' => ['type' => 'string', 'maxLength' => 200],
                'filters' => [
                    'type' => 'object',
                    'properties' => [
                        // Het schema dwingt af wat de prompt belooft: alleen een
                        // slug of conditie die bestaat, anders null.
                        'categorie' => ['type' => ['string', 'null'], 'maxLength' => 80, 'enum' => $this->input()['categories']],
                        'vanaf' => ['type' => ['integer', 'null']],
                        'tot' => ['type' => ['integer', 'null']],
                        'conditie' => ['type' => ['string', 'null'], 'maxLength' => 20, 'enum' => array_column(LotCondition::cases(), 'value')],
                    ],
                ],
                'confidence' => ['type' => 'number'],
            ],
        ];
    }

    /** De interpretatie ís de uitkomst; er is niets om bij te werken. */
    public function apply(AiRun $run): void {}

    public function cacheKey(): string
    {
        return hash('sha256', json_encode([
            $this->name(),
            (int) config('aucvy.ai.prompt_version'),
            // Alleen de zoekterm: wie hetzelfde zoekt, krijgt dezelfde
            // lezing - ongeacht wie het is.
            mb_strtolower(mb_substr(trim($this->query), 0, 120)),
        ], JSON_UNESCAPED_UNICODE) ?: '');
    }

    private function schemaJson(): string
    {
        return json_encode($this->schema(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}
