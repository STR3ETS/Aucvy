<?php

declare(strict_types=1);

use App\Enums\LotStatus;
use App\Lots\DraftAccess;
use App\Models\AuditLog;
use App\Models\Lot;
use App\Models\LotMedia;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
| Foto's: omslag kiezen in de wizard, en foto's beheren op een kavel die al
| live is (briefing sectie 4 en 5). Alleen de eigenaar, met spoor.
*/

beforeEach(function () {
    Storage::fake('public');
    $this->withCredentials();
});

it('zet een foto als omslag en nummert de rest door', function () {
    $this->postJson('/api/drafts')->assertCreated();
    $lot = Lot::sole();
    $first = LotMedia::factory()->create(['lot_id' => $lot->id, 'position' => 0]);
    $second = LotMedia::factory()->create(['lot_id' => $lot->id, 'position' => 1]);
    $third = LotMedia::factory()->create(['lot_id' => $lot->id, 'position' => 2]);

    $this->withCookie(DraftAccess::COOKIE, $lot->draft_token)
        ->postJson("/api/drafts/{$lot->id}/media/{$third->id}/cover")
        ->assertOk()
        ->assertJsonPath('all.0.id', $third->id)
        ->assertJsonPath('all.1.id', $first->id)
        ->assertJsonPath('all.2.id', $second->id);

    expect($third->fresh()->position)->toBe(0)
        ->and($first->fresh()->position)->toBe(1)
        ->and($second->fresh()->position)->toBe(2);
});

it('laat de eigenaar foto\'s toevoegen en verwijderen op een live kavel, met spoor', function () {
    $seller = User::factory()->verified()->create();
    $lot = Lot::factory()->create(['seller_id' => $seller->id, 'status' => LotStatus::Live, 'published_at' => now()]);
    $existing = LotMedia::factory()->create(['lot_id' => $lot->id, 'position' => 0]);

    $this->actingAs($seller)
        ->post("/api/drafts/{$lot->id}/media", ['file' => UploadedFile::fake()->image('extra.jpg', 1200, 900)])
        ->assertCreated()
        ->assertJsonCount(2, 'all');

    $this->actingAs($seller)
        ->deleteJson("/api/drafts/{$lot->id}/media/{$existing->id}")
        ->assertOk()
        ->assertJsonCount(1, 'all');

    expect($lot->media()->sole()->position)->toBe(0)
        ->and(AuditLog::query()->where('action', 'lot.media_changed')->where('subject_id', $lot->id)->count())->toBe(2);
});

it('houdt anderen en afgeronde kavels buiten het fotobeheer', function () {
    $seller = User::factory()->verified()->create();
    $live = Lot::factory()->create(['seller_id' => $seller->id, 'status' => LotStatus::Live]);
    $sold = Lot::factory()->create(['seller_id' => $seller->id, 'status' => LotStatus::Sold]);

    $this->actingAs(User::factory()->verified()->create())
        ->post("/api/drafts/{$live->id}/media", ['file' => UploadedFile::fake()->image('x.jpg')])
        ->assertNotFound();

    $this->actingAs($seller)
        ->post("/api/drafts/{$sold->id}/media", ['file' => UploadedFile::fake()->image('x.jpg')])
        ->assertNotFound();
});

it('toont het fotobeheer op de bewerkpagina van een live kavel', function () {
    $seller = User::factory()->verified()->create();
    $lot = Lot::factory()->create(['seller_id' => $seller->id, 'status' => LotStatus::Live]);
    LotMedia::factory()->create(['lot_id' => $lot->id]);

    $this->actingAs($seller)->get(route('dashboard.lots.edit', $lot))
        ->assertOk()
        ->assertSee('lotMedia(', escape: false)
        ->assertSee("Foto's toevoegen", escape: false);
});
