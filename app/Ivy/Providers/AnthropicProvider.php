<?php

declare(strict_types=1);

namespace App\Ivy\Providers;

use Anthropic\Client;
use App\Ivy\AiResult;
use App\Ivy\Contracts\AiTask;
use App\Ivy\Contracts\LlmProvider;

/**
 * De echte multimodale API, via de officiële Anthropic PHP SDK.
 *
 * Bewust een dun laagje: prompt en foto's erin, ruwe tekst eruit. Schema-
 * validatie en het frasenfilter zitten in de guards - die mogen niet per
 * provider verschillen. Draait alleen met ANTHROPIC_API_KEY gezet en
 * AUCVY_AI_PROVIDER=anthropic.
 */
final class AnthropicProvider implements LlmProvider
{
    /** Wat de Messages API als foto accepteert, en tot welke grootte. */
    private const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    private const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

    /**
     * Een advertentie of vertaling van een lange omschrijving past niet in
     * 2048 tokens; afgekapte JSON is een mislukte (en betaalde) run.
     */
    private const LONG_TEXT_TASKS = ['draft_listing', 'translate_listing'];

    public function complete(AiTask $task, string $prompt): AiResult
    {
        $client = new Client(apiKey: (string) config('services.anthropic.key'));
        $model = (string) config('aucvy.ai.model');

        $content = [];

        // Maximaal acht foto's mee: genoeg voor herkenning, en het houdt de
        // tokenkosten van een fotorijk concept begrensd.
        foreach (array_slice($task->images(), 0, 8) as $path) {
            if (! is_file($path) || filesize($path) > self::MAX_IMAGE_BYTES) {
                continue;
            }

            // De API neemt alleen jpeg, png, gif en webp aan; één heic in de
            // reeks zou anders élke analyse van dit concept laten mislukken.
            $mime = (string) (mime_content_type($path) ?: '');

            if (! in_array($mime, self::IMAGE_TYPES, true)) {
                continue;
            }

            $content[] = [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'mediaType' => $mime,
                    'data' => base64_encode((string) file_get_contents($path)),
                ],
            ];
        }

        // Documentcontrole: de pdf's zelf gaan mee. Maximaal vijf en niet
        // groter dan 4 MB per stuk - genoeg voor facturen en bewijzen,
        // begrensd tegen een handleiding van 300 pagina's.
        foreach (array_slice($task->documents(), 0, 5) as $path) {
            if (! is_file($path) || filesize($path) > 4 * 1024 * 1024) {
                continue;
            }

            $content[] = [
                'type' => 'document',
                'source' => [
                    'type' => 'base64',
                    'mediaType' => 'application/pdf',
                    'data' => base64_encode((string) file_get_contents($path)),
                ],
            ];
        }

        $content[] = ['type' => 'text', 'text' => $prompt];

        $message = $client->messages->create(
            model: $model,
            maxTokens: in_array($task->name(), self::LONG_TEXT_TASKS, true) ? 8192 : 2048,
            system: [[
                'type' => 'text',
                'text' => 'Je bent Ivy, de analyse-assistent van veilingplatform Aucvy. '
                    .'Je antwoordt uitsluitend met één JSON-object dat exact aan het '
                    .'gevraagde schema voldoet - geen tekst eromheen, geen codehekken. '
                    .'Je doet nooit garanties of beloften namens Aucvy en je noemt geen '
                    .'escrow of keuring. Alle tekstwaarden zijn in het Nederlands.',
            ]],
            messages: [['role' => 'user', 'content' => $content]],
        );

        $text = '';

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return new AiResult(
            text: $text,
            model: $model,
            tokensIn: $message->usage->inputTokens,
            tokensOut: $message->usage->outputTokens,
        );
    }
}
