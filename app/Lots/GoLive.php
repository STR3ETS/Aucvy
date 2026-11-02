<?php

declare(strict_types=1);

namespace App\Lots;

use App\Auctions\Jobs\CloseAuctionJob;
use App\Enums\LotStatus;
use App\Enums\SaleType;
use App\Lots\Sales\SaleStudio;
use App\Models\Auction;
use App\Models\Lot;
use App\Models\User;
use App\Support\Audit\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * De tweede helft van publiceren: een ingediende en goedgekeurde kavel
 * live zetten. Kavel live, veiling aangemaakt, sluiting ingepland,
 * garantie en verklaringen vastgelegd.
 *
 * Wordt aangeroepen door de controle van Ivy (ScreenListing) bij een
 * goedkeuring, en door een medewerker die een kavel in beoordeling
 * goedkeurt. Staat vooraf modereren uit, dan direct door PublishLot.
 *
 * De prijzen en de looptijd komen uit `draft_meta`. Hier worden ze
 * definitief: de staffel en de anti-snipe-waarden worden uit config
 * gekopieerd en daarna nooit meer gelezen, zodat een configwijziging een
 * lopende veiling niet raakt (briefing hfst. 5).
 */
final class GoLive
{
    /**
     * Statussen van waaruit een kavel live kan: ingediend, of (zonder
     * vooraf modereren) nog een concept.
     */
    public const FROM = [LotStatus::PendingReview, LotStatus::Draft];

    public function handle(Lot $lot, ?User $actor = null, ?string $ip = null, ?string $approvedBy = null): Lot
    {
        $published = DB::transaction(function () use ($lot, $actor, $ip, $approvedBy): Lot {
            $locked = Lot::query()->whereKey($lot->id)->lockForUpdate()->firstOrFail();

            // Al live (dubbele goedkeuring, retry): dezelfde uitkomst.
            if (! in_array($locked->status, self::FROM, true)) {
                return $locked->fresh(['auction']);
            }

            $meta = $locked->draft_meta ?? [];
            $saleType = SaleType::tryFrom((string) ($meta['sale_type'] ?? '')) ?? SaleType::Auction;

            // Zit de kavel in een gepubliceerde veiling, dan geeft die de sluittijd
            // (Auction Studio); anders geldt de gekozen looptijd vanaf nu.
            $durationDays = (int) ($meta['duration_days'] ?? 7);
            $closing = SaleStudio::closingFor($locked);
            $endsAt = $closing !== null && $closing->isFuture()
                ? $closing
                : CarbonImmutable::now()->addDays(max(1, min(30, $durationDays)));

            $auction = Auction::create([
                'lot_id' => $locked->id,
                'starts_at' => now(),
                'ends_at' => $endsAt,
                'original_ends_at' => $endsAt,
                'start_price_cents' => (int) ($meta['start_price_cents'] ?? 0),
                'reserve_price_cents' => $meta['reserve_price_cents'] ?? null,
                'current_price_cents' => $saleType === SaleType::Dutch
                    ? (int) ($meta['dutch_start_cents'] ?? 0)
                    : (int) ($meta['start_price_cents'] ?? 0),
                'increment_table' => config('aucvy.auction.increment_table'),
                'anti_snipe_window_s' => (int) config('aucvy.auction.anti_snipe_window_seconds'),
                'anti_snipe_extend_s' => (int) config('aucvy.auction.anti_snipe_extend_seconds'),
                'buy_now_price_cents' => $meta['buy_now_price_cents'] ?? null,
                'buy_now_active' => isset($meta['buy_now_price_cents']),
                'dutch_start_cents' => $meta['dutch_start_cents'] ?? null,
                'dutch_floor_cents' => $meta['dutch_floor_cents'] ?? null,
                'dutch_step_cents' => $meta['dutch_step_cents'] ?? null,
                'dutch_interval_s' => $meta['dutch_interval_s'] ?? null,
            ]);

            // De verklaringen uit de wachtkamer worden eigenschappen van de kavel.
            $locked->warranty = $meta['warranty'] ?? $locked->warranty;
            $locked->warranty_until = $meta['warranty_until'] ?? $locked->warranty_until;
            $locked->documents_declared_at = ($meta['documents_authentic'] ?? false) === true ? now() : $locked->documents_declared_at;

            $locked->sale_type = $saleType;
            $locked->status = LotStatus::Live;
            $locked->published_at = now();
            // De slug bevat het kavelnummer, zodat een latere titelwijziging
            // de URL niet breekt (hfst. 5).
            $locked->slug = Str::slug((string) $locked->title).'-'.$locked->public_id;
            // De wachtkamer is leeg; vanaf nu is de veiling de waarheid.
            $locked->draft_meta = null;
            $locked->draft_token = null;
            $locked->save();

            // Sluiting inplannen; het vangnet auctions:close-overdue vangt een
            // verloren job op (hfst. 6).
            CloseAuctionJob::dispatch($auction->id)->delay($endsAt);

            Audit::record('lot.published', $locked, $actor, after: [
                'sale_type' => $saleType->value,
                'auction_id' => $auction->id,
                'ends_at' => $endsAt->toIso8601String(),
                'approved_by' => $approvedBy,
            ], ip: $ip);

            return $locked->fresh(['auction']);
        });

        // Engelse vertaling door Ivy (briefing stap 4), buiten de transactie.
        Translation::start($published);

        return $published;
    }
}
