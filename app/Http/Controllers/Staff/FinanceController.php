<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Admin\Ledger;
use App\Admin\Reports;
use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Http\Controllers\Controller;
use App\Models\AdminAccessLog;
use App\Models\CostEntry;
use App\Models\MetricSnapshot;
use App\Models\Order;
use App\Models\Payout;
use App\Notifications\MoneyNeedsAttention;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Financiën (control center): van omzet naar winst, geld van klanten dat
 * bij de betaalpartij staat, vastgezette uitbetalingen en de kosten uit de
 * boekhouding. Alleen Eigenaar en Financiën.
 */
final class FinanceController extends Controller
{
    public function index(Request $request, Ledger $ledger): View
    {
        $month = CarbonImmutable::parse((string) $request->query('maand', now()->format('Y-m')).'-01');
        $from = $month->startOfMonth();
        $to = $month->endOfMonth();

        $breakdown = $ledger->breakdown($from, $to);
        $booked = CostEntry::query()->where('period', $from->toDateString())->get();
        $revenue = $ledger->platformRevenueCents($from, $to);
        $costsTotal = $ledger->paymentCostCents($from, $to) + (int) $booked->sum('amount_cents');
        $volume = (int) Order::query()->whereBetween('paid_at', [$from, $to])->sum('hammer_cents');
        $transactions = (int) Order::query()->whereBetween('paid_at', [$from, $to])->count();

        return view('admin.finance', [
            'month' => $month,
            'months' => collect(range(0, 11))->map(fn (int $offset) => CarbonImmutable::now()->subMonths($offset)->startOfMonth()),
            'volume' => $volume,
            'transactions' => $transactions,
            'revenue' => $revenue,
            'costsTotal' => $costsTotal,
            'profit' => $revenue - $costsTotal,
            'lines' => [
                ['label' => __('admin.ledger.fee'), 'cents' => $breakdown[Ledger::FEE] ?? 0],
                ['label' => __('admin.ledger.admin_fee'), 'cents' => $breakdown[Ledger::ADMIN_FEE] ?? 0],
                ['label' => __('admin.ledger.subscription'), 'cents' => $breakdown[Ledger::SUBSCRIPTION] ?? 0],
                ['label' => __('admin.ledger.promotion'), 'cents' => $breakdown[Ledger::PROMOTION] ?? 0],
                ['label' => __('admin.ledger.refund'), 'cents' => $breakdown[Ledger::REFUND] ?? 0],
            ],
            'costLines' => [
                ['label' => __('admin.costs.payment'), 'cents' => -$ledger->paymentCostCents($from, $to), 'source' => 'betaalpartij'],
                ...collect(CostEntry::CATEGORIES)->reject(fn ($category) => $category === 'payment')->map(fn ($category) => [
                    'label' => __('admin.costs.'.$category),
                    'cents' => -(int) $booked->where('category', $category)->sum('amount_cents'),
                    'source' => 'boekhouding',
                ])->all(),
            ],
            'customerMoney' => [
                'held' => (int) Order::query()->whereIn('status', [OrderStatus::Paid, OrderStatus::ReadyForPickup, OrderStatus::Shipped])->sum('hammer_cents'),
                'releasable' => (int) Payout::query()->where('status', PayoutStatus::Held)->sum('amount_cents'),
                'disputed' => (int) Order::query()->where('status', OrderStatus::Disputed)->sum('hammer_cents'),
                'paid_out' => (int) Payout::query()->where('status', PayoutStatus::Paid)->whereBetween('released_at', [$from, $to])->sum('amount_cents'),
            ],
            'reconciliation' => (int) (MetricSnapshot::query()->where('metric', 'reconciliation_diff_cents')->latest('date')->value('value') ?? 0),
            'blockedPayouts' => Payout::query()->with(['seller.company', 'order'])->where('status', PayoutStatus::Failed)->orWhereNotNull('blocked_reason')->whereIn('status', [PayoutStatus::Failed, PayoutStatus::Held, PayoutStatus::Scheduled])->get(),
            'costCategories' => CostEntry::CATEGORIES,
        ]);
    }

    public function storeCost(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', Rule::in(CostEntry::CATEGORIES)],
            'amount' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'period' => ['required', 'date_format:Y-m'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        CostEntry::create([
            'category' => $validated['category'],
            'amount_cents' => (int) round((float) $validated['amount'] * 100),
            'period' => $validated['period'].'-01',
            'source' => 'manual',
            'note' => $validated['note'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.finance', ['maand' => $validated['period']])->with('status', 'Kostenpost geboekt.');
    }

    /**
     * De verkoper vragen om een nieuw rekeningnummer bij een vastgezette
     * uitbetaling.
     */
    public function requestIban(Request $request, Payout $payout): RedirectResponse
    {
        $seller = $payout->seller;

        $seller?->notify(new MoneyNeedsAttention(
            'Je uitbetaling staat vast',
            ['Je uitbetaling van '.Money::format($payout->amount_cents).' staat vast: '.($payout->blocked_reason ?? $payout->failure_reason ?? 'de rekeninggegevens kloppen niet met de KVK-registratie').'.', 'Controleer het rekeningnummer in je organisatie-instellingen; dan gaat de uitbetaling alsnog.'],
            route('organisation.show'),
        ));

        return back()->with('status', 'De verkoper heeft een verzoek gekregen.');
    }

    public function export(Request $request, Reports $reports): RedirectResponse
    {
        $month = CarbonImmutable::parse((string) $request->query('maand', now()->format('Y-m')).'-01');
        $path = $reports->store('revenue', $month->startOfMonth(), $month->endOfMonth());

        AdminAccessLog::record($request->user(), 'export', 'revenue:'.$month->format('Y-m'), null, $request->ip());

        return redirect()->route('admin.finance', ['maand' => $month->format('Y-m')])->with('status', 'Export staat klaar.')->with('export_url', $reports->signedUrl($path));
    }
}
