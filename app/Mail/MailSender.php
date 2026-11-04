<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\AutomationRun;
use App\Models\Lot;
use App\Models\MailCampaign;
use App\Models\MailDelivery;
use App\Models\MailTemplate;
use App\Models\MailTemplateVersion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Verstuurt een mail uit de builder naar een klant: beleid toetsen, de
 * actuele versie renderen met verse kavels, versturen en het afleverlog
 * laten vullen. Idempotent per reeksstap: dezelfde stap twee keer geeft
 * één mail.
 */
final class MailSender
{
    public function __construct(private readonly MailRenderer $renderer, private readonly MailPolicy $policy) {}

    /**
     * @param  array<string, mixed>  $context  lot, order, lots en losse velden
     * @return string|null null als verstuurd, anders de reden waarom niet
     */
    public function send(User $user, string $templateKey, array $context = [], ?AutomationRun $run = null, ?MailCampaign $campaign = null, ?MailTemplateVersion $version = null): ?string
    {
        $template = MailTemplate::query()->where('key', $templateKey)->with('currentVersion')->first();

        if ($template === null) {
            MailCatalog::seed();
            $template = MailTemplate::query()->where('key', $templateKey)->with('currentVersion')->firstOrFail();
        }

        $version ??= $template->currentVersion;

        if ($version === null) {
            return 'no_version';
        }

        if ($run !== null && MailDelivery::query()->where('automation_run_id', $run->id)->where('automation_step', $run->current_step)->exists()) {
            return 'already_sent';
        }

        $reason = $this->policy->reasonNotToSend($user, $template->stream);

        if ($reason !== null) {
            return $reason;
        }

        $lots = $context['lots'] ?? (isset($context['lot']) && $context['lot'] instanceof Lot ? collect([$context['lot']]) : null);
        $unsubscribe = $template->isMarketing() ? self::unsubscribeUrl($user, $template->stream) : null;
        $rendered = $this->renderer->render($version, MailFields::build($user, $context), $lots instanceof Collection ? $lots : null, $unsubscribe);

        $lot = $context['lot'] ?? null;
        $order = $context['order'] ?? null;

        Mail::to($user->email, $user->name)->send(new TemplatedMail($rendered, $template->stream, [
            'lot_id' => $lot instanceof Lot ? $lot->id : ($order?->lot_id ?? null),
            'order_id' => $order?->id,
            'template_key' => $template->key,
            'template_version_id' => $version->id,
            'stream' => $template->stream,
            'automation_run_id' => $run?->id,
            'automation_step' => $run?->current_step,
            'campaign_id' => $campaign?->id,
        ], $unsubscribe));

        return null;
    }

    public static function unsubscribeUrl(User $user, string $stream): string
    {
        return URL::signedRoute('mail.unsubscribe', ['user' => $user->id, 'stream' => $stream]);
    }
}
