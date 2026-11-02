<?php

declare(strict_types=1);

namespace App\Lots;

use App\Compliance\Permission;
use App\Compliance\PermissionEngine;
use App\Enums\DocumentKind;
use App\Enums\MediaType;
use App\Enums\SaleType;
use App\Models\Lot;
use App\Models\User;

/**
 * De preflight van stap 8 (briefing sectie 5): verplichte gegevens,
 * eigendom, verboden categorie, onbevestigde AI-velden en de rechten van
 * de verkoper (KYB). Eén lijst voor twee plekken: de funnel toont hem live,
 * en `PublishLot` weigert op precies dezelfde regels.
 */
final class Preflight
{
    /** @var array<string, int> De funnelstap waar elk punt op te lossen is. */
    private const STEPS = [
        'title' => 3, 'category' => 3, 'forbidden' => 3, 'condition' => 3, 'description' => 3, 'confirmed' => 3, 'unit' => 3,
        'photos' => 1, 'ownership' => 1, 'warranty' => 1, 'documents_authentic' => 1,
        'pricing' => 4, 'price_order' => 4,
        'account' => 7, 'permission' => 7,
    ];

    public function __construct(private readonly PermissionEngine $permissions) {}

    /**
     * @return array{ok: bool, blocking: list<string>, checks: list<array{key: string, label: string, ok: bool, blocking: bool, hint: string|null, step: int, route: string|null}>}
     */
    public function for(Lot $lot, ?User $seller): array
    {
        $lot->loadMissing(['media', 'documents', 'category.parent']);
        $meta = $lot->draft_meta ?? [];
        $unconfirmed = Provenance::unconfirmed($lot);

        $checks = [
            $this->check('title', $lot->title !== null && trim((string) $lot->title) !== ''),
            $this->check('category', $lot->category_id !== null),
            $this->check('forbidden', ! $this->isForbidden($lot)),
            $this->check('photos', $lot->media->where('type', MediaType::Image)->isNotEmpty()),
            $this->check('condition', $lot->condition !== null, blocking: false),
            $this->check('description', mb_strlen(trim((string) $lot->description)) >= 40, blocking: false),
            $this->check('pricing', $this->pricingComplete($meta)),
            $this->check('price_order', $this->priceOrderValid($meta)),
            $this->check('ownership', $this->ownershipShown($lot, $meta)),
            // De publicatiecriteria van Raph (2 november 2026): hoeveelheid
            // met eenheid, een garantieverklaring, en de echtheid van
            // aangeleverde documenten aangevinkt.
            $this->check('unit', (int) $lot->quantity >= 1 && $lot->unit !== null),
            $this->check('warranty', isset($meta['warranty']) && array_key_exists((string) $meta['warranty'], (array) config('listings.warranty_options'))),
            $this->check('documents_authentic', $lot->documents->isEmpty() || ($meta['documents_authentic'] ?? false) === true),
            $this->check('confirmed', $unconfirmed === [], hint: $unconfirmed === [] ? null : __('funnel.preflight.confirmed_hint', [
                'fields' => implode(', ', array_map(fn (string $field): string => __('funnel.fields.'.$field), $unconfirmed)),
            ])),
        ];

        if ($seller === null) {
            $checks[] = $this->check('account', false);
        } else {
            $decision = $this->permissions->decide($seller, Permission::Publish);
            $route = $decision->denied() ? $decision->reason->resolutionRoute() : null;

            $checks[] = $this->check(
                'permission',
                ! $decision->denied(),
                hint: $decision->denied() ? $decision->reason->message() : null,
                route: $route !== null ? route($route) : null,
            );
        }

        $blocking = array_values(array_map(
            fn (array $check): string => $check['key'],
            array_filter($checks, fn (array $check): bool => $check['blocking'] && ! $check['ok']),
        ));

        return ['ok' => $blocking === [], 'blocking' => $blocking, 'checks' => $checks];
    }

    /**
     * @return array{key: string, label: string, ok: bool, blocking: bool, hint: string|null, step: int, route: string|null}
     */
    private function check(string $key, bool $ok, bool $blocking = true, ?string $hint = null, ?string $route = null): array
    {
        return [
            'key' => $key,
            'label' => __('funnel.preflight.'.$key),
            'ok' => $ok,
            'blocking' => $blocking,
            'hint' => $ok ? null : ($hint ?? __('funnel.preflight.'.$key.'_hint')),
            'step' => self::STEPS[$key],
            'route' => $route,
        ];
    }

    /**
     * De lijst verboden categorieën is een besluit van Raphael; tot die er
     * is, is hij leeg en blokkeert dit punt niets. De slug van de categorie
     * of van haar ouder telt.
     */
    private function isForbidden(Lot $lot): bool
    {
        $forbidden = (array) config('aucvy.listings.forbidden_categories', []);

        if ($forbidden === [] || $lot->category === null) {
            return false;
        }

        return in_array($lot->category->slug, $forbidden, true)
            || in_array($lot->category->parent?->slug, $forbidden, true);
    }

    /**
     * Eigendom: de verklaring van de verkoper, of een eigendomsbewijs of
     * factuur als document.
     *
     * @param  array<string, mixed>  $meta
     */
    private function ownershipShown(Lot $lot, array $meta): bool
    {
        if (($meta['ownership_declared'] ?? false) === true) {
            return true;
        }

        return $lot->documents
            ->whereIn('kind', [DocumentKind::Ownership, DocumentKind::Invoice])
            ->isNotEmpty();
    }

    /**
     * Dezelfde volgorde-eisen als PublishLot::guardPriceOrder, zodat de knop
     * in de wizard niet groen is terwijl de server straks weigert: reserve
     * niet onder start, koop-direct boven start én reserve, de bodem van een
     * afslag niet onder de reserve en niet boven de startprijs.
     *
     * @param  array<string, mixed>  $meta
     */
    private function priceOrderValid(array $meta): bool
    {
        $saleType = SaleType::tryFrom((string) ($meta['sale_type'] ?? ''));
        $start = (int) ($meta['start_price_cents'] ?? 0);
        $reserve = (int) ($meta['reserve_price_cents'] ?? 0);
        $buyNow = (int) ($meta['buy_now_price_cents'] ?? 0);
        $floor = (int) ($meta['dutch_floor_cents'] ?? 0);
        $dutchStart = (int) ($meta['dutch_start_cents'] ?? 0);

        if ($saleType === SaleType::Auction && $reserve > 0 && $reserve < $start) {
            return false;
        }

        if ($buyNow > 0 && $saleType !== SaleType::Dutch && $buyNow < max($start, $reserve)) {
            return false;
        }

        if ($saleType === SaleType::Dutch && (($reserve > 0 && $floor < $reserve) || ($dutchStart > 0 && $floor > $dutchStart))) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function pricingComplete(array $meta): bool
    {
        return match (SaleType::tryFrom((string) ($meta['sale_type'] ?? ''))) {
            SaleType::Auction => ($meta['start_price_cents'] ?? 0) > 0,
            SaleType::BuyNow => ($meta['buy_now_price_cents'] ?? 0) > 0,
            SaleType::Dutch => ($meta['dutch_start_cents'] ?? 0) > 0
                && ($meta['dutch_floor_cents'] ?? 0) > 0
                && ($meta['dutch_step_cents'] ?? 0) > 0
                && ($meta['dutch_interval_s'] ?? 0) > 0
                && $meta['dutch_floor_cents'] <= $meta['dutch_start_cents'],
            null => false,
        };
    }
}
