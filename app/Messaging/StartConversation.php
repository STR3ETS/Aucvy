<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Models\Conversation;
use App\Models\Lot;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Een korte vraag of een gesprek beginnen over een kavel. Een koper mag
 * dat met een bevestigd e-mailadres en telefoonnummer (dezelfde drempel
 * als bieden tot 250 euro); een verkoper mag het alleen na een bod of
 * verkoop.
 */
final class StartConversation
{
    public function __construct(private readonly SendMessage $send) {}

    public function asBuyer(Lot $lot, User $buyer, string $kind, string $body, ?string $lotPart = null, ?string $ip = null): Conversation
    {
        $this->guardBuyer($lot, $buyer);

        $conversation = $kind === Conversation::KIND_CHAT
            ? $this->existingChat($lot, $buyer) ?? $this->create($lot, $buyer, $kind, $lotPart)
            : $this->create($lot, $buyer, $kind, $lotPart);

        $this->send->handle($conversation, $buyer, $body, ip: $ip, lotPart: $lotPart);

        return $conversation->fresh(['messages']);
    }

    /**
     * De verkoper begint een gesprek met een koper die een bod deed of de
     * kavel kocht; nooit zomaar met een bezoeker.
     */
    public function asSeller(Lot $lot, User $seller, User $buyer, string $body, ?string $ip = null): Conversation
    {
        if (! $lot->isManagedBy($seller)) {
            throw ValidationException::withMessages(['lot' => __('funnel.errors.not_yours')]);
        }

        $hasBidOrOrder = ($lot->order !== null && $lot->order->buyer_id === $buyer->id)
            || ($lot->auction !== null && $lot->auction->bids()->where('user_id', $buyer->id)->exists());

        if (! $hasBidOrOrder) {
            throw ValidationException::withMessages(['buyer' => __('messaging.errors.blocked')]);
        }

        $conversation = $this->existingChat($lot, $buyer) ?? $this->create($lot, $buyer, Conversation::KIND_CHAT, null);

        $this->send->handle($conversation, $seller, $body, ip: $ip);

        return $conversation->fresh(['messages']);
    }

    private function guardBuyer(Lot $lot, User $buyer): void
    {
        if ($lot->isManagedBy($buyer)) {
            throw ValidationException::withMessages(['lot' => __('messaging.errors.own_lot')]);
        }

        if (! $lot->status->isPublic() && ($lot->order === null || $lot->order->buyer_id !== $buyer->id)) {
            throw ValidationException::withMessages(['lot' => __('messaging.errors.not_live')]);
        }

        $verified = $buyer->hasVerifiedEmail() && (! config('messaging.require_verified_phone') || $buyer->getAttributes()['phone_verified_at'] !== null);

        if (! $verified) {
            throw ValidationException::withMessages(['verification' => __('messaging.errors.verify_first')]);
        }
    }

    private function existingChat(Lot $lot, User $buyer): ?Conversation
    {
        return Conversation::query()
            ->where('lot_id', $lot->id)
            ->where('buyer_id', $buyer->id)
            ->where('kind', Conversation::KIND_CHAT)
            ->first();
    }

    private function create(Lot $lot, User $buyer, string $kind, ?string $lotPart): Conversation
    {
        return Conversation::create([
            'lot_id' => $lot->id,
            'order_id' => $lot->order?->buyer_id === $buyer->id ? $lot->order->id : null,
            'buyer_id' => $buyer->id,
            'seller_id' => $lot->seller_id,
            'company_id' => $lot->company_id,
            'kind' => $kind,
            'status' => Conversation::STATUS_OPEN,
            'lot_part' => $lotPart,
        ]);
    }
}
