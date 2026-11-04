<?php

declare(strict_types=1);

namespace App\Ivy\Chat;

final readonly class ChatResponse
{
    /**
     * @param  list<array{id: string, name: string, input: array<string, mixed>}>  $toolCalls
     */
    public function __construct(
        public string $text,
        public array $toolCalls = [],
        public string $model = 'fake',
        public int $tokensIn = 0,
        public int $tokensOut = 0,
    ) {}

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }
}
