<?php

declare(strict_types=1);

namespace App\Support\Privacy;

use App\Models\AutoBid;
use App\Models\Bid;
use App\Models\Deposit;
use App\Models\Location;
use App\Models\Order;
use App\Models\Review;
use App\Models\SavedSearch;
use App\Models\User;
use App\Models\Viewing;
use App\Models\WatchlistItem;
use App\Notifications\DataExportReady;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Het recht op inzage (hfst. 15) als job: alles wat we over iemand hebben,
 * als leesbaar JSON op de private disk. De gebruiker krijgt een mail met een
 * tijdelijk getekende downloadlink - het bestand zelf is nooit publiek.
 */
final class ExportUserData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly User $user) {}

    public function handle(): void
    {
        Storage::disk('local')->put(
            self::path($this->user),
            json_encode($this->payload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );

        $this->user->notify(new DataExportReady);
    }

    public static function path(User $user): string
    {
        return "exports/user-{$user->id}.json";
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $user = $this->user;

        return [
            'aangemaakt_op' => now()->toIso8601String(),
            'profiel' => $user->only([
                'name', 'display_name', 'email', 'phone', 'type',
                'kyc_status', 'created_at',
            ]),
            'bedrijf' => $user->company?->only(['name', 'kvk_number', 'vat_number']),
            'adressen' => Location::query()->where('user_id', $user->id)
                ->get(['label', 'street', 'house_number', 'postal_code', 'city', 'province'])->toArray(),
            'biedingen' => Bid::query()->where('user_id', $user->id)
                ->get(['auction_id', 'amount_cents', 'type', 'status', 'placed_at'])->toArray(),
            'automatische_maxima' => AutoBid::query()->where('user_id', $user->id)
                ->get(['auction_id', 'max_amount_cents', 'active', 'created_at'])->toArray(),
            'borgreserveringen' => Deposit::query()->where('user_id', $user->id)
                ->get(['auction_id', 'amount_cents', 'status', 'created_at'])->toArray(),
            'bestellingen_als_koper' => Order::query()->where('buyer_id', $user->id)
                ->get(['order_number', 'total_cents', 'status', 'created_at'])->toArray(),
            'verkopen' => Order::query()->where('seller_id', $user->id)
                ->get(['order_number', 'total_cents', 'status', 'created_at'])->toArray(),
            'bezichtigingen' => Viewing::query()->where('user_id', $user->id)
                ->get(['lot_id', 'slot_at', 'status', 'note'])->toArray(),
            'volglijst' => WatchlistItem::query()->where('user_id', $user->id)
                ->get(['lot_id', 'created_at'])->toArray(),
            'zoekopdrachten' => SavedSearch::query()->where('user_id', $user->id)
                ->get(['name', 'query', 'created_at'])->toArray(),
            'reviews_geschreven' => Review::query()->where('reviewer_id', $user->id)
                ->get(['order_id', 'rating', 'body', 'published_at'])->toArray(),
        ];
    }
}
