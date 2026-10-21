<?php

declare(strict_types=1);

namespace App\Compliance;

/**
 * De uitkomst van de Permission Engine: mag het, en zo niet, waarom niet.
 */
final readonly class Decision
{
    private function __construct(
        public bool $allowed,
        public ?DenialReason $reason,
    ) {}

    public static function allow(): self
    {
        return new self(true, null);
    }

    public static function deny(DenialReason $reason): self
    {
        return new self(false, $reason);
    }

    public function denied(): bool
    {
        return ! $this->allowed;
    }
}
