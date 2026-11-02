<?php

declare(strict_types=1);

namespace App\Lots;

use App\Compliance\Permission;
use App\Compliance\PermissionEngine;
use App\Enums\LotStatus;
use App\Enums\SaleType;
use App\Ivy\StartTask;
use App\Ivy\Tasks\ScreenListing;
use App\Lots\Sales\SaleStudio;
use App\Models\Lot;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Dient een concept in voor publicatie: alle poortwachters, dan de status
 * "in beoordeling" en de controle van Ivy (ScreenListing). Live zetten doet
 * GoLive, na akkoord van Ivy of een medewerker (besluit Raph, 2 november
 * 2026: vooraf modereren).
 *
 * De prijzen en de looptijd blijven tot het live gaan in `draft_meta`; de
 * verkoper kan in die tijd niets wijzigen, want alleen een concept is te
 * bewerken.
 */
final class PublishLot
{
    public function __construct(
        private readonly PermissionEngine $permissions,
        private readonly Preflight $preflight,
    ) {}

    public function handle(Lot $lot, User $seller, ?string $ip = null): Lot
    {
        $this->guardDraft($lot);
        $this->guardSeller($seller);

        $meta = $lot->draft_meta ?? [];
        $saleType = $this->saleType($meta);

        $this->guardContent($lot);
        $this->guardPricing($saleType, $meta);
        $this->guardPreflight($lot, $seller);
        $this->guardOwnership($lot, $seller);
        $this->guardSaleSlot($lot);

        $didSubmit = false;

        $submitted = DB::transaction(function () use ($lot, $seller, $ip, &$didSubmit): Lot {
            /*
             | Twee keer op publiceren klikken: de tweede aanvraag wacht op de
             | lock van de eerste en ziet daarna een kavel die al ingediend of
             | live is. Die geeft dezelfde uitkomst terug in plaats van een
             | tweede indiening (briefing, wizard-scenario 12).
             */
            $locked = Lot::query()->whereKey($lot->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== LotStatus::Draft) {
                return $locked->fresh(['auction']);
            }

            // Een concept zonder eigenaar (cookie) komt op naam van wie publiceert;
            // een kavel van een collega blijft van die collega (organisatierollen).
            if ($locked->seller_id === null) {
                $locked->seller_id = $seller->id;
                $locked->company_id = $seller->company_id;
            }

            $locked->status = LotStatus::PendingReview;
            $locked->submitted_at = now();
            $locked->screening = null;
            $locked->save();

            Audit::record('lot.submitted', $locked, $seller, after: ['sale_type' => ($locked->draft_meta['sale_type'] ?? null)], ip: $ip);

            $didSubmit = true;

            return $locked;
        });

        // Alleen de aanvraag die de status echt wijzigde start de controle;
        // een gelijktijdige tweede klik krijgt dezelfde uitkomst terug.
        if (! $didSubmit) {
            return $submitted;
        }

        /*
         | Vooraf modereren (besluit Raph, 2 november 2026): Ivy controleert
         | de kavel en zet hem live, stuurt hem terug of legt hem bij een
         | medewerker. Uitgezet (alleen lokaal of in een noodgeval) gaat de
         | kavel direct live zoals voorheen.
         */
        if (! config('aucvy.listings.pre_moderation')) {
            return app(GoLive::class)->handle($submitted, $seller, $ip, 'direct');
        }

        StartTask::run(new ScreenListing($submitted->fresh(['documents', 'media', 'category'])), $seller);

        return $submitted->fresh(['auction']);
    }

    // Poortwachters ---------------------------------------------------------

    /**
     * Een concept met eigenaar publiceert alleen die eigenaar of een collega
     * met een rol die kavels mag bewerken; nooit wie toevallig de cookie heeft.
     */
    private function guardOwnership(Lot $lot, User $seller): void
    {
        if ($lot->seller_id !== null && ! $lot->isManagedBy($seller)) {
            throw ValidationException::withMessages(['lot' => __('funnel.errors.not_yours')]);
        }
    }

    /**
     * Zit de kavel in een gepubliceerde veiling waarvan zijn sluitmoment al
     * voorbij is, dan hoort hij daar eerst uit (of in een nieuwe veiling).
     */
    private function guardSaleSlot(Lot $lot): void
    {
        $sale = $lot->sale;

        if ($sale !== null && $sale->isPublished() && SaleStudio::closingFor($lot) === null) {
            throw ValidationException::withMessages(['sale' => __('sales.errors.slot_passed')]);
        }
    }

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
        $decision = $this->permissions->decide($seller, Permission::Publish);

        if ($decision->denied()) {
            throw ValidationException::withMessages(['seller' => $decision->reason->message()]);
        }
    }

    /**
     * De preflight van stap 8 (sectie 5): foto's, eigendom, verboden
     * categorie, onbevestigde AI-velden en de rechten van de verkoper. Zelfde
     * lijst als de funnel toont, zodat de knop nooit iets belooft wat de
     * server weigert.
     */
    private function guardPreflight(Lot $lot, User $seller): void
    {
        foreach ($this->preflight->for($lot, $seller)['checks'] as $check) {
            if ($check['blocking'] && ! $check['ok']) {
                throw ValidationException::withMessages([$check['key'] => $check['hint'] ?? $check['label']]);
            }
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

        $this->guardPriceOrder($saleType, $meta);
    }

    /**
     * Koop-direct hoort boven start- en reserveprijs; een reserve hoort op of
     * boven de startprijs; bij een afslag mag de bodem niet onder de reserve.
     * Anders klikt een koper op een prijs die de veiling niet kan waarmaken.
     *
     * @param  array<string, mixed>  $meta
     */
    private function guardPriceOrder(SaleType $saleType, array $meta): void
    {
        $start = (int) ($meta['start_price_cents'] ?? 0);
        $reserve = (int) ($meta['reserve_price_cents'] ?? 0);
        $buyNow = (int) ($meta['buy_now_price_cents'] ?? 0);
        $floor = (int) ($meta['dutch_floor_cents'] ?? 0);

        if ($saleType === SaleType::Auction && $reserve > 0 && $reserve < $start) {
            throw ValidationException::withMessages(['reserve_price_cents' => __('funnel.errors.reserve_below_start')]);
        }

        if ($buyNow > 0 && $saleType !== SaleType::Dutch && $buyNow < max($start, $reserve)) {
            throw ValidationException::withMessages(['buy_now_price_cents' => __('funnel.errors.buy_now_below_prices')]);
        }

        if ($saleType === SaleType::Dutch && $reserve > 0 && $floor < $reserve) {
            throw ValidationException::withMessages(['dutch' => __('funnel.errors.dutch_floor_below_reserve')]);
        }
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
