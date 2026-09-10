<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('stuurt een herstel-link zonder te verklappen of het adres bestaat', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/wachtwoord-vergeten', ['email' => $user->email])
        ->assertSessionHas('status');

    // Onbekend adres: exact dezelfde respons.
    $this->post('/wachtwoord-vergeten', ['email' => 'bestaat-niet@example.nl'])
        ->assertSessionHas('status')
        ->assertSessionDoesntHaveErrors();

    Notification::assertSentTo($user, ResetPassword::class);
});

it('stelt met een geldig token een nieuw wachtwoord in', function () {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    $this->post('/wachtwoord-herstellen', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'gloednieuw-wachtwoord',
        'password_confirmation' => 'gloednieuw-wachtwoord',
    ])->assertRedirect(route('login'));

    expect(Auth::validate(['email' => $user->email, 'password' => 'gloednieuw-wachtwoord']))->toBeTrue()
        ->and(Auth::validate(['email' => $user->email, 'password' => 'wachtwoord']))->toBeFalse();
});

it('weigert een vervalst token', function () {
    $user = User::factory()->create();

    $this->post('/wachtwoord-herstellen', [
        'token' => 'vervalst-token',
        'email' => $user->email,
        'password' => 'gloednieuw-wachtwoord',
        'password_confirmation' => 'gloednieuw-wachtwoord',
    ])->assertSessionHasErrors('email');
});
