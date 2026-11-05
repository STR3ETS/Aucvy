<?php

declare(strict_types=1);

namespace App\Ivy\Chat;

use Anthropic\Client;

/**
 * De echte Ivy: Claude via de Messages API met tools. Een goedkoper model
 * voor eenvoudige vragen, het sterkere voor advies en foto's; één plek in
 * de config. Het EU-endpoint volgt de instelling van de SDK-client.
 */
final class AnthropicChatProvider implements ChatProvider
{
    public function respond(string $system, array $messages, array $tools, string $tier = 'fast'): ChatResponse
    {
        $client = new Client(apiKey: (string) config('services.anthropic.key'));
        $model = (string) ($tier === 'strong' ? config('aucvy.ai.model') : config('aucvy.ai.chat_model_fast', config('aucvy.ai.model')));

        $message = $client->messages->create(
            model: $model,
            maxTokens: 1500,
            system: [['type' => 'text', 'text' => $system]],
            messages: $this->forApi($messages),
            tools: $tools === [] ? null : $tools,
        );

        $text = '';
        $calls = [];

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }

            if ($block->type === 'tool_use') {
                $calls[] = ['id' => (string) $block->id, 'name' => (string) $block->name, 'input' => (array) $block->input];
            }
        }

        return new ChatResponse($text, $calls, $model, (int) $message->usage->inputTokens, (int) $message->usage->outputTokens);
    }

    /**
     * De beurt zet bij elk toolresultaat de naam van de tool (de nepversie
     * leest die); de API kent dat veld niet en weigert het hele verzoek.
     *
     * @param  list<array{role: string, content: string|array<int, array<string, mixed>>}>  $messages
     * @return list<array{role: string, content: string|array<int, array<string, mixed>>}>
     */
    private function forApi(array $messages): array
    {
        return array_map(function (array $message): array {
            if (is_array($message['content'])) {
                $message['content'] = array_map(function (array $block): array {
                    if (($block['type'] ?? '') === 'tool_result') {
                        unset($block['name']);
                    }

                    return $block;
                }, $message['content']);
            }

            return $message;
        }, $messages);
    }
}
