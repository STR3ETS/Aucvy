<?php

declare(strict_types=1);

use App\Enums\LotStatus;
use App\Models\Auction;
use App\Models\AuditLog;
use App\Models\Lot;
use App\Models\User;
use App\Notifications\LotModerated;
use Illuminate\Support\Facades\Notification;

/*
| Moderatie (briefing: admin P0): elke gepubliceerde kavel komt één keer
| langs. Goedkeuren legt de beoordeling vast; afwijzen haalt de kavel
| offline met een reden, sluit een lopende veiling en mailt de verkoper.
*/

beforeEach(function () {
    Notification::fake();
    $this->staff = User::factory()->create(['is_staff' => true]);
});

function liveLot(string $title = 'Heftruck Linde H25'): Lot
{
    $lot = Lot::factory()->live()->create(['title' => $title, 'published_at' => now()->subHour()]);
    Auction::factory()->create(['lot_id' => $lot->id, 'current_price_cents' => 250_000, 'ends_at' => now()->addDays(3)]);

    return $lot;
}

it('toont alleen gepubliceerde kavels die nog niet beoordeeld zijn', function () {
    $open = liveLot('Nog te beoordelen');
    $done = liveLot('Al beoordeeld');
    $done->forceFill(['reviewed_at' => now()])->save();
    Lot::factory()->anonymousDraft()->create(['title' => 'Concept blijft weg']);

    $this->actingAs($this->staff)->get('/admin/moderatie')
        ->assertOk()
        ->assertSee('Nog te beoordelen')
        ->assertDontSee('Al beoordeeld')
        ->assertDontSee('Concept blijft weg')
        ->assertSee(route('admin.moderation.reject', $open), escape: false);
});

it('keurt een kavel goed, met spoor en mail', function () {
    $lot = liveLot();

    $this->actingAs($this->staff)
        ->post(route('admin.moderation.approve', $lot), ['note' => 'Foto\'s en documenten kloppen.'])
        ->assertRedirect();

    $lot->refresh();

    expect($lot->reviewed_at)->not->toBeNull()
        ->and($lot->reviewed_by)->toBe($this->staff->id)
        ->and($lot->status)->toBe(LotStatus::Live)
        ->and(AuditLog::query()->where('action', 'lot.approved')->where('subject_id', $lot->id)->exists())->toBeTrue();

    Notification::assertSentTo($lot->seller, LotModerated::class, fn (LotModerated $mail) => $mail->approved);
});

it('wijst een kavel af: offline, veiling gesloten zonder winnaar, reden per mail', function () {
    $lot = liveLot();

    $this->actingAs($this->staff)
        ->post(route('admin.moderation.reject', $lot), ['reason' => 'Het eigendomsbewijs ontbreekt en de foto\'s tonen een ander object.'])
        ->assertRedirect();

    $lot->refresh();

    expect($lot->status)->toBe(LotStatus::Cancelled)
        ->and($lot->moderation_note)->toContain('eigendomsbewijs')
        ->and($lot->auction->isClosed())->toBeTrue()
        ->and($lot->auction->winner_id)->toBeNull()
        ->and(AuditLog::query()->where('action', 'lot.rejected')->where('subject_id', $lot->id)->exists())->toBeTrue();

    Notification::assertSentTo($lot->seller, LotModerated::class, fn (LotModerated $mail) => ! $mail->approved && str_contains((string) $mail->reason, 'eigendomsbewijs'));

    $this->get(route('lots.show', $lot))->assertNotFound();
});

it('eist een reden van minstens tien tekens bij afwijzen', function () {
    $lot = liveLot();

    $this->actingAs($this->staff)
        ->from('/admin/moderatie')
        ->post(route('admin.moderation.reject', $lot), ['reason' => 'nee'])
        ->assertSessionHasErrors('reason');

    expect($lot->fresh()->status)->toBe(LotStatus::Live);
});

it('houdt gewone gebruikers buiten de moderatie', function () {
    $lot = liveLot();

    $this->actingAs(User::factory()->create())->get('/admin/moderatie')->assertForbidden();
    $this->actingAs(User::factory()->create())->post(route('admin.moderation.approve', $lot))->assertForbidden();
});
