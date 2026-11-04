<?php

declare(strict_types=1);

namespace App\Distribution\Publishers;

use App\Distribution\Channel;
use App\Models\ChannelConnection;
use App\Models\LotDistribution;

/**
 * Welke plaatser bij een bericht hoort: de echte koppeling van de verkoper
 * als die er is en werkt, anders de handmatige deelkit.
 */
final class PublisherFactory
{
    public function for(LotDistribution $distribution): ChannelPublisher
    {
        $connection = $this->connectionFor($distribution);

        if ($connection === null) {
            return new ManualPublisher;
        }

        return match ($connection->channel()) {
            Channel::Facebook => new FacebookPublisher($connection),
            Channel::Instagram => new InstagramPublisher($connection),
            Channel::LinkedIn => new LinkedInPublisher($connection),
            default => new ManualPublisher,
        };
    }

    public function connectionFor(LotDistribution $distribution): ?ChannelConnection
    {
        $lot = $distribution->lot;

        if ($lot === null) {
            return null;
        }

        $channel = $distribution->channel()->connection();

        return ChannelConnection::query()
            ->where('channel', $channel->value)
            ->where(fn ($query) => $query->where('user_id', $lot->seller_id)
                ->when($lot->company_id !== null, fn ($inner) => $inner->orWhere('company_id', $lot->company_id)))
            ->get()
            ->first(fn (ChannelConnection $connection) => $connection->isUsable());
    }
}
