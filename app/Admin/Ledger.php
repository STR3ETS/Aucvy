<?php

declare(strict_types=1);

namespace App\Admin;

use App\Models\LedgerEntry;
use App\Models\Order;
use App\Support\Money\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Eén grootboek (briefing nieuwe functies, "Hoe de cijfers kloppen"): elke
 * geldgebeurtenis van het platform is een regel. Omzet wordt nooit uit losse
 * bestellingen in het dashboard gerekend. Idempotent op source_ref, dus
 * dezelfde webhook twee keer verwerken geeft één boeking.
 *
 * Het geld van de transacties zelf (het hamerbedrag) is niet van Aucvy en
 * staat hier niet in; dat heeft een eigen blok.
 */
final class Ledger
{
    public const string FEE = 'fee';

    public const string ADMIN_FEE = 'admin_fee';

    public const string REFUND = 'refund';

    public const string PAYMENT_COST = 'payment_cost';

    public const string SUBSCRIPTION = 'subscription';

    public const string PROMOTION = 'promotion';

    /** @var list<string> Wat platformomzet is. */
    public const REVENUE = [self::FEE, self::ADMIN_FEE, self::SUBSCRIPTION, self::PROMOTION, self::REFUND];

    /**
     * Een betaalde order: de succesfee, de administratiekosten en de
     * geschatte betaalkosten. De echte betaalkosten komen straks uit de
     * Stripe-webhook, op hetzelfde source_ref.
     */
    public function recordPaidOrder(Order $order, ?string $externalId = null): void
    {
        $order->loadMissing(['payout', 'seller.company']);
        $feeCents = (int) ($order->payout?->fee_cents ?? Money::payoutFor($order->hammer_cents, $order->seller?->company)->feeCents);
        $adminFeeCents = (int) ($order->getAttributes()['admin_fee_cents'] ?? 0);
        $companyId = $order->seller?->company_id;
        $at = $order->paid_at ?? now();

        $this->record(self::FEE, $feeCents, "fee:order:{$order->id}", $order->id, $companyId, $at, Money::percentageOf($feeCents, Money::vatRatePercent()), $externalId);

        if ($adminFeeCents > 0) {
            $this->record(self::ADMIN_FEE, $adminFeeCents, "admin_fee:order:{$order->id}", $order->id, $companyId, $at, Money::percentageOf($adminFeeCents, Money::vatRatePercent()), $externalId);
        }

        $paymentCost = Money::percentageOf($order->total_cents, (float) config('aucvy.ledger.payment_cost_percent', 1.4)) + (int) config('aucvy.ledger.payment_cost_fixed_cents', 25);
        $this->record(self::PAYMENT_COST, -$paymentCost, "payment_cost:order:{$order->id}", $order->id, $companyId, $at, 0, $externalId);
    }

    /**
     * Een terugbetaling verlaagt de platformomzet in de maand van de
     * terugbetaling, niet die van de verkoop.
     */
    public function recordRefund(Order $order, int $amountCents, string $ref, ?CarbonInterface $at = null): void
    {
        $this->record(self::REFUND, -abs($amountCents), "refund:{$ref}", $order->id, $order->seller?->company_id, $at ?? now(), 0);
    }

    public function record(string $type, int $amountCents, string $sourceRef, ?int $orderId, ?int $companyId, CarbonInterface $at, int $vatCents = 0, ?string $externalId = null): ?LedgerEntry
    {
        if (LedgerEntry::query()->where('source_ref', $sourceRef)->exists()) {
            return null;
        }

        return LedgerEntry::create([
            'order_id' => $orderId,
            'company_id' => $companyId,
            'type' => $type,
            'amount_cents' => $amountCents,
            'vat_cents' => $vatCents,
            'external_id' => $externalId,
            'source_ref' => $sourceRef,
            'occurred_at' => $at,
            'created_at' => now(),
        ]);
    }

    /**
     * Platformomzet in een periode: fee, abonnementen, promotie, min
     * terugbetaald. Exclusief btw.
     */
    public function platformRevenueCents(CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) LedgerEntry::query()->whereIn('type', self::REVENUE)->whereBetween('occurred_at', [$from, $to])->sum('amount_cents');
    }

    public function paymentCostCents(CarbonInterface $from, CarbonInterface $to): int
    {
        return abs((int) LedgerEntry::query()->where('type', self::PAYMENT_COST)->whereBetween('occurred_at', [$from, $to])->sum('amount_cents'));
    }

    /**
     * @return array<string, int> type => centen
     */
    public function breakdown(CarbonInterface $from, CarbonInterface $to): array
    {
        return LedgerEntry::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->select('type', DB::raw('sum(amount_cents) as total'))
            ->groupBy('type')
            ->pluck('total', 'type')
            ->map(fn ($total) => (int) $total)
            ->all();
    }
}
