<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Enums\InsightSeverity;
use App\Models\AiInsight;
use App\Models\SupportTicket;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Het overzicht van taken, meldingen en tickets op het dashboard: één lijst,
 * nieuwste bovenaan, met tabs om te filteren. Taken zijn de signalen met
 * een knop (wat jouw akkoord of actie vraagt), meldingen is wat per mail of
 * push ging, tickets zijn je eigen supportvragen.
 *
 * @phpstan-type Item array{kind: string, title: string, body: string|null, url: string, at: CarbonInterface, unread: bool, icon: string, tone: string, agent: string|null, dismiss_id: int|null}
 */
final class ActivityFeed
{
    /**
     * @param  Collection<int, AiInsight>  $tasks
     * @return array{items: list<Item>, tabs: array<string, array{0: string, 1: int}>}
     */
    public function for(User $user, Collection $tasks, int $limit = 40): array
    {
        $items = collect();

        $tasks->each(fn (AiInsight $insight) => $items->push([
            'kind' => 'task',
            'title' => $insight->title,
            'body' => $insight->body,
            'url' => route($insight->action_route, $insight->action_params ?? []),
            'at' => $insight->created_at,
            'unread' => true,
            'icon' => match ($insight->severity) {
                InsightSeverity::Urgent => 'megaphone',
                InsightSeverity::Attention => 'clock',
                InsightSeverity::Info => 'sparkles',
            },
            'tone' => match ($insight->severity) {
                InsightSeverity::Urgent => 'red',
                InsightSeverity::Attention => 'orange',
                InsightSeverity::Info => 'blue',
            },
            'agent' => $insight->agent->value,
            'dismiss_id' => $insight->id,
        ]));

        $user->notifications()->latest()->limit($limit)->get()->each(function ($notification) use ($items): void {
            $data = $notification->data;
            [$icon, $tone] = $this->notificationStyle((string) ($data['kind'] ?? ''));

            $items->push([
                'kind' => 'notification',
                'title' => (string) ($data['title'] ?? 'Melding'),
                'body' => $data['body'] ?? null,
                'url' => route('notifications.open', $notification->id),
                'at' => $notification->created_at,
                'unread' => $notification->read_at === null,
                'icon' => $icon,
                'tone' => $tone,
                'agent' => null,
                'dismiss_id' => null,
            ]);
        });

        SupportTicket::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit(10)
            ->get()
            ->each(fn (SupportTicket $ticket) => $items->push([
                'kind' => 'ticket',
                'title' => 'Ticket #'.$ticket->id.': '.$ticket->subject,
                'body' => $ticket->isOpen()
                    ? 'Open. We reageren per mail, meestal binnen één werkdag.'
                    // De notitie bij het sluiten is intern (zo staat het ook
                    // op het formulier) en komt dus niet in de feed van de melder.
                    : 'Gesloten. Je hebt antwoord per mail gekregen.',
                'url' => route('contact'),
                'at' => $ticket->created_at,
                'unread' => $ticket->isOpen(),
                'icon' => 'message-circle',
                'tone' => $ticket->isOpen() ? 'purple' : 'grey',
                'agent' => null,
                'dismiss_id' => null,
            ]));

        $items = $items
            ->sortByDesc(fn (array $item): int => $item['at']->getTimestamp())
            ->values()
            ->take($limit);

        return [
            'items' => $items->all(),
            'tabs' => [
                'alles' => ['Alles', $items->count()],
                'ongelezen' => ['Ongelezen', $items->where('unread', true)->count()],
                'task' => ['Taken', $items->where('kind', 'task')->count()],
                'notification' => ['Meldingen', $items->where('kind', 'notification')->count()],
                'ticket' => ['Tickets', $items->where('kind', 'ticket')->count()],
            ],
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function notificationStyle(string $kind): array
    {
        return match ($kind) {
            'outbid' => ['gavel', 'orange'],
            'auction_lost' => ['gavel', 'grey'],
            'order' => ['shopping-bag', 'purple'],
            'payout' => ['banknote', 'green'],
            'dispute' => ['shield-check', 'red'],
            'alert' => ['search', 'blue'],
            'watchlist' => ['heart', 'blue'],
            'viewing' => ['clock', 'blue'],
            'moderation', 'verification' => ['badge-check', 'green'],
            default => ['bell', 'blue'],
        };
    }
}
