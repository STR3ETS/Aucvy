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
            return 0;
        }

        $total = $this->tokensIn * (int) $pricing['input_cents_per_mtok']
            + $this->tokensOut * (int) $pricing['output_cents_per_mtok'];

        return intdiv($total + 999_999, 1_000_000);
    }
}
