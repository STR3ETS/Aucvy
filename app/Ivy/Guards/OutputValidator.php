<?php

declare(strict_types=1);

namespace App\Ivy\Guards;

/**
 * De poort tussen modeluitvoer en de rest van de applicatie.
 *
 * Twee controles, allebei hard (hfst. 8):
 *
 *  1. het JSON-schema van de taak - één herkansing met een strengere prompt,
 *     daarna een nette fout. Ongevalideerde uitvoer komt nooit op een scherm;
 *  2. verboden formuleringen - geen garanties, geen escrow- of
 *     keuringsclaims. Prompts driften, deze lijst niet.
 *
 * De schemavalidatie is bewust een eigen, kleine implementatie: onze schema's
 * gebruiken alleen type/required/properties/items/enum/maxLength, en een
 * validator die je zelf kunt lezen is hier meer waard dan een dependency met
 * honderd features die we niet gebruiken.
 */
final class OutputValidator
{
    /**
     * Formuleringen die Ivy nooit mag doen, wat de prompt ook zegt. Beloften
     * die het platform niet waarmaakt (escrow, keuring) of niet mág doen
     * (garanties, zekere opbrengsten) blijven zo buiten elk scherm.
     */
    private const FORBIDDEN_PHRASES = [
        'gegarandeerd', 'garanderen', 'wij garanderen', 'gegarandeerde opbrengst',
        'escrow', 'derdenrekening',
        'gekeurd door aucvy', 'door ons gekeurd', 'aucvy heeft dit gecontroleerd',
        'zeker weten dat', 'levert zeker',
    ];

    /**
     * Pak JSON uit de modeltekst. Modellen zetten er soms een codehek of een
     * zin omheen; alles tussen de buitenste accolades is de kandidaat.
     *
     * @return array<string, mixed>|null
     */
    public function extractJson(string $text): ?array
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $output
     * @param  array<string, mixed>  $schema
     * @return list<string> Lege lijst betekent: geldig.
     */
    public function schemaErrors(array $output, array $schema): array
    {
        $errors = [];
        $this->check($output, $schema, '$', $errors);

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $output
     * @return list<string> De aangetroffen verboden formuleringen.
     */
    public function forbiddenPhrases(array $output): array
    {
        $haystack = mb_strtolower(json_encode($this->withoutEchoedInput($output), JSON_UNESCAPED_UNICODE) ?: '');

        return array_values(array_filter(
            self::FORBIDDEN_PHRASES,
            fn (string $phrase): bool => str_contains($haystack, $phrase),
        ));
    }

    /**
     * Velden die het schema het model laat overschrijven uit de invoer
     * (de bestandsnaam van de verkoper) zijn geen formulering van Ivy.
     *
     * @param  array<string, mixed>  $output
     * @return array<string, mixed>
     */
    private function withoutEchoedInput(array $output): array
    {
        foreach ($output as $key => $value) {
            if ($key === 'filename') {
                unset($output[$key]);
            } elseif (is_array($value)) {
                $output[$key] = $this->withoutEchoedInput($value);
            }
        }

        return $output;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  list<string>  $errors
     */
    private function check(mixed $value, array $schema, string $path, array &$errors): void
    {
        $type = $schema['type'] ?? null;

        if ($value === null) {
            // `type: ['string', 'null']` - nullable velden.
            if (is_array($type) && in_array('null', $type, true)) {
                return;
            }

            $errors[] = "{$path}: mag niet null zijn";

            return;
        }

        $expected = is_array($type) ? array_diff($type, ['null']) : [$type];

        if (! $this->matchesType($value, reset($expected) ?: 'object')) {
            $errors[] = "{$path}: verwacht ".implode('|', $expected);

            return;
        }

        if (isset($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            $errors[] = "{$path}: waarde valt buiten ".implode('/', $schema['enum']);
        }

        if (isset($schema['maxLength']) && is_string($value) && mb_strlen($value) > $schema['maxLength']) {
            $errors[] = "{$path}: langer dan {$schema['maxLength']} tekens";
        }

        if (in_array('object', $expected, true) && is_array($value)) {
            foreach ($schema['required'] ?? [] as $key) {
                if (! array_key_exists($key, $value)) {
                    $errors[] = "{$path}.{$key}: ontbreekt";
                }
            }

            foreach ($schema['properties'] ?? [] as $key => $propertySchema) {
                if (array_key_exists($key, $value)) {
                    $this->check($value[$key], $propertySchema, "{$path}.{$key}", $errors);
                }
            }
        }

        if (in_array('array', $expected, true) && is_array($value) && isset($schema['items'])) {
            foreach ($value as $index => $item) {
                $this->check($item, $schema['items'], "{$path}[{$index}]", $errors);
            }
        }
    }

    private function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'array' => is_array($value) && array_is_list($value),
            // Een leeg array decodeert als lijst maar mag ook een leeg object zijn.
            'object' => is_array($value) && (! array_is_list($value) || $value === []),
            default => true,
        };
    }
}
