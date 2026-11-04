<?php

declare(strict_types=1);

namespace App\Ivy\Chat;

use Illuminate\Support\Str;

/**
 * Deterministische Ivy voor tests en lokaal: leest de bedoeling uit
 * trefwoorden en kiest een tool of antwoordt uit de kennisbank. Noemt
 * nooit zelf een bedrag dat niet uit een tool komt.
 */
final class FakeChatProvider implements ChatProvider
{
    public function respond(string $system, array $messages, array $tools, string $tier = 'fast'): ChatResponse
    {
        $last = $this->lastUser($messages);
        $toolResults = $this->lastToolResults($messages);
        $available = array_column($tools, 'name');
        $text = Str::lower($last);
        $lotId = $this->lotIdFrom($system);

        // Tweede ronde: de toolresultaten zijn er, nu de tekst.
        if ($toolResults !== []) {
            return new ChatResponse($this->summarise($toolResults));
        }

        $call = fn (string $name, array $input) => new ChatResponse('', [['id' => 'call-'.Str::random(6), 'name' => $name, 'input' => $input]]);

        if (preg_match('/\b(?:bied|bod van|zet in op)\s*€?\s*([\d.]+)/u', $text, $match) === 1 && in_array('propose_bid', $available, true)) {
            return $call('propose_bid', ['lot_id' => $lotId, 'amount_cents' => (int) str_replace('.', '', $match[1]) * 100]);
        }

        if (preg_match('/automatisch.*?tot\s*€?\s*([\d.]+)|limiet\s*(?:van\s*)?€?\s*([\d.]+)/u', $text, $match) === 1 && in_array('propose_auto_bid', $available, true)) {
            return $call('propose_auto_bid', ['lot_id' => $lotId, 'limit_cents' => (int) str_replace('.', '', $match[1] !== '' ? $match[1] : $match[2]) * 100]);
        }

        if (str_contains($text, 'koop direct') && in_array('propose_buy_now', $available, true)) {
            return $call('propose_buy_now', ['lot_id' => $lotId]);
        }

        if (str_contains($text, 'publiceer') && in_array('propose_publish', $available, true)) {
            return $call('propose_publish', ['lot_id' => $lotId]);
        }

        if (str_contains($text, 'reserve')) {
            return $call('get_lot', ['lot_id' => $lotId]);
        }

        if ((str_contains($text, 'wat kost') || str_contains($text, 'totaal')) && $lotId !== null && ! str_contains($text, 'verkopen') && in_array('calc_buyer_total', $available, true)) {
            return $call('calc_buyer_total', ['lot_id' => $lotId, 'bid_cents' => $this->amountFrom($text)]);
        }

        if ((str_contains($text, 'netto') || str_contains($text, 'wat hou ik over') || str_contains($text, 'fee')) && in_array('calc_seller_net', $available, true)) {
            return $call('calc_seller_net', ['amount_cents' => $this->amountFrom($text) ?? 1_200_000]);
        }

        if ((str_contains($text, 'zoek') || str_contains($text, 'ik wil') || str_contains($text, 'heb je')) && in_array('search_lots', $available, true)) {
            return $call('search_lots', ['query' => $last]);
        }

        if ((str_contains($text, 'bewaar') || str_contains($text, 'blijf zoeken') || str_contains($text, 'alert')) && in_array('save_search', $available, true)) {
            return $call('save_search', ['query' => $last]);
        }

        if ((str_contains($text, 'mijn biedingen') || str_contains($text, 'mijn bestelling') || str_contains($text, 'mijn kavels') || str_contains($text, 'mijn orders')) && in_array('get_my_activity', $available, true)) {
            return $call('get_my_activity', []);
        }

        if ((str_contains($text, 'wat moet ik nog') || str_contains($text, 'volgende stap')) && in_array('get_next_steps', $available, true)) {
            return $call('get_next_steps', ['lot_id' => $lotId]);
        }

        if ((str_contains($text, 'interesse') || str_contains($text, 'hoeveel views')) && in_array('get_lot_stats', $available, true)) {
            return $call('get_lot_stats', ['lot_id' => $lotId]);
        }

        if (str_contains($text, 'medewerker') && in_array('create_handoff', $available, true)) {
            return $call('create_handoff', ['reason' => 'customer_request', 'summary' => Str::limit($last, 200)]);
        }

        if (preg_match('/[\w.+-]+@[\w-]+\.[\w.]+/u', $last, $mail) === 1 && in_array('capture_lead', $available, true)) {
            return $call('capture_lead', ['email' => $mail[0], 'name' => 'Bezoeker', 'intent' => 'sell']);
        }

        if ((str_contains($text, 'wat doen jullie') || str_contains($text, 'pitch') || str_contains($text, 'wat is aucvy')) && in_array('kb_search', $available, true)) {
            return $call('kb_search', ['query' => 'pitch']);
        }

        if (in_array('kb_search', $available, true)) {
            return $call('kb_search', ['query' => $last]);
        }

        return new ChatResponse('Ik ben Ivy, de AI-assistent van Aucvy. Vertel me wat je zoekt of wilt verkopen.');
    }

    /**
     * @param  list<array{name: string, result: array<string, mixed>}>  $results
     */
    private function summarise(array $results): string
    {
        $parts = [];

        foreach ($results as $item) {
            $result = $item['result'];

            if (isset($result['error'])) {
                $parts[] = (string) $result['error'];

                continue;
            }

            $parts[] = match ($item['name']) {
                'search_lots' => ($result['count'] ?? 0) > 0 ? 'Ik vond '.$result['count'].' open kavels.'.(($result['interpretation'] ?? '') !== '' ? ' '.$result['interpretation'] : '').' Zal ik deze zoekvraag blijven bewaken?' : 'Ik vond nu geen open kavels die passen. Zal ik een zoekvraag bewaren, dan meld ik het zodra er iets binnenkomt?',
                'get_lot' => (string) ($result['summary'] ?? 'Dit is de kavel.'),
                'calc_buyer_total' => 'Totaal: '.($result['total'] ?? '').' excl. btw, met '.($result['lines_text'] ?? '').' Alle kosten staan vóór het bieden vast.',
                'calc_seller_net' => 'Bij '.($result['amount'] ?? '').' is de succesfee '.($result['fee_percentage'] ?? '').' ('.($result['fee'] ?? '').'); je houdt '.($result['net'] ?? '').' over.',
                'get_value_estimate' => (string) ($result['text'] ?? 'Te weinig data voor een indicatie.'),
                'kb_search' => (string) ($result['answer'] ?? 'Dat weet ik niet zeker; ik zoek het voor je uit.'),
                'get_my_activity' => (string) ($result['text'] ?? ''),
                'get_lot_stats' => (string) ($result['text'] ?? ''),
                'get_next_steps' => (string) ($result['text'] ?? ''),
                'save_search' => 'Ik bewaak deze zoekvraag en meld nieuwe kavels. Je kunt dit altijd uitzetten bij je alerts.',
                'follow_lot' => (string) ($result['text'] ?? 'Gedaan.'),
                'set_lot_mode' => (string) ($result['text'] ?? 'Gedaan.'),
                'ask_seller' => (string) ($result['text'] ?? 'Je vraag staat bij de kavel.'),
                'propose_bid', 'propose_auto_bid', 'propose_buy_now', 'propose_publish', 'propose_dutch_accept', 'propose_reserve_change' => (string) ($result['text'] ?? 'Ik heb het klaargezet; bevestig het even.'),
                'capture_lead' => 'Dank je. Ik heb je gegevens doorgegeven; je hoort snel van het team.',
                'create_handoff' => 'Ik geef dit met een samenvatting door aan een medewerker. Je hoort het zodra iemand het oppakt.',
                default => '',
            };
        }

        return trim(implode(' ', array_filter($parts))) ?: 'Dat weet ik niet zeker; ik zoek het voor je uit.';
    }

    /**
     * @param  list<array{role: string, content: string|array<int, array<string, mixed>>}>  $messages
     */
    private function lastUser(array $messages): string
    {
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if ($messages[$i]['role'] === 'user' && is_string($messages[$i]['content'])) {
                return $messages[$i]['content'];
            }
        }

        return '';
    }

    /**
     * @param  list<array{role: string, content: string|array<int, array<string, mixed>>}>  $messages
     * @return list<array{name: string, result: array<string, mixed>}>
     */
    private function lastToolResults(array $messages): array
    {
        $last = end($messages);

        if ($last === false || ! is_array($last['content'])) {
            return [];
        }

        $results = [];

        foreach ($last['content'] as $block) {
            if (($block['type'] ?? '') === 'tool_result') {
                $results[] = ['name' => (string) ($block['name'] ?? ''), 'result' => (array) json_decode((string) $block['content'], true)];
            }
        }

        return $results;
    }

    private function lotIdFrom(string $system): ?int
    {
        return preg_match('/\[kavel_id:(\d+)\]/', $system, $match) === 1 ? (int) $match[1] : null;
    }

    private function amountFrom(string $text): ?int
    {
        return preg_match('/€?\s*([\d]{1,3}(?:\.\d{3})+|\d{3,})/u', $text, $match) === 1 ? (int) str_replace('.', '', $match[1]) * 100 : null;
    }
}
