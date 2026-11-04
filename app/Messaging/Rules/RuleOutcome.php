<?php

declare(strict_types=1);

namespace App\Messaging\Rules;

use App\Models\MessageModeration;

/**
 * Wat de regels van een bericht vinden.
 */
final readonly class RuleOutcome
{
    public function __construct(
        public string $action,
        public string $signal,
        public float $confidence,
        public ?string $maskedBody = null,
        public ?string $explanation = null,
        public ?string $hint = null,
    ) {}

    public static function allow(): self
    {
        return new self(MessageModeration::ALLOW, 'none', 1.0);
    }

    public function blocksDelivery(): bool
    {
        return in_array($this->action, [MessageModeration::HOLD, MessageModeration::REJECT], true);
    }
}
