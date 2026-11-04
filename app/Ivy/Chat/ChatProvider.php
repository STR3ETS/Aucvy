<?php

declare(strict_types=1);

namespace App\Ivy\Chat;

/**
 * Het chatcontract met tools (Ivy-briefing IVY-003): één beurt met het
 * systeembericht, de geschiedenis en de toegestane tools; terug komt tekst
 * en eventueel een lijst toolaanroepen. Eén interface, zodat wisselen van
 * model of leverancier één klasse is; de nepversie draait zonder sleutel.
 */
interface ChatProvider
{
    /**
     * @param  list<array{role: string, content: string|array<int, array<string, mixed>>}>  $messages
     * @param  list<array{name: string, description: string, input_schema: array<string, mixed>}>  $tools
     */
    public function respond(string $system, array $messages, array $tools, string $tier = 'fast'): ChatResponse;
}
