<?php

declare(strict_types=1);

use App\Lots\DraftAccess;
use App\Models\Lot;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
| Hervatbare upload in delen (briefing sectie 5, stap 1): starten, delen
| sturen in willekeurige volgorde, tussendoor kijken wat er al is, en
| samenvoegen tot een gewone foto.
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    $this->withCredentials();
    $this->postJson('/api/drafts')->assertCreated();
    $this->lot = Lot::sole();
});

function chunkedPng(): string
{
    return UploadedFile::fake()->image('groot.png', 600, 400)->getContent();
}

function chunksOf(string $binary, int $size): array
{
    return str_split($binary, $size);
}

it('voegt de delen samen tot een foto en meldt tussendoor wat er al binnen is', function () {
    $binary = chunkedPng();
    $parts = chunksOf($binary, 4096);
    $as = fn () => $this->withCookie(DraftAccess::COOKIE, $this->lot->draft_token);

    $started = $as()->postJson("/api/drafts/{$this->lot->id}/uploads", ['name' => 'groot.png', 'size' => strlen($binary), 'type' => 'image/png', 'chunks' => count($parts)])
        ->assertCreated()
        ->json();

    $upload = $started['upload'];

    // Delen in willekeurige volgorde, het eerste deel als laatste.
    foreach (array_reverse(array_keys($parts)) as $index) {
        if ($index === 0) {
            continue;
        }

        $as()->post("/api/drafts/{$this->lot->id}/uploads/{$upload}/{$index}", ['chunk' => UploadedFile::fake()->createWithContent("deel-{$index}.bin", $parts[$index])])->assertOk();
    }

    $status = $as()->getJson("/api/drafts/{$this->lot->id}/uploads/{$upload}")->assertOk()->json();
    expect($status['received'])->not->toContain(0)->and(count($status['received']))->toBe(count($parts) - 1);

    $as()->postJson("/api/drafts/{$this->lot->id}/uploads/{$upload}/complete")->assertStatus(422)->assertJsonPath('missing.0', 0);

    $as()->post("/api/drafts/{$this->lot->id}/uploads/{$upload}/0", ['chunk' => UploadedFile::fake()->createWithContent('deel-0.bin', $parts[0])])->assertOk();

    $response = $as()->postJson("/api/drafts/{$this->lot->id}/uploads/{$upload}/complete")->assertCreated();

    $media = $this->lot->media()->sole();
    expect($media->checksum)->toBe(hash('sha256', $binary))
        ->and($media->size_bytes)->toBe(strlen($binary))
        ->and($media->width)->toBe(600)
        ->and($response->json('all.0.id'))->toBe($media->id)
        ->and(Storage::disk('local')->exists("chunks/{$this->lot->id}/{$upload}/meta.json"))->toBeFalse();

    $as()->getJson("/api/drafts/{$this->lot->id}/uploads/{$upload}")->assertNotFound();
});

it('weigert wat geen foto is en houdt vreemden buiten', function () {
    $as = fn () => $this->withCookie(DraftAccess::COOKIE, $this->lot->draft_token);
    $text = str_repeat('geen foto ', 500);

    $upload = $as()->postJson("/api/drafts/{$this->lot->id}/uploads", ['name' => 'tekst.txt', 'size' => strlen($text), 'type' => 'image/png', 'chunks' => 1])->assertCreated()->json('upload');
    $as()->post("/api/drafts/{$this->lot->id}/uploads/{$upload}/0", ['chunk' => UploadedFile::fake()->createWithContent('deel.bin', $text)])->assertOk();
    $as()->postJson("/api/drafts/{$this->lot->id}/uploads/{$upload}/complete")->assertStatus(422);

    expect($this->lot->media()->count())->toBe(0);

    // Een ander concept, waar deze browser geen token voor heeft.
    $other = Lot::factory()->anonymousDraft()->create();
    $as()->postJson("/api/drafts/{$other->id}/uploads", ['name' => 'x.png', 'size' => 10, 'type' => 'image/png', 'chunks' => 1])->assertNotFound();
    $as()->postJson("/api/drafts/{$this->lot->id}/uploads", ['name' => 'x.png', 'size' => 10, 'type' => 'application/pdf', 'chunks' => 1])->assertStatus(422);
});

it('gooit een upload weg die groter wordt dan aangekondigd en begrenst het aantal open uploads', function () {
    $as = fn () => $this->withCookie(DraftAccess::COOKIE, $this->lot->draft_token);

    $upload = $as()->postJson("/api/drafts/{$this->lot->id}/uploads", ['name' => 'klein.png', 'size' => 100, 'type' => 'image/png', 'chunks' => 2])->assertCreated()->json('upload');
    $as()->post("/api/drafts/{$this->lot->id}/uploads/{$upload}/0", ['chunk' => UploadedFile::fake()->createWithContent('deel.bin', str_repeat('x', 90))])->assertOk();
    $as()->post("/api/drafts/{$this->lot->id}/uploads/{$upload}/1", ['chunk' => UploadedFile::fake()->createWithContent('deel.bin', str_repeat('x', 90))])->assertStatus(422);
    $as()->getJson("/api/drafts/{$this->lot->id}/uploads/{$upload}")->assertNotFound();

    foreach (range(1, 5) as $index) {
        $as()->postJson("/api/drafts/{$this->lot->id}/uploads", ['name' => "open-{$index}.png", 'size' => 10, 'type' => 'image/png', 'chunks' => 1])->assertCreated();
    }

    $as()->postJson("/api/drafts/{$this->lot->id}/uploads", ['name' => 'zesde.png', 'size' => 10, 'type' => 'image/png', 'chunks' => 1])->assertStatus(422);
});
