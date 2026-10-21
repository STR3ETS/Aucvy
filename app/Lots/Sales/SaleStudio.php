<?php

declare(strict_types=1);

namespace App\Lots\Sales;

use App\Enums\LotStatus;
use App\Enums\SaleState;
use App\Lots\PublicId;
use App\Lots\PublishLot;
use App\Models\Lot;
use App\Models\Sale;
use App\Models\User;
use App\Support\Audit\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Auction Studio (briefing sectie 4): een veiling plannen, kavels erin
 * zetten, publiceren en sluiten. De kavels zelf gaan door de gewone
 * publicatie (wizard, preflight); de veiling bepaalt alleen hun sluittijd.
 */
final class SaleStudio
{
    public const MIN_LEAD_MINUTES = 60;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $seller, array $data): Sale
    {
        $publicId = PublicId::fresh();

        $sale = Sale::create([
            ...$this->fields($data),
            'public_id' => $publicId,
            'seller_id' => $seller->id,
            'company_id' => $seller->company_id,
            'slug' => Str::slug((string) $data['title']).'-'.$publicId,
        ]);

        Audit::record('sale.created', $sale, $seller, after: ['title' => $sale->title, 'ends_at' => $sale->ends_at->toIso8601String()]);

        return $sale;
    }

    /**
     * Wijzigt de gegevens; schuift het sluitschema mee voor kavels zonder
     * bod. Waar al geboden is blijft de sluittijd staan: bieders rekenden erop.
     *
     * @param  array<string, mixed>  $data
     * @return array{sale: Sale, rescheduled: int, kept: int}
     */
    public function update(Sale $sale, User $actor, array $data): array
    {
        $before = ['title' => $sale->title, 'ends_at' => $sale->ends_at->toIso8601String(), 'closing_gap_seconds' => $sale->closing_gap_seconds];
        $fields = $this->fields($data);

        if ($fields['ends_at']->lessThan(now()->addMinutes(self::MIN_LEAD_MINUTES))) {
            throw ValidationException::withMessages(['ends_at' => __('sales.errors.ends_too_soon', ['minutes' => self::MIN_LEAD_MINUTES])]);
        }

        $sale->fill($fields)->save();

        $rescheduled = 0;
        $kept = 0;

        // Alleen een gepubliceerde veiling heeft een schema dat telt.
        foreach ($sale->isPublished() ? $sale->lots()->with('auction')->get() : [] as $lot) {
            if ($lot->auction === null || $lot->auction->closed_at !== null) {
                continue;
            }

            if ($lot->auction->bid_count > 0) {
                $kept++;

                continue;
            }

            $closing = $sale->closingFor((int) $lot->sale_position);
            $lot->auction->forceFill(['ends_at' => $closing, 'original_ends_at' => $closing])->save();
            $rescheduled++;
        }

        Audit::record('sale.updated', $sale, $actor, $before, ['title' => $sale->title, 'ends_at' => $sale->ends_at->toIso8601String(), 'closing_gap_seconds' => $sale->closing_gap_seconds, 'rescheduled' => $rescheduled]);

        return ['sale' => $sale, 'rescheduled' => $rescheduled, 'kept' => $kept];
    }

    public function attach(Sale $sale, User $actor, Lot $lot): void
    {
        if (! $lot->isManagedBy($actor) || ! in_array($lot->status, [LotStatus::Draft, LotStatus::Scheduled], true)) {
            throw ValidationException::withMessages(['lot' => __('sales.errors.not_attachable')]);
        }

        if ($lot->sale_id === $sale->id) {
            return;
        }

        if ($lot->sale_id !== null) {
            throw ValidationException::withMessages(['lot' => __('sales.errors.in_other_sale')]);
        }

        if ($sale->isCancelled()) {
            throw ValidationException::withMessages(['lot' => __('sales.errors.cancelled')]);
        }

        $position = (int) $sale->lots()->max('sale_position') + ($sale->lots()->exists() ? 1 : 0);
        $lot->forceFill(['sale_id' => $sale->id, 'sale_position' => $position])->save();

        Audit::record('sale.lot_attached', $sale, $actor, after: ['lot_id' => $lot->id, 'position' => $position]);
    }

    /**
     * Meerdere kavels in één keer, of geen enkele: één fout en niets verandert.
     *
     * @param  iterable<int, Lot>  $lots
     */
    public function attachMany(Sale $sale, User $actor, iterable $lots): int
    {
        return DB::transaction(function () use ($sale, $actor, $lots): int {
            $count = 0;

            foreach ($lots as $lot) {
                $this->attach($sale, $actor, $lot);
                $count++;
            }

            return $count;
        });
    }

    public function detach(Sale $sale, User $actor, Lot $lot): void
    {
        abort_unless($lot->sale_id === $sale->id, 404);

        $lot->forceFill(['sale_id' => null, 'sale_position' => null])->save();
        $this->renumber($sale);

        Audit::record('sale.lot_detached', $sale, $actor, after: ['lot_id' => $lot->id]);
    }

    /**
     * Eén plek omhoog of omlaag; de sluittijden van kavels zonder bod volgen.
     */
    public function move(Sale $sale, User $actor, Lot $lot, int $delta): void
    {
        abort_unless($lot->sale_id === $sale->id, 404);

        $ids = $sale->lots()->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $index = array_search($lot->id, $ids, true);
        $target = $index + $delta;

        if ($index === false || $target < 0 || $target >= count($ids)) {
            return;
        }

        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];

        DB::transaction(function () use ($sale, $ids): void {
            foreach ($ids as $position => $id) {
                Lot::query()->whereKey($id)->update(['sale_position' => $position]);
            }

            $this->reschedule($sale);
        });
    }

    public function publish(Sale $sale, User $actor): Sale
    {
        if ($sale->isPublished()) {
            return $sale;
        }

        if ($sale->isCancelled()) {
            throw ValidationException::withMessages(['sale' => __('sales.errors.cancelled')]);
        }

        if ($sale->ends_at->lessThan(now()->addMinutes(self::MIN_LEAD_MINUTES))) {
            throw ValidationException::withMessages(['ends_at' => __('sales.errors.ends_too_soon', ['minutes' => self::MIN_LEAD_MINUTES])]);
        }

        if (! $sale->lots()->exists()) {
            throw ValidationException::withMessages(['sale' => __('sales.errors.no_lots')]);
        }

        $sale->forceFill(['published_at' => now()])->save();

        // Kavels die al los liepen krijgen nu het schema, zolang er niet geboden is.
        $this->reschedule($sale);

        Audit::record('sale.published', $sale, $actor, after: ['lots' => $sale->lots()->count(), 'ends_at' => $sale->ends_at->toIso8601String()]);

        return $sale;
    }

    /**
     * Alle concepten in de veiling door de gewone publicatie halen. Wat de
     * preflight niet haalt blijft concept, met de reden erbij.
     *
     * @return array{published: int, failed: array<int, string>}
     */
    public function publishDrafts(Sale $sale, User $actor, PublishLot $publish, ?string $ip = null): array
    {
        if (! $sale->isPublished()) {
            throw ValidationException::withMessages(['sale' => __('sales.errors.not_published')]);
        }

        if ($sale->state() === SaleState::Closed) {
            throw ValidationException::withMessages(['sale' => __('sales.errors.closed')]);
        }

        $published = 0;
        $failed = [];

        foreach ($sale->lots()->where('status', LotStatus::Draft)->get() as $lot) {
            try {
                $publish->handle($lot, $actor, $ip);
                $published++;
            } catch (ValidationException $exception) {
                $failed[$lot->id] = (string) collect($exception->errors())->flatten()->first();
            }
        }

        return ['published' => $published, 'failed' => $failed];
    }

    public function cancel(Sale $sale, User $actor): void
    {
        $withBids = $sale->lots()->whereHas('auction', fn ($query) => $query->where('bid_count', '>', 0)->whereNull('closed_at'))->exists();

        if ($withBids) {
            throw ValidationException::withMessages(['sale' => __('sales.errors.has_bids')]);
        }

        $sale->forceFill(['cancelled_at' => now()])->save();

        // Concepten gaan weer los; lopende kavels zonder bod lopen gewoon door.
        $sale->lots()->where('status', LotStatus::Draft)->update(['sale_id' => null, 'sale_position' => null]);

        Audit::record('sale.cancelled', $sale, $actor);
    }

    /**
     * De sluittijd die een kavel bij publicatie krijgt als hij in een
     * gepubliceerde veiling zit; anders null en geldt de gewone looptijd.
     */
    public static function closingFor(Lot $lot): ?CarbonImmutable
    {
        $sale = $lot->sale;

        if ($sale === null || ! $sale->isPublished()) {
            return null;
        }

        $closing = $sale->closingFor((int) $lot->sale_position);

        return $closing->greaterThan(now()->addMinutes(10)) ? $closing : null;
    }

    /**
     * Eigen concepten die nog in een veiling kunnen.
     *
     * @return Collection<int, Lot>
     */
    public function attachableFor(User $user): Collection
    {
        return Lot::query()
            ->whereIn('seller_id', $user->lotSellerIds())
            ->whereNull('sale_id')
            ->whereIn('status', [LotStatus::Draft, LotStatus::Scheduled])
            ->latest('updated_at')
            ->limit(100)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fields(array $data): array
    {
        return [
            'title' => trim((string) $data['title']),
            'description' => isset($data['description']) && trim((string) $data['description']) !== '' ? trim((string) $data['description']) : null,
            'ends_at' => CarbonImmutable::parse((string) $data['ends_at'], config('aucvy.display_timezone'))->utc(),
            'closing_gap_seconds' => (int) ($data['closing_gap_seconds'] ?? 120),
            'viewing_at' => isset($data['viewing_at']) && $data['viewing_at'] !== '' ? CarbonImmutable::parse((string) $data['viewing_at'], config('aucvy.display_timezone'))->utc() : null,
            'viewing_note' => $data['viewing_note'] ?? null,
            'pickup_from' => isset($data['pickup_from']) && $data['pickup_from'] !== '' ? $data['pickup_from'] : null,
            'pickup_until' => isset($data['pickup_until']) && $data['pickup_until'] !== '' ? $data['pickup_until'] : null,
            'pickup_note' => $data['pickup_note'] ?? null,
            'terms' => isset($data['terms']) && trim((string) $data['terms']) !== '' ? trim((string) $data['terms']) : null,
        ];
    }

    private function renumber(Sale $sale): void
    {
        foreach ($sale->lots()->pluck('id') as $position => $id) {
            Lot::query()->whereKey($id)->update(['sale_position' => $position]);
        }

        $this->reschedule($sale);
    }

    private function reschedule(Sale $sale): void
    {
        if (! $sale->isPublished()) {
            return;
        }

        foreach ($sale->lots()->with('auction')->get() as $lot) {
            $auction = $lot->auction;

            if ($auction === null || $auction->closed_at !== null || $auction->bid_count > 0) {
                continue;
            }

            $closing = $sale->closingFor((int) $lot->sale_position);
            $auction->forceFill(['ends_at' => $closing, 'original_ends_at' => $closing])->save();
        }
    }
}
