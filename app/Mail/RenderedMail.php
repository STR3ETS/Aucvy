<?php

declare(strict_types=1);

namespace App\Mail;

final readonly class RenderedMail
{
    public function __construct(
        public string $subject,
        public string $preheader,
        public string $html,
        public string $text,
    ) {}
}
