<?php

declare(strict_types=1);

namespace App\Lots;

use App\Enums\DocumentKind;
use App\Enums\MediaType;
use App\Models\Lot;

/**
 * De compleetheidsscore: 0–100, berekend uit tellbare feiten.
 *
 * Nadrukkelijk in PHP en niet door de LLM: een score die per aanroep varieert
 * is geen score (briefing hfst. 13.4). Ivy mag straks helpen de gaten te
 * vúllen; wat er staat wordt hier geteld.
 *
 * De verdeling is zo gekozen dat beeld het zwaarst weegt - dat is wat kopers
 * als eerste missen - gevolgd door de basisgegevens en de documenten.
 */
final class CompletenessScore
{
    /**
     * @return array{score: int, label: string, hint: string, missing: array<int, string>}
     */
    public function for(Lot $lot): array
    {
        // Vers uit create() draagt een model alleen de gezette velden; de
        // databasedefaults (pickup_only, viewing_available) komen pas na een
        // refresh mee. Zonder deze regel is `viewing_available` hier null.
        if ($lot->wasRecentlyCreated) {
            $lot->refresh();
        }

        $lot->loadMissing(['media', 'documents', 'category']);

        $score = 0;
        $missing = [];

        // Basis - 35 punten -----------------------------------------------
        $score += $this->has($lot->title && mb_strlen((string) $lot->title) >= 10, 8, $missing, 'title');
        $score += $this->has($lot->description && mb_strlen((string) $lot->description) >= 40, 9, $missing, 'description');
        $score += $this->has($lot->category_id !== null, 6, $missing, 'category');
        $score += $this->has($lot->condition !== null, 6, $missing, 'condition');
        $score += $this->has($lot->brand !== null || $lot->model !== null, 6, $missing, 'brand');

        // Beeld - 35 punten ------------------------------------------------
        $photos = $lot->media->where('type', MediaType::Image)->count();
        $score += $this->has($photos >= 1, 12, $missing, 'photos');
        $score += $photos >= 4 ? 10 : 0;
        $score += $photos >= 8 ? 5 : 0;
        $score += $this->has(
            $lot->media->whereIn('type', [MediaType::Video, MediaType::Tour360])->isNotEmpty(),
            8,
            $missing,
            'video',
        );

        // Documenten - 18 punten -------------------------------------------
        $kinds = $lot->documents->pluck('kind')->all();
        $score += $this->has($kinds !== [], 8, $missing, 'documents');
        $score += in_array(DocumentKind::Invoice, $kinds, true) ? 5 : 0;
        $score += in_array(DocumentKind::Ownership, $kinds, true) ? 5 : 0;

        // Logistiek - 12 punten --------------------------------------------
        // Ontbrekende transportinfo is óók een dashboardsignaal (Flow); hier
        // telt dat de koper wéét waar hij aan toe is.
        $score += $this->has(
            $lot->transport_from_cents !== null || (bool) $lot->pickup_only,
            6,
            $missing,
            'transport',
        );
        $score += $this->has((bool) $lot->viewing_available, 6, $missing, 'viewing');

        $score = min(100, $score);

        // De kolom is de cache van deze telling, voor lijsten en het
        // conceptoverzicht; de telling zelf blijft hier.
        if ($lot->exists && $lot->completeness_score !== $score) {
            $lot->forceFill(['completeness_score' => $score])->saveQuietly();
        }

        return [
            'score' => $score,
            'label' => $this->label($score),
            'hint' => __('funnel.completeness.'.$this->label($score)),
            'missing' => $missing,
        ];
    }

    /**
     * De vier drempels uit de briefing: start, goed, sterk, compleet.
     */
    private function label(int $score): string
    {
        return match (true) {
            $score >= 90 => 'complete',
            $score >= 70 => 'strong',
            $score >= 40 => 'good',
            default => 'start',
        };
    }

    /**
     * @param  array<int, string>  $missing
     */
    private function has(bool $condition, int $points, array &$missing, string $key): int
    {
        if ($condition) {
            return $points;
        }

        $missing[] = $key;

        return 0;
    }
}
