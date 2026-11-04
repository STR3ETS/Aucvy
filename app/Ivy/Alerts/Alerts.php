<?php

declare(strict_types=1);

namespace App\Ivy\Alerts;

use App\Models\IvyAlert;
use App\Models\IvyAlertDelivery;
use App\Models\Lot;
use App\Models\NotificationPreference;
use App\Models\Order;
use App\Models\User;
use App\Notifications\IvyAlertNotification;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Alerts zijn regels op domeinevents met een vaste sjabloontekst en twee
 * of drie keuzes, altijd met een uitweg. Ontdubbeld per gebruiker, kavel
 * en soort; gebundeld waar de briefing dat zegt; stil tussen 22:00 en
 * 08:00 behalve waar de klant dat zelf aanzet. Terugval van push naar mail.
 * Een alert werkt ook zonder AI.
 */
final class Alerts
{
    /**
     * @param  array<string, mixed>  $payload  title, body, advice, url
     * @param  list<array{key: string, label: string, url?: string, primary?: bool}>  $choices
     * @param  list<string>  $channels  inapp, push, mail
     */
    public function raise(User $user, string $key, string $role, array $payload, array $choices, array $channels = ['inapp'], ?Lot $lot = null, ?Order $order = null, string $level = IvyAlert::TASK, ?string $dedupe = null, ?DateTimeInterface $expiresAt = null, bool $urgent = false): IvyAlert
    {
        $dedupe ??= implode(':', [$user->id, $key, $lot?->id ?? '-', $order?->id ?? '-']);

        $alert = IvyAlert::query()->where('dedupe_key', $dedupe)->whereNull('acted_at')->first();
        $isUpdate = $alert !== null;

        if ($alert === null) {
            $alert = new IvyAlert(['dedupe_key' => $dedupe, 'user_id' => $user->id, 'key' => $key, 'role' => $role, 'lot_id' => $lot?->id, 'order_id' => $order?->id]);
        }

        $alert->fill(['level' => $level, 'payload' => $payload, 'choices' => $choices, 'read_at' => null, 'expires_at' => $expiresAt])->save();

        foreach (array_diff($channels, ['inapp']) as $channel) {
            $this->deliver($alert, $user, $channel, $isUpdate, $urgent);
        }

        return $alert;
    }

    private function deliver(IvyAlert $alert, User $user, string $channel, bool $isUpdate, bool $urgent): void
    {
        if (! NotificationPreference::allows($user, $alert->key, $channel)) {
            return;
        }

        // Stille uren, behalve bij kavels waarvoor de klant dat zelf aanzet.
        if (! $urgent && $this->inQuietHours()) {
            $this->record($alert, $channel, 'quiet_hours');

            return;
        }

        // Bundelen: een bijgewerkte alert (biedoorlog) stuurt niet opnieuw binnen het venster.
        if ($isUpdate && ! Cache::add("ivy-alert-sent:{$alert->id}:{$channel}", 1, (int) config('aucvy.ivy.bundle_minutes', 15) * 60)) {
            return;
        }

        if ($channel === 'push' && $user->pushSubscriptions()->count() === 0) {
            $channel = 'mail';
        }

        try {
            $user->notify(new IvyAlertNotification($alert, $channel));
            $this->record($alert, $channel, 'sent');
        } catch (Throwable $exception) {
            report($exception);
            $this->record($alert, $channel, 'failed', $exception->getMessage());

            if ($channel === 'push') {
                $this->deliver($alert, $user, 'mail', false, true);
            }
        }
    }

    private function record(IvyAlert $alert, string $channel, string $status, ?string $reason = null): void
    {
        IvyAlertDelivery::create(['alert_id' => $alert->id, 'channel' => $channel, 'status' => $status, 'sent_at' => $status === 'sent' ? now() : null, 'failed_reason' => $reason !== null ? mb_substr($reason, 0, 200) : null]);
    }

    public function inQuietHours(): bool
    {
        $hour = (int) now()->timezone(config('aucvy.display_timezone'))->format('G');
        $start = (int) config('aucvy.ivy.quiet_start', 22);
        $end = (int) config('aucvy.ivy.quiet_end', 8);

        return $hour >= $start || $hour < $end;
    }

    public function act(IvyAlert $alert, string $choice): void
    {
        $alert->forceFill(['acted_at' => now(), 'acted_choice' => $choice, 'read_at' => now()])->save();
    }

    /**
     * @return array{tasks: Collection<int, IvyAlert>, done: Collection<int, IvyAlert>, count: int}
     */
    public function inbox(User $user): array
    {
        $open = IvyAlert::query()->where('user_id', $user->id)->open()->with(['lot', 'order'])->latest('updated_at')->get();

        return [
            'tasks' => $open->where('level', IvyAlert::TASK)->values(),
            'info' => $open->where('level', IvyAlert::INFO)->values(),
            'done' => IvyAlert::query()->where('user_id', $user->id)->where(fn ($query) => $query->whereNotNull('acted_at')->orWhere('level', IvyAlert::DONE))->with(['lot', 'order'])->latest('updated_at')->limit(20)->get(),
            'count' => $open->where('level', IvyAlert::TASK)->count(),
        ];
    }
}
