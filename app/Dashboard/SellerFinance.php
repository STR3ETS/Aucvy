<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Support\Collection;

/**
 * Het financiële overzicht van een verkoper (briefing sectie 4: verkopen,
 * fees, payoutstatus). Per verkoop het hamerbedrag, de succesfee met btw
 * en wat er uitbetaald is of wordt; totalen per maand. Facturen zijn een
 * besluit (wie factureert aan wie); dit is de afrekening per verkoop.
 *
 * @phpstan-type Line array{order: Order, hammer_cents: int, fee_cents: int, fee_vat_cents: int, payout_cents: int, payout_status: string, month: string}
 */
final class SellerFinance
{
    /**
     * @return Collection<int, Line>
     */
    public function lines(User $seller): Collection
    {
        return Order::query()
            ->whereIn('seller_id', $seller->financeSellerIds())
            ->whereNotIn('status', [OrderStatus::AwaitingPayment, OrderStatus::Cancelled])
            ->with(['lot:id,title,public_id', 'payout'])
            ->latest('id')
            ->get()
            ->map(function (Order $order) use ($seller): array {
                $feeCents = $order->payout?->fee_cents ?? Money::payoutFor($order->hammer_cents, $seller->company)->feeCents;
                $feeVatCents = Money::percentageOf($feeCents, Money::vatRatePercent());

                return [
                    'order' => $order,
                    'hammer_cents' => $order->hammer_cents,
                    'fee_cents' => $feeCents,
                    'fee_vat_cents' => $feeVatCents,
                    'payout_cents' => $order->payout?->amount_cents ?? ($order->hammer_cents - $feeCents),
                    'payout_status' => $order->payout?->status->label() ?? 'Nog niet ingepland',
                    'month' => $order->created_at->timezone(config('aucvy.display_timezone'))->format('Y-m'),
                ];
            });
    }

    /**
     * @param  Collection<int, Line>  $lines
     * @return array{sales: int, hammer_cents: int, fee_cents: int, fee_vat_cents: int, payout_cents: int}
     */
    public function totals(Collection $lines): array
    {
        return [
            'sales' => $lines->count(),
            'hammer_cents' => (int) $lines->sum('hammer_cents'),
            'fee_cents' => (int) $lines->sum('fee_cents'),
            'fee_vat_cents' => (int) $lines->sum('fee_vat_cents'),
            'payout_cents' => (int) $lines->sum('payout_cents'),
        ];
    }

    /**
     * Per maand, nieuwste eerst.
     *
     * @param  Collection<int, Line>  $lines
     * @return Collection<string, array{sales: int, hammer_cents: int, fee_cents: int, fee_vat_cents: int, payout_cents: int}>
     */
    public function perMonth(Collection $lines): Collection
    {
        return $lines->groupBy('month')->sortKeysDesc()->map(fn (Collection $group): array => $this->totals($group));
    }

    /**
     * De regels als csv, met puntkomma en komma als decimaalteken: wat een
     * Nederlandse spreadsheet direct opent.
     *
     * @param  Collection<int, Line>  $lines
     */
    public function csv(Collection $lines): string
    {
        $rows = [['Bestelnummer', 'Datum', 'Kavel', 'Hamerbedrag', 'Succesfee', 'Btw over fee', 'Uitbetaling', 'Status uitbetaling']];

        foreach ($lines as $line) {
            $rows[] = [
                $line['order']->order_number,
                $line['order']->created_at->timezone(config('aucvy.display_timezone'))->format('d-m-Y'),
                $line['order']->lot?->title ?? '',
                $this->amount($line['hammer_cents']),
                $this->amount($line['fee_cents']),
                $this->amount($line['fee_vat_cents']),
                $this->amount($line['payout_cents']),
                $line['payout_status'],
            ];
        }

        return implode("\r\n", array_map(fn (array $row): string => implode(';', array_map(
            fn (string $cell): string => '"'.str_replace('"', '""', $this->neutralise($cell)).'"',
            $row,
        )), $rows))."\r\n";
    }

    private function amount(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '');
    }

    /**
     * Een kaveltitel als "=HYPERLINK(...)" mag in een spreadsheet geen
     * formule worden: een collega-redacteur kiest de titel, de financiële
     * gebruiker opent het bestand. Bedragen blijven bedragen.
     */
    private function neutralise(string $cell): string
    {
        if (preg_match('/^-?\d+(,\d+)?$/', $cell) === 1) {
            return $cell;
        }

        return preg_match('/^[=+\-@\t\r]/', $cell) === 1 ? "'".$cell : $cell;
    }
}
