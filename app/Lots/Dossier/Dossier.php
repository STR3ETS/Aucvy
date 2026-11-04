<?php

declare(strict_types=1);

namespace App\Lots\Dossier;

use App\Models\Lot;
use App\Models\LotActivity;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Het kaveldossier (briefing nieuwe functies, 3 november 2026): één plek om
 * een regel op de tijdlijn te zetten en één plek om hem te lezen.
 *
 * Regels:
 *  - alleen toevoegen, nooit herschrijven: een correctie is een nieuwe regel;
 *  - elke regel heeft een zichtbaarheid; een koper ziet alleen zijn eigen
 *    gesprek en zijn eigen order, nooit biedingen of gesprekken van anderen;
 *  - een source_ref maakt een projectie idempotent: dezelfde bronregel komt
 *    nooit twee keer op de tijdlijn.
 */
final class Dossier
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function record(
        Lot|int $lot,
        string $source,
        string $type,
        string $visibility = LotActivity::STAFF,
        array $payload = [],
        ?User $actor = null,
        Order|int|null $order = null,
        ?int $userId = null,
        ?string $ref = null,
        ?CarbonInterface $at = null,
        ?string $actorType = null,
    ): ?LotActivity {
        $lotId = $lot instanceof Lot ? $lot->id : $lot;

        if ($ref !== null && LotActivity::query()->where('source_ref', $ref)->exists()) {
            return null;
        }

        return LotActivity::create([
            'lot_id' => $lotId,
            'order_id' => $order instanceof Order ? $order->id : $order,
            'source' => $source,
            'type' => $type,
            'actor_type' => $actorType ?? self::actorType($actor),
            'actor_id' => $actor?->id,
            'visibility' => $visibility,
            'user_id' => $userId,
            'payload' => $payload === [] ? null : $payload,
            'source_ref' => $ref,
            'occurred_at' => $at ?? now(),
            'created_at' => now(),
        ]);
    }

    /**
     * De tijdlijn zoals deze lezer hem mag zien, nieuwste eerst.
     *
     * @param  'seller'|'buyer'|'staff'|'public'  $role
     * @return Collection<int, LotActivity>
     */
    public static function timeline(Lot $lot, string $role, ?User $user = null, ?string $source = null, ?int $orderId = null, int $limit = 200): Collection
    {
        return LotActivity::query()
            ->where('lot_id', $lot->id)
            ->visibleTo($role, $user)
            ->when($source !== null && $source !== '', fn ($query) => $query->where('source', $source))
            // Een koper ziet van de order alleen zijn eigen order.
            ->when($role === 'buyer' && $orderId !== null, fn ($query) => $query->where(fn ($query) => $query->whereNull('order_id')->orWhere('order_id', $orderId)))
            ->with('actor')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Hoe een lezer zich tot de kavel verhoudt.
     *
     * @return 'seller'|'buyer'|'staff'|'public'
     */
    public static function roleOf(?User $user, Lot $lot): string
    {
        if ($user === null) {
            return 'public';
        }

        if ($user->is_staff) {
            return 'staff';
        }

        if ($lot->isManagedBy($user)) {
            return 'seller';
        }

        return Order::query()->where('lot_id', $lot->id)->where('buyer_id', $user->id)->exists() ? 'buyer' : 'public';
    }

    private static function actorType(?User $actor): string
    {
        if ($actor === null) {
            return 'system';
        }

        return $actor->is_staff ? 'staff' : 'user';
    }
}
