<?php

declare(strict_types=1);

namespace App\Distribution\Publishers;

use App\Models\ChannelConnection;
use App\Models\LotDistribution;
use Illuminate\Support\Facades\Http;

/**
 * LinkedIn via Share on LinkedIn: een bericht met link namens de
 * ingelogde persoon. Bedrijfspagina's vragen een aparte goedkeuring.
 */
final class LinkedInPublisher implements ChannelPublisher
{
    private const string API = 'https://api.linkedin.com/v2';

    public function __construct(private readonly ChannelConnection $connection) {}

    public function publish(LotDistribution $distribution, string $body, string $visualUrl): PublishResult
    {
        $person = $this->connection->external_id;
        $token = $this->connection->token();

        if ($person === null || $token === null) {
            return PublishResult::failed('Geen LinkedIn-profiel gekoppeld.');
        }

        $response = Http::timeout(20)->withToken($token)->withHeaders(['X-Restli-Protocol-Version' => '2.0.0'])->post(self::API.'/ugcPosts', [
            'author' => "urn:li:person:{$person}",
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => ['text' => $body],
                    'shareMediaCategory' => 'ARTICLE',
                    'media' => [[
                        'status' => 'READY',
                        'originalUrl' => $distribution->lot ? route('lots.show', $distribution->lot) : $visualUrl,
                        'thumbnails' => [['url' => $visualUrl]],
                    ]],
                ],
            ],
            'visibility' => ['com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'],
        ]);

        if (! $response->successful()) {
            return PublishResult::failed((string) ($response->json('message') ?? 'LinkedIn antwoordde met '.$response->status()));
        }

        $id = (string) ($response->header('X-RestLi-Id') ?: $response->json('id'));

        return PublishResult::posted($id, 'https://www.linkedin.com/feed/update/'.$id);
    }

    public function withdraw(LotDistribution $distribution): void
    {
        $token = $this->connection->token();

        if ($distribution->external_id === null || $token === null) {
            return;
        }

        Http::timeout(20)->withToken($token)->withHeaders(['X-Restli-Protocol-Version' => '2.0.0'])
            ->delete(self::API.'/ugcPosts/'.rawurlencode($distribution->external_id))->throw();
    }
}
