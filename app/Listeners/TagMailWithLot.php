<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Auctions\Events\Outbid;
use App\Models\Auction;
use App\Models\Dispute;
use App\Models\Lot;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\Payout;
use App\Models\Review;
use App\Models\Viewing;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Symfony\Component\Mailer\Header\MetadataHeader;

/**
 * Geeft elke mail over een kavel het kavel- en ordernummer mee als
 * metadata (bij Mailgun: variabelen), zodat het afleverlog en de webhook de
 * mail in het kaveldossier kunnen zetten.
 *
 * De notificatie zelf is niet beschikbaar op het moment dat de mail de deur
 * uitgaat; daarom onthoudt deze listener bij NotificationSending wat er aan
 * de notificatie hangt (een kavel, een order, een veiling) en zet dat bij
 * MessageSending op het bericht.
 */
final class TagMailWithLot
{
    /** @var array{lot_id: int|null, order_id: int|null}|null */
    private static ?array $current = null;

    public function handleSending(NotificationSending $event): void
    {
        self::$current = self::contextOf($event->notification);
    }

    public function handleMessage(MessageSending $event): void
    {
        $headers = $event->message->getHeaders();

        foreach (self::$current ?? [] as $key => $value) {
            if ($value !== null && ! $headers->has('X-Metadata-'.$key)) {
                $headers->add(new MetadataHeader($key, (string) $value));
            }
        }
    }

    public function handleSent(NotificationSent $event): void
    {
        self::$current = null;
    }

    /**
     * Zoekt op de publieke eigenschappen van de notificatie naar iets dat bij
     * een kavel hoort.
     *
     * @return array{lot_id: int|null, order_id: int|null}|null
     */
    public static function contextOf(object $notification): ?array
    {
        foreach (get_object_vars($notification) as $value) {
            $context = match (true) {
                $value instanceof Lot => ['lot_id' => $value->id, 'order_id' => null],
                $value instanceof Order => ['lot_id' => $value->lot_id, 'order_id' => $value->id],
                $value instanceof Auction => ['lot_id' => $value->lot_id, 'order_id' => null],
                $value instanceof Outbid => ['lot_id' => $value->auction->lot_id, 'order_id' => null],
                $value instanceof Payout, $value instanceof Dispute, $value instanceof OrderMessage, $value instanceof Review => ['lot_id' => $value->order?->lot_id, 'order_id' => $value->order_id],
                $value instanceof Viewing => ['lot_id' => $value->lot_id, 'order_id' => null],
                default => null,
            };

            if ($context !== null) {
                return $context;
            }
        }

        return null;
    }
}
