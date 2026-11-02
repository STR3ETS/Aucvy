<?php

declare(strict_types=1);

use App\Enums\AiRunStatus;
use App\Enums\LotCondition;
use App\Ivy\Jobs\RunAiTask;
use App\Ivy\TaskRegistry;
use App\Lots\DraftAccess;
use App\Lots\Provenance;
use App\Lots\PublishLot;
use App\Models\AiRun;
use App\Models\Category;
use App\Models\Lot;
use App\Models\LotMedia;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

/*
| Herkomst per veld (briefing sectie 5, vertrouwensregels 1, 2 en 4): elk
| veld van Ivy heeft bron en zekerheid; onder de drempel moet de verkoper
| bevestigen; wat de verkoper zelf typt wint; publiceren weigert zolang er
| iets onbevestigd staat.
*/

beforeEach(function () {
    $this->withCredentials();
    config()->set('aucvy.ai.confirmation_threshold', 0.8);
});

function recognise(Lot $lot): AiRun
{
    $task = TaskRegistry::forLot('recognise_lot', $lot);

    $run = AiRun::create([
        'subject_type' => $lot->getMorphClass(),
        'subject_id' => $lot->id,
        'user_id' => $lot->seller_id,
        'agent' => $task->agent(),
        'task' => $task->name(),
        'input' => $task->input(),
        'cache_key' => $task->cacheKey(),
        'status' => AiRunStatus::Queued,
    ]);

    RunAiTask::dispatch($run, $task);

    return $run->fresh();
}

it('geeft elk veld van Ivy een bron en een zekerheid, en vraagt bevestiging onder de drempel', function () {
    $lot = Lot::factory()->anonymousDraft()->create([
        'description' => 'Keuken van Siemens uit een showroom.',
        'brand' => null, 'model' => null, 'year' => null, 'condition' => null,
    ]);

    $run = recognise($lot);
    $lot->refresh();

    expect($run->status)->toBe(AiRunStatus::Completed)
        ->and($lot->brand)->toBe('Siemens')
        ->and($lot->field_sources['brand']['source'])->toBe(Provenance::PHOTO)
        ->and($lot->field_sources['brand']['confidence'])->toBe(0.95)
        ->and($lot->field_sources['brand']['needs_confirmation'])->toBeFalse()
        ->and($lot->field_sources['brand']['run_id'])->toBe($run->id)
        // De conditie uit foto's alleen is onzeker: die moet de verkoper bevestigen.
        ->and($lot->field_sources['condition']['needs_confirmation'])->toBeTrue()
        ->and(Provenance::unconfirmed($lot))->toBe(['condition']);
});

it('markeert eigen invoer als bron verkoper, en Ivy overschrijft die niet', function () {
    $lot = Lot::factory()->anonymousDraft()->create(['brand' => null, 'condition' => null, 'description' => 'Siemens keuken']);

    $this->withCookie(DraftAccess::COOKIE, $lot->draft_token)
        ->patchJson("/api/drafts/{$lot->id}", ['condition' => LotCondition::New->value])
        ->assertOk()
        ->assertJsonPath('fields.condition.source', 'seller')
        ->assertJsonPath('fields.condition.needs_confirmation', false);

    // De verkoper wist het merk weer: ook dat is een menselijk besluit.
    $this->withCookie(DraftAccess::COOKIE, $lot->draft_token)
        ->patchJson("/api/drafts/{$lot->id}", ['brand' => null])
        ->assertOk();

    recognise($lot->fresh());
    $lot->refresh();

    expect($lot->condition)->toBe(LotCondition::New)
        ->and($lot->brand)->toBeNull()
        ->and($lot->field_sources['brand']['source'])->toBe('seller');
});

it('weigert publiceren zolang een Ivy-veld onbevestigd is, en niet meer na bevestiging', function () {
    $seller = User::factory()->verified()->business()->create();
    $lot = Lot::factory()->anonymousDraft()->create([
        'seller_id' => $seller->id,
        'title' => 'Showroomkeuken',
        'category_id' => Category::factory()->create()->id,
        'description' => 'Keuken uit een showroom, drie jaar oud, met complete apparatuur erbij.',
        'condition' => null,
        'draft_meta' => ['sale_type' => 'auction', 'start_price_cents' => 50_000, 'ownership_declared' => true, 'warranty' => 'none'],
    ]);
    LotMedia::factory()->create(['lot_id' => $lot->id]);

    recognise($lot);

    expect(Provenance::unconfirmed($lot->fresh()))->toBe(['condition']);

    // Vanaf hier alleen nog de sluitingsjob van het publiceren faken.
    Queue::fakeExcept([RunAiTask::class]);

    $this->actingAs($seller)
        ->postJson("/api/drafts/{$lot->id}/publish")
        ->assertStatus(422)
        ->assertJsonPath('errors.confirmed.0', fn (string $message) => str_contains($message, 'conditie'));

    $this->actingAs($seller)
        ->postJson("/api/drafts/{$lot->id}/confirm", ['fields' => ['condition']])
        ->assertOk()
        ->assertJsonPath('fields.condition.needs_confirmation', false)
        ->assertJsonPath('preflight.ok', true);

    $this->actingAs($seller)
        ->postJson("/api/drafts/{$lot->id}/publish")
        ->assertOk()
        ->assertJsonPath('published', true);
});

it('bevestigt alles in één keer', function () {
    $lot = Lot::factory()->anonymousDraft()->create(['condition' => null, 'brand' => null, 'description' => 'Een steiger.']);
    recognise($lot);

    $this->withCookie(DraftAccess::COOKIE, $lot->draft_token)
        ->postJson("/api/drafts/{$lot->id}/confirm", ['all' => true])
        ->assertOk();

    expect(Provenance::unconfirmed($lot->fresh()))->toBe([])
        ->and($lot->fresh()->field_sources['condition']['confirmed_at'])->not->toBeNull();
});

it('geeft de waarde-indicatie een bron met het aantal vergelijkingen, zonder bevestigingsplicht', function () {
    $lot = Lot::factory()->anonymousDraft()->create(['description' => 'Siemens keuken', 'title' => 'Keuken']);
    LotMedia::factory()->count(2)->create(['lot_id' => $lot->id]);

    $task = TaskRegistry::forLot('estimate_value', $lot);
    $run = AiRun::create([
        'subject_type' => $lot->getMorphClass(), 'subject_id' => $lot->id,
        'agent' => $task->agent(), 'task' => $task->name(), 'input' => $task->input(),
        'cache_key' => $task->cacheKey(), 'status' => AiRunStatus::Queued,
    ]);
    RunAiTask::dispatch($run, $task);

    $lot->refresh();

    expect($lot->value_min_cents)->not->toBeNull()
        ->and($lot->field_sources['value']['source'])->toBe(Provenance::BENCHMARK)
        ->and($lot->field_sources['value']['comparisons'])->toBeGreaterThan(0)
        ->and($lot->field_sources['value']['needs_confirmation'])->toBeFalse();
});

it('laat PublishLot zelf ook weigeren bij een onbevestigd veld', function () {
    $seller = User::factory()->verified()->business()->create();
    $lot = Lot::factory()->anonymousDraft()->create([
        'seller_id' => $seller->id,
        'category_id' => Category::factory()->create()->id,
        'draft_meta' => ['sale_type' => 'auction', 'start_price_cents' => 50_000, 'ownership_declared' => true, 'warranty' => 'none'],
    ]);
    LotMedia::factory()->create(['lot_id' => $lot->id]);
    Provenance::markAi($lot, ['year' => 0.4], Provenance::PHOTO);

    expect(fn () => app(PublishLot::class)->handle($lot->fresh(), $seller))
        ->toThrow(ValidationException::class);
});
