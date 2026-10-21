<?php

declare(strict_types=1);

use App\Enums\AiRunStatus;
use App\Enums\LotStatus;
use App\Ivy\Jobs\RunAiTask;
use App\Ivy\Tasks\TranslateListing;
use App\Lots\Management\UpdateLot;
use App\Lots\Translation;
use App\Models\Auction;
use App\Models\Lot;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

/*
| Vertaling van de advertentie (briefing sectie 5, stap 4): Ivy vertaalt
| naar het Engels bij publicatie en na een tekstwijziging; de kavelpagina
| toont het Engels op verzoek, de Nederlandse tekst blijft bindend.
*/

beforeEach(fn () => config(['aucvy.ai.provider' => 'fake', 'aucvy.ai.enabled' => true, 'aucvy.ai.translate' => true]));

function translatableLot(): Lot
{
    $seller = User::factory()->verified()->create();
    $lot = Lot::factory()->for($seller, 'seller')->create(['status' => LotStatus::Live, 'title' => 'Linde H25 heftruck', 'description' => "Nette heftruck uit 2019.\nOnderhoud gedaan."]);
    Auction::factory()->for($lot)->create();

    return $lot;
}

it('vertaalt een gepubliceerde kavel en toont het Engels op de kavelpagina op verzoek', function () {
    $lot = translatableLot();

    $run = Translation::start($lot);

    expect($run)->not->toBeNull()
        ->and($run->fresh()->status)->toBe(AiRunStatus::Completed);

    $english = $lot->fresh()->translation('en');

    expect($english['title'])->toBe('[EN] Linde H25 heftruck')
        ->and($english['description'])->toStartWith('[EN] Nette heftruck')
        ->and($english['run_id'])->toBe($run->id);

    $this->get(route('lots.show', $lot))
        ->assertOk()
        ->assertSee('Read in English')
        ->assertDontSee('[EN] Linde');

    $this->get(route('lots.show', ['lot' => $lot, 'lang' => 'en']))
        ->assertOk()
        ->assertSee('[EN] Linde H25 heftruck')
        ->assertSee('Automatically translated')
        ->assertSee('Toon Nederlands');

    // Een tweede start is overbodig zolang de tekst gelijk blijft.
    expect(Translation::start($lot->fresh()))->toBeNull();
});

it('laat de vertaling vervallen na een tekstwijziging en vraagt een nieuwe aan', function () {
    $lot = translatableLot();
    Translation::start($lot);
    expect($lot->fresh()->translation('en'))->not->toBeNull();

    Queue::fake();
    app(UpdateLot::class)->handle($lot->fresh(), $lot->seller, ['title' => 'Linde H25 heftruck, bouwjaar 2019']);

    expect($lot->fresh()->translation('en'))->toBeNull();
    Queue::assertPushed(RunAiTask::class, fn (RunAiTask $job): bool => $job->task instanceof TranslateListing && $job->task->lot->is($lot));

    $this->get(route('lots.show', ['lot' => $lot, 'lang' => 'en']))->assertOk()->assertDontSee('[EN]')->assertSee('Linde H25 heftruck, bouwjaar 2019');
});

it('laat de verkoper een vertaling aanvragen en respecteert de noodrem', function () {
    $lot = translatableLot();

    Queue::fake();
    $this->actingAs($lot->seller)
        ->post(route('dashboard.lots.translate', $lot))
        ->assertRedirect(route('dashboard.lots.edit', $lot))
        ->assertSessionHas('status');
    Queue::assertPushed(RunAiTask::class, fn (RunAiTask $job): bool => $job->task instanceof TranslateListing);

    $this->actingAs(User::factory()->create())->post(route('dashboard.lots.translate', $lot))->assertNotFound();

    config(['aucvy.ai.enabled' => false]);
    expect(Translation::start($lot->fresh(), 'en', true))->toBeNull();

    config(['aucvy.ai.enabled' => true, 'aucvy.ai.translate' => false]);
    expect(Translation::start($lot->fresh(), 'en', true))->toBeNull();

    $this->actingAs($lot->seller)->get(route('dashboard.lots.edit', $lot))->assertOk()->assertSee('Engelse vertaling');
});

it('vertaalt ook een gepauzeerde kavel opnieuw na een tekstwijziging', function () {
    $lot = translatableLot();
    Translation::start($lot);
    $lot->forceFill(['status' => LotStatus::Paused])->save();

    Queue::fake();
    app(UpdateLot::class)->handle($lot->fresh(), $lot->seller, ['description' => 'Nieuwe tekst tijdens de pauze.']);

    Queue::assertPushed(RunAiTask::class, fn (RunAiTask $job): bool => $job->task instanceof TranslateListing);
});
