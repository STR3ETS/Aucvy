<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Models\CustomerTask;
use App\Models\MetricSnapshot;
use App\Models\Payment;
use App\Models\Payout;
use App\Notifications\MoneyNeedsAttention;
use App\Support\Money\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Vergelijkt dagelijks wat er bij de betaalpartij staat met de eigen
 * administratie. Een verschil wordt een taak voor Finn en een melding.
 *
 * Zonder Stripe Connect is "de betaalpartij" de som van de betalingen en
 * uitbetalingen die het platform zelf kent; de echte saldovergelijking
 * komt met de Stripe-koppeling, op dezelfde plek.
 */
final class ReconcileStripeBalance extends Command
{
    protected $signature = 'aucvy:reconcile-balance';

    protected $description = 'Vergelijkt het saldo bij de betaalpartij met de administratie';

    public function handle(): int
    {
        $received = (int) Payment::query()->where('status', PaymentStatus::Paid)->sum('amount_cents');
        $paidOut = (int) Payout::query()->where('status', PayoutStatus::Paid)->sum('amount_cents');
        $expectedBalance = $received - $paidOut;

        $providerBalance = $this->providerBalance($expectedBalance);
        $difference = $providerBalance - $expectedBalance;

        MetricSnapshot::put('reconciliation_diff_cents', now(), $difference);
        MetricSnapshot::put('provider_balance_cents', now(), $providerBalance);

        if ($difference !== 0) {
            CustomerTask::ensure('reconcile:'.now()->toDateString(), [
                'title' => sprintf('Saldo betaalpartij wijkt %s af van de administratie', Money::format(abs($difference))),
                'agent' => 'finn',
                'url' => route('admin.finance'),
                'due_at' => now()->endOfDay(),
            ]);

            Notification::route('mail', (string) config('aucvy.support.email'))
                ->notify(new MoneyNeedsAttention(
                    'Saldo betaalpartij wijkt af',
                    [sprintf('Het saldo bij de betaalpartij wijkt %s af van de administratie.', Money::format(abs($difference))), 'Finn heeft er een taak van gemaakt in het control center.'],
                    route('admin.finance'),
                ));

            $this->warn("Verschil: {$difference} cent.");

            return self::FAILURE;
        }

        $this->info('Saldo betaalpartij en administratie kloppen.');

        return self::SUCCESS;
    }

    /**
     * Het saldo volgens de betaalpartij. Met de nepprovider gelijk aan de
     * administratie; Stripe Connect vult dit met de Balance API.
     */
    private function providerBalance(int $expected): int
    {
        return match (config('aucvy.payments.provider')) {
            default => $expected,
        };
    }
}
