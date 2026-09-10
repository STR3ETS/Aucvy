<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * De route van hamerslag naar uitbetaling (hfst. 9):
 *
 *   awaiting_payment -> paid -> ready_for_pickup|shipped -> delivered
 *      -> completed  (geen melding binnen de meldtermijn)
 *      -> disputed   (wel een melding; uitbetaling staat stil)
 */
enum OrderStatus: string
{
    use HasLabel;

    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case ReadyForPickup = 'ready_for_pickup';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Disputed = 'disputed';
    case Cancelled = 'cancelled';

    /**
     * Toegestane vervolgstappen. De statusmachine wordt hier vastgelegd zodat
     * een verboden overgang een testbare fout is en geen stille update.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::AwaitingPayment => [self::Paid, self::Cancelled],
            self::Paid => [self::ReadyForPickup, self::Shipped, self::Cancelled],
            self::ReadyForPickup, self::Shipped => [self::Delivered, self::Disputed],
            self::Delivered => [self::Completed, self::Disputed],
            self::Disputed => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Alleen een afgeronde order geeft recht op een review (hfst. 13.6).
     */
    public function allowsReview(): bool
    {
        return $this === self::Completed;
    }
}
