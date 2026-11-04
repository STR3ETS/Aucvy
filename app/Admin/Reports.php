<?php

declare(strict_types=1);

namespace App\Admin;

use App\Enums\OrderStatus;
use App\Models\CostEntry;
use App\Models\Dispute;
use App\Models\Lot;
use App\Models\Order;
use App\Models\Payout;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * De vaste rapporten van het control center, als csv. Een export loopt op
 * de wachtrij en geeft een link die na 24 uur verloopt; elke export staat
 * in admin_access_logs.
 */
final class Reports
{
    /** @var list<string> */
    public const KEYS = ['revenue', 'transactions', 'lots', 'customers', 'tickets', 'payouts'];

    public const int LINK_HOURS = 24;

    /**
     * @return array{columns: list<string>, rows: list<list<string|int|float|null>>}
     */
    public function build(string $key, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return match ($key) {
            'revenue' => $this->revenue($from, $to),
            'transactions' => $this->transactions($from, $to),
            'lots' => $this->lots($from, $to),
            'customers' => $this->customers($from, $to),
            'tickets' => $this->tickets($from, $to),
            'payouts' => $this->payouts($from, $to),
            default => throw new InvalidArgumentException("Onbekend rapport: {$key}"),
        };
    }

    public function toCsv(array $report): string
    {
        $lines = [implode(';', $report['columns'])];

        foreach ($report['rows'] as $row) {
            $lines[] = implode(';', array_map(fn ($value) => '"'.str_replace('"', '""', (string) $value).'"', $row));
        }

        return "\xEF\xBB\xBF".implode("\r\n", $lines)."\r\n";
    }

    /**
     * Schrijft het bestand en geeft het pad; de link maakt de controller.
     */
    public function store(string $key, CarbonImmutable $from, CarbonImmutable $to): string
    {
        $path = 'exports/rapport-'.$key.'-'.$from->format('Ymd').'-'.$to->format('Ymd').'-'.Str::lower(Str::random(8)).'.csv';

        Storage::disk('local')->put($path, $this->toCsv($this->build($key, $from, $to)));

        return $path;
    }

    public function signedUrl(string $path): string
    {
        return URL::temporarySignedRoute('admin.reports.download', now()->addHours(self::LINK_HOURS), ['path' => $path]);
    }

    private function revenue(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = [];
        $ledger = app(Ledger::class);

        for ($month = $from->startOfMonth(); $month->lessThanOrEqualTo($to); $month = $month->addMonth()) {
            $start = $month->startOfMonth();
            $end = $month->endOfMonth();
            $breakdown = $ledger->breakdown($start, $end);
            $revenue = $ledger->platformRevenueCents($start, $end);
            $costs = $ledger->paymentCostCents($start, $end) + (int) CostEntry::query()->where('period', $start->toDateString())->sum('amount_cents');

            $rows[] = [
                $month->format('Y-m'),
                (int) Order::query()->whereBetween('paid_at', [$start, $end])->count(),
                Money::format((int) Order::query()->whereBetween('paid_at', [$start, $end])->sum('hammer_cents')),
                Money::format($breakdown[Ledger::FEE] ?? 0),
                Money::format($breakdown[Ledger::ADMIN_FEE] ?? 0),
                Money::format($breakdown[Ledger::REFUND] ?? 0),
                Money::format($revenue),
                Money::format($costs),
                Money::format($revenue - $costs),
            ];
        }

        return ['columns' => ['Maand', 'Transacties', 'Omzet transacties', 'Succesfee', 'Administratiekosten', 'Terugbetaald', 'Platformomzet', 'Kosten', 'Winst vóór belasting'], 'rows' => $rows];
    }

    private function transactions(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = Order::query()->with(['lot', 'buyer', 'seller.company', 'payout'])->whereBetween('created_at', [$from, $to])->orderBy('id')->get()
            ->map(fn (Order $order) => [
                $order->order_number,
                $order->created_at->toDateString(),
                $order->lot?->title,
                $order->seller?->company?->name ?? $order->seller?->name,
                $order->buyer?->publicName(),
                $order->status->label(),
                Money::format($order->hammer_cents),
                Money::format((int) ($order->payout?->fee_cents ?? 0)),
                Money::format($order->total_cents),
            ])->all();

        return ['columns' => ['Bestelling', 'Datum', 'Kavel', 'Verkoper', 'Koper', 'Status', 'Hamerbedrag', 'Fee', 'Totaal koper'], 'rows' => $rows];
    }

    private function lots(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = Lot::query()->with(['category', 'seller.company', 'auction'])->whereBetween('created_at', [$from, $to])->orderBy('id')->get()
            ->map(fn (Lot $lot) => [
                $lot->public_id, $lot->title, $lot->category?->name, $lot->seller?->company?->name ?? $lot->seller?->name,
                $lot->status->label(), $lot->sale_type?->label(), $lot->auction ? Money::format($lot->auction->current_price_cents) : '', (int) ($lot->auction?->bid_count ?? 0),
                $lot->published_at?->toDateString(),
            ])->all();

        return ['columns' => ['Kavel', 'Titel', 'Categorie', 'Verkoper', 'Status', 'Verkoopvorm', 'Stand', 'Biedingen', 'Live sinds'], 'rows' => $rows];
    }

    private function customers(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = User::query()->with('company')->withCount(['lots', 'bids'])->whereBetween('created_at', [$from, $to])->orderBy('id')->get()
            ->map(fn (User $user) => [
                $user->id, $user->type?->label(), $user->company?->name, $user->kyc_status->label(), $user->created_at->toDateString(),
                $user->lots_count, $user->bids_count,
                Money::format((int) Order::query()->where('buyer_id', $user->id)->whereIn('status', [OrderStatus::Paid, OrderStatus::ReadyForPickup, OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::Completed])->sum('total_cents')),
            ])->all();

        return ['columns' => ['Id', 'Soort', 'Bedrijf', 'Verificatie', 'Sinds', 'Kavels', 'Biedingen', 'Gekocht'], 'rows' => $rows];
    }

    private function tickets(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = SupportTicket::query()->with('assignee')->whereBetween('created_at', [$from, $to])->orderBy('id')->get()
            ->map(fn (SupportTicket $ticket) => [
                $ticket->number, $ticket->created_at->toDateString(), $ticket->typeLabel(), $ticket->priorityLabel(), $ticket->subject, $ticket->status,
                $ticket->first_response_at ? $ticket->created_at->diffInMinutes($ticket->first_response_at) : '', $ticket->assignee?->name,
            ])->all();

        $disputes = Dispute::query()->with('order')->whereBetween('created_at', [$from, $to])->get()
            ->map(fn (Dispute $dispute) => ['G-'.$dispute->id, $dispute->created_at->toDateString(), 'Geschil', '', $dispute->reason, $dispute->status->value, '', ''])->all();

        return ['columns' => ['Nummer', 'Datum', 'Type', 'Prioriteit', 'Onderwerp', 'Status', 'Eerste reactie (min)', 'Toegewezen'], 'rows' => [...$rows, ...$disputes]];
    }

    private function payouts(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = Payout::query()->with(['order', 'seller.company'])->whereBetween('created_at', [$from, $to])->orderBy('id')->get()
            ->map(fn (Payout $payout) => [
                $payout->order?->order_number, $payout->seller?->company?->name ?? $payout->seller?->name, Money::format($payout->amount_cents), Money::format($payout->fee_cents),
                $payout->status->label(), $payout->hold_until?->toDateTimeString(), $payout->released_at?->toDateTimeString(), $payout->blocked_reason,
            ])->all();

        return ['columns' => ['Bestelling', 'Verkoper', 'Bedrag', 'Fee', 'Status', 'Vast tot', 'Vrijgegeven', 'Blokkade'], 'rows' => $rows];
    }
}
