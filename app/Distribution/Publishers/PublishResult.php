<?php

declare(strict_types=1);

namespace App\Distribution\Publishers;

use App\Models\LotDistribution;

final readonly class PublishResult
{
    public function __construct(
        public string $status,
        public ?string $externalId = null,
        public ?string $url = null,
        public ?string $error = null,
    ) {}

    public static function posted(string $externalId, ?string $url = null): self
    {
        return new self(LotDistribution::POSTED, $externalId, $url);
    }

    public static function ready(): self
    {
        return new self(LotDistribution::READY);
    }

    public static function failed(string $error): self
    {
        return new self(LotDistribution::FAILED, error: $error);
    }
}
