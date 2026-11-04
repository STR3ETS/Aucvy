<?php

declare(strict_types=1);

namespace App\Distribution;

use App\Lots\Dossier\Dossier;
use App\Models\Lot;
use App\Models\LotActivity;
use App\Models\LotDistribution;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * De deelkit van de verkoper (fase 1): per kanaal een tekst, het beeld in
 * drie formaten en een link met UTM. De verkoper plaatst zelf; "Ik heb dit
 * geplaatst" zet de status en de klikken komen uit de UTM.
 */
final class ShareKit
{
    /**
     * @return Collection<int, LotDistribution>
     */
    public function for(Lot $lot): Collection
    {
        $lot->loadMissing(['auction', 'category', 'location']);

        foreach (Channel::KIT as $channel) {
            LotDistribution::query()->firstOrCreate(
                ['client_key' => LotDistribution::clientKey($lot, $channel, LotDistribution::KIT)],
                [
                    'lot_id' => $lot->id,
                    'channel' => $channel->value,
                    'moment' => LotDistribution::KIT,
                    'status' => LotDistribution::DRAFT,
                    'body' => ChannelTexts::template($lot, $channel, LotDistribution::KIT),
                    'body_source' => 'template',
                ],
            );
        }

        $order = array_flip(array_map(fn (Channel $channel) => $channel->value, Channel::KIT));

        return LotDistribution::query()->where('lot_id', $lot->id)->where('moment', LotDistribution::KIT)->get()
            ->sortBy(fn (LotDistribution $row) => $order[$row->channel] ?? 99)->values();
    }

    /**
     * De link met herkomst: kanaal, deelkit of social push, en de kavel.
     */
    public static function link(Lot $lot, Channel $channel, string $moment = LotDistribution::KIT): string
    {
        return route('lots.show', $lot).'?'.http_build_query([
            'utm_source' => $channel->value,
            'utm_medium' => $moment === LotDistribution::KIT ? 'deelkit' : 'social',
            'utm_campaign' => 'kavel-'.$lot->public_id,
            'utm_content' => $moment,
        ]);
    }

    public function updateText(LotDistribution $distribution, string $body): void
    {
        $distribution->forceFill(['body' => trim($body), 'body_source' => 'seller'])->save();
    }

    /**
     * De verkoper heeft het zelf geplaatst: status, tijd en eventueel de
     * link naar het bericht. In het dossier als "geplaatst op".
     */
    public function markPosted(LotDistribution $distribution, User $seller, ?string $url = null): void
    {
        $distribution->forceFill([
            'status' => LotDistribution::POSTED,
            'posted_at' => now(),
            'withdrawn_at' => null,
            'url' => $url,
            'last_error' => null,
        ])->save();

        Dossier::record(
            lot: $distribution->lot_id,
            source: LotActivity::SOURCE_DISTRIBUTION,
            type: 'posted',
            visibility: LotActivity::SELLER,
            payload: ['channel' => $distribution->channelLabel(), 'moment' => $distribution->moment, 'by' => 'seller'],
            actor: $seller,
            ref: "distribution:{$distribution->id}:posted:".now()->timestamp,
        );
    }

    public function markWithdrawn(LotDistribution $distribution, User $seller): void
    {
        $distribution->forceFill(['status' => LotDistribution::OFFLINE, 'withdrawn_at' => now()])->save();

        Dossier::record(
            lot: $distribution->lot_id,
            source: LotActivity::SOURCE_DISTRIBUTION,
            type: 'withdrawn',
            visibility: LotActivity::SELLER,
            payload: ['channel' => $distribution->channelLabel()],
            actor: $seller,
            ref: "distribution:{$distribution->id}:withdrawn:".now()->timestamp,
        );
    }

    /**
     * Een bezoek via een link met UTM telt als klik bij het kanaal; één
     * keer per sessie, alleen echte cijfers.
     */
    public static function recordClick(Lot $lot, string $source, ?string $content, ?string $sessionKey = null): void
    {
        $channel = Channel::tryFrom($source);

        if ($channel === null) {
            return;
        }

        $moment = $content !== null && (in_array($content, LotDistribution::MOMENTS, true) || $content === LotDistribution::KIT) ? $content : LotDistribution::KIT;
        $distribution = LotDistribution::query()->where('lot_id', $lot->id)->where('channel', $channel->value)->where('moment', $moment)->first();

        if ($distribution === null) {
            return;
        }

        if ($sessionKey !== null) {
            $key = "click:{$distribution->id}";

            if (session()->has($key)) {
                return;
            }

            session()->put($key, true);
        }

        $distribution->increment('clicks');
    }
}
