<?php

declare(strict_types=1);

namespace App\Ivy;

use App\Ivy\Contracts\AiTask;
use App\Ivy\Tasks\BidAdvice;
use App\Ivy\Tasks\CheckDocuments;
use App\Ivy\Tasks\DraftListing;
use App\Ivy\Tasks\EstimateValue;
use App\Ivy\Tasks\InterpretSearch;
use App\Ivy\Tasks\RecogniseLot;
use App\Ivy\Tasks\WeeklyInsights;
use App\Models\Lot;
use App\Models\User;

/**
 * Welke taken er bestaan, hoe hun API-naam aan een klasse hangt en wie ze
 * mag starten.
 *
 * De API praat in namen (`recognise_lot`), de code in klassen. Wat hier niet
 * staat, bestaat niet - een onbekende taaknaam is een 422, geen 500. De
 * scope bepaalt de poort: concepttaken lopen via DraftAccess, kaveltaken
 * vragen een ingelogde kijker op een live kavel, zoektaken zijn publiek.
 */
final class TaskRegistry
{
    /** Concept-gebonden: alleen wie bij het concept mag (hfst. 13.4). */
    private const DRAFT_TASKS = [
        'recognise_lot' => RecogniseLot::class,
        'draft_listing' => DraftListing::class,
        'estimate_value' => EstimateValue::class,
        'check_documents' => CheckDocuments::class,
    ];

    /** Live-kavel-gebonden: elke ingelogde gebruiker mag ze aanvragen. */
    private const LIVE_LOT_TASKS = [
        'bid_advice' => BidAdvice::class,
    ];

    public static function knows(string $name): bool
    {
        return isset(self::DRAFT_TASKS[$name]) || isset(self::LIVE_LOT_TASKS[$name]);
    }

    public static function isDraftScoped(string $name): bool
    {
        return isset(self::DRAFT_TASKS[$name]);
    }

    public static function forLot(string $name, Lot $lot): AiTask
    {
        $class = self::DRAFT_TASKS[$name] ?? self::LIVE_LOT_TASKS[$name];

        return new $class($lot);
    }

    /**
     * Gebruikersgebonden taken starten niet via het API-endpoint maar via
     * scheduled commands - vandaar een aparte ingang zonder naamgokwerk.
     */
    public static function weeklyInsightsFor(User $user): AiTask
    {
        return new WeeklyInsights($user);
    }

    /**
     * Zoekinterpretatie: geen kavel als onderwerp maar een zoekvraag, en ook
     * zonder account beschikbaar - de zoekpagina is publiek (hfst. 13.2).
     */
    public static function searchFor(string $query, ?User $user): AiTask
    {
        return new InterpretSearch($query, $user);
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return [...array_keys(self::DRAFT_TASKS), ...array_keys(self::LIVE_LOT_TASKS)];
    }
}
