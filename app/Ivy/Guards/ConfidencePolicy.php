<?php

declare(strict_types=1);

namespace App\Ivy\Guards;

/**
 * Vertaalt de ruwe confidence (0–1) naar wat de UI mag tonen (hfst. 8).
 *
 * De regel die telt: bij `low` geen exacte bedragen als advies. Een
 * waardeschatting met lage zekerheid verschijnt dan als bandbreedte met een
 * slag om de arm - nooit als "dit is hij waard".
 */
final class ConfidencePolicy
{
    public function level(?float $confidence): string
    {
        return match (true) {
            $confidence === null => 'low',
            $confidence >= 0.8 => 'high',
            $confidence >= 0.5 => 'medium',
            default => 'low',
        };
    }

    public function allowsExactAmounts(?float $confidence): bool
    {
        return $this->level($confidence) !== 'low';
    }

    /** De tekst die de UI naast het advies zet. */
    public function hint(?float $confidence): string
    {
        return __('ivy.confidence.'.$this->level($confidence));
    }
}
