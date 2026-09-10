<?php

declare(strict_types=1);

use App\Enums\ViewingStatus;
use App\Models\Lot;
use App\Models\User;
use App\Models\Viewing;
use App\Notifications\ViewingCancelled;
use App\Notifications\ViewingConfirmed;
use App\Notifications\ViewingRequested;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/*
| Bezichtigingen (hfst. 7): de koper stelt een moment voor, de verkoper
| bevestigt per getekende maillink, en pas daarná gaat het adres mee - per
| mail, nooit op de pagina.
*/

beforeEach(function () {
    Notification::fake();

    $this->lot = Lot::factory()->live()->create(['viewing_available' => true]);
    $this->buyer = User::factory()->verified()->create();
});

it('maakt een aanvraag en mailt de verkoper', function () {
    $this->actingAs($this->buyer)
        ->postJson("/api/lots/{$this->lot->id}/viewings", [
            'slot_at' => now()->addDays(2)->toDateTimeString(),
            'note' => 'Ik kom met een aanhanger.',
        ])
        ->assertCreated()
        ->assertJsonPath('viewing.status', 'requested');

    Notification::assertSentTo($this->lot->seller, ViewingRequested::class);
});

it('weigert een aanvraag als bezichtiging niet kan', function () {
    $this->lot->update(['viewing_available' => false]);

    $this->actingAs($this->buyer)
        ->postJson("/api/lots/{$this->lot->id}/viewings", [
            'slot_at' => now()->addDays(2)->toDateTimeString(),
        ])
        ->assertStatus(422);

    Notification::assertNothingSent();
});

it('weigert de verkoper op zijn eigen kavel', function () {
    $this->actingAs($this->lot->seller)
        ->postJson("/api/lots/{$this->lot->id}/viewings", [
            'slot_at' => now()->addDays(2)->toDateTimeString(),
        ])
        ->assertStatus(422);
});

it('houdt het bij een aanvraag per koper per kavel', function () {
    $payload = ['slot_at' => now()->addDays(2)->toDateTimeString()];

    $this->actingAs($this->buyer)->postJson("/api/lots/{$this->lot->id}/viewings", $payload)->assertCreated();
    $this->actingAs($this->buyer)->postJson("/api/lots/{$this->lot->id}/viewings", $payload)->assertOk();

    expect(Viewing::query()->count())->toBe(1);
    Notification::assertSentToTimes($this->lot->seller, ViewingRequested::class, 1);
});

it('stuurt het adres pas na bevestiging via de getekende link', function () {
    $viewing = Viewing::create([
        'lot_id' => $this->lot->id,
        'user_id' => $this->buyer->id,
        'slot_at' => now()->addDays(2),
        'status' => ViewingStatus::Requested,
        'note' => null,
    ]);

    $this->get(URL::signedRoute('viewings.confirm', ['viewing' => $viewing]))
        ->assertOk()
        ->assertSee('Bezichtiging bevestigd');

    $viewing->refresh();
    expect($viewing->status)->toBe(ViewingStatus::Confirmed)
        ->and($viewing->address_sent_at)->not->toBeNull();

    // De bevestigingsmail aan de koper bevat het volledige adres.
    Notification::assertSentTo($this->buyer, ViewingConfirmed::class, function (ViewingConfirmed $notification) {
        $mail = $notification->toMail($this->buyer);

        return str_contains(implode(' ', $mail->introLines), $this->lot->location->street);
    });
});

it('wijst af via de getekende link zonder adres te delen', function () {
    $viewing = Viewing::create([
        'lot_id' => $this->lot->id,
        'user_id' => $this->buyer->id,
        'slot_at' => now()->addDays(2),
        'status' => ViewingStatus::Requested,
        'note' => null,
    ]);

    $this->get(URL::signedRoute('viewings.decline', ['viewing' => $viewing]))
        ->assertOk()
        ->assertSee('Moment afgewezen');

    expect($viewing->refresh()->status)->toBe(ViewingStatus::Cancelled);
    Notification::assertSentTo($this->buyer, ViewingCancelled::class);
});

it('doet niets zonder geldige handtekening', function () {
    $viewing = Viewing::create([
        'lot_id' => $this->lot->id,
        'user_id' => $this->buyer->id,
        'slot_at' => now()->addDays(2),
        'status' => ViewingStatus::Requested,
        'note' => null,
    ]);

    $this->get(route('viewings.confirm', ['viewing' => $viewing]))->assertForbidden();

    expect($viewing->refresh()->status)->toBe(ViewingStatus::Requested);
});

it('toont het adres nooit op de kavelpagina', function () {
    $this->actingAs($this->buyer)
        ->get('/kavel/'.$this->lot->slug)
        ->assertOk()
        ->assertSee('Bezichtiging plannen')
        ->assertDontSee($this->lot->location->street);
});
