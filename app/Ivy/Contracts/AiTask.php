<?php

declare(strict_types=1);

namespace App\Ivy\Contracts;

use App\Enums\AiAgent;
use App\Models\AiRun;
use Illuminate\Database\Eloquent\Model;

/**
 * Eén AI-taak: wat gaat erin, wat mag eruit, en wat gebeurt er met de
 * uitkomst.
 *
 * Alles wat het LLM raakt loopt via dit contract door de queue en landt in
 * `ai_runs` - geen uitzonderingen (hfst. 8). De taak beschrijft zichzelf
 * volledig: de job en de provider kennen geen taakspecifieke logica.
 */
interface AiTask
{
    /** De specialist wiens naam dit advies straks draagt (hfst. 13.5). */
    public function agent(): AiAgent;

    /** Taaknaam zoals hij in `ai_runs.task` en het API-verzoek staat. */
    public function name(): string;

    /**
     * Waar de uitkomst over gaat - polymorf gelogd op de run. Null bij taken
     * zonder onderwerp, zoals een zoekinterpretatie.
     */
    public function subject(): ?Model;

    /**
     * De invoer, serialiseerbaar. Dit is wat er gelogd wordt én waar de
     * cachesleutel van afgeleid is - dezelfde invoer twee keer analyseren
     * kost dan niets extra.
     *
     * @return array<string, mixed>
     */
    public function input(): array;

    /** De opdrachttekst voor het model. Vraagt altijd om kale JSON. */
    public function prompt(): string;

    /**
     * Absolute paden van afbeeldingen die meegaan (multimodale herkenning).
     *
     * @return list<string>
     */
    public function images(): array;

    /**
     * Absolute paden van pdf-documenten die meegaan (documentcontrole).
     *
     * @return list<string>
     */
    public function documents(): array;

    /**
     * JSON-schema waar de uitvoer aan moet voldoen. Ongevalideerde uitvoer
     * komt nooit op een scherm - prompts driften, validators niet (hfst. 8).
     *
     * @return array<string, mixed>
     */
    public function schema(): array;

    /**
     * Verwerk een geslaagde run: gevalideerde uitvoer op het onderwerp
     * toepassen. Vult alleen gaten - wat de gebruiker zelf invulde blijft
     * altijd staan.
     */
    public function apply(AiRun $run): void;

    /** hash(input + promptversie) - zie config aucvy.ai.prompt_version. */
    public function cacheKey(): string;
}
