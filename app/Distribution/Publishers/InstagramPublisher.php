<?php

declare(strict_types=1);

namespace App\Distribution\Publishers;

use App\Distribution\Channel;
use App\Models\ChannelConnection;
use App\Models\LotDistribution;
use Illuminate\Support\Facades\Http;

/**
 * Instagram via Content Publishing: eerst een container, dan publiceren.
 * Alleen professionele accounts; reken met 50 berichten per 24 uur.
 * Verwijderen kan de API niet; offline halen is daar handwerk.
 */
final class InstagramPublisher implements ChannelPublisher
{
    private const string GRAPH = 'https://graph.facebook.com/v21.0';

    public function __construct(private readonly ChannelConnection $connection) {}

    public function publish(LotDistribution $distribution, string $body, string $visualUrl): PublishResult
    {
        $account = $this->connection->external_id;
        $token = $this->connection->token();

        if ($account === null || $token === null) {
            return PublishResult::failed('Geen Instagram-account gekoppeld.');
        }

        $container = Http::timeout(20)->asForm()->post(self::GRAPH."/{$account}/media", array_filter([
            'image_url' => $visualUrl,
            'caption' => $body,
            'media_type' => $distribution->channel() === Channel::Story ? 'STORIES' : null,
            'access_token' => $token,
        ]));

        if (! $container->successful()) {
            return PublishResult::failed((string) ($container->json('error.message') ?? 'Instagram antwoordde met '.$container->status()));
        }

        $publish = Http::timeout(20)->asForm()->post(self::GRAPH."/{$account}/media_publish", [
            'creation_id' => (string) $container->json('id'),
            'access_token' => $token,
        ]);

        if (! $publish->successful()) {
            return PublishResult::failed((string) ($publish->json('error.message') ?? 'Instagram antwoordde met '.$publish->status()));
        }

        return PublishResult::posted((string) $publish->json('id'));
    }

    public function withdraw(LotDistribution $distribution): void
    {
        // De Instagram-API kent geen verwijderen; de verkoper haalt het bericht zelf weg.
    }
}
