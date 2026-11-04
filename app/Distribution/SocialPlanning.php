<?php

declare(strict_types=1);

namespace App\Distribution;

use App\Lots\Dossier\Dossier;
use App\Models\Lot;
use App\Models\LotActivity;
use App\Models\LotDistribution;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Social push per kavel: één planning met drie momenten, die de verkoper
 * in één keer goedkeurt en per bericht kan aanpassen. Bij sluiting gaan
 * de advertenties offline; dat regelt WithdrawLotFromChannels.
 */
final class SocialPlanning
{
    /** @var array<string, list<Channel>> moment => standaardkanalen */
    public const DEFAULTS = [
        LotDistribution::START => [Channel::Facebook, Channel::Instagram, Channel::Marktplaats],
        LotDistribution::DAY_BEFORE => [Channel::Facebook, Channel::Instagram, Channel::Story],
        LotDistribution::LAST_HOUR => [Channel::Facebook, Channel::Story],
    ];

    /**
     * Maakt de planning aan waar die nog ontbreekt en geeft alle geplande
     * berichten terug, op moment en kanaal.
     *
     * @return Collection<int, LotDistribution>
     */
    public function for(Lot $lot): Collection
    {
        $lot->loadMissing(['auction', 'category', 'location']);

        foreach (self::DEFAULTS as $moment => $channels) {
            $scheduledAt = $this->scheduledAt($lot, $moment);

            foreach ($channels as $channel) {
                LotDistribution::query()->firstOrCreate(
                    ['client_key' => LotDistribution::clientKey($lot, $channel, $moment)],
                    [
                        'lot_id' => $lot->id,
                        'channel' => $channel->value,
                        'moment' => $moment,
                        'status' => LotDistribution::DRAFT,
                        'body' => ChannelTexts::template($lot, $channel, $moment),
                        'body_source' => 'template',
                        'scheduled_at' => $scheduledAt,
                    ],
                );
            }
        }

        return $this->planned($lot);
    }

    /**
     * @return Collection<int, LotDistribution>
     */
    public function planned(Lot $lot): Collection
    {
        $momentOrder = array_flip(LotDistribution::MOMENTS);

        return LotDistribution::query()->where('lot_id', $lot->id)->whereIn('moment', LotDistribution::MOMENTS)->get()
            ->sortBy(fn (LotDistribution $row) => ($momentOrder[$row->moment] ?? 9).'-'.$row->channel)->values();
    }

    public function scheduledAt(Lot $lot, string $moment): ?CarbonInterface
    {
        $ends = $lot->auction?->ends_at;

        return match ($moment) {
            LotDistribution::START => $lot->published_at ?? ($lot->auction?->starts_at ?? now()),
            LotDistribution::DAY_BEFORE => $ends?->copy()->subDay(),
            LotDistribution::LAST_HOUR => $ends?->copy()->subHour(),
            default => null,
        };
    }

    /**
     * Eén akkoord op de hele planning: alles wat nog concept is, staat
     * daarna ingepland. Momenten die al voorbij zijn, blijven concept.
     */
    public function approve(Lot $lot, User $seller): int
    {
        $approved = 0;

        foreach ($this->planned($lot) as $row) {
            if ($row->status !== LotDistribution::DRAFT) {
                continue;
            }

            $scheduledAt = $this->scheduledAt($lot, $row->moment);

            if ($scheduledAt === null || ($row->moment !== LotDistribution::START && $scheduledAt->isPast())) {
                continue;
            }

            $row->forceFill([
                'status' => LotDistribution::SCHEDULED,
                'scheduled_at' => $row->moment === LotDistribution::START ? max($scheduledAt, now()) : $scheduledAt,
                'approved_by' => $seller->id,
                'approved_at' => now(),
            ])->save();

            $approved++;
        }

        if ($approved > 0) {
            Dossier::record(
                lot: $lot->id,
                source: LotActivity::SOURCE_DISTRIBUTION,
                type: 'planned',
                visibility: LotActivity::SELLER,
                payload: ['count' => $approved],
                actor: $seller,
                ref: "planning:{$lot->id}:".now()->timestamp,
            );
        }

        return $approved;
    }

    public function skip(LotDistribution $distribution): void
    {
        if (in_array($distribution->status, [LotDistribution::DRAFT, LotDistribution::SCHEDULED], true)) {
            $distribution->forceFill(['status' => LotDistribution::OFFLINE, 'withdrawn_at' => now()])->save();
        }
    }
}
