<?php

declare(strict_types=1);

namespace App\Support\Privacy;

use App\Enums\KycStatus;
use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\Lot;
use App\Models\Order;
use App\Models\SavedSearch;
use App\Models\User;
use App\Models\Viewing;
use App\Models\WatchlistItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Verwijdering als anonimisering (hfst. 15): een order met een geschil mag
 * niet verdwijnen, de persoon erachter wel losgekoppeld worden. De
 * transactierijen (biedingen, orders) blijven staan tot hun fiscale
 * bewaartermijn; alles wat een persoon identificeert wordt overschreven.
 */
final class AnonymiseUser
{
    public function handle(User $user): void
    {
        $this->guardAgainstRunningObligations($user);

        DB::transaction(function () use ($user): void {
            // Persoonlijk gedrag: weg, dit is niemand anders z'n data.
            WatchlistItem::query()->where('user_id', $user->id)->delete();
            SavedSearch::query()->where('user_id', $user->id)->delete();
            Viewing::query()->where('user_id', $user->id)->delete();

            // Adressen: de plaats blijft (context van oude orders), de rest niet.
            Location::query()->where('user_id', $user->id)->update([
                'label' => 'Verwijderd',
                'street' => '',
                'house_number' => '',
                'postal_code' => '',
                'latitude' => null,
                'longitude' => null,
            ]);

            $user->forceFill([
                'name' => 'Verwijderde gebruiker',
                'display_name' => null,
                'email' => "verwijderd-{$user->id}@anoniem.aucvy.test",
                'email_verified_at' => null,
                'phone' => null,
                'phone_verified_at' => null,
                'password' => Str::random(64),
                'remember_token' => null,
                'kyc_status' => KycStatus::Unverified,
                'kyc_verified_at' => null,
                'can_bid' => false,
                'is_staff' => false,
                'notification_prefs' => null,
            ])->save();

            // Soft delete: de rij blijft bestaan voor de verwijzingen vanuit
            // biedingen en orders, maar de persoon is er niet meer.
            $user->delete();

            AuditLog::create([
                'actor_id' => null,
                'action' => 'user.anonymised',
                'subject_type' => $user->getMorphClass(),
                'subject_id' => $user->id,
                'before' => [],
                'after' => ['anonymised' => true],
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Eerst afronden, dan vertrekken: lopende verplichtingen blokkeren.
     */
    private function guardAgainstRunningObligations(User $user): void
    {
        $runningOrder = Order::query()
            ->where(fn ($query) => $query->where('buyer_id', $user->id)->orWhere('seller_id', $user->id))
            ->whereNotIn('status', [OrderStatus::Completed, OrderStatus::Cancelled])
            ->exists();

        if ($runningOrder) {
            throw new RuntimeException('Deze gebruiker heeft nog een lopende bestelling. Rond die eerst af.');
        }

        $liveLot = Lot::query()->public()->where('seller_id', $user->id)->exists();

        if ($liveLot) {
            throw new RuntimeException('Deze gebruiker heeft nog een lopende kavel. Sluit die eerst.');
        }
    }
}
