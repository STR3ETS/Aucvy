<?php

declare(strict_types=1);

namespace App\Ivy\Tasks;

use App\Enums\AiAgent;
use App\Enums\LotStatus;
use App\Enums\OrderStatus;
use App\Ivy\Contracts\AiTask;
use App\Models\AiInsight;
use App\Models\AiRun;
use App\Models\Bid;
use App\Models\Lot;
use App\Models\Order;
use App\Models\User;
use App\Models\WatchlistItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;

/**
 * De wekelijkse bevindingen voor "Wat Ivy deze week zag" (hfst. 8 en 13.5).
 *
 * De cijfers komen deterministisch uit PHP; het model mag ze alleen dúiden.
 * Elke bevinding draagt de specialist die hem opmerkte. De uitkomst is een
 * observatie, geen actiekaart - de signalen met knoppen komen uit SignalSync.
 */
final class WeeklyInsights implements AiTask
{
    use SerializesModels;

    public function __construct(public User $user) {}

    public function agent(): AiAgent
    {
        return AiAgent::Ivy;
    }

    public function name(): string
    {
        return 'weekly_insights';
    }

    public function subject(): Model
    {
        return $this->user;
    }

    public function input(): array
    {
        $since = now()->subWeek();
        $lotIds = $this->user->lots()->pluck('id');

        return [
            // De weeknotatie zit in de cachesleutel: volgende week is een
            // nieuwe analyse, binnen de week is het dezelfde.
            'week' => now()->format('o-\wW'),
            'live_lots' => Lot::query()->whereIn('id', $lotIds)->where('status', LotStatus::Live)->count(),
            'draft_lots' => Lot::query()->whereIn('id', $lotIds)->where('status', LotStatus::Draft)->count(),
            'bids_received' => Bid::query()
                ->whereHas('auction', fn ($query) => $query->whereIn('lot_id', $lotIds))
                ->where('placed_at', '>=', $since)
                ->count(),
            'new_watchers' => WatchlistItem::query()
                ->whereIn('lot_id', $lotIds)
                ->where('created_at', '>=', $since)
                ->count(),
            'orders_sold' => Order::query()
                ->where('seller_id', $this->user->id)
                ->where('created_at', '>=', $since)
                ->count(),
            'orders_awaiting_payment' => Order::query()
                ->where('seller_id', $this->user->id)
                ->where('status', OrderStatus::AwaitingPayment)
                ->count(),
        ];
    }

    public function prompt(): string
    {
        $facts = json_encode($this->input(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';

        return <<<PROMPT
            Dit zijn de weekcijfers van één verkoper op veilingplatform Aucvy:

            {$facts}

            Schrijf twee tot vier korte bevindingen voor zijn dashboard, elk
            toegewezen aan de specialist waar hij het beste past: ivy
            (coördinatie), atlas (markt), nova (marketing), finn (geld), flow
            (logistiek) of lex (compliance).

            Regels: alleen duiden wat er in de cijfers staat - geen aannames
            over bezoekers of markten waar geen cijfer voor is. Observaties,
            geen opdrachten. Geen beloften of garanties.

            Antwoord met één JSON-object volgens dit schema, zonder tekst eromheen:
            {$this->schemaJson()}
            PROMPT;
    }

    public function images(): array
    {
        return [];
    }

    public function documents(): array
    {
        return [];
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['insights', 'confidence'],
            'properties' => [
                'insights' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['agent', 'title'],
                        'properties' => [
                            'agent' => ['type' => 'string', 'enum' => array_column(AiAgent::cases(), 'value')],
                            'title' => ['type' => 'string', 'maxLength' => 120],
                            'body' => ['type' => ['string', 'null'], 'maxLength' => 300],
                        ],
                    ],
                ],
                'confidence' => ['type' => 'number'],
            ],
        ];
    }

    public function apply(AiRun $run): void
    {
        // De week van vorige keer maakt plaats; maximaal vier bevindingen.
        AiInsight::query()
            ->where('user_id', $this->user->id)
            ->where('type', 'weekly')
            ->delete();

        foreach (array_slice($run->output['insights'] ?? [], 0, 4) as $insight) {
            AiInsight::create([
                'user_id' => $this->user->id,
                'agent' => AiAgent::from($insight['agent']),
                'type' => 'weekly',
                'title' => $insight['title'],
                'body' => $insight['body'] ?? null,
                'severity' => 'info',
                'valid_until' => now()->addDays(8),
            ]);
        }
    }

    public function cacheKey(): string
    {
        return hash('sha256', json_encode([
            $this->name(),
            (int) config('aucvy.ai.prompt_version'),
            $this->input(),
        ], JSON_UNESCAPED_UNICODE) ?: '');
    }

    private function schemaJson(): string
    {
        return json_encode($this->schema(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}
