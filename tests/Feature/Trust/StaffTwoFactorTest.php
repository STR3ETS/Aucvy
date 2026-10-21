<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\TwoFactorCode;
use Illuminate\Support\Facades\Notification;

/*
| 2FA verplicht voor beheer (briefing sectie 2 en 8): wie bij de backoffice
| kan, logt altijd in twee stappen in, ook zonder uitbetaalrekening.
*/

it('vraagt een medewerker altijd om een code', function () {
    Notification::fake();

    $staff = User::factory()->verified()->create(['password' => 'wachtwoord', 'is_staff' => true]);

    $this->post('/inloggen', ['email' => $staff->email, 'password' => 'wachtwoord'])
        ->assertRedirect(route('two-factor.challenge'));

    $this->assertGuest();
    Notification::assertSentTo($staff, TwoFactorCode::class);
});
