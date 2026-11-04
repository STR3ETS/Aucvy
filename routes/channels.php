<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcastkanalen
|--------------------------------------------------------------------------
|
| `auction.{id}` staat hier bewust niet: dat is een publiek kanaal en heeft
| dus geen autorisatie nodig. Wat daarover gaat is precies wat ook op de
| kavelpagina staat - prijs, aantal bieders, "R. Jansen" (hfst. 7).
|
*/

/**
 * Persoonlijke berichten: overboden, gewonnen, verloren, order bijgewerkt.
 *
 * Alleen jij komt op je eigen kanaal.
 */
Broadcast::channel('user.{userId}', function (User $user, string $userId): bool {
    return $user->id === (int) $userId;
});

/**
 * Een gesprek (briefing nieuwe functies): alleen de twee partijen en
 * beheer. Het kanaal geeft alleen een seintje; de tekst haalt de client op
 * met zijn eigen rechten.
 */
Broadcast::channel('conversation.{conversationId}', function (User $user, string $conversationId): bool {
    $conversation = Conversation::query()->find((int) $conversationId);

    return $conversation !== null && ($user->is_staff || $conversation->isParticipant($user));
});

/**
 * Kijkersaantal: "34 kijkers".
 *
 * Dit ziet eruit alsof het het publieke veilingkanaal overschrijft, maar dat
 * doet het niet. Laravel strippt bij autorisatie het `presence-`- en
 * `private-`-voorvoegsel, dus deze callback hoort bij `presence-auction.{id}`.
 * Het publieke `auction.{id}` komt hier nooit langs - publieke kanalen worden
 * niet geautoriseerd. In Echo:
 *
 *     Echo.channel('auction.1')   publiek  → biedingen, verlengingen, sluiten
 *     Echo.join('auction.1')      presence → kijkersaantal
 *
 * Een presence-kanaal deelt de gegevens die deze callback teruggeeft met álle
 * leden. Daarom staat hier geen naam en geen gebruikers-id, maar een hash die
 * per veiling verschilt: genoeg om te kunnen tellen, te weinig om iemand te
 * herkennen of om twee veilingen aan elkaar te knopen.
 *
 * De briefing zegt het kort: alleen aantallen uitzenden, geen namen.
 */
Broadcast::channel('auction.{auctionId}', function (User $user, string $auctionId): array {
    return [
        'id' => hash('xxh128', $auctionId.'|'.$user->id.'|'.config('app.key')),
    ];
});
