<?php

declare(strict_types=1);

use App\Enums\AiAgent;
use App\Ivy\Chat\ContextBuilder;
use App\Models\AiInsight;
use App\Models\IvyConversation;
use App\Models\User;

/*
| Het team in het paneel: wie is ingelogd praat met Ivy of met een van de
| vijf specialisten. Elk teamlid heeft zijn eigen gesprek, zijn eigen toon
| en laat zien wat er vanuit zijn vak nog op de klant wacht.
*/

function teamTask(User $user, string $agent, string $title, string $severity = 'attention'): AiInsight
{
    return AiInsight::create([
        'user_id' => $user->id,
        'agent' => $agent,
        'type' => 'weekly',
        'title' => $title,
        'severity' => $severity,
        'action_route' => 'dashboard.lots',
        'action_label' => 'Regelen',
        'valid_until' => now()->addDay(),
    ]);
}

it('geeft per teamlid alleen de taken uit zijn eigen vak, met de tellers van het hele team', function () {
    $user = User::factory()->verified()->create();
    teamTask($user, 'finn', 'Betaal de zit-sta bureaus', 'urgent');
    teamTask($user, 'finn', 'Uitbetaling staat klaar');
    teamTask($user, 'lex', 'Eigendomsbewijs ontbreekt');

    $finn = $this->actingAs($user)->getJson(route('ivy.show', ['agent' => 'finn', 'page' => 'dashboard']))
        ->assertOk()
        ->assertJsonPath('agent.key', 'finn')
        ->assertJsonPath('agent.name', 'Finn')
        ->assertJsonCount(2, 'tasks')
        // Het dringendste staat bovenaan, met de knop van de taak.
        ->assertJsonPath('tasks.0.title', 'Betaal de zit-sta bureaus')
        ->assertJsonPath('tasks.0.when', 'Nu')
        ->assertJsonPath('tasks.0.action', 'Regelen')
        ->assertJsonPath('tasks.0.url', route('dashboard.lots', [], false))
        ->assertJsonCount(6, 'agents');

    $counts = collect($finn->json('agents'))->pluck('count', 'key');

    expect($counts->get('finn'))->toBe(2)
        ->and($counts->get('lex'))->toBe(1)
        ->and($counts->get('ivy'))->toBe(0)
        ->and(collect($finn->json('agents'))->firstWhere('key', 'finn')['urgent'])->toBeTrue();

    $this->actingAs($user)->getJson(route('ivy.show', ['agent' => 'lex', 'page' => 'dashboard']))
        ->assertJsonCount(1, 'tasks')
        ->assertJsonPath('tasks.0.title', 'Eigendomsbewijs ontbreekt');
});

it('houdt per teamlid een eigen gesprek bij', function () {
    $user = User::factory()->verified()->create();

    $ivy = $this->actingAs($user)->getJson(route('ivy.show', ['page' => 'dashboard']))->json('conversation_id');
    $finn = $this->actingAs($user)->getJson(route('ivy.show', ['agent' => 'finn', 'page' => 'dashboard']))->json('conversation_id');

    expect($finn)->not->toBe($ivy)
        ->and(IvyConversation::find($finn)->agent)->toBe(AiAgent::Finn)
        ->and(IvyConversation::find($ivy)->agent)->toBe(AiAgent::Ivy);

    // Een bericht aan Finn komt in het gesprek met Finn, niet in dat met Ivy.
    $this->actingAs($user)->postJson(route('ivy.send', ['agent' => 'finn', 'page' => 'dashboard']), ['text' => 'mijn biedingen', 'path' => '/dashboard'])->assertOk();

    expect(IvyConversation::find($finn)->messages()->count())->toBe(2)
        ->and(IvyConversation::find($ivy)->messages()->count())->toBe(0);

    // Terug bij Finn staat het gesprek er nog; hetzelfde gesprek, geen nieuw.
    $this->actingAs($user)->getJson(route('ivy.show', ['agent' => 'finn', 'page' => 'dashboard']))
        ->assertJsonPath('conversation_id', $finn)
        ->assertJsonCount(2, 'messages');
});

it('laat een specialist zich voorstellen met zijn vak en vragen uit dat vak', function () {
    $user = User::factory()->verified()->create();

    $this->actingAs($user)->getJson(route('ivy.show', ['agent' => 'flow', 'page' => 'dashboard']))
        ->assertOk()
        ->assertJsonPath('greeting', fn (string $greeting): bool => str_contains($greeting, 'Ik ben Flow') && str_contains($greeting, 'AI-specialist'))
        ->assertJsonPath('chips.0', 'Hoe werkt ophalen?');
});

it('geeft de specialist zijn eigen rol en alleen zijn eigen taken in het systeembericht', function () {
    $user = User::factory()->verified()->create();
    teamTask($user, 'finn', 'Betaal de zit-sta bureaus');
    teamTask($user, 'lex', 'Eigendomsbewijs ontbreekt');

    $builder = app(ContextBuilder::class);
    $finn = $builder->build(IvyConversation::create(['user_id' => $user->id, 'role' => IvyConversation::BUYER, 'agent' => AiAgent::Finn, 'status' => IvyConversation::OPEN]), null, $user);
    $ivy = $builder->build(IvyConversation::create(['user_id' => $user->id, 'role' => IvyConversation::BUYER, 'agent' => AiAgent::Ivy, 'status' => IvyConversation::OPEN]), null, $user);

    expect($finn)->toContain('Je bent Finn, de AI-specialist voor geld')
        ->and($finn)->toContain('geen mens')
        ->and($finn)->toContain('Betaal de zit-sta bureaus')
        ->and($finn)->not->toContain('Eigendomsbewijs ontbreekt')
        // Wat bij een ander wacht, weet hij alleen als aantal.
        ->and($finn)->toContain('Lex 1')
        // Ivy overziet alles.
        ->and($ivy)->toContain('Je bent Ivy, de AI-veilingassistent van Aucvy')
        ->and($ivy)->toContain('Betaal de zit-sta bureaus')
        ->and($ivy)->toContain('Eigendomsbewijs ontbreekt');
});

it('laat een bezoeker zonder account alleen met Ivy praten', function () {
    $this->getJson(route('ivy.show', ['agent' => 'finn']))
        ->assertOk()
        ->assertJsonPath('agent.key', 'ivy')
        ->assertJsonPath('agents', [])
        ->assertJsonPath('tasks', []);
});

it('valt bij een onbekend teamlid terug op Ivy', function () {
    $this->actingAs(User::factory()->verified()->create())
        ->getJson(route('ivy.show', ['agent' => 'bestaat-niet', 'page' => 'dashboard']))
        ->assertOk()
        ->assertJsonPath('agent.key', 'ivy');
});
