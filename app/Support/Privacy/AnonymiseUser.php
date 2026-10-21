<?php

declare(strict_types=1);

namespace App\Support\Privacy;

use App\Enums\KycStatus;
use App\Enums\LotStatus;
use App\Enums\OrderStatus;
use App\Lots\DeleteDraft;
use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\Location;
use App\Models\Lot;
use App\Models\Order;
use App\Models\SavedSearch;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Viewing;
use App\Models\WatchlistItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Verwijdering als anonimisering (hfst. 15): een order met een geschil mag
 * niet verdwijnen, de persoon erachter wel losgekoppeld worden. De
 * transactierijen (biedingen, orders, berichten bij een order) blijven staan
 * tot hun fiscale bewaartermijn; alles wat een persoon identificeert wordt
 * overschreven of weggegooid: adressen, apparaten, meldingen, concepten,
 * IP-adressen, en de inzage-export op schijf.
 */
final class AnonymiseUser
{
    /**
     * Een kavel die nog in de verkoop zit of erin kan komen. Verkocht of
     * onverkocht is afgerond en blokkeert niet - anders kan niemand die ooit
     * iets verkocht heeft nog vertrekken.
     */
    private const RUNNING_LOT_STATUSES = [
        LotStatus::PendingReview,
        LotStatus::Scheduled,
        LotStatus::Live,
        LotStatus::Closing,
        LotStatus::Paused,
    ];

    public function handle(User $user): void
    {
        $this->guardAgainstRunningObligations($user);

        DB::transaction(function () use ($user): void {
            // Persoonlijk gedrag: weg, dit is niemand anders z'n data.
            WatchlistItem::query()->where('user_id', $user->id)->delete();
            SavedSearch::query()->where('user_id', $user->id)->delete();
            Viewing::query()->where('user_id', $user->id)->delete();

            // Apparaten en de meldingenbak: namen en kaveltitels van anderen
            // staan erin, en er hoeft niets meer aan te komen.
            $user->pushSubscriptions()->delete();
            $user->notifications()->delete();

            // Concepten zijn nooit publiek geweest en van niemand anders.
            Lot::query()
                ->where('seller_id', $user->id)
                ->where('status', LotStatus::Draft)
                ->get()
                ->each(fn (Lot $draft) => app(DeleteDraft::class)->handle($draft));

            // Biedingen blijven (fiscaal), maar herkomst en apparaat niet.
            Bid::query()->where('user_id', $user->id)->update(['ip' => null, 'user_agent' => null]);

            // Adressen: de plaats blijft (context van oude orders), de rest niet.
            Location::query()->where('user_id', $user->id)->update([
                'label' => 'Verwijderd',
                'street' => '',
                'house_number' => '',
                'postal_code' => '',
                'latitude' => null,
                'longitude' => null,
            ]);

            // Supportverzoeken: de vraag mag blijven voor de afhandeling, de
            // afzender niet. Ook tickets zonder account maar met dit adres.
            SupportTicket::query()
                ->where(fn ($query) => $query->where('user_id', $user->id)->orWhere('email', $user->email))
                ->update([
                    'name' => 'Verwijderde gebruiker',
                    'email' => "verwijderd-{$user->id}@anoniem.aucvy.test",
                    'ip' => null,
                ]);

            // De inzage-export bevat alles hierboven in leesbare vorm.
            Storage::disk('local')->delete(ExportUserData::path($user));

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

        $runningLot = Lot::query()
            ->where('seller_id', $user->id)
            ->whereIn('status', self::RUNNING_LOT_STATUSES)
            ->exists();

        if ($runningLot) {
            throw new RuntimeException('Deze gebruiker heeft nog een lopende kavel. Sluit die eerst.');
        }
    }
}
