<?php

declare(strict_types=1);

use App\Ivy\Tasks\GroupPhotos;
use App\Lots\DraftAccess;
use App\Models\Lot;
use App\Models\LotMedia;
use Illuminate\Support\Facades\Storage;

/*
| Een inventaris in één concept: Ivy zet de foto's per product bij elkaar, de
| verkoper maakt er losse kavels van. Elk product krijgt een eigen concept
| met zijn eigen foto's; ze blijven als groep bij elkaar.
*/

beforeEach(function () {
    $this->withCredentials();
    Storage::fake('public');

    $this->postJson('/api/drafts')->assertCreated();
    $this->lot = Lot::sole();
    $this->lot->update(['draft_meta' => ['scope' => 'inventory', 'intake' => 'Restpartij kantoor.', 'sale_type' => 'auction']]);

    // Drie foto's van drie producten, met een bestand zodat Ivy ze ook echt krijgt.
    $this->photos = collect(range(0, 2))->map(function (int $position): LotMedia {
        $stored = 'lots/'.$this->lot->public_id.'/foto-'.$position.'.jpg';
        Storage::disk('public')->put($stored, 'jpg');

        return LotMedia::factory()->create(['lot_id' => $this->lot->id, 'position' => $position, 'variants' => ['original' => $stored]]);
    });
});

it('stelt per product een groep voor, in foto-id\'s van het concept', function () {
    $started = $this->withCookie(DraftAccess::COOKIE, $this->lot->draft_token)
        ->postJson('/api/ivy/tasks', ['task' => 'group_photos', 'lot_id' => $this->lot->id])
        ->assertCreated()
        ->json('run');

    $run = $this->withCookie(DraftAccess::COOKIE, $this->lot->draft_token)
        ->getJson("/api/ivy/tasks/{$started['id']}")
        ->assertOk()
        ->json('run');
    $groups = $run['groups'];

    // De zekerheid staat er in gewone taal, niet als vertaalsleutel.
    expect($run['confidence_hint'])->toStartWith('Redelijke zekerheid');

    expect($groups)->toHaveCount(3)
        ->and(array_merge(...array_column($groups, 'media')))->toBe($this->photos->pluck('id')->all())
        // Een voorstel: het concept zelf blijft zoals het is.
        ->and($this->lot->fresh()->media)->toHaveCount(3);
});

it('vertaalt fotonummers naar id\'s en laat geen foto zoekraken', function () {
    $groups = GroupPhotos::groupsFor(['groups' => [
        ['photos' => [2, 2], 'label' => 'Monitor'],
        ['photos' => [9], 'label' => 'Bestaat niet'],
        ['photos' => [3], 'label' => ''],
    ]], [11, 12, 13]);

    expect($groups)->toBe([
        // Foto 1 vergat het model: die gaat bij de eerste groep.
        ['label' => 'Monitor', 'media' => [12, 11]],
        ['label' => 'Product 2', 'media' => [13]],
    ]);
});

it('maakt van elke groep een eigen kavel met zijn eigen foto\'s', function () {
    [$monitor, $jacket, $speakers] = $this->photos->pluck('id')->all();

    $response = $this->withCookie(DraftAccess::COOKIE, $this->lot->draft_token)
        ->postJson("/api/drafts/{$this->lot->id}/split", ['groups' => [[$monitor], [$jacket], [$speakers]]])
        ->assertOk()
        ->assertJsonCount(2, 'draft.siblings')
        ->assertJsonCount(1, 'draft.media');

    $siblings = Lot::query()->whereKeyNot($this->lot->id)->orderBy('id')->get();
    $batch = $this->lot->fresh()->draft_meta['batch'];

    expect($siblings)->toHaveCount(2)
        // Elk een eigen token; de cookie van het eerste concept opent ze allemaal.
        ->and($siblings->pluck('draft_token')->unique())->toHaveCount(2)
        ->and($siblings->pluck('draft_token'))->not->toContain($this->lot->draft_token)
        ->and($siblings->map(fn (Lot $lot) => $lot->draft_meta['batch'])->unique()->all())->toBe([$batch])
        ->and($siblings[0]->draft_meta['sale_type'])->toBe('auction')
        ->and($siblings[0]->media->pluck('id')->all())->toBe([$jacket])
        ->and($siblings[1]->media->pluck('id')->all())->toBe([$speakers])
        ->and($response->json('draft.siblings.0.id'))->toBe($siblings[0]->id);

    // Vanuit een ander kavel zie je de rest van de groep ook.
    $this->withCookie(DraftAccess::COOKIE, $this->lot->draft_token)
        ->getJson("/api/drafts/{$siblings[0]->id}")
        ->assertOk()
        ->assertJsonCount(2, 'draft.siblings');
});

it('weigert een verdeling met een foto van een ander concept', function () {
    $foreign = LotMedia::factory()->create();

    $this->withCookie(DraftAccess::COOKIE, $this->lot->draft_token)
        ->postJson("/api/drafts/{$this->lot->id}/split", ['groups' => [[$this->photos[0]->id], [$foreign->id]]])
        ->assertStatus(422);

    expect(Lot::query()->where('draft_token', $this->lot->draft_token)->count())->toBe(1);
});
