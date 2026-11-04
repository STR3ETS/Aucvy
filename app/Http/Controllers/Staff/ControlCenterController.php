<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Admin\Ledger;
use App\Admin\StaffRole;
use App\Enums\LotStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Bid;
use App\Models\CostEntry;
use App\Models\CustomerTask;
use App\Models\Dispute;
use App\Models\Lot;
use App\Models\MetricSnapshot;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\Health\Health;
use App\Support\Health\IvySignals;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Het control center op /admin (briefing nieuwe functies): geld- en
 * werkcijfers volgens de definities, de grafiek omzet en kosten, "Wacht op
 * jou" per specialist, systemen en de lijsten onderaan. Geldcijfers alleen
 * voor Eigenaar en Financiën.
 */
final class ControlCenterController extends Controller
{
    public function index(Request $request, Ledger $ledger, Health $health, IvySignals $signals): View
    {
        $role = StaffRole::of($request->user());
        $period = (string) $request->query('periode', 'maand');
        [$from, $to, $previousFrom, $previousTo, $label] = $this->period($period);

        $money = $role?->can('money') ? $this->moneyTiles($ledger, $from, $to, $previousFrom, $previousTo) : null;

        return view('admin.control-center', [
            'role' => $role,
            'period' => $period,
            'periodLabel' => $label,
            'money' => $money,
            'work' => $this->workTiles(),
            'chart' => $role?->can('money') ? $this->monthlyChart($ledger) : null,
            'bidsPerHour' => $this->bidsPerHour(),
            'funnel' => $this->funnel($from, $to),
            'tasks' => CustomerTask::query()->open()->with('customer')->orderBy('due_at')->limit(12)->get(),
            'signals' => $signals->all(),
            'systems' => $health->checks(),
            'latestOrders' => Order::query()->with(['lot', 'buyer', 'seller.company'])->latest('id')->limit(6)->get(),
            'pendingLots' => Lot::query()->where('status', LotStatus::PendingReview)->with(['seller', 'category'])->oldest('submitted_at')->limit(6)->get(),
            'newCustomers' => User::query()->with('company')->whereNotNull('email_verified_at')->latest('id')->limit(6)->get(),
            'oldestTickets' => SupportTicket::query()->open()->with('user')->oldest('created_at')->limit(6)->get(),
            'topSellers' => $role?->can('money') ? $this->topSellers() : collect(),
            'ticketTopics' => $this->ticketTopics($from, $to),
        ]);
    }

    public function completeTask(Request $request, CustomerTask $task): RedirectResponse
    {
        $task->forceFill(['done_at' => now(), 'done_by' => $request->user()->id])->save();

        return back()->with('status', 'Afgehandeld.');
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: CarbonImmutable, 3: CarbonImmutable, 4: string}
     */
    private function period(string $period): array
    {
        $now = CarbonImmutable::now();

        return match ($period) {
            'vorige' => [$now->subMonth()->startOfMonth(), $now->subMonth()->endOfMonth(), $now->subMonths(2)->startOfMonth(), $now->subMonths(2)->endOfMonth(), $now->subMonth()->isoFormat('MMMM YYYY')],
            'jaar' => [$now->startOfYear(), $now, $now->subYear()->startOfYear(), $now->subYear(), 'Dit jaar'],
            default => [$now->startOfMonth(), $now, $now->subMonth()->startOfMonth(), $now->subMonth(), $now->isoFormat('MMMM YYYY')],
        };
    }

    /**
     * @return array<string, array{label: string, value: string, sub: string, trend: float|null, url: string, definition: string}>
     */
    private function moneyTiles(Ledger $ledger, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $previousFrom, CarbonImmutable $previousTo): array
    {
        $paid = fn (CarbonImmutable $a, CarbonImmutable $b) => Order::query()->whereBetween('paid_at', [$a, $b]);
        $volume = (int) $paid($from, $to)->sum('hammer_cents');
        $previousVolume = (int) $paid($previousFrom, $previousTo)->sum('hammer_cents');
        $count = (int) $paid($from, $to)->count();
        $previousCount = (int) $paid($previousFrom, $previousTo)->count();
        $revenue = $ledger->platformRevenueCents($from, $to);
        $previousRevenue = $ledger->platformRevenueCents($previousFrom, $previousTo);
        $costs = $this->costs($ledger, $from, $to);
        $previousCosts = $this->costs($ledger, $previousFrom, $previousTo);
        $profit = $revenue - $costs;
        $previousProfit = $previousRevenue - $previousCosts;

        return [
            'volume' => ['label' => 'Omzet van transacties', 'value' => Money::format($volume), 'sub' => 'wat kopers betaalden aan verkopers', 'trend' => $this->trend($volume, $previousVolume), 'url' => route('admin.transactions'), 'definition' => __('admin.definitions.volume')],
            'transactions' => ['label' => 'Transacties', 'value' => (string) $count, 'sub' => $count > 0 ? 'gemiddeld '.Money::format(intdiv($volume, $count)).' per bestelling' : 'nog geen betaalde bestellingen', 'trend' => $this->trend($count, $previousCount), 'url' => route('admin.transactions'), 'definition' => __('admin.definitions.transactions')],
            'revenue' => ['label' => 'Platformomzet Aucvy', 'value' => Money::format($revenue), 'sub' => 'succesfee, administratiekosten, promotie', 'trend' => $this->trend($revenue, $previousRevenue), 'url' => route('admin.finance'), 'definition' => __('admin.definitions.revenue')],
            'profit' => ['label' => 'Winst Aucvy', 'value' => Money::format($profit), 'sub' => $revenue > 0 ? 'marge '.(int) round($profit / $revenue * 100).'%, vóór belasting' : 'vóór belasting', 'trend' => $this->trend($profit, $previousProfit), 'url' => route('admin.finance'), 'definition' => __('admin.definitions.profit')],
        ];
    }

    private function costs(Ledger $ledger, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $booked = (int) CostEntry::query()->whereBetween('period', [$from->startOfMonth()->toDateString(), $to->toDateString()])->sum('amount_cents');

        return $ledger->paymentCostCents($from, $to) + $booked;
    }

    /**
     * @return array<string, array{label: string, value: string, sub: string, chip: string|null, url: string, definition: string}>
     */
    private function workTiles(): array
    {
        $live = Lot::query()->whereIn('status', [LotStatus::Live, LotStatus::Closing])->count();
        $pending = Lot::query()->where('status', LotStatus::PendingReview)->count();
        $users = User::query()->whereNotNull('email_verified_at')->count();
        $business = User::query()->whereNotNull('email_verified_at')->whereNotNull('company_id')->count();
        $newUsers = User::query()->whereNotNull('email_verified_at')->where('created_at', '>=', now()->startOfMonth())->count();
        $openTickets = SupportTicket::query()->open()->count();
        $lateTickets = SupportTicket::query()->late()->count();
        $complaints = SupportTicket::query()->open()->where('type', SupportTicket::TYPE_COMPLAINT)->count() + Dispute::query()->whereNull('resolved_at')->count();
        $dueToday = Dispute::query()->whereNull('resolved_at')->whereDate('sla_due_at', today())->count();

        return [
            'lots' => ['label' => 'Kavels live', 'value' => (string) $live, 'sub' => "{$pending} wachten op controle", 'chip' => $pending > 0 ? "{$pending} te doen" : null, 'url' => route('admin.moderation'), 'definition' => __('admin.definitions.lots_live')],
            'users' => ['label' => 'Gebruikers', 'value' => number_format($users, 0, ',', '.'), 'sub' => "{$business} zakelijk · {$newUsers} nieuw deze maand", 'chip' => null, 'url' => route('admin.customers'), 'definition' => __('admin.definitions.users')],
            'tickets' => ['label' => 'Open tickets', 'value' => (string) $openTickets, 'sub' => $this->firstResponseLabel(), 'chip' => $lateTickets > 0 ? "{$lateTickets} te laat" : null, 'url' => route('admin.service'), 'definition' => __('admin.definitions.tickets')],
            'complaints' => ['label' => 'Klachten en geschillen', 'value' => (string) $complaints, 'sub' => $dueToday > 0 ? "{$dueToday} hebben vandaag een deadline" : 'geen deadline vandaag', 'chip' => $dueToday > 0 ? "{$dueToday} vandaag" : null, 'url' => route('staff.disputes.index'), 'definition' => __('admin.definitions.complaints')],
        ];
    }

    private function firstResponseLabel(): string
    {
        $minutes = SupportTicket::query()->whereNotNull('first_response_at')->where('created_at', '>=', now()->subDays(30))->get()
            ->avg(fn (SupportTicket $ticket) => $ticket->created_at->diffInMinutes($ticket->first_response_at));

        if ($minutes === null) {
            return 'nog geen reactietijd gemeten';
        }

        return sprintf('eerste reactie gemiddeld %d u %02d min', intdiv((int) $minutes, 60), (int) $minutes % 60);
    }

    /**
     * @return list<array{month: string, revenue_cents: int, costs_cents: int}>
     */
    private function monthlyChart(Ledger $ledger): array
    {
        $rows = [];

        for ($offset = 11; $offset >= 0; $offset--) {
            $month = CarbonImmutable::now()->subMonths($offset);
            $from = $month->startOfMonth();
            $to = $month->endOfMonth();
            $rows[] = ['month' => $month->isoFormat('MMM'), 'revenue_cents' => $ledger->platformRevenueCents($from, $to), 'costs_cents' => $this->costs($ledger, $from, $to)];
        }

        return $rows;
    }

    /**
     * @return list<int> 24 waarden, één per uur, laatste 24 uur
     */
    private function bidsPerHour(): array
    {
        $counts = Bid::query()->where('placed_at', '>=', now()->subDay())->get(['placed_at'])
            ->groupBy(fn (Bid $bid) => $bid->placed_at->timezone(config('aucvy.display_timezone'))->format('H'))
            ->map->count();

        $hours = [];

        for ($i = 23; $i >= 0; $i--) {
            $hour = now()->subHours($i)->timezone(config('aucvy.display_timezone'))->format('H');
            $hours[] = ['hour' => $hour, 'count' => (int) ($counts[$hour] ?? 0)];
        }

        return $hours;
    }

    /**
     * Van geplaatste kavel naar uitbetaling, in de periode.
     *
     * @return list<array{label: string, value: int}>
     */
    private function funnel(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $submitted = Lot::query()->whereBetween('submitted_at', [$from, $to]);
        $paid = Order::query()->whereBetween('paid_at', [$from, $to]);

        return [
            ['label' => 'Ingediend', 'value' => (clone $submitted)->count()],
            ['label' => 'Online gekomen', 'value' => Lot::query()->whereBetween('published_at', [$from, $to])->count()],
            ['label' => 'Bod ontvangen', 'value' => Lot::query()->whereBetween('published_at', [$from, $to])->whereHas('auction', fn ($query) => $query->where('bid_count', '>', 0))->count()],
            ['label' => 'Verkocht', 'value' => Order::query()->whereBetween('created_at', [$from, $to])->count()],
            ['label' => 'Betaald', 'value' => (clone $paid)->count()],
            ['label' => 'Uitbetaald', 'value' => (clone $paid)->whereHas('payout', fn ($query) => $query->whereIn('status', ['released', 'paid']))->count()],
        ];
    }

    /**
     * @return Collection<int, array{name: string, lots: int, revenue_cents: int, user: User}>
     */
    private function topSellers(): Collection
    {
        return Order::query()->with(['seller.company'])->whereIn('status', [OrderStatus::Paid, OrderStatus::ReadyForPickup, OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::Completed])
            ->get()->groupBy('seller_id')
            ->map(fn ($orders) => [
                'user' => $orders->first()->seller,
                'name' => $orders->first()->seller?->company?->name ?? $orders->first()->seller?->name ?? 'Onbekend',
                'lots' => $orders->count(),
                'revenue_cents' => (int) $orders->sum(fn (Order $order) => $order->payout?->fee_cents ?? 0),
            ])
            ->sortByDesc('revenue_cents')->take(3)->values();
    }

    /**
     * @return Collection<string, int>
     */
    private function ticketTopics(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $topics = ['Ophalen en transport' => ['ophalen', 'transport', 'bezorg', 'afhalen'], 'Betaling' => ['betaal', 'betaling', 'factuur', 'ideal'], 'Staat van de kavel' => ['staat', 'defect', 'kapot', 'beschadig'], 'Account' => ['account', 'inlog', 'wachtwoord', 'verific']];
        $tickets = SupportTicket::query()->whereBetween('created_at', [$from, $to])->get(['subject', 'message']);
        $counts = collect($topics)->map(fn () => 0);

        foreach ($tickets as $ticket) {
            $text = mb_strtolower($ticket->subject.' '.$ticket->message);

            foreach ($topics as $label => $words) {
                foreach ($words as $word) {
                    if (str_contains($text, $word)) {
                        $counts[$label]++;

                        continue 2;
                    }
                }
            }
        }

        return $counts->sortDesc();
    }

    private function trend(int $now, int $before): ?float
    {
        return $before === 0 ? null : round(($now - $before) / abs($before) * 100, 1);
    }

    /**
     * @return array<string, int>
     */
    public static function metric(string $metric, int $days = 30): array
    {
        return MetricSnapshot::query()->where('metric', $metric)->where('date', '>=', now()->subDays($days))->orderBy('date')->pluck('value', 'date')->map(fn ($value) => (int) $value)->all();
    }
}
