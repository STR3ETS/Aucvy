<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\Company;
use App\Models\Order;

/**
 * Eén bron voor de getallen van het platform (besluiten Raph en Don,
 * 2 november 2026; Ivy-briefing hoofdstuk 6): de fee-staffel, de
 * administratiekosten, de biedtreden, de borgtreden en de termijnen.
 * Chat, mail en pagina's lezen hier; één getal wijzigen in de config is
 * genoeg. Geen tekst of prompt mag deze getallen hardcoded bevatten.
 */
final class PlatformFacts
{
    /**
     * De succesfee als staffel over de omzet van de verkoper op Aucvy in de
     * laatste twaalf maanden. Het tarief ligt vast op het moment van verkoop
     * en wordt nooit achteraf verrekend.
     *
     * @return list<array{up_to_cents: int|null, percent: float}>
     */
    public static function feeTiers(): array
    {
        return array_values(array_map(
            fn (array $tier): array => ['up_to_cents' => $tier['up_to_cents'] === null ? null : (int) $tier['up_to_cents'], 'percent' => (float) $tier['percent']],
            (array) config('aucvy.pricing.fee_tiers', []),
        ));
    }

    public static function usesFeeTiers(): bool
    {
        return (bool) config('aucvy.pricing.use_fee_tiers', true) && self::feeTiers() !== [];
    }

    /**
     * Het tarief voor deze verkoper, nu.
     */
    public static function feePercentageFor(?Company $company): float
    {
        return self::tierFor(self::revenueCentsFor($company))['percent'];
    }

    /**
     * @return array{up_to_cents: int|null, percent: float}
     */
    public static function tierFor(int $revenueCents): array
    {
        $tiers = self::feeTiers();

        foreach ($tiers as $tier) {
            if ($tier['up_to_cents'] === null || $revenueCents < $tier['up_to_cents']) {
                return $tier;
            }
        }

        return $tiers[array_key_last($tiers)] ?? ['up_to_cents' => null, 'percent' => 0.0];
    }

    /**
     * De volgende, lagere trede en hoeveel omzet daar nog voor nodig is.
     *
     * @return array{percent: float, remaining_cents: int}|null
     */
    public static function nextTierFor(int $revenueCents): ?array
    {
        $tiers = self::feeTiers();

        foreach ($tiers as $index => $tier) {
            if ($tier['up_to_cents'] === null || $revenueCents < $tier['up_to_cents']) {
                $next = $tiers[$index + 1] ?? null;

                return $next === null ? null : ['percent' => $next['percent'], 'remaining_cents' => max(0, (int) $tier['up_to_cents'] - $revenueCents)];
            }
        }

        return null;
    }

    /**
     * Omzet op Aucvy: betaalde en afgeronde orders van het bedrijf in het
     * venster, op hamerbedrag.
     */
    public static function revenueCentsFor(?Company $company): int
    {
        if ($company === null) {
            return 0;
        }

        $sellerIds = $company->users()->pluck('id');

        return (int) Order::query()
            ->whereIn('seller_id', $sellerIds)
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::ReadyForPickup, OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::Completed])
            ->where('created_at', '>=', now()->subMonths((int) config('aucvy.pricing.fee_tier_window_months', 12)))
            ->sum('hammer_cents');
    }

    /** Administratiekosten voor de koper, per order, exclusief btw. */
    public static function adminFeeCents(): int
    {
        return (int) config('aucvy.pricing.admin_fee_cents', 0);
    }

    /** Tot dit bedrag bieden met bevestigd e-mailadres en telefoonnummer; daarboven identiteit. */
    public static function bidFreeLimitCents(): int
    {
        return (int) config('aucvy.pricing.bid_free_limit_cents', 0);
    }

    /**
     * De borg die bij een bod van dit bedrag nodig is; 0 als er geen nodig is.
     */
    public static function depositRequiredFor(int $amountCents): int
    {
        $required = 0;

        foreach ((array) config('aucvy.pricing.deposit_tiers', []) as $tier) {
            if ($amountCents > (int) $tier['above_cents']) {
                $required = (int) $tier['deposit_cents'];
            }
        }

        return $required;
    }

    /**
     * @return list<array{above_cents: int, deposit_cents: int}>
     */
    public static function depositTiers(): array
    {
        return array_values(array_map(fn (array $tier) => ['above_cents' => (int) $tier['above_cents'], 'deposit_cents' => (int) $tier['deposit_cents']], (array) config('aucvy.pricing.deposit_tiers', [])));
    }

    public static function publicSoldPrices(): bool
    {
        return (bool) config('aucvy.pricing.public_sold_prices', true);
    }

    public static function paymentDeadlineHours(): int
    {
        return (int) config('aucvy.orders.payment_deadline_hours');
    }

    public static function disputeWindowHours(): int
    {
        return (int) config('aucvy.orders.dispute_window_hours');
    }

    public static function autoDeliverAfterDays(): int
    {
        return (int) config('aucvy.orders.auto_deliver_after_days');
    }

    public static function sellerResponseHours(): int
    {
        return (int) config('aucvy.orders.seller_response_hours');
    }

    public static function payoutHoldHours(): int
    {
        return (int) config('aucvy.orders.payout_hold_hours');
    }

    /**
     * Alles bij elkaar, voor Ivy, de kennisbank en de prijspagina.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return [
            'fee_tiers' => self::feeTiers(),
            'admin_fee_cents' => self::adminFeeCents(),
            'bid_free_limit_cents' => self::bidFreeLimitCents(),
            'deposit_tiers' => self::depositTiers(),
            'payment_deadline_hours' => self::paymentDeadlineHours(),
            'dispute_window_hours' => self::disputeWindowHours(),
            'auto_deliver_after_days' => self::autoDeliverAfterDays(),
            'seller_response_hours' => self::sellerResponseHours(),
            'payout_hold_hours' => self::payoutHoldHours(),
            'public_sold_prices' => self::publicSoldPrices(),
        ];
    }
}
