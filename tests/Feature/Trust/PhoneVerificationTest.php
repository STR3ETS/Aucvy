<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Sms\PhoneVerification;
use App\Support\Sms\SmsSender;

/*
| Telefoon bevestigen per sms (besluit Raph, 2 november 2026). De partij is
| nog open (V3); tot dan gaat de sms naar het log, achter hetzelfde contract.
*/

function lastSmsCode(): string
{
    $sent = app(SmsSender::class)->sent();
    preg_match('/\b(\d{6})\b/', end($sent)['message'], $match);

    return $match[1];
}

it('maakt van een Nederlands nummer een internationaal nummer', function (string $input, ?string $expected) {
    expect(PhoneVerification::normalise($input))->toBe($expected);
})->with([
    ['06 12 34 56 78', '+31612345678'],
    ['+31 6 12345678', '+31612345678'],
    ['0032 470 12 34 56', '+32470123456'],
    ['12', null],
]);

it('stuurt een code en bevestigt het nummer met de juiste code', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('verification.phone'), ['phone' => '06 12345678'])
        ->assertRedirect(route('verification.overview'))
        ->assertSessionHas('status');

    expect($user->fresh()->phone)->toBe('+31612345678')
        ->and($user->fresh()->phone_verified_at)->toBeNull()
        ->and(app(SmsSender::class)->sent()[0]['to'])->toBe('+31612345678');

    $this->actingAs($user)->post(route('verification.phone.confirm'), ['code' => '000000'])
        ->assertSessionHasErrors('code');

    $this->actingAs($user)->post(route('verification.phone.confirm'), ['code' => lastSmsCode()])
        ->assertRedirect(route('verification.overview'));

    expect($user->fresh()->phone_verified_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'user.phone_verified')->where('subject_id', $user->id)->exists())->toBeTrue();
});

it('laat een code niet gelden voor een ander nummer', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('verification.phone'), ['phone' => '0612345678']);
    $code = lastSmsCode();

    $user->forceFill(['phone' => '+31687654321'])->save();

    $this->actingAs($user)->post(route('verification.phone.confirm'), ['code' => $code])
        ->assertSessionHasErrors('code');
});

it('stuurt hooguit drie codes per tien minuten', function () {
    $user = User::factory()->create(['phone' => '+31612345678']);

    foreach (range(1, 3) as $attempt) {
        $this->actingAs($user)->post(route('verification.phone.resend'))->assertSessionHasNoErrors();
    }

    $this->actingAs($user)->post(route('verification.phone.resend'))->assertSessionHasErrors('phone');

    expect(app(SmsSender::class)->sent())->toHaveCount(3);
});

it('weigert een onbruikbaar nummer zonder sms', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('verification.phone'), ['phone' => 'bel me'])
        ->assertSessionHasErrors('phone');

    expect(app(SmsSender::class)->sent())->toBe([]);
});
