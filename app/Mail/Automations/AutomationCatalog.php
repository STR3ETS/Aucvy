<?php

declare(strict_types=1);

namespace App\Mail\Automations;

use App\Enums\LotStatus;
use App\Enums\OrderStatus;
use App\Models\AutomationRun;
use App\Models\Company;
use App\Models\Lot;
use App\Models\MailAutomation;
use App\Models\MailTemplate;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * De acht reeksen uit de briefing. Vier lopen via de reeksmotor (start,
 * wacht, controle, mail, einde); vier bestonden al als melding of opdracht
 * en staan hier als "gebouwd" zodat beheer ze ziet. Elke reeks heeft een
 * doel en stopt zodra dat is bereikt.
 */
final class AutomationCatalog
{
    /**
     * @return list<array{key: string, number: int, name: string, stream: string, status: string, goal_event: string, weekly_limit_applies: bool, description: string, steps: list<array<string, mixed>>}>
     */
    public static function all(): array
    {
        return [
            ['key' => 'payment_reminder', 'number' => 1, 'name' => 'Betaalverzoek met herinnering', 'stream' => MailTemplate::SERVICE, 'status' => MailAutomation::ACTIVE, 'goal_event' => 'order.paid', 'weekly_limit_applies' => false,
                'description' => 'Start bij kavel gewonnen, herinnering na 12 uur; stopt als betaald of de deadline voorbij is.',
                'steps' => [['type' => 'start', 'event' => 'order.created'], ['type' => 'wait', 'hours' => 12], ['type' => 'check', 'rule' => 'order_unpaid'], ['type' => 'mail', 'template' => 'betaalherinnering'], ['type' => 'end']]],
            ['key' => 'draft_unfinished', 'number' => 2, 'name' => 'Concept niet afgemaakt', 'stream' => MailTemplate::ACTIVATION, 'status' => MailAutomation::ACTIVE, 'goal_event' => 'lot.published', 'weekly_limit_applies' => true,
                'description' => 'Start als een concept 24 uur niet is aangepast; stopt als de kavel online is of het concept is verwijderd.',
                'steps' => [['type' => 'start', 'event' => 'lot.draft_idle_24h'], ['type' => 'mail', 'template' => 'concept_bijna_klaar'], ['type' => 'wait', 'hours' => 72], ['type' => 'check', 'rule' => 'draft_still_open'], ['type' => 'mail', 'template' => 'concept_bijna_klaar'], ['type' => 'end']]],
            ['key' => 'first_lot', 'number' => 3, 'name' => 'Eerste kavel van een verkoper', 'stream' => MailTemplate::ACTIVATION, 'status' => MailAutomation::ACTIVE, 'goal_event' => 'lot.published', 'weekly_limit_applies' => true,
                'description' => 'Start bij bedrijfscontrole goedgekeurd; stopt als de eerste kavel online is.',
                'steps' => [['type' => 'start', 'event' => 'company.verified'], ['type' => 'wait', 'hours' => 72], ['type' => 'check', 'rule' => 'no_lot_yet'], ['type' => 'mail', 'template' => 'bedrijf_geverifieerd'], ['type' => 'end']]],
            ['key' => 'watched_lot_closing', 'number' => 4, 'name' => 'Gevolgde kavel sluit bijna', 'stream' => MailTemplate::ACTIVATION, 'status' => MailAutomation::BUILT_IN, 'goal_event' => 'bid.placed', 'weekly_limit_applies' => true,
                'description' => 'Bestaat als opdracht watchlist:notify-ending en melding WatchlistEnding: 24 uur voor sluiting, zonder bod; stopt bij een bod of sluiting.',
                'steps' => [['type' => 'start', 'event' => 'watchlist.ending_24h'], ['type' => 'check', 'rule' => 'no_bid_yet'], ['type' => 'mail', 'template' => 'gevolgd_sluit_bijna'], ['type' => 'end']]],
            ['key' => 'alert_match', 'number' => 5, 'name' => 'Alert-match', 'stream' => MailTemplate::ACTIVATION, 'status' => MailAutomation::BUILT_IN, 'goal_event' => 'alert.paused', 'weekly_limit_applies' => true,
                'description' => 'Bestaat als opdracht alerts:match en melding AlertMatches: per dag gebundeld; stopt als de alert uit staat.',
                'steps' => [['type' => 'start', 'event' => 'lot.published_match'], ['type' => 'wait', 'hours' => 24, 'bundle' => true], ['type' => 'mail', 'template' => 'alert_bundel'], ['type' => 'end']]],
            ['key' => 'almost_won', 'number' => 6, 'name' => 'Net niet gewonnen', 'stream' => MailTemplate::ACTIVATION, 'status' => MailAutomation::BUILT_IN, 'goal_event' => 'mail.unsubscribed', 'weekly_limit_applies' => true,
                'description' => 'Bestaat als melding AuctionLost bij sluiting; stopt bij afmelden.',
                'steps' => [['type' => 'start', 'event' => 'auction.closed_lost'], ['type' => 'wait', 'hours' => 24], ['type' => 'mail', 'template' => 'net_niet_gewonnen'], ['type' => 'end']]],
            ['key' => 'review_request', 'number' => 7, 'name' => 'Waardering vragen', 'stream' => MailTemplate::SERVICE, 'status' => MailAutomation::BUILT_IN, 'goal_event' => 'review.given', 'weekly_limit_applies' => false,
                'description' => 'Bestaat als melding ReviewRequested na ontvangst; stopt als de waardering is gegeven of een geschil is geopend.',
                'steps' => [['type' => 'start', 'event' => 'order.delivered'], ['type' => 'wait', 'hours' => 48], ['type' => 'check', 'rule' => 'no_review_no_dispute'], ['type' => 'mail', 'template' => 'waardering_vragen'], ['type' => 'end']]],
            ['key' => 'dormant_buyer', 'number' => 8, 'name' => 'Slapende koper', 'stream' => MailTemplate::ACTIVATION, 'status' => MailAutomation::ACTIVE, 'goal_event' => 'user.logged_in', 'weekly_limit_applies' => true,
                'description' => 'Start na 6 maanden geen login, eenmalig; stopt als de koper weer inlogt.',
                'steps' => [['type' => 'start', 'event' => 'user.dormant_6m'], ['type' => 'mail', 'template' => 'slapende_koper'], ['type' => 'end']]],
        ];
    }

    public static function seed(): void
    {
        foreach (self::all() as $entry) {
            MailAutomation::query()->firstOrCreate(['key' => $entry['key']], $entry);
        }
    }

    /**
     * Wie er vandaag in een reeks hoort: per reeks een lijst van
     * [gebruiker, sleutel, context]. De sleutel voorkomt dubbele runs.
     *
     * @return Collection<int, array{user: User, dedupe_key: string, lot_id: int|null, order_id: int|null}>
     */
    public static function enrol(string $key): Collection
    {
        return match ($key) {
            'payment_reminder' => Order::query()->with('buyer')->where('status', OrderStatus::AwaitingPayment)->where('created_at', '>=', now()->subDays(3))->get()
                ->filter(fn (Order $order) => $order->buyer !== null)
                ->map(fn (Order $order) => ['user' => $order->buyer, 'dedupe_key' => "payment_reminder:order:{$order->id}", 'lot_id' => $order->lot_id, 'order_id' => $order->id]),
            'draft_unfinished' => Lot::query()->with('seller')->where('status', LotStatus::Draft)->whereNotNull('seller_id')
                ->where('updated_at', '<=', now()->subDay())->where('created_at', '>=', now()->subDays(30))->get()
                ->filter(fn (Lot $lot) => $lot->seller !== null)
                ->map(fn (Lot $lot) => ['user' => $lot->seller, 'dedupe_key' => "draft_unfinished:lot:{$lot->id}", 'lot_id' => $lot->id, 'order_id' => null]),
            'first_lot' => Company::query()->with('users')->whereNotNull('verified_at')->where('verified_at', '>=', now()->subDays(30))
                ->whereDoesntHave('lots', fn ($query) => $query->whereNotIn('status', [LotStatus::Draft]))->get()
                ->flatMap(fn (Company $company) => $company->users->map(fn (User $user) => ['user' => $user, 'dedupe_key' => "first_lot:user:{$user->id}", 'lot_id' => null, 'order_id' => null])),
            'dormant_buyer' => User::query()->whereNotNull('email_verified_at')->where('is_staff', false)
                ->where(fn ($query) => $query->where('last_login_at', '<=', now()->subMonths(6))->orWhere(fn ($inner) => $inner->whereNull('last_login_at')->where('created_at', '<=', now()->subMonths(6))))
                ->get()->map(fn (User $user) => ['user' => $user, 'dedupe_key' => "dormant_buyer:user:{$user->id}", 'lot_id' => null, 'order_id' => null]),
            default => collect(),
        };
    }

    /**
     * De stopregel: null als de reeks door mag, anders de reden.
     */
    public static function stopReason(AutomationRun $run): ?string
    {
        return match ($run->automation->key) {
            'payment_reminder' => match (true) {
                $run->order === null => 'order_missing',
                $run->order->status !== OrderStatus::AwaitingPayment => 'order_'.$run->order->status->value,
                $run->order->payment_deadline_at !== null && $run->order->payment_deadline_at->isPast() => 'deadline_passed',
                default => null,
            },
            'draft_unfinished' => match (true) {
                $run->lot === null => 'lot_deleted',
                $run->lot->status !== LotStatus::Draft => 'lot_'.$run->lot->status->value,
                default => null,
            },
            'first_lot' => Lot::query()->where('seller_id', $run->user_id)->whereNotIn('status', [LotStatus::Draft])->exists() ? 'lot_published' : null,
            'dormant_buyer' => $run->user->last_login_at !== null && $run->user->last_login_at->greaterThan($run->created_at) ? 'logged_in' : null,
            default => null,
        };
    }

    /**
     * Een controlestap: true als de reeks verder mag.
     */
    public static function check(string $rule, AutomationRun $run): bool
    {
        return match ($rule) {
            'order_unpaid' => $run->order !== null && $run->order->status === OrderStatus::AwaitingPayment,
            'draft_still_open' => $run->lot !== null && $run->lot->status === LotStatus::Draft,
            'no_lot_yet' => ! Lot::query()->where('seller_id', $run->user_id)->whereNotIn('status', [LotStatus::Draft])->exists(),
            default => true,
        };
    }

    /**
     * De velden voor de mail van een reeks.
     *
     * @return array<string, mixed>
     */
    public static function context(AutomationRun $run): array
    {
        $context = array_filter(['lot' => $run->lot, 'order' => $run->order]);

        if ($run->automation->key === 'payment_reminder' && $run->order?->payment_deadline_at) {
            $context['resterend'] = now()->diffForHumans($run->order->payment_deadline_at, ['syntax' => CarbonInterface::DIFF_ABSOLUTE]);
        }

        if ($run->automation->key === 'draft_unfinished' && $run->lot !== null) {
            $issues = array_values(array_map(fn ($issue) => (string) ($issue['message'] ?? $issue), (array) ($run->lot->screening['issues'] ?? [])));
            $context['aantal'] = (string) max(1, count($issues));
            $context['taken'] = $issues === [] ? 'Controleer foto\'s, prijs, transport en documenten; dan kan hij live.' : implode(' ', $issues);
        }

        if ($run->automation->key === 'dormant_buyer') {
            $context['zoekvraag'] = (string) ($run->user->savedSearches()->latest('id')->value('name') ?? 'zakelijke goederen');
        }

        return $context;
    }
}
