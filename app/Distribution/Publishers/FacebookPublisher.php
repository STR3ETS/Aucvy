<?php

declare(strict_types=1);

namespace App\Distribution\Publishers;

use App\Models\ChannelConnection;
use App\Models\LotDistribution;
use Illuminate\Support\Facades\Http;

/**
 * Facebook via de Graph API: een foto met tekst op een bedrijfspagina,
 * nooit op een persoonlijk profiel. Vraagt een goedgekeurde app.
 */
final class FacebookPublisher implements ChannelPublisher
{
    private const string GRAPH = 'https://graph.facebook.com/v21.0';

    public function __construct(private readonly ChannelConnection $connection) {}

    public function publish(LotDistribution $distribution, string $body, string $visualUrl): PublishResult
    {
        $page = $this->connection->external_id;
        $token = $this->connection->token('page_token') ?? $this->connection->token();

        if ($page === null || $token === null) {
            return PublishResult::failed('Geen bedrijfspagina gekoppeld.');
        }

        $response = Http::timeout(20)->asForm()->post(self::GRAPH."/{$page}/photos", [
            'url' => $visualUrl,
            'message' => $body,
            'access_token' => $token,
        ]);

        if (! $response->successful()) {
            return PublishResult::failed((string) ($response->json('error.message') ?? 'Facebook antwoordde met '.$response->status()));
        }

        $id = (string) ($response->json('post_id') ?? $response->json('id'));

        return PublishResult::posted($id, 'https://www.facebook.com/'.$id);
    }

    public function withdraw(LotDistribution $distribution): void
    {
        $token = $this->connection->token('page_token') ?? $this->connection->token();

        if ($distribution->external_id === null || $token === null) {
            return;
        }

        Http::timeout(20)->delete(self::GRAPH."/{$distribution->external_id}", ['access_token' => $token])->throw();
    }
}
