<?php

declare(strict_types=1);

use App\Models\AiRun;
use App\Models\Category;
use App\Models\User;

/*
| Zoekinterpretatie (hfst. 13.2): publiek, gecachet op de kale zoekterm, en
| corrigeren gebeurt met links - dat laatste is UI en juist géén API-gedrag:
| er bestaat geen correctie-endpoint, en dat hoort zo.
*/

function seedCategory(): void
{
    $parent = Category::factory()->create(['slug' => 'horeca', 'parent_id' => null]);
    Category::factory()->create(['slug' => 'keukens', 'parent_id' => $parent->id]);
}

it('vertaalt een zoekvraag naar filters en één leesbare zin', function () {
    seedCategory();

    $started = $this->postJson('/api/ivy/tasks', [
        'task' => 'interpret_search',
        'query' => 'keukens onder 4000',
    ])->assertCreated()->json('run');

    $response = $this->getJson("/api/ivy/tasks/{$started['id']}")->assertOk();

    expect($response->json('run.status'))->toBe('completed')
        ->and($response->json('run.output.filters.categorie'))->toBe('keukens')
        ->and($response->json('run.output.filters.tot'))->toBe(4000)
        ->and($response->json('run.output.sentence'))->toStartWith('Ik lees:');
});

it('werkt zonder account - zoeken is publiek', function () {
    $started = $this->postJson('/api/ivy/tasks', [
        'task' => 'interpret_search',
        'query' => 'graafmachine',
    ])->assertCreated()->json('run');

    expect(AiRun::find($started['id'])->user_id)->toBeNull();

    $this->getJson("/api/ivy/tasks/{$started['id']}")->assertOk();
});

it('cachet op de kale zoekterm: twee keer dezelfde vraag kost één keer geld', function () {
    $this->postJson('/api/ivy/tasks', ['task' => 'interpret_search', 'query' => 'steigers']);
    $second = $this->postJson('/api/ivy/tasks', ['task' => 'interpret_search', 'query' => 'Steigers'])->json('run');

    // Hoofdletters maken geen andere vraag; de tweede run is een treffer.
    expect(AiRun::find($second['id'])->cost_cents)->toBe(0);
});

it('schermt runs van ingelogde zoekers af', function () {
    $searcher = User::factory()->create();

    $started = $this->actingAs($searcher)
        ->postJson('/api/ivy/tasks', ['task' => 'interpret_search', 'query' => 'koelcel'])
        ->json('run');

    // Een ander account mag de run van deze zoeker niet lezen.
    $this->actingAs(User::factory()->create())
        ->getJson("/api/ivy/tasks/{$started['id']}")
        ->assertNotFound();
});

it('valideert de zoekterm', function () {
    $this->postJson('/api/ivy/tasks', ['task' => 'interpret_search', 'query' => 'a'])
        ->assertStatus(422);
});
