<?php

declare(strict_types=1);

namespace App\Lots;

use App\Models\Lot;

/**
 * Herkomst per veld (briefing sectie 5 en de vertrouwensregels): elk veld
 * dat Ivy invult krijgt een bron, een zekerheid en de vlag of de verkoper
 * het nog moet bevestigen. Wat de verkoper zelf typt is altijd bron
 * `seller`, wint van elke AI-waarde en wordt bij hergenereren niet
 * overschreven. Onder de drempel is bevestigen verplicht; publiceren
 * weigert zolang er iets onbevestigd staat.
 *
 * Opslag: `lots.field_sources`, per veld
 * `{source, confidence, needs_confirmation, confirmed_at, run_id}`.
 */
final class Provenance
{
    public const SELLER = 'seller';

    public const PHOTO = 'photo';

    public const DOCUMENT = 'document';

    public const TEXT = 'ivy';

    public const BENCHMARK = 'benchmark';

    /** @var list<string> */
    public const FIELDS = ['title', 'description', 'category_id', 'condition', 'brand', 'model', 'year', 'value'];

    public static function threshold(): float
    {
        return (float) config('aucvy.ai.confirmation_threshold', 0.8);
    }

    /**
     * De verkoper heeft deze velden zelf gezet (of gewist).
     *
     * @param  list<string>  $fields
     */
    public static function markSeller(Lot $lot, array $fields): void
    {
        $sources = $lot->field_sources ?? [];

        foreach ($fields as $field) {
            if (! in_array($field, self::FIELDS, true)) {
                continue;
            }

            $sources[$field] = [
                'source' => self::SELLER,
                'confidence' => 1.0,
                'needs_confirmation' => false,
                'confirmed_at' => now()->toIso8601String(),
                'run_id' => null,
            ];
        }

        self::store($lot, $sources);
    }

    /**
     * Ivy heeft deze velden ingevuld, met per veld haar zekerheid.
     *
     * @param  array<string, float|int>  $confidenceByField
     * @param  array<string, mixed>  $extra  Extra feiten bij de bron, zoals het aantal vergelijkingen.
     */
    public static function markAi(Lot $lot, array $confidenceByField, string $source, ?int $runId = null, bool $mayNeedConfirmation = true, array $extra = []): void
    {
        if ($confidenceByField === []) {
            return;
        }

        $sources = $lot->field_sources ?? [];

        foreach ($confidenceByField as $field => $confidence) {
            if (! in_array($field, self::FIELDS, true)) {
                continue;
            }

            $confidence = round(max(0.0, min(1.0, (float) $confidence)), 2);

            $sources[$field] = [
                'source' => $source,
                'confidence' => $confidence,
                'needs_confirmation' => $mayNeedConfirmation && $confidence < self::threshold(),
                'confirmed_at' => null,
                'run_id' => $runId,
                ...$extra,
            ];
        }

        self::store($lot, $sources);
    }

    /**
     * De verkoper heeft gekeken en zegt: dit klopt.
     *
     * @param  list<string>  $fields
     */
    public static function confirm(Lot $lot, array $fields): void
    {
        $sources = $lot->field_sources ?? [];

        foreach ($fields as $field) {
            if (! isset($sources[$field])) {
                continue;
            }

            $sources[$field]['needs_confirmation'] = false;
            $sources[$field]['confirmed_at'] = now()->toIso8601String();
        }

        self::store($lot, $sources);
    }

    /**
     * Ivy heeft deze velden weer leeggemaakt: er hoort geen herkomst meer bij.
     *
     * @param  list<string>  $fields
     */
    public static function forget(Lot $lot, array $fields): void
    {
        if ($fields === []) {
            return;
        }

        self::store($lot, array_diff_key($lot->field_sources ?? [], array_flip($fields)));
    }

    public static function isHuman(Lot $lot, string $field): bool
    {
        return ($lot->field_sources[$field]['source'] ?? null) === self::SELLER;
    }

    /**
     * @return list<string>
     */
    public static function unconfirmed(Lot $lot): array
    {
        return array_values(array_keys(array_filter(
            $lot->field_sources ?? [],
            fn (array $info): bool => (bool) ($info['needs_confirmation'] ?? false),
        )));
    }

    /**
     * De vorm voor de funnel: per veld de bron, de zekerheid en een label
     * in gewone taal.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function describe(Lot $lot): array
    {
        $described = [];

        foreach ($lot->field_sources ?? [] as $field => $info) {
            $described[$field] = [
                ...$info,
                'label' => self::label($info),
            ];
        }

        return $described;
    }

    /**
     * @param  array<string, mixed>  $info
     */
    public static function label(array $info): string
    {
        $source = (string) ($info['source'] ?? self::SELLER);
        $percent = (int) round(((float) ($info['confidence'] ?? 0)) * 100);

        return match ($source) {
            self::SELLER => __('funnel.provenance.seller'),
            self::BENCHMARK => __('funnel.provenance.benchmark', ['count' => (int) ($info['comparisons'] ?? 0)]),
            default => __('funnel.provenance.'.$source, ['percent' => $percent]),
        };
    }

    /**
     * @param  array<string, array<string, mixed>>  $sources
     */
    private static function store(Lot $lot, array $sources): void
    {
        $lot->field_sources = $sources;
        $lot->saveQuietly();
    }
}
