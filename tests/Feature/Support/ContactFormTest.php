<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketForwarded;
use App\Notifications\SupportTicketReceived;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

/*
| Support (briefing sectie 1 en 7): het contactformulier wordt een ticket,
| gaat naar de supportinbox en krijgt een ontvangstbevestiging; beheer sluit
| het in de backoffice.
*/

beforeEach(function () {
    Notification::fake();
    config()->set('aucvy.support.email', 'support@aucvy.test');
});

it('toont het formulier, ook zonder account', function () {
    $this->get('/contact')->assertOk()->assertSee('Stel je vraag')->assertSee('Versturen');
});

it('maakt een ticket, mailt support en bevestigt aan de afzender', function () {
    $this->post('/contact', [
        'name' => 'Sanne de Boer',
        'email' => 'sanne@example.test',
        'subject' => 'Vraag over transport',
        'message' => 'Kan ik een heftruck ook laten bezorgen in België, en wat kost dat ongeveer?',
    ])->assertRedirect(route('contact'))->assertSessionHas('status');

    $ticket = SupportTicket::query()->sole();

    expect($ticket->name)->toBe('Sanne de Boer')
        ->and($ticket->user_id)->toBeNull()
        ->and($ticket->isOpen())->toBeTrue();

    Notification::assertSentOnDemand(SupportTicketForwarded::class, fn (SupportTicketForwarded $mail, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'support@aucvy.test' && $mail->ticket->is($ticket));
    Notification::assertSentOnDemand(SupportTicketReceived::class, fn (SupportTicketReceived $mail, array $channels, AnonymousNotifiable $notifiable) => array_key_exists('sanne@example.test', $notifiable->routes['mail']));
});

it('koppelt het ticket aan het account van wie is ingelogd', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/contact', [
        'name' => $user->name,
        'email' => $user->email,
        'subject' => 'Verificatie',
        'message' => 'Mijn identiteitscontrole staat al twee dagen in behandeling, klopt dat?',
    ])->assertRedirect();

    expect(SupportTicket::query()->sole()->user_id)->toBe($user->id);
});

it('weigert een te kort bericht en een gevulde honingpot', function () {
    $this->from('/contact')->post('/contact', [
        'name' => 'Bot', 'email' => 'bot@example.test', 'subject' => 'Hoi', 'message' => 'kort',
    ])->assertSessionHasErrors('message');

    $this->from('/contact')->post('/contact', [
        'name' => 'Bot', 'email' => 'bot@example.test', 'subject' => 'Hallo daar', 'message' => str_repeat('spam ', 10), 'website' => 'http://spam',
    ])->assertSessionHasErrors('website');

    expect(SupportTicket::query()->count())->toBe(0);
});

it('laat beheer tickets zien, sluiten en heropenen', function () {
    $staff = User::factory()->create(['is_staff' => true]);
    $ticket = SupportTicket::factory()->create(['subject' => 'Factuur ontbreekt']);
    SupportTicket::factory()->closed()->create(['subject' => 'Al afgehandeld']);

    $this->actingAs($staff)->get('/admin/support')
        ->assertOk()
        ->assertSee('Factuur ontbreekt')
        ->assertDontSee('Al afgehandeld');

    $this->actingAs($staff)->get('/admin/support?gesloten=1')->assertOk()->assertSee('Al afgehandeld');

    $this->actingAs($staff)->post(route('admin.support.close', $ticket), ['reply' => 'Factuur nagestuurd.'])->assertRedirect();

    $ticket->refresh();

    expect($ticket->isOpen())->toBeFalse()
        ->and($ticket->closed_by)->toBe($staff->id)
        ->and($ticket->reply)->toBe('Factuur nagestuurd.')
        ->and(AuditLog::query()->where('action', 'support.closed')->where('subject_id', $ticket->id)->exists())->toBeTrue();

    $this->actingAs($staff)->post(route('admin.support.reopen', $ticket))->assertRedirect();
    expect($ticket->fresh()->isOpen())->toBeTrue();

    $this->actingAs(User::factory()->create())->get('/admin/support')->assertForbidden();
});
