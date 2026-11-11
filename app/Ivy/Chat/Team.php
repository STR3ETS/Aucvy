<?php

declare(strict_types=1);

namespace App\Ivy\Chat;

use App\Enums\AiAgent;
use App\Enums\InsightSeverity;
use App\Models\AiInsight;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Het team in het paneel: Ivy en de vijf specialisten. De klant praat met
 * één van hen tegelijk; elk teamlid laat zien wat er vanuit zijn vak nog op
 * de klant wacht en wat hem deze week opviel.
 *
 * @phpstan-type TeamTask array{id: int, title: string, body: string|null, url: string, action: string, urgency: string, when: string}
 * @phpstan-type TeamMember array{key: string, name: string, speciality: string, photo: string, count: int, urgent: bool}
 */
final class Team
{
    /**
     * Het teamlid uit het verzoek. Een bezoeker zonder account praat
     * altijd met Ivy.
     */
    public function resolve(?string $key, ?User $user): AiAgent
    {
        return $user === null ? AiAgent::Ivy : (AiAgent::tryFrom((string) $key) ?? AiAgent::Ivy);
    }

    /**
     * Alles wat nog op de klant wacht, het dringendste eerst.
     *
     * @return Collection<int, AiInsight>
     */
    public function open(User $user): Collection
    {
        return AiInsight::query()
            ->where('user_id', $user->id)
            ->current()
            ->orderByRaw("field(severity, 'urgent', 'attention', 'info')")
            ->limit(60)
            ->get()
            ->filter(fn (AiInsight $insight): bool => $insight->hasAction())
            ->values();
    }

    /**
     * Wat er vanuit dit teamlid nog op de klant wacht, klaar voor het paneel.
     *
     * @param  Collection<int, AiInsight>|null  $open
     * @return list<TeamTask>
     */
    public function tasks(User $user, AiAgent $agent, ?Collection $open = null, int $limit = 6): array
    {
        return ($open ?? $this->open($user))
            ->filter(fn (AiInsight $insight): bool => $insight->agent === $agent)
            ->take($limit)
            ->map(function (AiInsight $insight): ?array {
                try {
                    $url = route($insight->action_route, $insight->action_params ?? [], false);
                } catch (Throwable $exception) {
                    // Een taak met een route die niet meer bestaat mag het paneel niet breken.
                    report($exception);

                    return null;
                }

                return [
                    'id' => $insight->id,
                    'title' => Str::limit((string) $insight->title, 140),
                    'body' => $insight->body !== null ? Str::limit((string) $insight->body, 160) : null,
                    'url' => $url,
                    'action' => $insight->action_label ?? 'Bekijken',
                    'urgency' => $insight->severity->value,
                    'when' => match ($insight->severity) {
                        InsightSeverity::Urgent => 'Nu',
                        InsightSeverity::Attention => 'Vandaag',
                        InsightSeverity::Info => 'Deze week',
                    },
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Wat dit teamlid deze week opviel: observaties zonder knop.
     *
     * @return list<array{title: string, body: string|null}>
     */
    public function notes(User $user, AiAgent $agent, int $limit = 3): array
    {
        return AiInsight::query()
            ->where('user_id', $user->id)
            ->where('type', 'weekly')
            ->where('agent', $agent)
            ->current()
            ->latest('id')
            ->limit($limit * 2)
            ->get()
            ->reject(fn (AiInsight $insight): bool => $insight->hasAction())
            ->take($limit)
            ->map(fn (AiInsight $insight): array => ['title' => Str::limit((string) $insight->title, 160), 'body' => $insight->body !== null ? Str::limit((string) $insight->body, 200) : null])
            ->values()
            ->all();
    }

    /**
     * Het hele team met per teamlid hoeveel er nog wacht.
     *
     * @param  Collection<int, AiInsight>|null  $open
     * @return list<TeamMember>
     */
    public function roster(?User $user, ?Collection $open = null): array
    {
        $open = $user === null ? collect() : ($open ?? $this->open($user));

        return array_map(fn (AiAgent $agent): array => [
            'key' => $agent->value,
            'name' => $agent->label(),
            'speciality' => $agent->speciality(),
            'photo' => asset('images/agents/'.$agent->value.'.jpg'),
            'count' => $open->filter(fn (AiInsight $insight): bool => $insight->agent === $agent)->count(),
            'urgent' => $open->contains(fn (AiInsight $insight): bool => $insight->agent === $agent && $insight->severity === InsightSeverity::Urgent),
        ], AiAgent::cases());
    }
}
