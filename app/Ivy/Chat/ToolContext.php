<?php

declare(strict_types=1);

namespace App\Ivy\Chat;

use App\Models\IvyConversation;
use App\Models\Lot;
use App\Models\User;

/**
 * Met wiens rechten een tool draait: de klant van dit gesprek, zijn rol en
 * de kavel van de pagina. Data per vraag via tools, niet als één grote
 * database.
 */
final readonly class ToolContext
{
    public function __construct(
        public IvyConversation $conversation,
        public ?User $user,
        public string $role,
        public ?Lot $lot,
        public ?string $ip = null,
    ) {}

    public function isGuest(): bool
    {
        return $this->user === null;
    }
}
