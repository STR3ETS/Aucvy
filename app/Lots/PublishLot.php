<?php

declare(strict_types=1);

namespace App\Lots;

use App\Auctions\Jobs\CloseAuctionJob;
use App\Enums\LotStatus;
use App\Enums\SaleType;
use App\Models\Auction;
use App\Models\Lot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Zet een concept live: kavel → status live, veiling aangemaakt, sluiting
 * ingepland.
 *
 * De prijzen en de looptijd komen uit `draft_meta` - de wachtkamer die de
 * funnel vult. Hier worden ze definitief: de staffel en de anti-snipe-waarden
 * worden uit config gekopieerd en daarna nooit meer gelezen, zodat een
 * configwijziging een lopende veiling niet raakt (briefing hfst. 5).
 */
final class PublishLot
{
    public function handle(Lot $lot, User $seller): Lot
    {
        $this->guardDraft($lot);
        $this->guardSeller($seller);

        $meta = $lot->draft_meta ?? [];
        $saleType = $this->saleType($meta);

        $this->guardContent($lot);
        $this->guardPricing($saleType, $meta);

        return DB::transaction(function () use ($lot, $seller, $meta, $saleType): Lot {
            $durationDays = (int) ($meta['duration_days'] ?? 7);
            $endsAt = now()->addDays(max(1, min(30, $durationDays)));

            $auction = Auction::create([
                'lot_id' => $lot->id,
                'starts_at' => now(),
                'ends_at' => $endsAt,
                'original_ends_at' => $endsAt,
                'start_price_cents' => (int) ($meta['start_price_cents'] ?? 0),
                'reserve_price_cents' => $meta['reserve_price_cents'] ?? null,
                'current_price_cents' => $saleType === SaleType::Dutch
                    ? (int) $meta['dutch_start_cents']
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

            $lot->seller_id = $seller->id;
            $lot->company_id = $seller->company_id;
            $lot->sale_type = $saleType;
            $lot->status = LotStatus::Live;
            $lot->published_at = now();
            // De slug bevat het kavelnummer, zodat een latere titelwijziging
            // de URL niet breekt (hfst. 5).
            $lot->slug = Str::slug((string) $lot->title).'-'.$lot->public_id;
            // De wachtkamer is leeg; vanaf nu is de veiling de waarheid.
            $lot->draft_meta = null;
            $lot->draft_token = null;
            $lot->save();

            // Sluiting inplannen; het vangnet auctions:close-overdue vangt een
            // verloren job op (hfst. 6).
            CloseAuctionJob::dispatch($auction->id)->delay($endsAt);

            return $lot->fresh(['auction']);
        });
    }

    // Poortwachters ---------------------------------------------------------

    private function guardDraft(Lot $lot): void
    {
        if ($lot->status !== LotStatus::Draft) {
            throw ValidationException::withMessages(['lot' => __('funnel.errors.not_a_draft')]);
        }
    }

    /**
     * "KVK-controle en identiteitscheck voordat een kavel live kan" - dat is
     * de belofte op de zekerheidssectie, dus hij wordt hier afgedwongen en
     * niet alleen in de UI.
     */
    private function guardSeller(User $seller): void
    {
        if (! $seller->kyc_status->allowsBidding()) {
            throw ValidationException::withMessages(['seller' => __('funnel.errors.verification_required')]);
        }
    }

    private function guardContent(Lot $lot): void
    {
        if ($lot->title === null || trim($lot->title) === '') {
            throw ValidationException::withMessages(['title' => __('funnel.errors.title_required')]);
        }

        if ($lot->category_id === null) {
            throw ValidationException::withMessages(['category' => __('funnel.errors.category_required')]);
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function saleType(array $meta): SaleType
    {
        $saleType = SaleType::tryFrom((string) ($meta['sale_type'] ?? ''));

        if ($saleType === null) {
            throw ValidationException::withMessages(['sale_type' => __('funnel.errors.sale_type_required')]);
        }

        return $saleType;
    }

    /**
     * Elke verkoopvorm heeft zijn eigen verplichte bedragen. Publiceren met
     * een halve prijsstelling levert een veiling op die niet kán werken -
     * beter hier hard falen dan straks in de biedmotor.
     *
     * @param  array<string, mixed>  $meta
     */
    private function guardPricing(SaleType $saleType, array $meta): void
    {
        match ($saleType) {
            SaleType::Auction => ($meta['start_price_cents'] ?? 0) > 0
                ?: throw ValidationException::withMessages(['start_price_cents' => __('funnel.errors.start_price_required')]),

            SaleType::BuyNow => ($meta['buy_now_price_cents'] ?? 0) > 0
                ?: throw ValidationException::withMessages(['buy_now_price_cents' => __('funnel.errors.buy_now_price_required')]),

            SaleType::Dutch => $this->guardDutch($meta),
        };
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function guardDutch(array $meta): bool
    {
        $complete = ($meta['dutch_start_cents'] ?? 0) > 0
            && ($meta['dutch_floor_cents'] ?? 0) > 0
            && ($meta['dutch_step_cents'] ?? 0) > 0
            && ($meta['dutch_interval_s'] ?? 0) > 0;

        if (! $complete) {
            throw ValidationException::withMessages(['dutch' => __('funnel.errors.dutch_settings_required')]);
        }

        if ($meta['dutch_floor_cents'] > $meta['dutch_start_cents']) {
            throw ValidationException::withMessages(['dutch' => __('funnel.errors.dutch_floor_above_start')]);
        }

        return true;
    }
}
