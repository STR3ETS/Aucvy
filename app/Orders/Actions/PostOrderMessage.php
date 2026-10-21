<?php

declare(strict_types=1);

namespace App\Orders\Actions;

use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\User;
use App\Notifications\OrderMessageReceived;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Een bericht in de draad van een bestelling, van koper, verkoper of
 * support. De andere partij krijgt het in-app en per mail; support leest
 * alles mee in de backoffice en krijgt geen mail per bericht.
 */
final class PostOrderMessage
{
    public function handle(Order $order, User $author, string $body, ?string $ip = null): OrderMessage
    {
        $fromStaff = $author->is_staff && ! in_array($author->id, [$order->buyer_id, $order->seller_id], true);

        return DB::transaction(function () use ($order, $author, $body, $fromStaff, $ip): OrderMessage {
            $message = $order->messages()->create([
                'user_id' => $author->id,
                'from_staff' => $fromStaff,
                'body' => trim($body),
            ]);

            Audit::record('order.message', $order, $author, after: ['message_id' => $message->id, 'from_staff' => $fromStaff], ip: $ip);

            foreach ($this->recipients($order, $author, $fromStaff) as $recipient) {
                $recipient->notify(new OrderMessageReceived($message));
            }

            return $message;
        });
    }

    /**
     * Support schrijft naar beide partijen; een partij schrijft naar de andere.
     *
     * @return list<User>
     */
    private function recipients(Order $order, User $author, bool $fromStaff): array
    {
        $parties = array_filter([$order->buyer, $order->seller]);

        return array_values(array_filter($parties, fn (User $party): bool => $fromStaff || ! $party->is($author)));
    }
}
