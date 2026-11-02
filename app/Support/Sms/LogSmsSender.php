<?php

declare(strict_types=1);

namespace App\Support\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Sms naar het log, voor lokaal en zolang er geen partij gekozen is. De
 * berichten blijven ook in het geheugen, zodat tests kunnen meelezen.
 */
final class LogSmsSender implements SmsSender
{
    /** @var list<array{to: string, message: string}> */
    private array $sent = [];

    public function send(string $to, string $message): void
    {
        $this->sent[] = ['to' => $to, 'message' => $message];

        Log::info('Sms (log-driver)', ['to' => $to, 'message' => $message]);
    }

    /**
     * @return list<array{to: string, message: string}>
     */
    public function sent(): array
    {
        return $this->sent;
    }
}
