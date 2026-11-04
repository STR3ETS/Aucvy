<?php

declare(strict_types=1);

namespace App\Orders\Invoicing;

use App\Enums\VatRegime;
use App\Models\Company;
use App\Models\Dispute;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Order;
use App\Models\User;
use App\Support\Money\Money;
use App\Support\PlatformFacts;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Maakt bij een betaalde order drie facturen (voorstel hoofdbriefing p. 7,
 * te toetsen door de boekhouder): de kavel van verkoper aan koper
 * (opgemaakt door Aucvy namens de verkoper), de administratiekosten van
 * Aucvy aan de koper en de succesfee van Aucvy aan de verkoper. Een
 * prijscorrectie na een geschil wordt een creditnota op de kavelfactuur.
 * Margeregeling: geen btw, wel de vermelding "bijzondere regeling -
 * gebruikte goederen". Idempotent per order en soort.
 */
final class Invoices
{
    /**
     * @return Collection<int, Invoice>
     */
    public function forPaidOrder(Order $order): Collection
    {
        $order->loadMissing(['lot', 'buyer.company', 'seller.company', 'payout']);

        return DB::transaction(function () use ($order): Collection {
            $invoices = collect();

            $lotInvoice = $this->existing($order, Invoice::LOT) ?? $this->lotInvoice($order);
            $invoices->push($lotInvoice);

            $adminFee = (int) ($order->getAttributes()['admin_fee_cents'] ?? 0);

            if ($adminFee > 0) {
                $invoices->push($this->existing($order, Invoice::ADMIN_FEE) ?? $this->adminFeeInvoice($order, $adminFee));
            }

            $feeCents = (int) ($order->payout?->fee_cents ?? Money::payoutFor($order->hammer_cents, $order->seller?->company)->feeCents);

            if ($feeCents > 0) {
                $invoices->push($this->existing($order, Invoice::FEE) ?? $this->feeInvoice($order, $feeCents));
            }

            return $invoices;
        });
    }

    public function creditNote(Dispute $dispute): ?Invoice
    {
        $order = $dispute->order;
        $correction = (int) ($dispute->correction_cents ?? 0);

        if ($order === null || $correction <= 0) {
            return null;
        }

        $order->loadMissing(['lot', 'buyer.company', 'seller.company']);

        return DB::transaction(function () use ($order, $correction, $dispute): Invoice {
            $lotInvoice = $this->existing($order, Invoice::LOT) ?? $this->lotInvoice($order);

            $existing = Invoice::query()->where('order_id', $order->id)->where('kind', Invoice::CREDIT)->where('credits_invoice_id', $lotInvoice->id)->first();

            if ($existing !== null) {
                return $existing;
            }

            $regime = $order->vat_regime;
            $vat = $regime->isDeductible() ? Money::percentageOf($correction, Money::vatRatePercent()) : 0;

            return $this->store(
                kind: Invoice::CREDIT,
                order: $order,
                issuerType: Invoice::ISSUER_SELLER,
                issuerCompany: $order->seller?->company,
                recipient: $order->buyer,
                regime: $regime,
                lines: [$this->line('Creditnota op factuur '.$lotInvoice->number.': prijscorrectie na geschil', 1, -$correction, $regime)],
                subtotal: -$correction,
                vat: -$vat,
                note: 'Geschil '.$dispute->id.'. Het bedrag wordt terugbetaald via de betaalpartij.',
                credits: $lotInvoice,
            );
        });
    }

    private function lotInvoice(Order $order): Invoice
    {
        $lot = $order->lot;
        $regime = $order->vat_regime;
        $quantity = max(1, (int) ($lot?->quantity ?? 1));
        $unit = (string) ($lot?->unit ?? 'stuk');
        $base = $order->hammer_cents + $order->transport_cents;
        $vat = $regime->isDeductible() ? Money::percentageOf($base, Money::vatRatePercent()) : 0;

        $lines = [$this->line(sprintf('Kavel %s: %s (%d %s)', $lot?->public_id, $lot?->title, $quantity, $unit), $quantity, $order->hammer_cents, $regime)];

        if ($order->transport_cents > 0) {
            $lines[] = $this->line('Transport', 1, $order->transport_cents, $regime);
        }

        return $this->store(
            kind: Invoice::LOT,
            order: $order,
            issuerType: Invoice::ISSUER_SELLER,
            issuerCompany: $order->seller?->company,
            recipient: $order->buyer,
            regime: $regime,
            lines: $lines,
            subtotal: $base,
            vat: $vat,
            note: $regime->isDeductible() ? null : 'Bijzondere regeling - gebruikte goederen.',
        );
    }

    private function adminFeeInvoice(Order $order, int $adminFee): Invoice
    {
        $vat = Money::percentageOf($adminFee, Money::vatRatePercent());

        return $this->store(
            kind: Invoice::ADMIN_FEE,
            order: $order,
            issuerType: Invoice::ISSUER_AUCVY,
            issuerCompany: null,
            recipient: $order->buyer,
            regime: VatRegime::Vat21,
            lines: [$this->line('Administratiekosten bestelling '.$order->order_number, 1, $adminFee, VatRegime::Vat21)],
            subtotal: $adminFee,
            vat: $vat,
            note: null,
        );
    }

    private function feeInvoice(Order $order, int $feeCents): Invoice
    {
        $vat = Money::percentageOf($feeCents, Money::vatRatePercent());
        $percentage = PlatformFacts::feePercentageFor($order->seller?->company);

        return $this->store(
            kind: Invoice::FEE,
            order: $order,
            issuerType: Invoice::ISSUER_AUCVY,
            issuerCompany: null,
            recipient: $order->seller,
            regime: VatRegime::Vat21,
            lines: [$this->line(sprintf('Succesfee %s%% over %s, kavel %s', rtrim(rtrim(number_format($percentage, 1, ',', ''), '0'), ','), Money::format($order->hammer_cents), $order->lot?->public_id), 1, $feeCents, VatRegime::Vat21)],
            subtotal: $feeCents,
            vat: $vat,
            note: 'Verrekend met de uitbetaling van bestelling '.$order->order_number.'.',
        );
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function store(string $kind, Order $order, string $issuerType, ?Company $issuerCompany, ?User $recipient, VatRegime $regime, array $lines, int $subtotal, int $vat, ?string $note, ?Invoice $credits = null): Invoice
    {
        $series = $issuerType === Invoice::ISSUER_AUCVY ? 'F-'.now()->year : 'V'.($issuerCompany?->id ?? (int) $order->seller_id).'-'.now()->year;
        $sequence = ((int) Invoice::query()->where('series', $series)->lockForUpdate()->max('sequence')) + 1;

        return Invoice::create([
            'number' => $series.'-'.str_pad((string) $sequence, $issuerType === Invoice::ISSUER_AUCVY ? 5 : 4, '0', STR_PAD_LEFT),
            'series' => $series,
            'sequence' => $sequence,
            'kind' => $kind,
            'order_id' => $order->id,
            'issuer_type' => $issuerType,
            'issuer_company_id' => $issuerCompany?->id,
            'recipient_user_id' => $recipient?->id,
            'recipient_company_id' => $recipient?->company_id,
            'credits_invoice_id' => $credits?->id,
            'issued_at' => now()->toDateString(),
            'delivery_date' => ($order->delivered_at ?? $order->paid_at ?? now())->toDateString(),
            'vat_regime' => $regime,
            'parties' => [
                'issuer' => $issuerType === Invoice::ISSUER_AUCVY ? $this->aucvy() : $this->party($order->seller, $issuerCompany, 'Opgemaakt door Aucvy namens de verkoper'),
                'recipient' => $this->party($recipient, $recipient?->company),
            ],
            'lines' => $lines,
            'subtotal_cents' => $subtotal,
            'vat_cents' => $vat,
            'total_cents' => $subtotal + $vat,
            'note' => $note,
        ]);
    }

    /**
     * @return array{description: string, quantity: int, unit_cents: int, total_cents: int, vat_rate: float}
     */
    private function line(string $description, int $quantity, int $totalCents, VatRegime $regime): array
    {
        return [
            'description' => $description,
            'quantity' => $quantity,
            'unit_cents' => $quantity > 0 ? intdiv($totalCents, $quantity) : $totalCents,
            'total_cents' => $totalCents,
            'vat_rate' => $regime->isDeductible() ? Money::vatRatePercent() : 0.0,
        ];
    }

    /**
     * @return array{name: string, address: list<string>, kvk: string|null, vat: string|null, note: string|null}
     */
    private function party(?User $user, ?Company $company, ?string $note = null): array
    {
        $location = $company !== null
            ? Location::query()->where('company_id', $company->id)->orderBy('id')->first()
            : null;
        $location ??= $user !== null ? Location::query()->where('user_id', $user->id)->orderBy('id')->first() : null;

        return [
            'name' => $company?->name ?? $user?->name ?? 'Onbekend',
            'contact' => $company !== null ? $user?->name : null,
            'address' => $location !== null
                ? array_values(array_filter([trim($location->street.' '.$location->house_number), trim($location->postal_code.' '.$location->city), $location->country]))
                : ['Adres niet bekend; aan te vullen in het account'],
            'kvk' => $company?->kvk_number,
            'vat' => $company?->vat_number,
            'note' => $note,
        ];
    }

    /**
     * @return array{name: string, address: list<string>, kvk: string|null, vat: string|null, note: string|null}
     */
    private function aucvy(): array
    {
        $company = (array) config('aucvy.company', []);

        return [
            'name' => (string) ($company['name'] ?? 'Aucvy B.V.'),
            'contact' => null,
            'address' => array_values(array_filter([(string) ($company['address'] ?? ''), (string) ($company['postal_city'] ?? ''), 'Nederland'])),
            'kvk' => $company['kvk'] ?? null,
            'vat' => $company['vat'] ?? null,
            'note' => null,
        ];
    }

    private function existing(Order $order, string $kind): ?Invoice
    {
        return Invoice::query()->where('order_id', $order->id)->where('kind', $kind)->whereNull('credits_invoice_id')->first();
    }
}
