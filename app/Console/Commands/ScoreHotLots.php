<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SaleType;
use App\Lots\Dossier\Dossier;
use App\Lots\Interest\Interest;
use App\Models\Lot;
use App\Models\LotActivity;
use App\Models\LotSignal;
use Illuminate\Console\Command;

/**
 * Elk uur: de Hot-score van de afgelopen 48 uur per kavel, en Hot voor
 * wie bij de hoogste 10% van zijn categorie hoort met minimaal 50 kijkers
 * en, bij een veiling, 3 bieders. Drempels staan in de instellingen.
 */
final class ScoreHotLots extends Command
{
    protected $signature = 'aucvy:hot-score';

    protected $description = 'Berekent de interesse-score en het Hot-label per kavel';

    public function handle(): int
    {
        $lots = Lot::query()->public()->with('auction')->get();
        $since = now()->subHours((int) config('aucvy.interest.window_hours', 48));
        $minViewers = (int) config('aucvy.interest.min_viewers', 50);
        $minBidders = (int) config('aucvy.interest.min_bidders', 3);
        $fraction = (float) config('aucvy.interest.top_fraction', 0.10);
        $hot = 0;

        $scored = $lots->map(fn (Lot $lot) => ['lot' => $lot, 'score' => Interest::score($lot)]);

        foreach ($scored->groupBy(fn (array $row) => (string) $row['lot']->category_id) as $group) {
            $sorted = $group->sortByDesc('score')->values();
            $cutoff = max(1, (int) ceil($sorted->count() * $fraction));

            foreach ($sorted as $index => $row) {
                /** @var Lot $lot */
                $lot = $row['lot'];
                $score = (int) $row['score'];

                $viewers = (int) LotSignal::query()->where('lot_id', $lot->id)->where('kind', LotSignal::VIEWER)->where('occurred_at', '>=', $since)->distinct()->count('visitor_hash');
                $bidders = Interest::bidders($lot, $since);
                $needsBidders = $lot->sale_type === SaleType::Auction;

                $isHot = $index < $cutoff && $score > 0 && $viewers >= $minViewers && (! $needsBidders || $bidders >= $minBidders);

                $wasHot = (bool) $lot->is_hot;
                $lot->forceFill([
                    'interest_score' => $score,
                    'is_hot' => $isHot,
                    'hot_since' => $isHot ? ($lot->hot_since ?? now()) : null,
                ])->saveQuietly();

                if ($isHot && ! $wasHot) {
                    Dossier::record(
                        lot: $lot->id,
                        source: LotActivity::SOURCE_LOT,
                        type: 'hot',
                        visibility: LotActivity::SELLER,
                        payload: ['score' => $score, 'viewers' => $viewers, 'bidders' => $bidders],
                        ref: "hot:{$lot->id}:".now()->format('YmdH'),
                        actorType: 'system',
                    );
                }

                $hot += $isHot ? 1 : 0;
            }
        }

        $this->info("{$lots->count()} kavels gescoord, {$hot} Hot.");

        return self::SUCCESS;
    }
}
