<?php

declare(strict_types=1);

namespace App\Ivy\Tasks;

use App\Enums\AiAgent;
use App\Enums\LotCondition;
use App\Lots\Provenance;
use App\Models\AiRun;
use Illuminate\Support\Str;

/**
 * Herken het object uit de foto's en de eigen woorden van de verkoper
 * (funnel stap 3, hfst. 13.4). Vult alleen velden die nog leeg zijn en die
 * de verkoper niet zelf heeft gezet - wat de verkoper invulde wint altijd
 * van wat Ivy denkt te zien. Elk ingevuld veld krijgt een herkomst met
 * zekerheid; onder de drempel moet de verkoper het bevestigen.
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
            - `field_confidence`: per ingevuld veld (brand, model, year, condition) je zekerheid, 0 tot 1.
              Een merk dat leesbaar op een label staat is zeker; een bouwjaar dat je schat is dat niet.
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
                'field_confidence' => ['type' => 'object'],
            ],
        ];
    }

    public function apply(AiRun $run): void
    {
        $output = $run->output ?? [];
        $lot = $this->lot->fresh();

        if (! $lot->isDraft()) {
            return;
        }

        $specifications = $lot->specifications ?? [];

        $confidence = (float) ($output['confidence'] ?? 0.5);

        // Het objecttype heeft geen eigen kolom; het leeft in specifications
        // zodat DraftListing er straks een titel van kan maken. Eerst, zodat
        // het binnen het maximum van veertig kenmerken valt.
        $specifications['objecttype'] ??= ['value' => $output['object_type'], 'source' => 'ai', 'confidence' => $confidence];

        /*
         | Dezelfde grenzen als de autosave van de wizard (max 40 kenmerken,
         | één waarde van hooguit 120 tekens, sleutel als slug): wat het model
         | hier buiten zet, kan de verkoper straks niet meer bewerken of
         | weghalen.
         */
        foreach (($output['specifications'] ?? []) as $key => $value) {
            $key = Str::of((string) $key)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->limit(40, '')->toString();

            if ($key === '' || ! is_scalar($value) || mb_strlen((string) $value) > 120) {
                continue;
            }

            if (count($specifications) >= 40 && ! array_key_exists($key, $specifications)) {
                break;
            }

            $specifications[$key] ??= ['value' => $value, 'source' => 'ai', 'confidence' => $confidence];
        }

        $year = $output['year'] ?? null;
        $year = is_int($year) && $year >= 1900 && $year <= (int) now()->year + 1 ? $year : null;

        $candidates = [
            'brand' => $output['brand'] ?? null,
            'model' => $output['model'] ?? null,
            'year' => $year,
            'condition' => isset($output['condition']) ? LotCondition::from($output['condition']) : null,
        ];

        $changes = ['specifications' => $specifications];
        $filled = [];

        foreach ($candidates as $field => $value) {
            if ($value === null || $lot->{$field} !== null || Provenance::isHuman($lot, $field)) {
                continue;
            }

            $changes[$field] = $value;
            $filled[$field] = (float) ($output['field_confidence'][$field] ?? $output['confidence'] ?? 0.5);
        }

        $lot->update($changes);

        Provenance::markAi($lot->fresh(), $filled, Provenance::PHOTO, $run->id);
    }

    private function schemaJson(): string
    {
        return json_encode($this->schema(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}
