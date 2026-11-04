<?php

declare(strict_types=1);

namespace App\Ivy\Providers;

use App\Ivy\AiResult;
use App\Ivy\Contracts\AiTask;
use App\Ivy\Contracts\LlmProvider;

/**
 * Deterministische provider voor lokaal en tests - draait zonder API-sleutel.
 *
 * Deterministisch is hier een eis, geen gemak: de cache werkt op
 * hash(input + promptversie), dus dezelfde invoer moet dezelfde uitvoer
 * geven, ook hier. Alle willekeur is afgeleid van een crc32 over de invoer.
 *
 * De uitvoer gaat als tekst terug en loopt daarna door exact dezelfde
 * validator als bij de echte provider - zo testen de tests het hele pad.
 */
final class FakeProvider implements LlmProvider
{
    private const BRANDS = ['Siemens', 'Bosch', 'Miele', 'DeWalt', 'Makita', 'Caterpillar', 'Volvo', 'Rational', 'Gaggenau', 'Festool'];

    public function complete(AiTask $task, string $prompt): AiResult
    {
        $input = $task->input();
        $seed = crc32(json_encode($input, JSON_UNESCAPED_UNICODE) ?: '');

        $output = match ($task->name()) {
            'recognise_lot' => $this->recognise($input, $seed),
            'draft_listing' => $this->listing($input),
            'estimate_value' => $this->estimate($input, $seed),
            'weekly_insights' => $this->weekly($input),
            'bid_advice' => $this->advice($input),
            'check_documents' => $this->checkDocuments($input),
            'interpret_search' => $this->interpret($input),
            'translate_listing' => $this->translate($input),
            // De nepcontrole keurt goed; de vaste regels in ScreenListing
            // (verboden waar, prijs, documenten) blijven gewoon gelden.
            'screen_listing' => ['verdict' => 'approve', 'issues' => [], 'confidence' => 0.9],
            // Ivy ziet niets in een bericht; de vaste regels doen het werk.
            'moderate_message' => ['signal' => 'none', 'confidence' => 0.9],
            'draft_ticket_reply' => ['reply' => 'Bedankt voor je bericht over "'.($input['subject'] ?? 'je vraag').'". Een medewerker zoekt dit voor je uit en komt vandaag bij je terug.', 'confidence' => 0.7],
            default => ['confidence' => 0.5],
        };

        $text = json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}';

        return new AiResult(
            text: $text,
            model: 'fake',
            tokensIn: intdiv(mb_strlen($prompt), 4),
            tokensOut: intdiv(mb_strlen($text), 4),
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function recognise(array $input, int $seed): array
    {
        $description = mb_strtolower((string) ($input['description'] ?? ''));
        $photos = (int) ($input['photo_count'] ?? 0);

        $objectType = match (true) {
            str_contains($description, 'keuken') => 'Professionele keuken',
            str_contains($description, 'graafmachine') || str_contains($description, 'kraan') => 'Graafmachine',
            str_contains($description, 'koel') => 'Koeltechniek',
            str_contains($description, 'steiger') => 'Steigermateriaal',
            str_contains($description, 'stoel') || str_contains($description, 'bureau') => 'Kantoorinventaris',
            $description !== '' => 'Bedrijfsmiddel',
            default => 'Object op de foto\'s',
        };

        $brand = null;

        foreach (self::BRANDS as $candidate) {
            if (str_contains($description, mb_strtolower($candidate))) {
                $brand = $candidate;
                break;
            }
        }

        // Meer materiaal, meer zekerheid - net als bij de echte herkenning.
        $confidence = round(min(0.9, 0.35 + $photos * 0.08 + ($description !== '' ? 0.15 : 0)), 2);

        return [
            'object_type' => $objectType,
            'brand' => $brand,
            'model' => null,
            'year' => null,
            'condition' => ['good', 'used', 'like_new'][$seed % 3],
            'specifications' => $brand !== null ? ['merk' => $brand] : [],
            'confidence' => $confidence,
            // Een merk dat letterlijk in de omschrijving staat is zeker; de
            // conditie uit foto's alleen is dat zelden - die vraagt om een
            // bevestiging van de verkoper.
            'field_confidence' => [
                'brand' => $brand !== null ? 0.95 : 0.0,
                'condition' => round(min(0.75, $confidence), 2),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function listing(array $input): array
    {
        $objectType = (string) ($input['object_type'] ?: 'Bedrijfsmiddel');
        $brand = $input['brand'] ?? null;
        $description = trim((string) ($input['description'] ?? ''));

        $title = trim($objectType.($brand !== null ? ' van '.$brand : ''));

        $body = $description !== ''
            ? "In eigen woorden van de verkoper: {$description}\n\n"
            : '';

        $body .= "Aangeboden: {$title}. De foto's geven een eerlijk beeld van de staat; "
            .'gebreken die zichtbaar zijn, zijn niet weggepoetst. Bezichtigen kan in '
            .'overleg. Vragen over specificaties of transport? Stel ze gerust via het platform.';

        return [
            'title' => mb_substr($title, 0, 120),
            'description' => $body,
            'confidence' => $description !== '' ? 0.75 : 0.55,
        ];
    }

    /**
     * Nep-vertaling: herkenbaar aan het voorvoegsel, verder de bron.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function translate(array $input): array
    {
        $tag = '['.strtoupper((string) ($input['locale'] ?? 'en')).'] ';

        return [
            'title' => $tag.(string) ($input['title'] ?? ''),
            'description' => $tag.(string) ($input['description'] ?? ''),
            'confidence' => 0.9,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function advice(array $input): array
    {
        $bidders = (int) ($input['bidder_count'] ?? 0);
        $hours = (int) ($input['hours_left'] ?? 0);
        $hasRange = ($input['value_max_cents'] ?? null) !== null;

        $assessment = $bidders > 2
            ? 'Er is duidelijke belangstelling: meerdere bieders zijn actief. Bied alleen mee als de kavel binnen je eigen maximum blijft - laat het tempo van anderen je niet opjagen.'
            : 'De belangstelling is nog rustig. Er is geen reden om vroeg hoog te bieden; het minimum volstaat om mee te doen.';

        $assessment .= $hours < 24
            ? ' De veiling loopt bijna af - reken op activiteit in het laatste uur.'
            : ' Er is nog ruim tijd; volg de kavel en beslis later.';

        return [
            'assessment' => $assessment,
            'risks' => $hasRange ? null : 'Er is geen waardeschatting voor deze kavel; bepaal je maximum extra behoedzaam.',
            'confidence' => $hasRange ? 0.75 : 0.4,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function checkDocuments(array $input): array
    {
        $documents = collect($input['documents'] ?? [])
            ->map(fn (array $document): array => [
                'filename' => (string) $document['filename'],
                'readable' => true,
                'issues' => null,
            ])
            ->values()
            ->all();

        $missing = $input['missing_kinds'] ?? [];

        return [
            'documents' => $documents,
            'summary' => $missing === []
                ? count($documents).' document(en) bekeken; geen bijzonderheden.'
                : count($documents).' document(en) bekeken. Nog niet aanwezig: '.implode(', ', $missing).'.',
            'confidence' => 0.7,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function interpret(array $input): array
    {
        $query = mb_strtolower((string) ($input['query'] ?? ''));
        $categories = $input['categories'] ?? [];

        $categorie = collect($categories)
            ->first(fn (string $slug): bool => str_contains($query, str_replace('-', ' ', $slug))
                || str_contains($query, $slug));

        // "onder 4000", "tot 4.000", "max 4000 euro"
        $tot = preg_match('/(?:onder|tot|max(?:imaal)?)\s*€?\s*([\d.]{2,})/u', $query, $match)
            ? (int) str_replace('.', '', $match[1])
            : null;

        $sentence = 'Ik lees: '.trim(($categorie ? str_replace('-', ' ', $categorie) : 'alles').
            ($tot !== null ? ', tot € '.number_format($tot, 0, ',', '.') : '')).'.';

        return [
            'sentence' => $sentence,
            'filters' => [
                'categorie' => $categorie,
                'vanaf' => null,
                'tot' => $tot,
                'conditie' => null,
            ],
            'confidence' => $categorie !== null || $tot !== null ? 0.7 : 0.4,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function weekly(array $input): array
    {
        $insights = [];

        if (($input['bids_received'] ?? 0) > 0) {
            $insights[] = [
                'agent' => 'atlas',
                'title' => "Je ontving {$input['bids_received']} biedingen deze week.",
                'body' => 'De belangstelling zit vooral op je lopende veilingen.',
            ];
        }

        if (($input['draft_lots'] ?? 0) > 0) {
            $insights[] = [
                'agent' => 'ivy',
                'title' => "Er staan nog {$input['draft_lots']} concepten klaar om af te maken.",
                'body' => 'Een concept dat live staat, kan bieders trekken; een concept in de la niet.',
            ];
        }

        if (($input['orders_awaiting_payment'] ?? 0) > 0) {
            $insights[] = [
                'agent' => 'finn',
                'title' => "{$input['orders_awaiting_payment']} verkochte kavel(s) wachten nog op betaling.",
                'body' => 'De betaaltermijn bewaakt zichzelf; na 48 uur vervalt de koop automatisch.',
            ];
        }

        if ($insights === []) {
            $insights[] = [
                'agent' => 'ivy',
                'title' => 'Een rustige week - geen bijzonderheden in je cijfers.',
                'body' => null,
            ];
        }

        return ['insights' => $insights, 'confidence' => 0.8];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function estimate(array $input, int $seed): array
    {
        // Basisprijs deterministisch uit de invoer; merk en foto's tellen mee.
        $base = 40_000 + ($seed % 12) * 15_000;
        $base += ($input['brand'] ?? null) !== null ? 30_000 : 0;
        $base += (int) ($input['photo_count'] ?? 0) * 5_000;

        return [
            'comparisons' => [
                ['label' => 'Vergelijkbaar object, recent geveild', 'price_cents' => $base],
                ['label' => 'Zelfde type in mindere staat', 'price_cents' => intdiv($base * 7, 10)],
                ['label' => 'Zelfde type, jonger bouwjaar', 'price_cents' => intdiv($base * 13, 10)],
            ],
            'reasoning' => 'Schatting op basis van vergelijkbare objecten in dezelfde categorie. '
                .'De uiteindelijke opbrengst hangt af van conditie, compleetheid en het moment.',
            'confidence' => (($input['brand'] ?? null) !== null) ? 0.72 : 0.45,
        ];
    }
}
