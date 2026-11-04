<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\IvyConversation;
use App\Models\IvyGuestUpload;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;

/**
 * ClaimGuestConversation: wie als gast met Ivy sprak en daarna inlogt of
 * een account maakt, neemt het gesprek en de foto's mee op naam.
 */
final class ClaimGuestIvyConversation
{
    public function handleLogin(Login $event): void
    {
        $this->claim($event->user->id);
    }

    public function handleRegistered(Registered $event): void
    {
        $this->claim($event->user->id);
    }

    private function claim(int $userId): void
    {
        $token = (string) request()->cookie('ivy_guest', '');

        if ($token === '') {
            return;
        }

        IvyConversation::query()->where('guest_token', $token)->whereNull('user_id')->update(['user_id' => $userId, 'guest_token' => null]);
        IvyGuestUpload::query()->where('guest_token', $token)->update(['expires_at' => now()->addDays(30)]);
    }
}
