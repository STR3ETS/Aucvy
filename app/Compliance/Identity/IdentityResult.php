<?php

declare(strict_types=1);

namespace App\Compliance\Identity;

/**
 * De uitkomst van een identiteitscheck bij de partij.
 */
final readonly class IdentityResult
{
    public function __construct(
        public bool $verified,
        public bool $adult = false,
        public ?string $reference = null,
        public ?string $failure = null,
    ) {}

    public static function failed(string $failure): self
    {
        return new self(verified: false, failure: $failure);
    }

    /**
     * Bieden en verkopen vraagt een vastgestelde identiteit van een
     * meerderjarige.
     */
    public function passes(): bool
    {
        return $this->verified && $this->adult;
    }
}
