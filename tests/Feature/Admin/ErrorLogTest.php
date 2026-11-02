<?php

declare(strict_types=1);

use App\Enums\LotStatus;
use App\Models\AuditLog;
use App\Models\ErrorEvent;
use App\Models\Lot;
use App\Models\User;
use App\Support\Health\ErrorLog;
use App\Support\Health\IvySignals;

/*
| Foutlog met audittrail in beheer en Ivy-statusmeldingen op het overzicht
| (besluit Raph, 2 november 2026).
*/

beforeEach(function () {
    $this->staff = User::factory()->create(['is_staff' => true]);
});

it('telt dezelfde soort fout op in één regel', function () {
    $log = app(ErrorLog::class);

    foreach (range(1, 3) as $attempt) {
        $log->record(new RuntimeException("Poging {$attempt} mislukt"));
    }

    $event = ErrorEvent::query()->sole();

    expect($event->occurrences)->toBe(3)
        ->and($event->message)->toBe('Poging 3 mislukt')
        ->and($event->class)->toBe(RuntimeException::class)
        ->and($event->file)->toBe('tests/Feature/Admin/ErrorLogTest.php');
});

it('legt een gemelde fout automatisch vast', function () {
    report(new LogicException('Iets onverwachts'));

    expect(ErrorEvent::query()->where('class', LogicException::class)->value('message'))->toBe('Iets onverwachts');
});

it('maskeert sleutels, tokens en e-mailadressen', function () {
    $scrubbed = app(ErrorLog::class)->scrub('invalid x-api-key: sk-ant-geheim123 voor jan@bedrijf.nl met Bearer abc.def en ?token=xyz');

    expect($scrubbed)->not->toContain('geheim123')
        ->not->toContain('jan@bedrijf.nl')
        ->not->toContain('abc.def')
        ->not->toContain('xyz');
});

it('handelt een fout af met notitie en spoor, en opent hem weer als hij terugkomt', function () {
    $exception = new RuntimeException('Mailgun weigert');
    app(ErrorLog::class)->record($exception);
    $event = ErrorEvent::query()->sole();

    $this->actingAs($this->staff)->get(route('admin.errors'))
        ->assertOk()
        ->assertSee('Mailgun weigert');

    $this->actingAs($this->staff)
        ->post(route('admin.errors.resolve', $event), ['note' => 'Sleutel vervangen'])
        ->assertRedirect();

    $event->refresh();

    expect($event->isOpen())->toBeFalse()
        ->and($event->resolved_by)->toBe($this->staff->id)
        ->and(AuditLog::query()->where('action', 'error.resolved')->where('subject_id', $event->id)->exists())->toBeTrue();

    $this->actingAs($this->staff)->get(route('admin.errors'))->assertDontSee('Mailgun weigert');

    // Dezelfde fout (zelfde plek) komt terug: weer open.
    app(ErrorLog::class)->record($exception);

    expect($event->fresh()->isOpen())->toBeTrue()
        ->and($event->fresh()->occurrences)->toBe(2);
});

it('is alleen voor medewerkers', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.errors'))->assertForbidden();
});

it('toont Ivy-signalen op het overzicht, ernstigste eerst', function () {
    app(ErrorLog::class)->record(new RuntimeException('Nieuwe fout'));
    Lot::factory()->create(['status' => LotStatus::PendingReview, 'screening' => ['verdict' => 'escalate', 'issues' => []]]);

    $signals = app(IvySignals::class)->all();
    $titles = array_column($signals, 'title');

    expect($titles)->toContain('1 open fout', '1 kavel wacht op akkoord')
        ->and(array_search('1 open fout', $titles, true))->toBeLessThan(array_search('1 kavel wacht op akkoord', $titles, true));

    $this->actingAs($this->staff)->get(route('admin.index'))
        ->assertOk()
        ->assertSee('Dit vraagt aandacht')
        ->assertSee('1 open fout');
});
