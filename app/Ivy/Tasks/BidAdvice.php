<?php

declare(strict_types=1);

namespace App\Ivy\Tasks;

use App\Auctions\Support\BidGuards;
use App\Enums\AiAgent;
use App\Models\AiRun;

/**
 * Biedadvies op de kavelpagina (hfst. 8: Atlas + Finn) - altijd met
 * "jij beslist".
 *
 * De bedragen komen uit PHP: het minimumbod uit de staffel en het verstandige
 * maximum uit de waardeschatting die al op de kavel staat. Het model duidt -
 * conditie, biedverloop, resterende tijd - maar rekent niet. Bij lage
 * zekerheid toont de UI geen exact maximum (ConfidencePolicy).
 */
final class BidAdvice extends LotTask
{
    public function agent(): AiAgent
    {
        return AiAgent::Atlas;
    }

    public function name(): string
    {
        return 'bid_advice';
    }

    public function input(): array
    {
        $auction = $this->lot->auction;

        return [
            'title' => $this->lot->title,
            'condition' => $this->lot->condition?->value,
            'brand' => $this->lot->brand,
            'year' => $this->lot->year,

            // De bedragen - deterministisch, uit dezelfde bronnen als de
            // pagina zelf. Ze sturen de duiding én maken de cachesleutel
            // prijsbewust: een fors hoger bod is een nieuwe analyse.
            'current_price_cents' => $auction->current_price_cents,
            'minimum_bid_cents' => app(BidGuards::class)->minimumBid($auction),
            'value_min_cents' => $this->lot->value_min_cents,
            'value_max_cents' => $this->lot->value_max_cents,
            'value_confidence' => $this->lot->value_confidence?->value,

            'bid_count' => $auction->bid_count,
            'bidder_count' => $auction->bidder_count,
            'hours_left' => (int) now()->diffInHours($auction->ends_at, absolute: true),
            'reserve_met' => $auction->reserve_met,
        ];
    }

    public function prompt(): string
    {
        $facts = json_encode($this->input(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';

        return <<<PROMPT
            Een bieder op veilingplatform Aucvy vraagt advies bij deze kavel.
            De cijfers (in centen) zijn al berekend door het platform:

            {$facts}

            Schrijf een korte, nuchtere duiding: wat zeggen de conditie, het
            biedverloop en de resterende tijd? Noem géén bedragen - de bedragen
            toont het platform zelf uit zijn eigen berekening. Geen garanties,
            geen beloften over winst; de beslissing blijft bij de bieder.

            Antwoord met één JSON-object volgens dit schema, zonder tekst eromheen:
            {$this->schemaJson()}
            PROMPT;
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['assessment', 'confidence'],
            'properties' => [
                'assessment' => ['type' => 'string', 'maxLength' => 600],
                'risks' => ['type' => ['string', 'null'], 'maxLength' => 300],
                'confidence' => ['type' => 'number'],
            ],
        ];
    }

    /**
     * Niets om toe te passen: het advies is de uitkomst zelf, per run gelogd
     * en via de cache gedeeld tussen kijkers met dezelfde stand.
     */
    public function apply(AiRun $run): void {}

    private function schemaJson(): string
    {
        return json_encode($this->schema(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}
