<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Enums\LotStatus;
use App\Ivy\Chat\IvyFlags;
use App\Ivy\Chat\Pages;
use App\Models\Lot;
use App\Models\Order;
use App\Models\Sale;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Support\Str;

/**
 * Het zoekvenster van het dashboard (Ctrl K): één veld dat door alles van
 * de klant zoekt. Pagina's en instellingen, eigen kavels, bestellingen en
 * veilingen, en wat er nu op Aucvy te koop staat. Zonder zoekwoord toont
 * het de pagina's om heen te gaan.
 *
 * @phpstan-type Hit array{title: string, subtitle: string|null, url: string|null, icon: string, ask?: string}
 * @phpstan-type Group array{label: string, hits: list<Hit>}
 */
final class QuickSearch
{
    private const int PER_GROUP = 5;

    public function __construct(private readonly Pages $pages) {}

    /**
     * @return array{query: string, groups: list<Group>}
     */
    public function for(User $user, string $query): array
    {
        $query = Str::limit(trim($query), 80, '');

        if ($query === '') {
            return ['query' => '', 'groups' => [['label' => 'Ga naar', 'hits' => $this->destinations($user)]]];
        }

        $groups = [
            ['label' => 'Pagina\'s', 'hits' => $this->matching($this->destinations($user), $query)],
        ];

        // Een enkele letter levert vooral ruis op; vanaf twee tekens zoeken we in de gegevens.
        if (mb_strlen($query) >= 2) {
            $groups[] = ['label' => 'Mijn kavels', 'hits' => $this->lots($user, $query)];
            $groups[] = ['label' => 'Bestellingen', 'hits' => $this->orders($user, $query)];
            $groups[] = ['label' => 'Mijn veilingen', 'hits' => $this->sales($user, $query)];
            $groups[] = ['label' => 'Te koop op Aucvy', 'hits' => $this->market($query)];
        }

        if (config('aucvy.ai.enabled') && IvyFlags::on('ivy.enabled')) {
            $groups[] = ['label' => 'Niet gevonden wat je zoekt?', 'hits' => [[
                'title' => 'Vraag het aan Ivy: "'.$query.'"',
                'subtitle' => 'Ivy zoekt het voor je uit in het gesprek rechtsonder.',
                'url' => null,
                'icon' => 'sparkles',
                'ask' => $query,
            ]]];
        }

        return [
            'query' => $query,
            'groups' => array_values(array_filter($groups, fn (array $group): bool => $group['hits'] !== [])),
        ];
    }

    /**
     * De pagina's van het dashboard en de onderdelen van de instellingen.
     *
     * @return list<Hit>
     */
    private function destinations(User $user): array
    {
        $icons = [
            'dashboard' => 'target', 'mijn_kavels' => 'gavel', 'mijn_veilingen' => 'clock', 'biedingen' => 'trending-up',
            'bestellingen' => 'shopping-bag', 'berichten' => 'message-circle', 'volglijst' => 'heart', 'alerts' => 'search',
            'meldingen' => 'bell', 'uitbetalingen' => 'banknote', 'financien' => 'file-text', 'geschillen' => 'shield-check',
            'instellingen' => 'settings', 'verificatie' => 'badge-check', 'kavel_plaatsen' => 'plus', 'veilingen' => 'gavel',
            'veelgestelde_vragen' => 'file-text', 'contact' => 'message-circle',
        ];

        $hits = collect($this->pages->for($user))
            ->map(fn (array $page, string $key): array => ['title' => $page['label'], 'subtitle' => null, 'url' => $page['url'], 'icon' => $icons[$key] ?? 'layout'])
            ->values();

        $settings = [
            ['Persoonlijke gegevens', 'Naam, e-mail en telefoon', route('account.settings.section', 'gegevens', false), 'user'],
            ['Beveiliging', 'Wachtwoord en tweede stap', route('account.settings.section', 'beveiliging', false), 'shield-check'],
            ['Meldingen instellen', 'E-mail, push en voorkeuren', route('account.settings.section', 'meldingen', false), 'bell'],
            ['Apparaten', 'Actieve sessies', route('account.settings.section', 'apparaten', false), 'monitor'],
        ];

        if ($user->company_id !== null) {
            $settings[] = ['Organisatie', 'Bedrijfsgegevens en uitbetaalrekening', route('organisation.show', [], false), 'building'];
            $settings[] = ['Team', 'Collega\'s en rollen', route('team.index', [], false), 'users'];
            $settings[] = ['Koppelingen', 'Kanalen om kavels te delen', route('account.connections', [], false), 'share'];
        }

        return $hits
            ->concat(array_map(fn (array $item): array => ['title' => $item[0], 'subtitle' => $item[1], 'url' => $item[2], 'icon' => $item[3]], $settings))
            ->all();
    }

    /**
     * @param  list<Hit>  $hits
     * @return list<Hit>
     */
    private function matching(array $hits, string $query): array
    {
        $needle = Str::lower($query);

        return array_slice(array_values(array_filter(
            $hits,
            fn (array $hit): bool => str_contains(Str::lower($hit['title'].' '.($hit['subtitle'] ?? '')), $needle),
        )), 0, self::PER_GROUP);
    }

    /**
     * @return list<Hit>
     */
    private function lots(User $user, string $query): array
    {
        $like = $this->like($query);

        return Lot::query()
            ->whereIn('seller_id', $user->lotSellerIds())
            ->where(fn ($builder) => $builder->where('title', 'like', $like)->orWhere('public_id', 'like', $like))
            ->latest('updated_at')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Lot $lot): array => [
                'title' => $lot->title ?: 'Concept zonder titel',
                'subtitle' => $lot->status->label().($lot->status === LotStatus::Draft ? '' : ' · kavel #'.$lot->public_id),
                'url' => $lot->status === LotStatus::Draft ? route('sell', ['concept' => $lot->id], false) : route('dashboard.lots.show', $lot, false),
                'icon' => 'gavel',
            ])
            ->all();
    }

    /**
     * @return list<Hit>
     */
    private function orders(User $user, string $query): array
    {
        $like = $this->like($query);
        $sellerIds = $user->financeSellerIds();

        return Order::query()
            ->where(fn ($builder) => $builder->where('buyer_id', $user->id)->orWhereIn('seller_id', $sellerIds))
            ->where(fn ($builder) => $builder->where('order_number', 'like', $like)->orWhereHas('lot', fn ($lot) => $lot->where('title', 'like', $like)))
            ->with('lot:id,title')
            ->latest('id')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Order $order): array => [
                'title' => $order->lot?->title ?? 'Bestelling '.$order->order_number,
                'subtitle' => $order->order_number.' · '.$order->status->label().' · '.Money::format($order->buyer_id === $user->id ? $order->total_cents : $order->hammer_cents),
                'url' => route('orders.show', $order, false),
                'icon' => 'shopping-bag',
            ])
            ->all();
    }

    /**
     * @return list<Hit>
     */
    private function sales(User $user, string $query): array
    {
        if ($user->company_id === null) {
            return [];
        }

        return Sale::query()
            ->whereIn('seller_id', $user->lotSellerIds())
            ->where('title', 'like', $this->like($query))
            ->latest('id')
            ->limit(3)
            ->get()
            ->map(fn (Sale $sale): array => [
                'title' => $sale->title,
                'subtitle' => $sale->state()->label(),
                'url' => route('dashboard.sales.edit', $sale, false),
                'icon' => 'clock',
            ])
            ->all();
    }

    /**
     * Wat er nu te koop staat, met onderaan de weg naar alle resultaten op de site.
     *
     * @return list<Hit>
     */
    private function market(string $query): array
    {
        $hits = Lot::query()
            ->whereIn('status', [LotStatus::Scheduled, LotStatus::Live, LotStatus::Closing])
            ->where('title', 'like', $this->like($query))
            ->with('auction')
            ->orderByDesc('interest_score')
            ->limit(4)
            ->get()
            ->map(fn (Lot $lot): array => [
                'title' => $lot->title,
                'subtitle' => $lot->auction !== null ? $lot->sale_type->label().' · '.Money::format($lot->currentPriceCents()) : $lot->sale_type->label(),
                'url' => route('lots.show', $lot, false),
                'icon' => 'eye',
            ])
            ->all();

        $hits[] = [
            'title' => 'Alle resultaten voor "'.$query.'"',
            'subtitle' => 'Zoek in alle kavels op Aucvy',
            'url' => route('lots.index', ['q' => $query], false),
            'icon' => 'search',
        ];

        return $hits;
    }

    /**
     * Het zoekwoord als patroon, met de jokers van de klant als gewone tekens.
     */
    private function like(string $query): string
    {
        return '%'.addcslashes($query, '%_\\').'%';
    }
}
