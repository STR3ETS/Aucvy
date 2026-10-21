<?php

declare(strict_types=1);

namespace App\Ivy;

/**
 * Wat een provider teruggeeft: ruwe tekst plus de boekhouding.
 *
 * Nog niets hiervan is vertrouwd - de tekst moet eerst door de
 * OutputValidator voordat er iets mee gebeurt.
 */
final readonly class AiResult
{
    public function __construct(
        public string $text,
        public string $model,
        public int $tokensIn,
        public int $tokensOut,
    ) {}

    /**
     * Kosten in centen volgens de prijstabel in config. Onbekende modellen
     * (waaronder `fake`) kosten 0. Afronden naar boven: een budgetbewaker die
     * naar beneden afrondt, bewaakt niets.
     */
    public function costCents(): int
    {
        $pricing = config('aucvy.ai.pricing.'.$this->model);

        if (! is_array($pricing)) {
            // Een echt model zonder prijsregel (AUCVY_AI_MODEL naar een nieuw
            // id) mag de budgetten niet stilletjes uitzetten: reken dan met
            // het duurste bekende tarief. Alleen nep- en stubmodellen zijn gratis.
            if (! str_starts_with($this->model, 'claude')) {
                return 0;
            }

            $pricing = collect((array) config('aucvy.ai.pricing', []))
                ->sortByDesc(fn (array $row): int => (int) $row['output_cents_per_mtok'])
                ->first();

            if (! is_array($pricing)) {
                return 0;
            }
        }

        $total = $this->tokensIn * (int) $pricing['input_cents_per_mtok']
            + $this->tokensOut * (int) $pricing['output_cents_per_mtok'];

        return intdiv($total + 999_999, 1_000_000);
    }
}
