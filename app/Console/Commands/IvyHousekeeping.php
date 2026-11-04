<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\LotStatus;
use App\Ivy\Alerts\Alerts;
use App\Ivy\Chat\Proposals;
use App\Lots\Interest\Interest;
use App\Models\IvyAlert;
use App\Models\IvyConversation;
use App\Models\IvyGuestUpload;
use App\Models\Lot;
use App\Support\Money\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * De planner van Ivy (Ivy-briefing hfst. 8): ExpireProposals en
 * CheckReserveWarnings elke minuut, SendSellerDigest dagelijks om 18:00,
 * gastfoto's opruimen na de termijn. Eén opdracht met een taak-argument.
 */
final class IvyHousekeeping extends Command
{
    protected $signature = 'aucvy:ivy {task : expire|reserve|digest|prune|idle}';

    protected $description = 'Planmatige taken van Ivy: voorstellen verlopen, reservewaarschuwing, dagoverzicht, gastfoto\'s opruimen, stilstaande concepten';

    public function handle(Proposals $proposals, Alerts $alerts): int
    {
        $count = match ($this->argument('task')) {
            'expire' => $proposals->expireOpen(),
            'reserve' => $this->reserveWarnings($alerts),
            'digest' => $this->sellerDigest($alerts),
            'prune' => $this->prune(),
            'idle' => $this->idleDrafts($alerts),
            default => -1,
        };

        if ($count < 0) {
            $this->error('Onbekende taak.');

            return self::FAILURE;
        }

        $this->info("{$count} verwerkt.");

        return self::SUCCESS;
    }

    /**
     * "Jouw beslissing": reserve niet gehaald, 30 minuten voor sluiting, alleen naar de eigenaar.
     */
    private function reserveWarnings(Alerts $alerts): int
    {
        $count = 0;
        $lots = Lot::query()->live()->with(['auction', 'seller'])
            ->whereHas('auction', fn ($query) => $query->whereNull('closed_at')->whereNotNull('reserve_price_cents')->whereColumn('current_price_cents', '<', 'reserve_price_cents')->whereBetween('ends_at', [now(), now()->addMinutes(30)]))
            ->get();

        foreach ($lots as $lot) {
            if ($lot->seller === null) {
                continue;
            }

            $alerts->raise(
                user: $lot->seller,
                key: 'reserve_not_met',
                role: IvyConversation::SELLER,
                payload: ['title' => 'Jouw beslissing: '.$lot->title, 'body' => 'Nog '.(int) now()->diffInMinutes($lot->auction->ends_at).' minuten. Hoogste bod '.Money::format($lot->auction->current_price_cents).', reserve '.Money::format($lot->auction->reserve_price_cents).'.', 'url' => route('dashboard.lots.show', $lot)],
                choices: [['key' => 'lower_reserve', 'label' => 'Verlaag reserve', 'primary' => true, 'url' => route('dashboard.lots.edit', $lot)], ['key' => 'keep', 'label' => 'Laat staan']],
                channels: ['inapp', 'push'],
                lot: $lot,
                expiresAt: $lot->auction->ends_at,
                urgent: true,
            );
            $count++;
        }

        return $count;
    }

    /**
     * "Interesse": dagelijks om 18:00 per lopende kavel, één keer per dag.
     */
    private function sellerDigest(Alerts $alerts): int
    {
        $count = 0;

        foreach (Lot::query()->live()->with(['auction', 'seller'])->get() as $lot) {
            if ($lot->seller === null) {
                continue;
            }

            $stats = Interest::forSeller($lot);
            $alerts->raise(
                user: $lot->seller,
                key: 'interest',
                role: IvyConversation::SELLER,
                payload: ['title' => 'Zo liep het vandaag: '.$lot->title, 'body' => sprintf('%d kijkers, %d volgers, %d bieders. Hoogste bod %s.', $stats['viewers'], $stats['saved'], $stats['bidders'], Money::format($lot->currentPriceCents())), 'url' => route('dashboard.lots.show', $lot)],
                choices: [['key' => 'view', 'label' => 'Bekijk', 'primary' => true, 'url' => route('dashboard.lots.show', $lot)], ['key' => 'tips', 'label' => 'Tips voor meer bereik', 'url' => route('dashboard.lots.show', [$lot, 'tab' => 'verspreiding'])]],
                channels: ['inapp'],
                lot: $lot,
                level: IvyAlert::INFO,
                dedupe: $lot->seller_id.':interest:'.$lot->id.':'.now()->toDateString(),
            );
            $count++;
        }

        return $count;
    }

    /**
     * "Nog te doen": concept 24 uur stil.
     */
    private function idleDrafts(Alerts $alerts): int
    {
        $count = 0;
        $lots = Lot::query()->where('status', LotStatus::Draft)->whereNotNull('seller_id')->where('updated_at', '<=', now()->subDay())->where('created_at', '>=', now()->subDays(30))->with('seller')->get();

        foreach ($lots as $lot) {
            if ($lot->seller === null) {
                continue;
            }

            $alerts->raise(
                user: $lot->seller,
                key: 'draft_idle',
                role: IvyConversation::SELLER,
                payload: ['title' => 'Nog te doen: '.($lot->title ?: 'je concept'), 'body' => 'Je concept staat al een dag stil. Nog een paar dingen, dan kan hij live.', 'url' => route('dashboard.lots.edit', $lot)],
                choices: [['key' => 'fill', 'label' => 'Nu invullen', 'primary' => true, 'url' => route('dashboard.lots.edit', $lot)], ['key' => 'later', 'label' => 'Later herinneren']],
                channels: ['inapp'],
                lot: $lot,
            );
            $count++;
        }

        return $count;
    }

    private function prune(): int
    {
        $count = 0;

        foreach (IvyGuestUpload::query()->where('expires_at', '<=', now())->whereNull('claimed_listing_id')->get() as $upload) {
            Storage::disk('local')->delete($upload->path);
            $upload->delete();
            $count++;
        }

        IvyConversation::query()->whereNull('user_id')->where('updated_at', '<=', now()->subDays((int) config('aucvy.ivy.guest_retention_days', 30)))->delete();

        return $count;
    }
}
